<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Core\Exceptions;

use PHPUnit\Framework\TestCase;
use PhpCliAgent\Core\Exceptions\AgentException;
use PhpCliAgent\Core\Exceptions\ConfirmationDeniedException;

/**
 * Tests for ConfirmationDeniedException.
 *
 * @covers \PhpCliAgent\Core\Exceptions\ConfirmationDeniedException
 */
final class ConfirmationDeniedExceptionTest extends TestCase
{
	public function test_constructor_setsAction(): void
	{
		$exception = new ConfirmationDeniedException('delete file');

		$this->assertSame('delete file', $exception->getAction());
	}

	public function test_constructor_setsReason(): void
	{
		$exception = new ConfirmationDeniedException('execute command', 'Too risky');

		$this->assertSame('Too risky', $exception->getReason());
	}

	public function test_constructor_setsNullReasonByDefault(): void
	{
		$exception = new ConfirmationDeniedException('action');

		$this->assertNull($exception->getReason());
	}

	public function test_constructor_formatsMessageWithoutReason(): void
	{
		$exception = new ConfirmationDeniedException('run bash');

		$this->assertSame('Confirmation denied for action: run bash', $exception->getMessage());
	}

	public function test_constructor_formatsMessageWithReason(): void
	{
		$exception = new ConfirmationDeniedException('run bash', 'Not allowed');

		$this->assertStringContainsString('Confirmation denied for action: run bash', $exception->getMessage());
		$this->assertStringContainsString('Reason: Not allowed', $exception->getMessage());
	}

	public function test_constructor_setsContext(): void
	{
		$exception = new ConfirmationDeniedException('test action', 'test reason', ['extra' => 'data']);

		$context = $exception->getContext();
		$this->assertSame('test action', $context['action']);
		$this->assertSame('test reason', $context['denied_reason']);
		$this->assertSame('data', $context['extra']);
	}

	public function test_toolExecutionDenied_createsException(): void
	{
		$exception = ConfirmationDeniedException::toolExecutionDenied(
			'bash',
			['command' => 'rm -rf /'],
			'Dangerous command'
		);

		$this->assertStringContainsString('bash', $exception->getAction());
		$this->assertSame('Dangerous command', $exception->getReason());
		$this->assertSame('bash', $exception->getContextValue('tool'));
		$this->assertSame(['command' => 'rm -rf /'], $exception->getContextValue('arguments'));
	}

	public function test_fileOperationDenied_createsException(): void
	{
		$exception = ConfirmationDeniedException::fileOperationDenied(
			'delete',
			'/etc/passwd'
		);

		$this->assertStringContainsString('delete', $exception->getAction());
		$this->assertStringContainsString('/etc/passwd', $exception->getAction());
		$this->assertSame('delete', $exception->getContextValue('operation'));
		$this->assertSame('/etc/passwd', $exception->getContextValue('path'));
	}

	public function test_exception_extendsAgentException(): void
	{
		$exception = new ConfirmationDeniedException('action');

		$this->assertInstanceOf(AgentException::class, $exception);
	}

	public function test_exception_canBeCaughtAsAgentException(): void
	{
		$caught = false;

		try {
			throw ConfirmationDeniedException::toolExecutionDenied('bash', ['tool' => 'bash']);
		} catch (AgentException $e) {
			$caught = true;
			$this->assertSame('bash', $e->getContextValue('tool'));
		}

		$this->assertTrue($caught);
	}
}
