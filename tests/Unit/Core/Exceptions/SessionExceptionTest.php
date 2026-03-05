<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Core\Exceptions;

use PHPUnit\Framework\TestCase;
use Automattic\Automattic\WpAiAgent\Core\Exceptions\AgentException;
use Automattic\Automattic\WpAiAgent\Core\Exceptions\SessionException;

/**
 * Tests for SessionException.
 *
 * @covers \Automattic\WpAiAgent\Core\Exceptions\SessionException
 */
final class SessionExceptionTest extends TestCase
{
	public function test_invalidState_createsException(): void
	{
		$exception = SessionException::invalidState('Session is locked');

		$this->assertStringContainsString('Invalid session state', $exception->getMessage());
		$this->assertStringContainsString('Session is locked', $exception->getMessage());
		$this->assertSame('invalid_state', $exception->getContextValue('type'));
		$this->assertSame('Session is locked', $exception->getContextValue('reason'));
	}

	public function test_invalidState_acceptsPreviousException(): void
	{
		$previous = new \RuntimeException('Lock failed');
		$exception = SessionException::invalidState('Cannot acquire lock', $previous);

		$this->assertSame($previous, $exception->getPrevious());
	}

	public function test_expired_createsException(): void
	{
		$exception = SessionException::expired('session-123');

		$this->assertStringContainsString('session-123', $exception->getMessage());
		$this->assertStringContainsString('expired', $exception->getMessage());
		$this->assertSame('expired', $exception->getContextValue('type'));
		$this->assertSame('session-123', $exception->getContextValue('session_id'));
	}

	public function test_initializationFailed_createsException(): void
	{
		$exception = SessionException::initializationFailed('Database unavailable');

		$this->assertStringContainsString('Failed to initialize', $exception->getMessage());
		$this->assertSame('initialization_failed', $exception->getContextValue('type'));
	}

	public function test_corrupted_createsException(): void
	{
		$exception = SessionException::corrupted('session-456', 'Invalid JSON format');

		$this->assertStringContainsString('corrupted', $exception->getMessage());
		$this->assertSame('corrupted', $exception->getContextValue('type'));
		$this->assertSame('session-456', $exception->getContextValue('session_id'));
		$this->assertSame('Invalid JSON format', $exception->getContextValue('reason'));
	}

	public function test_exception_extendsAgentException(): void
	{
		$exception = SessionException::invalidState('test');

		$this->assertInstanceOf(AgentException::class, $exception);
	}

	public function test_exception_canBeCaughtAsAgentException(): void
	{
		$caught = false;

		try {
			throw SessionException::expired('test-id');
		} catch (AgentException $e) {
			$caught = true;
			$this->assertSame('expired', $e->getContextValue('type'));
		}

		$this->assertTrue($caught);
	}
}
