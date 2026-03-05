<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Integration\AiClient;

use Automattic\WpAiAgent\Core\ValueObjects\Message;
use Automattic\WpAiAgent\Integration\AiClient\AiResponse;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPress\AiClient\Tools\DTO\FunctionCall;

/**
 * Unit tests for AiResponse.
 *
 * @covers \Automattic\WpAiAgent\Integration\AiClient\AiResponse
 */
final class AiResponseTest extends TestCase
{
	/**
	 * Tests that getContent returns the text content from the response.
	 */
	public function test_getContent_withTextContent_returnsText(): void
	{
		$result = $this->createGenerativeAiResult('Hello, world!');

		$response = new AiResponse($result);

		$this->assertSame('Hello, world!', $response->getContent());
	}

	/**
	 * Tests that getContent returns empty string when no candidates exist.
	 *
	 * Note: GenerativeAiResult requires at least one candidate, so this test
	 * is skipped as the underlying library prevents this condition.
	 */
	public function test_getContent_withNoCandidates_returnsEmptyString(): void
	{
		// GenerativeAiResult throws when empty, so we skip this test
		$this->markTestSkipped('GenerativeAiResult requires at least one candidate');
	}

	/**
	 * Tests that getToolCalls extracts function calls from the response.
	 */
	public function test_getToolCalls_withFunctionCalls_returnsToolCalls(): void
	{
		$function_call = new FunctionCall(
			'call_123',
			'get_weather',
			['location' => 'London']
		);

		$parts = [new MessagePart($function_call)];
		$message = new ModelMessage($parts);
		$candidate = new Candidate($message, FinishReasonEnum::toolCalls());
		$result = $this->createGenerativeAiResultWithCandidates([$candidate]);

		$response = new AiResponse($result);

		$tool_calls = $response->getToolCalls();

		$this->assertCount(1, $tool_calls);
		$this->assertSame('call_123', $tool_calls[0]['id']);
		$this->assertSame('get_weather', $tool_calls[0]['name']);
		$this->assertSame(['location' => 'London'], $tool_calls[0]['arguments']);
	}

	/**
	 * Tests that hasToolCalls returns true when function calls exist.
	 */
	public function test_hasToolCalls_withFunctionCalls_returnsTrue(): void
	{
		$function_call = new FunctionCall(
			'call_456',
			'search',
			['query' => 'test']
		);

		$parts = [new MessagePart($function_call)];
		$message = new ModelMessage($parts);
		$candidate = new Candidate($message, FinishReasonEnum::toolCalls());
		$result = $this->createGenerativeAiResultWithCandidates([$candidate]);

		$response = new AiResponse($result);

		$this->assertTrue($response->hasToolCalls());
	}

	/**
	 * Tests that hasToolCalls returns false when no function calls exist.
	 */
	public function test_hasToolCalls_withoutFunctionCalls_returnsFalse(): void
	{
		$result = $this->createGenerativeAiResult('Just text content');

		$response = new AiResponse($result);

		$this->assertFalse($response->hasToolCalls());
	}

	/**
	 * Tests that getStopReason returns end_turn for stop finish reason.
	 */
	public function test_getStopReason_withStopReason_returnsEndTurn(): void
	{
		$result = $this->createGenerativeAiResultWithFinishReason(FinishReasonEnum::stop());

		$response = new AiResponse($result);

		$this->assertSame('end_turn', $response->getStopReason());
	}

	/**
	 * Tests that getStopReason returns tool_use for tool calls finish reason.
	 */
	public function test_getStopReason_withToolCallsReason_returnsToolUse(): void
	{
		$result = $this->createGenerativeAiResultWithFinishReason(FinishReasonEnum::toolCalls());

		$response = new AiResponse($result);

		$this->assertSame('tool_use', $response->getStopReason());
	}

	/**
	 * Tests that getStopReason returns max_tokens for length finish reason.
	 */
	public function test_getStopReason_withLengthReason_returnsMaxTokens(): void
	{
		$result = $this->createGenerativeAiResultWithFinishReason(FinishReasonEnum::length());

		$response = new AiResponse($result);

		$this->assertSame('max_tokens', $response->getStopReason());
	}

	/**
	 * Tests that isFinalResponse returns true for stop reason.
	 */
	public function test_isFinalResponse_withStopReason_returnsTrue(): void
	{
		$result = $this->createGenerativeAiResultWithFinishReason(FinishReasonEnum::stop());

		$response = new AiResponse($result);

		$this->assertTrue($response->isFinalResponse());
	}

	/**
	 * Tests that isFinalResponse returns false for tool calls reason.
	 */
	public function test_isFinalResponse_withToolCallsReason_returnsFalse(): void
	{
		$result = $this->createGenerativeAiResultWithFinishReason(FinishReasonEnum::toolCalls());

		$response = new AiResponse($result);

		$this->assertFalse($response->isFinalResponse());
	}

	/**
	 * Tests that getUsage returns correct token usage.
	 */
	public function test_getUsage_returnsCorrectTokenCounts(): void
	{
		$result = $this->createGenerativeAiResult('Test', 100, 50);

		$response = new AiResponse($result);

		$usage = $response->getUsage();

		$this->assertSame(100, $usage['input_tokens']);
		$this->assertSame(50, $usage['output_tokens']);
	}

	/**
	 * Tests that getRawResponse returns the array representation.
	 */
	public function test_getRawResponse_returnsArray(): void
	{
		$result = $this->createGenerativeAiResult('Test content');

		$response = new AiResponse($result);

		$raw = $response->getRawResponse();

		$this->assertIsArray($raw);
		$this->assertArrayHasKey('id', $raw);
		$this->assertArrayHasKey('candidates', $raw);
	}

	/**
	 * Tests that toMessage returns an assistant Message value object.
	 */
	public function test_toMessage_returnsAssistantMessage(): void
	{
		$result = $this->createGenerativeAiResult('Hello from AI');

		$response = new AiResponse($result);

		$message = $response->toMessage();

		$this->assertInstanceOf(Message::class, $message);
		$this->assertSame(Message::ROLE_ASSISTANT, $message->getRole());
		$this->assertSame('Hello from AI', $message->getContent());
	}

	/**
	 * Tests that toMessage includes tool calls in the message.
	 */
	public function test_toMessage_withToolCalls_includesToolCalls(): void
	{
		$function_call = new FunctionCall(
			'call_789',
			'calculate',
			['expression' => '2+2']
		);

		$parts = [
			new MessagePart('Let me calculate that'),
			new MessagePart($function_call),
		];
		$message = new ModelMessage($parts);
		$candidate = new Candidate($message, FinishReasonEnum::toolCalls());
		$result = $this->createGenerativeAiResultWithCandidates([$candidate]);

		$response = new AiResponse($result);

		$agent_message = $response->toMessage();

		$this->assertTrue($agent_message->hasToolCalls());
		$tool_calls = $agent_message->getToolCalls();
		$this->assertCount(1, $tool_calls);
		$this->assertSame('call_789', $tool_calls[0]['id']);
	}

	/**
	 * Creates a GenerativeAiResult with text content.
	 *
	 * @param string $content          The text content.
	 * @param int    $prompt_tokens    Prompt token count.
	 * @param int    $completion_tokens Completion token count.
	 *
	 * @return GenerativeAiResult
	 */
	private function createGenerativeAiResult(
		string $content,
		int $prompt_tokens = 10,
		int $completion_tokens = 20
	): GenerativeAiResult {
		$parts = [new MessagePart($content)];
		$message = new ModelMessage($parts);
		$candidate = new Candidate($message, FinishReasonEnum::stop());

		return $this->createGenerativeAiResultWithCandidates(
			[$candidate],
			$prompt_tokens,
			$completion_tokens
		);
	}

	/**
	 * Creates a GenerativeAiResult with a specific finish reason.
	 *
	 * @param FinishReasonEnum $finish_reason The finish reason.
	 *
	 * @return GenerativeAiResult
	 */
	private function createGenerativeAiResultWithFinishReason(
		FinishReasonEnum $finish_reason
	): GenerativeAiResult {
		$parts = [new MessagePart('Content')];
		$message = new ModelMessage($parts);
		$candidate = new Candidate($message, $finish_reason);

		return $this->createGenerativeAiResultWithCandidates([$candidate]);
	}

	/**
	 * Creates a GenerativeAiResult with custom candidates.
	 *
	 * @param array<Candidate> $candidates       The candidates.
	 * @param int              $prompt_tokens    Prompt token count.
	 * @param int              $completion_tokens Completion token count.
	 *
	 * @return GenerativeAiResult
	 */
	private function createGenerativeAiResultWithCandidates(
		array $candidates,
		int $prompt_tokens = 10,
		int $completion_tokens = 20
	): GenerativeAiResult {
		$token_usage = new TokenUsage(
			$prompt_tokens,
			$completion_tokens,
			$prompt_tokens + $completion_tokens
		);

		$provider_metadata = new ProviderMetadata(
			'anthropic',
			'Anthropic',
			ProviderTypeEnum::cloud()
		);

		$model_metadata = new ModelMetadata(
			'claude-sonnet-4-20250514',
			'Claude Sonnet 4',
			[CapabilityEnum::textGeneration()],
			[]
		);

		return new GenerativeAiResult(
			'response_' . uniqid(),
			$candidates,
			$token_usage,
			$provider_metadata,
			$model_metadata
		);
	}
}
