<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Integration\AiClient;

use PHPUnit\Framework\TestCase;
use Automattic\WpAiAgent\Core\Exceptions\AiClientException;
use Automattic\WpAiAgent\Integration\AiClient\ChatGptCodexTextGenerationModel;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

/**
 * Unit tests for ChatGptCodexTextGenerationModel.
 *
 * @covers \Automattic\WpAiAgent\Integration\AiClient\ChatGptCodexTextGenerationModel
 */
final class ChatGptCodexTextGenerationModelTest extends TestCase
{
	/**
	 * Mock HTTP transporter.
	 *
	 * @var HttpTransporterInterface&\PHPUnit\Framework\MockObject\MockObject
	 */
	private HttpTransporterInterface $mock_transporter;

	/**
	 * Mock request authentication.
	 *
	 * @var RequestAuthenticationInterface&\PHPUnit\Framework\MockObject\MockObject
	 */
	private RequestAuthenticationInterface $mock_auth;

	/**
	 * Sets up test dependencies.
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->mock_transporter = $this->createMock(HttpTransporterInterface::class);
		$this->mock_auth = $this->createMock(RequestAuthenticationInterface::class);
		$this->mock_auth->method('authenticateRequest')
			->willReturnCallback(fn(Request $r) => $r);
	}

	/**
	 * Creates a configured model instance for testing.
	 *
	 * @param string      $model_id         The model identifier.
	 * @param string|null $system_instruction Optional system instruction.
	 *
	 * @return ChatGptCodexTextGenerationModel
	 */
	private function createModel(
		string $model_id = 'o4-mini',
		?string $system_instruction = null
	): ChatGptCodexTextGenerationModel {
		$model_metadata = new ModelMetadata(
			$model_id,
			$model_id,
			[CapabilityEnum::textGeneration()],
			[]
		);
		$provider_metadata = new ProviderMetadata(
			'openai',
			'OpenAI',
			ProviderTypeEnum::cloud(),
			'https://platform.openai.com/api-keys',
			RequestAuthenticationMethod::apiKey()
		);

		$model = new ChatGptCodexTextGenerationModel($model_metadata, $provider_metadata);
		$model->setHttpTransporter($this->mock_transporter);
		$model->setRequestAuthentication($this->mock_auth);

		if ($system_instruction !== null) {
			$config = ModelConfig::fromArray([
				'systemInstruction' => $system_instruction,
			]);
			$model->setConfig($config);
		}

		return $model;
	}

	/**
	 * Builds an SSE body containing a single `response.completed` event.
	 *
	 * @param array<string, mixed> $data The response payload.
	 *
	 * @return string The SSE-formatted body.
	 */
	private function buildSseBody(array $data): string
	{
		return "event: response.completed\ndata: " . json_encode($data) . "\n\n";
	}

	/**
	 * Creates a simple text response payload.
	 *
	 * @param string $text The text content.
	 *
	 * @return array<string, mixed> The response payload.
	 */
	private function textResponsePayload(string $text): array
	{
		return [
			'id' => 'resp_test_123',
			'status' => 'completed',
			'output' => [
				[
					'type' => 'message',
					'role' => 'assistant',
					'content' => [
						[
							'type' => 'output_text',
							'text' => $text,
						],
					],
				],
			],
			'usage' => [
				'input_tokens' => 100,
				'output_tokens' => 50,
				'total_tokens' => 150,
			],
		];
	}

	/**
	 * Configures the mock transporter to return the given SSE body.
	 *
	 * @param string $sse_body The SSE body to return.
	 */
	private function mockTransporterReturns(string $sse_body): void
	{
		$response = new Response(200, ['Content-Type' => 'text/event-stream'], $sse_body);
		$this->mock_transporter->method('send')
			->willReturn($response);
	}

	/**
	 * Tests that the model implements TextGenerationModelInterface.
	 */
	public function test_implementsTextGenerationModelInterface(): void
	{
		$model = $this->createModel();

		$this->assertInstanceOf(TextGenerationModelInterface::class, $model);
	}

	/**
	 * Tests that generateTextResult includes stream and store params.
	 */
	public function test_generateTextResult_withDefaults_includesStreamAndStoreParams(): void
	{
		$captured_request = null;
		$this->mock_transporter->method('send')
			->willReturnCallback(function (Request $request) use (&$captured_request) {
				$captured_request = $request;
				$sse_body = $this->buildSseBody($this->textResponsePayload('Hello'));
				return new Response(200, [], $sse_body);
			});

		$model = $this->createModel();
		$prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];
		$model->generateTextResult($prompt);

		$this->assertNotNull($captured_request);
		$data = $captured_request->getData();
		$this->assertIsArray($data);
		$this->assertTrue($data['stream']);
		$this->assertFalse($data['store']);
	}

	/**
	 * Tests that instructions param is always present (mandatory).
	 */
	public function test_generateTextResult_withNoSystemInstruction_includesEmptyInstructions(): void
	{
		$captured_request = null;
		$this->mock_transporter->method('send')
			->willReturnCallback(function (Request $request) use (&$captured_request) {
				$captured_request = $request;
				$sse_body = $this->buildSseBody($this->textResponsePayload('Hello'));
				return new Response(200, [], $sse_body);
			});

		$model = $this->createModel();
		$prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];
		$model->generateTextResult($prompt);

		$this->assertNotNull($captured_request);
		$data = $captured_request->getData();
		$this->assertIsArray($data);
		$this->assertArrayHasKey('instructions', $data);
		$this->assertSame('', $data['instructions']);
	}

	/**
	 * Tests that instructions param contains the system instruction when set.
	 */
	public function test_generateTextResult_withSystemInstruction_includesInstructions(): void
	{
		$captured_request = null;
		$this->mock_transporter->method('send')
			->willReturnCallback(function (Request $request) use (&$captured_request) {
				$captured_request = $request;
				$sse_body = $this->buildSseBody($this->textResponsePayload('Hello'));
				return new Response(200, [], $sse_body);
			});

		$model = $this->createModel('o4-mini', 'You are a helpful assistant.');
		$prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];
		$model->generateTextResult($prompt);

		$this->assertNotNull($captured_request);
		$data = $captured_request->getData();
		$this->assertIsArray($data);
		$this->assertSame('You are a helpful assistant.', $data['instructions']);
	}

	/**
	 * Tests that a simple text response is parsed correctly.
	 */
	public function test_generateTextResult_withTextResponse_parsesContent(): void
	{
		$sse_body = $this->buildSseBody($this->textResponsePayload('Hello, world!'));
		$this->mockTransporterReturns($sse_body);

		$model = $this->createModel();
		$prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];
		$result = $model->generateTextResult($prompt);

		$this->assertSame('Hello, world!', $result->toText());
		$this->assertSame('resp_test_123', $result->getId());
	}

	/**
	 * Tests that token usage is parsed from the response.
	 */
	public function test_generateTextResult_withUsage_parsesTokenUsage(): void
	{
		$sse_body = $this->buildSseBody($this->textResponsePayload('Hi'));
		$this->mockTransporterReturns($sse_body);

		$model = $this->createModel();
		$prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];
		$result = $model->generateTextResult($prompt);

		$usage = $result->getTokenUsage();
		$this->assertSame(100, $usage->getPromptTokens());
		$this->assertSame(50, $usage->getCompletionTokens());
		$this->assertSame(150, $usage->getTotalTokens());
	}

	/**
	 * Tests that missing usage defaults to zeros.
	 */
	public function test_generateTextResult_withNoUsage_defaultsToZeros(): void
	{
		$payload = [
			'id' => 'resp_no_usage',
			'status' => 'completed',
			'output' => [
				[
					'type' => 'message',
					'role' => 'assistant',
					'content' => [
						['type' => 'output_text', 'text' => 'Hi'],
					],
				],
			],
		];
		$this->mockTransporterReturns($this->buildSseBody($payload));

		$model = $this->createModel();
		$prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];
		$result = $model->generateTextResult($prompt);

		$usage = $result->getTokenUsage();
		$this->assertSame(0, $usage->getPromptTokens());
		$this->assertSame(0, $usage->getCompletionTokens());
		$this->assertSame(0, $usage->getTotalTokens());
	}

	/**
	 * Tests that function call output is parsed correctly.
	 */
	public function test_generateTextResult_withFunctionCall_parsesFunctionCall(): void
	{
		$payload = [
			'id' => 'resp_func',
			'status' => 'completed',
			'output' => [
				[
					'type' => 'function_call',
					'call_id' => 'call_abc123',
					'name' => 'read_file',
					'arguments' => '{"path":"/tmp/test.txt"}',
				],
			],
			'usage' => [
				'input_tokens' => 50,
				'output_tokens' => 20,
				'total_tokens' => 70,
			],
		];
		$this->mockTransporterReturns($this->buildSseBody($payload));

		$model = $this->createModel();
		$prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Read the file')])];
		$result = $model->generateTextResult($prompt);

		$candidates = $result->getCandidates();
		$this->assertCount(1, $candidates);

		$candidate = $candidates[0];
		$this->assertTrue($candidate->getFinishReason()->isToolCalls());

		$parts = $candidate->getMessage()->getParts();
		$this->assertCount(1, $parts);
		$this->assertTrue($parts[0]->getType()->isFunctionCall());

		$function_call = $parts[0]->getFunctionCall();
		$this->assertNotNull($function_call);
		$this->assertSame('call_abc123', $function_call->getId());
		$this->assertSame('read_file', $function_call->getName());
		$this->assertSame(['path' => '/tmp/test.txt'], $function_call->getArgs());
	}

	/**
	 * Tests that empty function call arguments are normalized to null.
	 */
	public function test_generateTextResult_withEmptyFunctionCallArgs_normalizesToNull(): void
	{
		$payload = [
			'id' => 'resp_func_empty',
			'status' => 'completed',
			'output' => [
				[
					'type' => 'function_call',
					'call_id' => 'call_empty',
					'name' => 'get_time',
					'arguments' => '{}',
				],
			],
			'usage' => ['input_tokens' => 10, 'output_tokens' => 5, 'total_tokens' => 15],
		];
		$this->mockTransporterReturns($this->buildSseBody($payload));

		$model = $this->createModel();
		$prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('What time is it?')])];
		$result = $model->generateTextResult($prompt);

		$function_call = $result->getCandidates()[0]->getMessage()->getParts()[0]->getFunctionCall();
		$this->assertNotNull($function_call);
		$this->assertNull($function_call->getArgs());
	}

	/**
	 * Tests that an empty response body throws AiClientException.
	 */
	public function test_generateTextResult_withEmptyBody_throwsException(): void
	{
		$response = new Response(200, [], '');
		$this->mock_transporter->method('send')
			->willReturn($response);

		$model = $this->createModel();
		$prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];

		$this->expectException(AiClientException::class);
		$this->expectExceptionMessage('AI response body is empty');

		$model->generateTextResult($prompt);
	}

	/**
	 * Tests that a null response body throws AiClientException.
	 */
	public function test_generateTextResult_withNullBody_throwsException(): void
	{
		$response = new Response(200, [], null);
		$this->mock_transporter->method('send')
			->willReturn($response);

		$model = $this->createModel();
		$prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];

		$this->expectException(AiClientException::class);
		$this->expectExceptionMessage('AI response body is empty');

		$model->generateTextResult($prompt);
	}

	/**
	 * Tests that missing response.completed event throws AiClientException.
	 */
	public function test_generateTextResult_withMissingSseEvent_throwsException(): void
	{
		$sse_body = "event: response.created\ndata: {\"id\":\"resp_123\"}\n\n";
		$this->mockTransporterReturns($sse_body);

		$model = $this->createModel();
		$prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];

		$this->expectException(AiClientException::class);
		$this->expectExceptionMessage('Expected SSE event "response.completed" not found');

		$model->generateTextResult($prompt);
	}

	/**
	 * Tests that tool declarations are included in the request params.
	 */
	public function test_generateTextResult_withTools_includesToolsInRequest(): void
	{
		$captured_request = null;
		$this->mock_transporter->method('send')
			->willReturnCallback(function (Request $request) use (&$captured_request) {
				$captured_request = $request;
				$sse_body = $this->buildSseBody($this->textResponsePayload('Ok'));
				return new Response(200, [], $sse_body);
			});

		$model = $this->createModel();
		$config = ModelConfig::fromArray([
			'functionDeclarations' => [
				[
					'name' => 'read_file',
					'description' => 'Reads a file',
					'parameters' => [
						'type' => 'object',
						'properties' => [
							'path' => ['type' => 'string'],
						],
						'required' => ['path'],
					],
				],
			],
		]);
		$model->setConfig($config);

		$prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Read file')])];
		$model->generateTextResult($prompt);

		$this->assertNotNull($captured_request);
		$data = $captured_request->getData();
		$this->assertIsArray($data);
		$this->assertArrayHasKey('tools', $data);
		$this->assertCount(1, $data['tools']);
		$this->assertSame('function', $data['tools'][0]['type']);
		$this->assertSame('read_file', $data['tools'][0]['name']);
	}

	/**
	 * Tests that user messages are formatted with input_text type.
	 */
	public function test_generateTextResult_withUserMessage_formatsAsInputText(): void
	{
		$captured_request = null;
		$this->mock_transporter->method('send')
			->willReturnCallback(function (Request $request) use (&$captured_request) {
				$captured_request = $request;
				$sse_body = $this->buildSseBody($this->textResponsePayload('Hello'));
				return new Response(200, [], $sse_body);
			});

		$model = $this->createModel();
		$prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hello there')])];
		$model->generateTextResult($prompt);

		$this->assertNotNull($captured_request);
		$data = $captured_request->getData();
		$this->assertIsArray($data);
		$this->assertArrayHasKey('input', $data);
		$this->assertCount(1, $data['input']);
		$this->assertSame('user', $data['input'][0]['role']);
		$this->assertSame('input_text', $data['input'][0]['content'][0]['type']);
		$this->assertSame('Hello there', $data['input'][0]['content'][0]['text']);
	}

	/**
	 * Tests that model messages are formatted with output_text type and assistant role.
	 */
	public function test_generateTextResult_withModelMessage_formatsAsOutputText(): void
	{
		$captured_request = null;
		$this->mock_transporter->method('send')
			->willReturnCallback(function (Request $request) use (&$captured_request) {
				$captured_request = $request;
				$sse_body = $this->buildSseBody($this->textResponsePayload('World'));
				return new Response(200, [], $sse_body);
			});

		$model = $this->createModel();
		$prompt = [
			new Message(MessageRoleEnum::user(), [new MessagePart('Hi')]),
			new Message(MessageRoleEnum::model(), [new MessagePart('Hello')]),
			new Message(MessageRoleEnum::user(), [new MessagePart('How are you?')]),
		];
		$model->generateTextResult($prompt);

		$this->assertNotNull($captured_request);
		$data = $captured_request->getData();
		$this->assertIsArray($data);
		$this->assertCount(3, $data['input']);
		$this->assertSame('assistant', $data['input'][1]['role']);
		$this->assertSame('output_text', $data['input'][1]['content'][0]['type']);
	}

	/**
	 * Tests that the model ID is included in the request params.
	 */
	public function test_generateTextResult_includesModelIdInParams(): void
	{
		$captured_request = null;
		$this->mock_transporter->method('send')
			->willReturnCallback(function (Request $request) use (&$captured_request) {
				$captured_request = $request;
				$sse_body = $this->buildSseBody($this->textResponsePayload('Hi'));
				return new Response(200, [], $sse_body);
			});

		$model = $this->createModel('codex-mini-latest');
		$prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];
		$model->generateTextResult($prompt);

		$this->assertNotNull($captured_request);
		$data = $captured_request->getData();
		$this->assertIsArray($data);
		$this->assertSame('codex-mini-latest', $data['model']);
	}

	/**
	 * Tests that incomplete response status maps to length finish reason.
	 */
	public function test_generateTextResult_withIncompleteStatus_returnsLengthFinishReason(): void
	{
		$payload = [
			'id' => 'resp_incomplete',
			'status' => 'incomplete',
			'output' => [
				[
					'type' => 'message',
					'role' => 'assistant',
					'content' => [
						['type' => 'output_text', 'text' => 'Partial response...'],
					],
				],
			],
			'usage' => ['input_tokens' => 10, 'output_tokens' => 5, 'total_tokens' => 15],
		];
		$this->mockTransporterReturns($this->buildSseBody($payload));

		$model = $this->createModel();
		$prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];
		$result = $model->generateTextResult($prompt);

		$this->assertTrue($result->getCandidates()[0]->getFinishReason()->isLength());
	}

	/**
	 * Tests that failed response status maps to error finish reason.
	 */
	public function test_generateTextResult_withFailedStatus_returnsErrorFinishReason(): void
	{
		$payload = [
			'id' => 'resp_failed',
			'status' => 'failed',
			'output' => [
				[
					'type' => 'message',
					'role' => 'assistant',
					'content' => [
						['type' => 'output_text', 'text' => 'Error occurred'],
					],
				],
			],
			'usage' => ['input_tokens' => 10, 'output_tokens' => 5, 'total_tokens' => 15],
		];
		$this->mockTransporterReturns($this->buildSseBody($payload));

		$model = $this->createModel();
		$prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];
		$result = $model->generateTextResult($prompt);

		$this->assertTrue($result->getCandidates()[0]->getFinishReason()->isError());
	}

	/**
	 * Tests that function call input messages are formatted as top-level items.
	 */
	public function test_generateTextResult_withFunctionCallInput_formatsAsTopLevelItem(): void
	{
		$captured_request = null;
		$this->mock_transporter->method('send')
			->willReturnCallback(function (Request $request) use (&$captured_request) {
				$captured_request = $request;
				$sse_body = $this->buildSseBody($this->textResponsePayload('Done'));
				return new Response(200, [], $sse_body);
			});

		$function_call = new FunctionCall('call_123', 'read_file', ['path' => '/tmp/test.txt']);
		$function_response = new FunctionResponse('call_123', 'read_file', 'file contents here');

		$model = $this->createModel();
		$prompt = [
			new Message(MessageRoleEnum::user(), [new MessagePart('Read the file')]),
			new Message(MessageRoleEnum::model(), [new MessagePart($function_call)]),
			new Message(MessageRoleEnum::user(), [new MessagePart($function_response)]),
		];
		$model->generateTextResult($prompt);

		$this->assertNotNull($captured_request);
		$data = $captured_request->getData();
		$this->assertIsArray($data);
		$this->assertCount(3, $data['input']);

		// The function call should be a top-level item (no role/content wrapper).
		$this->assertSame('function_call', $data['input'][1]['type']);
		$this->assertSame('call_123', $data['input'][1]['call_id']);

		// The function response should also be a top-level item.
		$this->assertSame('function_call_output', $data['input'][2]['type']);
		$this->assertSame('call_123', $data['input'][2]['call_id']);
	}

	/**
	 * Tests that missing output key in response throws ResponseException.
	 */
	public function test_generateTextResult_withMissingOutput_throwsResponseException(): void
	{
		$payload = [
			'id' => 'resp_no_output',
			'status' => 'completed',
		];
		$this->mockTransporterReturns($this->buildSseBody($payload));

		$model = $this->createModel();
		$prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];

		$this->expectException(ResponseException::class);
		$this->expectExceptionMessage('Missing the "output" key');

		$model->generateTextResult($prompt);
	}

	/**
	 * Tests that total_tokens is computed when not provided.
	 */
	public function test_generateTextResult_withNoTotalTokens_computesSum(): void
	{
		$payload = [
			'id' => 'resp_no_total',
			'status' => 'completed',
			'output' => [
				[
					'type' => 'message',
					'role' => 'assistant',
					'content' => [
						['type' => 'output_text', 'text' => 'Hi'],
					],
				],
			],
			'usage' => [
				'input_tokens' => 40,
				'output_tokens' => 25,
			],
		];
		$this->mockTransporterReturns($this->buildSseBody($payload));

		$model = $this->createModel();
		$prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];
		$result = $model->generateTextResult($prompt);

		$usage = $result->getTokenUsage();
		$this->assertSame(40, $usage->getPromptTokens());
		$this->assertSame(25, $usage->getCompletionTokens());
		$this->assertSame(65, $usage->getTotalTokens());
	}

	/**
	 * Tests that multi-part function call messages throw validation error.
	 */
	public function test_generateTextResult_withMultiPartFunctionCall_throwsException(): void
	{
		$function_call = new FunctionCall('call_123', 'test_tool', null);

		$model = $this->createModel();
		$prompt = [
			new Message(MessageRoleEnum::model(), [
				new MessagePart('Some text'),
				new MessagePart($function_call),
			]),
		];

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Function call parts must be the only part');

		// Need to stub the transporter even though we expect the exception
		// to be thrown before the request is sent.
		$this->mockTransporterReturns($this->buildSseBody($this->textResponsePayload('x')));

		$model->generateTextResult($prompt);
	}

	/**
	 * Tests that the request URL targets the OpenAI Responses API endpoint.
	 */
	public function test_generateTextResult_targetsResponsesApiEndpoint(): void
	{
		$captured_request = null;
		$this->mock_transporter->method('send')
			->willReturnCallback(function (Request $request) use (&$captured_request) {
				$captured_request = $request;
				$sse_body = $this->buildSseBody($this->textResponsePayload('Ok'));
				return new Response(200, [], $sse_body);
			});

		$model = $this->createModel();
		$prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];
		$model->generateTextResult($prompt);

		$this->assertNotNull($captured_request);
		$this->assertStringContainsString('/responses', $captured_request->getUri());
	}

	/**
	 * Tests that additional SSE events before response.completed are ignored.
	 */
	public function test_generateTextResult_withMultipleSseEvents_usesLastCompletedEvent(): void
	{
		$sse_body = "event: response.created\ndata: {\"id\":\"resp_123\"}\n\n"
			. "event: response.output_text.delta\ndata: {\"delta\":\"Hel\"}\n\n"
			. "event: response.output_text.delta\ndata: {\"delta\":\"lo\"}\n\n"
			. "event: response.completed\ndata: " . json_encode($this->textResponsePayload('Hello, world!')) . "\n\n";
		$this->mockTransporterReturns($sse_body);

		$model = $this->createModel();
		$prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];
		$result = $model->generateTextResult($prompt);

		$this->assertSame('Hello, world!', $result->toText());
	}
}
