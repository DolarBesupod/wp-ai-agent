<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Core\Exceptions;

use PHPUnit\Framework\TestCase;
use Automattic\Automattic\WpAiAgent\Core\Exceptions\AgentException;
use Automattic\Automattic\WpAiAgent\Core\Exceptions\ToolNotFoundException;

/**
 * Tests for ToolNotFoundException.
 *
 * @covers \Automattic\WpAiAgent\Core\Exceptions\ToolNotFoundException
 */
final class ToolNotFoundExceptionTest extends TestCase
{
	public function test_constructor_setsToolName(): void
	{
		$exception = new ToolNotFoundException('bash');

		$this->assertSame('bash', $exception->getToolName());
	}

	public function test_constructor_setsDescriptiveMessage(): void
	{
		$exception = new ToolNotFoundException('custom_tool');

		$this->assertSame('Tool "custom_tool" not found in the registry.', $exception->getMessage());
	}

	public function test_constructor_includesToolInContext(): void
	{
		$exception = new ToolNotFoundException('my_tool');

		$this->assertSame('my_tool', $exception->getContext()['tool']);
	}

	public function test_constructor_setPreviousException(): void
	{
		$previous = new \RuntimeException('Previous');
		$exception = new ToolNotFoundException('tool', $previous);

		$this->assertSame($previous, $exception->getPrevious());
	}

	public function test_exception_extendsAgentException(): void
	{
		$exception = new ToolNotFoundException('tool');

		$this->assertInstanceOf(AgentException::class, $exception);
	}

	public function test_exception_canBeCaughtAsAgentException(): void
	{
		$caught = false;

		try {
			throw new ToolNotFoundException('test_tool');
		} catch (AgentException $e) {
			$caught = true;
			$this->assertSame('test_tool', $e->getContextValue('tool'));
		}

		$this->assertTrue($caught);
	}
}
