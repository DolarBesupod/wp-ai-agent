<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Integration\AiClient;

use Automattic\Automattic\WpAiAgent\Integration\AiClient\ToolCallExtractor;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPress\AiClient\Tools\DTO\FunctionCall;

/**
 * Unit tests for ToolCallExtractor.
 *
 * @covers \Automattic\WpAiAgent\Integration\AiClient\ToolCallExtractor
 */
final class ToolCallExtractorTest extends TestCase
{
	private ToolCallExtractor $extractor;

	protected function setUp(): void
	{
		$this->extractor = new ToolCallExtractor();
	}

	public function test_fromResult_withMultipleFunctionCalls_returnsAllFunctionCalls(): void
	{
		$function_call_1 = new FunctionCall('call_1', 'read_file', ['path' => '/tmp/test.txt']);
		$function_call_2 = new FunctionCall('call_2', 'write_file', ['path' => '/tmp/out.txt', 'content' => 'hello']);

		$parts = [
			new MessagePart($function_call_1),
			new MessagePart('Some text between calls'),
			new MessagePart($function_call_2),
		];

		$message = $this->createMock(Message::class);
		$message->method('getParts')->willReturn($parts);

		$candidate = $this->createMock(Candidate::class);
		$candidate->method('getMessage')->willReturn($message);

		$token_usage = $this->createMock(TokenUsage::class);

		$result = $this->createMock(GenerativeAiResult::class);
		$result->method('getCandidates')->willReturn([$candidate]);
		$result->method('getTokenUsage')->willReturn($token_usage);

		$function_calls = $this->extractor->fromResult($result);

		$this->assertCount(2, $function_calls);
		$this->assertInstanceOf(FunctionCall::class, $function_calls[0]);
		$this->assertInstanceOf(FunctionCall::class, $function_calls[1]);
		$this->assertSame('read_file', $function_calls[0]->getName());
		$this->assertSame('write_file', $function_calls[1]->getName());
	}

	public function test_fromResult_withOnlyTextParts_returnsEmptyArray(): void
	{
		$parts = [
			new MessagePart('Hello, this is some text.'),
			new MessagePart('More text here.'),
		];

		$message = $this->createMock(Message::class);
		$message->method('getParts')->willReturn($parts);

		$candidate = $this->createMock(Candidate::class);
		$candidate->method('getMessage')->willReturn($message);

		$result = $this->createMock(GenerativeAiResult::class);
		$result->method('getCandidates')->willReturn([$candidate]);

		$function_calls = $this->extractor->fromResult($result);

		$this->assertIsArray($function_calls);
		$this->assertCount(0, $function_calls);
	}

	public function test_fromResult_withNoCandidates_returnsEmptyArray(): void
	{
		$result = $this->createMock(GenerativeAiResult::class);
		$result->method('getCandidates')->willReturn([]);

		$function_calls = $this->extractor->fromResult($result);

		$this->assertIsArray($function_calls);
		$this->assertCount(0, $function_calls);
	}

	public function test_fromResult_withComplexArguments_preservesArgumentStructure(): void
	{
		$complex_args = [
			'file_path' => '/home/user/project/file.php',
			'options' => [
				'line_start' => 10,
				'line_end' => 50,
				'include_metadata' => true,
			],
			'tags' => ['important', 'review', 'urgent'],
			'metadata' => null,
		];

		$function_call = new FunctionCall('call_complex', 'process_file', $complex_args);

		$parts = [new MessagePart($function_call)];

		$message = $this->createMock(Message::class);
		$message->method('getParts')->willReturn($parts);

		$candidate = $this->createMock(Candidate::class);
		$candidate->method('getMessage')->willReturn($message);

		$result = $this->createMock(GenerativeAiResult::class);
		$result->method('getCandidates')->willReturn([$candidate]);

		$function_calls = $this->extractor->fromResult($result);

		$this->assertCount(1, $function_calls);
		$this->assertSame('process_file', $function_calls[0]->getName());

		$extracted_args = $function_calls[0]->getArgs();
		$this->assertIsArray($extracted_args);
		$this->assertSame('/home/user/project/file.php', $extracted_args['file_path']);
		$this->assertSame(10, $extracted_args['options']['line_start']);
		$this->assertSame(50, $extracted_args['options']['line_end']);
		$this->assertTrue($extracted_args['options']['include_metadata']);
		$this->assertCount(3, $extracted_args['tags']);
		$this->assertNull($extracted_args['metadata']);
	}

	public function test_fromMessage_extractsFunctionCalls(): void
	{
		$function_call = new FunctionCall('call_id', 'test_tool', ['param' => 'value']);

		$parts = [
			new MessagePart('Prefix text'),
			new MessagePart($function_call),
			new MessagePart('Suffix text'),
		];

		$message = $this->createMock(Message::class);
		$message->method('getParts')->willReturn($parts);

		$function_calls = $this->extractor->fromMessage($message);

		$this->assertCount(1, $function_calls);
		$this->assertSame('test_tool', $function_calls[0]->getName());
		$this->assertSame('call_id', $function_calls[0]->getId());
	}

	public function test_fromMessage_withEmptyParts_returnsEmptyArray(): void
	{
		$message = $this->createMock(Message::class);
		$message->method('getParts')->willReturn([]);

		$function_calls = $this->extractor->fromMessage($message);

		$this->assertIsArray($function_calls);
		$this->assertCount(0, $function_calls);
	}

	public function test_hasToolCalls_withFunctionCalls_returnsTrue(): void
	{
		$function_call = new FunctionCall('call_1', 'tool_name', []);

		$parts = [new MessagePart($function_call)];

		$message = $this->createMock(Message::class);
		$message->method('getParts')->willReturn($parts);

		$candidate = $this->createMock(Candidate::class);
		$candidate->method('getMessage')->willReturn($message);

		$result = $this->createMock(GenerativeAiResult::class);
		$result->method('getCandidates')->willReturn([$candidate]);

		$this->assertTrue($this->extractor->hasToolCalls($result));
	}

	public function test_hasToolCalls_withNoFunctionCalls_returnsFalse(): void
	{
		$parts = [new MessagePart('Just some text')];

		$message = $this->createMock(Message::class);
		$message->method('getParts')->willReturn($parts);

		$candidate = $this->createMock(Candidate::class);
		$candidate->method('getMessage')->willReturn($message);

		$result = $this->createMock(GenerativeAiResult::class);
		$result->method('getCandidates')->willReturn([$candidate]);

		$this->assertFalse($this->extractor->hasToolCalls($result));
	}

	public function test_hasToolCalls_withEmptyResult_returnsFalse(): void
	{
		$result = $this->createMock(GenerativeAiResult::class);
		$result->method('getCandidates')->willReturn([]);

		$this->assertFalse($this->extractor->hasToolCalls($result));
	}

	public function test_toArrayFormat_convertsToExpectedStructure(): void
	{
		$function_call_1 = new FunctionCall('id_123', 'bash', ['command' => 'ls -la']);
		$function_call_2 = new FunctionCall('id_456', 'read_file', ['path' => '/etc/hosts']);

		$parts = [
			new MessagePart($function_call_1),
			new MessagePart($function_call_2),
		];

		$message = $this->createMock(Message::class);
		$message->method('getParts')->willReturn($parts);

		$candidate = $this->createMock(Candidate::class);
		$candidate->method('getMessage')->willReturn($message);

		$result = $this->createMock(GenerativeAiResult::class);
		$result->method('getCandidates')->willReturn([$candidate]);

		$array_format = $this->extractor->toArrayFormat($result);

		$this->assertCount(2, $array_format);

		$this->assertArrayHasKey('id', $array_format[0]);
		$this->assertArrayHasKey('name', $array_format[0]);
		$this->assertArrayHasKey('arguments', $array_format[0]);
		$this->assertSame('id_123', $array_format[0]['id']);
		$this->assertSame('bash', $array_format[0]['name']);
		$this->assertSame(['command' => 'ls -la'], $array_format[0]['arguments']);

		$this->assertSame('id_456', $array_format[1]['id']);
		$this->assertSame('read_file', $array_format[1]['name']);
		$this->assertSame(['path' => '/etc/hosts'], $array_format[1]['arguments']);
	}

	public function test_toArrayFormat_generatesIdWhenNull(): void
	{
		// FunctionCall requires at least id or name, so we test with name only
		$function_call = new FunctionCall(null, 'tool_without_id', ['key' => 'value']);

		$parts = [new MessagePart($function_call)];

		$message = $this->createMock(Message::class);
		$message->method('getParts')->willReturn($parts);

		$candidate = $this->createMock(Candidate::class);
		$candidate->method('getMessage')->willReturn($message);

		$result = $this->createMock(GenerativeAiResult::class);
		$result->method('getCandidates')->willReturn([$candidate]);

		$array_format = $this->extractor->toArrayFormat($result);

		$this->assertCount(1, $array_format);
		$this->assertNotEmpty($array_format[0]['id']);
		$this->assertStringStartsWith('tool_call_', $array_format[0]['id']);
		$this->assertSame('tool_without_id', $array_format[0]['name']);
	}

	public function test_toArrayFormat_handlesNullArgs(): void
	{
		$function_call = new FunctionCall('call_id', 'no_args_tool', null);

		$parts = [new MessagePart($function_call)];

		$message = $this->createMock(Message::class);
		$message->method('getParts')->willReturn($parts);

		$candidate = $this->createMock(Candidate::class);
		$candidate->method('getMessage')->willReturn($message);

		$result = $this->createMock(GenerativeAiResult::class);
		$result->method('getCandidates')->willReturn([$candidate]);

		$array_format = $this->extractor->toArrayFormat($result);

		$this->assertCount(1, $array_format);
		$this->assertIsArray($array_format[0]['arguments']);
		$this->assertCount(0, $array_format[0]['arguments']);
	}

	public function test_toArrayFormat_withEmptyResult_returnsEmptyArray(): void
	{
		$result = $this->createMock(GenerativeAiResult::class);
		$result->method('getCandidates')->willReturn([]);

		$array_format = $this->extractor->toArrayFormat($result);

		$this->assertIsArray($array_format);
		$this->assertCount(0, $array_format);
	}

	public function test_fromResult_preservesOrderOfFunctionCalls(): void
	{
		$function_calls = [];
		$parts = [];

		for ($i = 1; $i <= 5; $i++) {
			$fc = new FunctionCall('call_' . $i, 'tool_' . $i, ['order' => $i]);
			$function_calls[] = $fc;
			$parts[] = new MessagePart($fc);

			if ($i < 5) {
				$parts[] = new MessagePart('Text between call ' . $i . ' and ' . ($i + 1));
			}
		}

		$message = $this->createMock(Message::class);
		$message->method('getParts')->willReturn($parts);

		$candidate = $this->createMock(Candidate::class);
		$candidate->method('getMessage')->willReturn($message);

		$result = $this->createMock(GenerativeAiResult::class);
		$result->method('getCandidates')->willReturn([$candidate]);

		$extracted = $this->extractor->fromResult($result);

		$this->assertCount(5, $extracted);

		for ($i = 0; $i < 5; $i++) {
			$this->assertSame('call_' . ($i + 1), $extracted[$i]->getId());
			$this->assertSame('tool_' . ($i + 1), $extracted[$i]->getName());
		}
	}
}
