<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Core\Tool;

use PHPUnit\Framework\TestCase;
use Automattic\WpAiAgent\Core\Contracts\ConfirmationHandlerInterface;
use Automattic\WpAiAgent\Core\Contracts\ToolInterface;
use Automattic\WpAiAgent\Core\Contracts\ToolRegistryInterface;
use Automattic\WpAiAgent\Core\Exceptions\ToolExecutionException;
use Automattic\WpAiAgent\Core\Tool\ToolExecutor;
use Automattic\WpAiAgent\Core\ValueObjects\ToolResult;
use Psr\Log\NullLogger;

/**
 * Tests for ToolExecutor.
 *
 * @covers \Automattic\WpAiAgent\Core\Tool\ToolExecutor
 */
final class ToolExecutorTest extends TestCase
{
	private ToolRegistryInterface $registry;
	private ConfirmationHandlerInterface $confirmation_handler;
	private ToolExecutor $executor;

	protected function setUp(): void
	{
		$this->registry = $this->createMock(ToolRegistryInterface::class);
		$this->confirmation_handler = $this->createMock(ConfirmationHandlerInterface::class);
		$this->executor = new ToolExecutor(
			$this->registry,
			$this->confirmation_handler,
			new NullLogger()
		);
	}

	public function test_execute_returnsToolResultOnSuccess(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('requiresConfirmation')->willReturn(false);
		$tool->method('execute')->willReturn(ToolResult::success('hi'));

		$this->registry->method('get')->with('bash')->willReturn($tool);
		$this->confirmation_handler->method('isAutoConfirm')->willReturn(true);

		$result = $this->executor->execute('bash', ['command' => 'echo hi']);

		$this->assertTrue($result->isSuccess());
		$this->assertSame('hi', $result->getOutput());
	}

	public function test_execute_throwsForNonexistentTool(): void
	{
		$this->registry->method('get')->with('nonexistent')->willReturn(null);

		$this->expectException(ToolExecutionException::class);
		$this->expectExceptionMessage('Tool "nonexistent" is not registered');

		$this->executor->execute('nonexistent', []);
	}

	public function test_execute_requestsConfirmationWhenRequired(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('requiresConfirmation')->willReturn(true);
		$tool->method('execute')->willReturn(ToolResult::success('executed'));

		$this->registry->method('get')->with('dangerous_tool')->willReturn($tool);
		$this->confirmation_handler->method('isAutoConfirm')->willReturn(false);
		$this->confirmation_handler->method('shouldBypass')->willReturn(false);
		$this->confirmation_handler->method('confirm')->willReturn(true);

		$result = $this->executor->execute('dangerous_tool', ['action' => 'delete']);

		$this->assertTrue($result->isSuccess());
	}

	public function test_execute_returnsFailureWhenDenied(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('requiresConfirmation')->willReturn(true);

		$this->registry->method('get')->with('dangerous_tool')->willReturn($tool);
		$this->confirmation_handler->method('isAutoConfirm')->willReturn(false);
		$this->confirmation_handler->method('shouldBypass')->willReturn(false);
		$this->confirmation_handler->method('confirm')->willReturn(false);

		$result = $this->executor->execute('dangerous_tool', []);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('denied', $result->getError() ?? '');
	}

	public function test_execute_skipsConfirmationWhenAutoConfirmEnabled(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('requiresConfirmation')->willReturn(true);
		$tool->method('execute')->willReturn(ToolResult::success('auto'));

		$this->registry->method('get')->with('bash')->willReturn($tool);
		$this->confirmation_handler->method('isAutoConfirm')->willReturn(true);
		$this->confirmation_handler->expects($this->never())->method('confirm');

		$result = $this->executor->execute('bash', []);

		$this->assertTrue($result->isSuccess());
	}

	public function test_execute_skipsConfirmationWhenBypassed(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('requiresConfirmation')->willReturn(true);
		$tool->method('execute')->willReturn(ToolResult::success('bypassed'));

		$this->registry->method('get')->with('safe_tool')->willReturn($tool);
		$this->confirmation_handler->method('isAutoConfirm')->willReturn(false);
		$this->confirmation_handler->method('shouldBypass')->with('safe_tool')->willReturn(true);
		$this->confirmation_handler->expects($this->never())->method('confirm');

		$result = $this->executor->execute('safe_tool', []);

		$this->assertTrue($result->isSuccess());
	}

	public function test_execute_skipsConfirmationWhenToolDoesNotRequire(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('requiresConfirmation')->willReturn(false);
		$tool->method('execute')->willReturn(ToolResult::success('no confirm'));

		$this->registry->method('get')->with('read_only')->willReturn($tool);
		$this->confirmation_handler->method('isAutoConfirm')->willReturn(false);
		$this->confirmation_handler->method('shouldBypass')->willReturn(false);
		$this->confirmation_handler->expects($this->never())->method('confirm');

		$result = $this->executor->execute('read_only', []);

		$this->assertTrue($result->isSuccess());
	}

	public function test_execute_handlesToolException(): void
	{
		$exception = new ToolExecutionException('failing_tool', 'Something went wrong');

		$tool = $this->createMock(ToolInterface::class);
		$tool->method('requiresConfirmation')->willReturn(false);
		$tool->method('execute')->willThrowException($exception);

		$this->registry->method('get')->with('failing_tool')->willReturn($tool);
		$this->confirmation_handler->method('isAutoConfirm')->willReturn(true);

		$this->expectException(ToolExecutionException::class);

		$this->executor->execute('failing_tool', []);
	}

	public function test_execute_catchesUnexpectedException(): void
	{
		$exception = new \RuntimeException('Unexpected error');

		$tool = $this->createMock(ToolInterface::class);
		$tool->method('requiresConfirmation')->willReturn(false);
		$tool->method('execute')->willThrowException($exception);

		$this->registry->method('get')->with('error_tool')->willReturn($tool);
		$this->confirmation_handler->method('isAutoConfirm')->willReturn(true);

		$result = $this->executor->execute('error_tool', []);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('Unexpected error', $result->getError() ?? '');
	}

	public function test_executeMultiple_executesAllToolCalls(): void
	{
		$tool1 = $this->createMock(ToolInterface::class);
		$tool1->method('requiresConfirmation')->willReturn(false);
		$tool1->method('execute')->willReturn(ToolResult::success('result1'));

		$tool2 = $this->createMock(ToolInterface::class);
		$tool2->method('requiresConfirmation')->willReturn(false);
		$tool2->method('execute')->willReturn(ToolResult::success('result2'));

		$this->registry->method('get')->willReturnCallback(function ($name) use ($tool1, $tool2) {
			return match ($name) {
				'tool1' => $tool1,
				'tool2' => $tool2,
				default => null,
			};
		});

		$this->confirmation_handler->method('isAutoConfirm')->willReturn(true);

		$tool_calls = [
			['name' => 'tool1', 'arguments' => ['arg' => 'value1']],
			['name' => 'tool2', 'arguments' => ['arg' => 'value2']],
		];

		$results = $this->executor->executeMultiple($tool_calls);

		$this->assertCount(2, $results);
		$this->assertSame('tool1', $results[0]['name']);
		$this->assertTrue($results[0]['result']->isSuccess());
		$this->assertSame('tool2', $results[1]['name']);
		$this->assertTrue($results[1]['result']->isSuccess());
	}

	public function test_executeMultiple_handlesFailedToolGracefully(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('requiresConfirmation')->willReturn(false);
		$tool->method('execute')->willReturn(ToolResult::success('ok'));

		$this->registry->method('get')->willReturnCallback(function ($name) use ($tool) {
			return $name === 'valid_tool' ? $tool : null;
		});

		$this->confirmation_handler->method('isAutoConfirm')->willReturn(true);

		$tool_calls = [
			['name' => 'valid_tool', 'arguments' => []],
			['name' => 'nonexistent', 'arguments' => []],
		];

		$results = $this->executor->executeMultiple($tool_calls);

		$this->assertCount(2, $results);
		$this->assertTrue($results[0]['result']->isSuccess());
		$this->assertFalse($results[1]['result']->isSuccess());
	}

	public function test_canExecuteWithoutConfirmation_returnsTrueWhenAutoConfirm(): void
	{
		$this->confirmation_handler->method('isAutoConfirm')->willReturn(true);

		$this->assertTrue($this->executor->canExecuteWithoutConfirmation('any_tool'));
	}

	public function test_canExecuteWithoutConfirmation_returnsTrueWhenBypassed(): void
	{
		$this->confirmation_handler->method('isAutoConfirm')->willReturn(false);
		$this->confirmation_handler->method('shouldBypass')->with('bypassed_tool')->willReturn(true);

		$this->assertTrue($this->executor->canExecuteWithoutConfirmation('bypassed_tool'));
	}

	public function test_canExecuteWithoutConfirmation_returnsTrueWhenToolDoesNotRequire(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('requiresConfirmation')->willReturn(false);

		$this->registry->method('get')->with('safe_tool')->willReturn($tool);
		$this->confirmation_handler->method('isAutoConfirm')->willReturn(false);
		$this->confirmation_handler->method('shouldBypass')->willReturn(false);

		$this->assertTrue($this->executor->canExecuteWithoutConfirmation('safe_tool'));
	}

	public function test_canExecuteWithoutConfirmation_returnsFalseWhenConfirmationRequired(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('requiresConfirmation')->willReturn(true);

		$this->registry->method('get')->with('dangerous_tool')->willReturn($tool);
		$this->confirmation_handler->method('isAutoConfirm')->willReturn(false);
		$this->confirmation_handler->method('shouldBypass')->willReturn(false);

		$this->assertFalse($this->executor->canExecuteWithoutConfirmation('dangerous_tool'));
	}
}
