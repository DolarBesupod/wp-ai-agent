<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Core\Exceptions;

use PHPUnit\Framework\TestCase;
use Automattic\Automattic\WpAiAgent\Core\Exceptions\AgentException;
use Automattic\Automattic\WpAiAgent\Core\Exceptions\ToolExecutionException;

/**
 * Tests for ToolExecutionException.
 *
 * @covers \Automattic\WpAiAgent\Core\Exceptions\ToolExecutionException
 */
final class ToolExecutionExceptionTest extends TestCase
{
	public function test_constructor_setsToolName(): void
	{
		$exception = new ToolExecutionException('bash', 'Command failed');

		$this->assertSame('bash', $exception->getToolName());
	}

	public function test_constructor_formatsMessage(): void
	{
		$exception = new ToolExecutionException('bash', 'Permission denied');

		$this->assertSame('Tool "bash" execution failed: Permission denied', $exception->getMessage());
	}

	public function test_constructor_setsArguments(): void
	{
		$arguments = ['command' => 'ls -la', 'timeout' => 30];
		$exception = new ToolExecutionException('bash', 'Failed', $arguments);

		$this->assertSame($arguments, $exception->getArguments());
	}

	public function test_constructor_setsPreviousException(): void
	{
		$previous = new \RuntimeException('IO error');
		$exception = new ToolExecutionException('file_read', 'Failed', [], $previous);

		$this->assertSame($previous, $exception->getPrevious());
	}

	public function test_exception_extendsAgentException(): void
	{
		$exception = new ToolExecutionException('tool', 'error');

		$this->assertInstanceOf(AgentException::class, $exception);
	}

	public function test_exception_canBeCaughtAsAgentException(): void
	{
		$caught = false;

		try {
			throw new ToolExecutionException('bash', 'Failed', ['tool' => 'bash']);
		} catch (AgentException $e) {
			$caught = true;
		}

		$this->assertTrue($caught);
	}
}
