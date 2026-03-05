<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Integration\AiClient;

use PHPUnit\Framework\TestCase;
use Automattic\Automattic\WpAiAgent\Core\Exceptions\AiClientException;
use Automattic\Automattic\WpAiAgent\Integration\AiClient\SseResponseParser;

/**
 * Unit tests for SseResponseParser.
 *
 * @covers \Automattic\WpAiAgent\Integration\AiClient\SseResponseParser
 */
final class SseResponseParserTest extends TestCase
{
	public function test_extractEventData_withValidSseBody_returnsDecodedJson(): void
	{
		$sse_body = "event: response.completed\ndata: {\"id\":\"resp_123\",\"status\":\"completed\"}\n\n";

		$result = SseResponseParser::extractEventData($sse_body, 'response.completed');

		$this->assertSame('resp_123', $result['id']);
		$this->assertSame('completed', $result['status']);
	}

	public function test_extractEventData_withMultipleEvents_returnsLastMatch(): void
	{
		$sse_body = "event: response.completed\ndata: {\"id\":\"resp_first\",\"status\":\"in_progress\"}\n\n"
			. "event: response.output_text.delta\ndata: {\"delta\":\"Hello\"}\n\n"
			. "event: response.completed\ndata: {\"id\":\"resp_last\",\"status\":\"completed\"}\n\n";

		$result = SseResponseParser::extractEventData($sse_body, 'response.completed');

		$this->assertSame('resp_last', $result['id']);
		$this->assertSame('completed', $result['status']);
	}

	public function test_extractEventData_withMissingEvent_throwsException(): void
	{
		$sse_body = "event: response.created\ndata: {\"id\":\"resp_123\"}\n\n";

		$this->expectException(AiClientException::class);
		$this->expectExceptionMessage('Expected SSE event "response.completed" not found');

		SseResponseParser::extractEventData($sse_body, 'response.completed');
	}

	public function test_extractEventData_withInvalidJson_throwsException(): void
	{
		$sse_body = "event: response.completed\ndata: not-valid-json\n\n";

		$this->expectException(AiClientException::class);

		SseResponseParser::extractEventData($sse_body, 'response.completed');
	}

	public function test_extractEventData_withEmptyBody_throwsException(): void
	{
		$this->expectException(AiClientException::class);
		$this->expectExceptionMessage('AI response body is empty');

		SseResponseParser::extractEventData('', 'response.completed');
	}

	public function test_parseEvents_withMultiLineData_concatenatesLines(): void
	{
		$sse_body = "event: response.completed\ndata: {\"id\":\"resp_123\",\ndata: \"status\":\"completed\"}\n\n";

		$events = SseResponseParser::parseEvents($sse_body);

		$this->assertCount(1, $events);
		$this->assertSame('response.completed', $events[0]['event']);
		$this->assertSame("{\"id\":\"resp_123\",\n\"status\":\"completed\"}", $events[0]['data']);
	}

	public function test_parseEvents_withNoEventLine_defaultsToMessage(): void
	{
		$sse_body = "data: {\"text\":\"hello\"}\n\n";

		$events = SseResponseParser::parseEvents($sse_body);

		$this->assertCount(1, $events);
		$this->assertSame('message', $events[0]['event']);
		$this->assertSame('{"text":"hello"}', $events[0]['data']);
	}

	public function test_extractEventData_withErrorEvent_extractsErrorData(): void
	{
		$sse_body = "event: response.created\ndata: {\"id\":\"resp_123\"}\n\n"
			. "event: error\ndata: {\"code\":\"rate_limit\",\"message\":\"Too many requests\"}\n\n";

		$result = SseResponseParser::extractEventData($sse_body, 'error');

		$this->assertSame('rate_limit', $result['code']);
		$this->assertSame('Too many requests', $result['message']);
	}
}
