<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Core\Exceptions;

use PHPUnit\Framework\TestCase;
use Automattic\WpAiAgent\Core\Exceptions\AgentException;
use Automattic\WpAiAgent\Core\Exceptions\AiClientException;

/**
 * Tests for AiClientException.
 *
 * @covers \Automattic\WpAiAgent\Core\Exceptions\AiClientException
 */
final class AiClientExceptionTest extends TestCase
{
	public function test_initializationFailed_createsException(): void
	{
		$exception = AiClientException::initializationFailed('Missing API endpoint');

		$this->assertStringContainsString('initialize AI client', $exception->getMessage());
		$this->assertSame('initialization_failed', $exception->getContextValue('type'));
		$this->assertSame('Missing API endpoint', $exception->getContextValue('reason'));
	}

	public function test_initializationFailed_acceptsPreviousException(): void
	{
		$previous = new \RuntimeException('Config error');
		$exception = AiClientException::initializationFailed('Failed', $previous);

		$this->assertSame($previous, $exception->getPrevious());
	}

	public function test_invalidApiKey_createsException(): void
	{
		$exception = AiClientException::invalidApiKey();

		$this->assertStringContainsString('Invalid or missing API key', $exception->getMessage());
		$this->assertSame('invalid_api_key', $exception->getContextValue('type'));
	}

	public function test_modelNotFound_createsException(): void
	{
		$exception = AiClientException::modelNotFound('claude-4-turbo');

		$this->assertStringContainsString('claude-4-turbo', $exception->getMessage());
		$this->assertSame('model_not_found', $exception->getContextValue('type'));
		$this->assertSame('claude-4-turbo', $exception->getContextValue('model'));
	}

	public function test_streamingFailed_createsException(): void
	{
		$exception = AiClientException::streamingFailed('Connection reset');

		$this->assertStringContainsString('streaming failed', $exception->getMessage());
		$this->assertSame('streaming_failed', $exception->getContextValue('type'));
	}

	public function test_messageFormattingFailed_createsException(): void
	{
		$exception = AiClientException::messageFormattingFailed('Invalid content type');

		$this->assertStringContainsString('format message', $exception->getMessage());
		$this->assertSame('message_formatting_failed', $exception->getContextValue('type'));
	}

	public function test_toolConversionFailed_createsException(): void
	{
		$exception = AiClientException::toolConversionFailed('custom_tool', 'Missing schema');

		$this->assertStringContainsString('custom_tool', $exception->getMessage());
		$this->assertSame('tool_conversion_failed', $exception->getContextValue('type'));
		$this->assertSame('custom_tool', $exception->getContextValue('tool'));
	}

	public function test_quotaExceeded_createsException(): void
	{
		$exception = AiClientException::quotaExceeded();

		$this->assertStringContainsString('quota exceeded', $exception->getMessage());
		$this->assertSame('quota_exceeded', $exception->getContextValue('type'));
	}

	public function test_quotaExceeded_withLimitType_createsException(): void
	{
		$exception = AiClientException::quotaExceeded('tokens');

		$this->assertStringContainsString('tokens limit', $exception->getMessage());
		$this->assertSame('tokens', $exception->getContextValue('limit_type'));
	}

	public function test_exception_extendsAgentException(): void
	{
		$exception = AiClientException::invalidApiKey();

		$this->assertInstanceOf(AgentException::class, $exception);
	}

	public function test_exception_canBeCaughtAsAgentException(): void
	{
		$caught = false;

		try {
			throw AiClientException::modelNotFound('test-model');
		} catch (AgentException $e) {
			$caught = true;
			$this->assertSame('test-model', $e->getContextValue('model'));
		}

		$this->assertTrue($caught);
	}

	public function test_emptyResponse_returnsCorrectException(): void
	{
		$exception = AiClientException::emptyResponse();

		$this->assertStringContainsString('empty', $exception->getMessage());
		$this->assertSame('empty_response', $exception->getContextValue('type'));
	}

	public function test_sseEventNotFound_returnsCorrectException(): void
	{
		$exception = AiClientException::sseEventNotFound('response.completed');

		$this->assertStringContainsString('response.completed', $exception->getMessage());
		$this->assertSame('sse_event_not_found', $exception->getContextValue('type'));
		$this->assertSame('response.completed', $exception->getContextValue('event_type'));
	}
}
