<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Integration\Cli;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PhpCliAgent\Core\Contracts\AgentInterface;
use PhpCliAgent\Core\Contracts\OutputHandlerInterface;
use PhpCliAgent\Core\Contracts\SessionRepositoryInterface;
use PhpCliAgent\Integration\Cli\ReplRunner;

/**
 * Tests for ReplRunner.
 *
 * @covers \PhpCliAgent\Integration\Cli\ReplRunner
 */
final class ReplRunnerTest extends TestCase
{
	private AgentInterface&MockObject $agent;
	private OutputHandlerInterface&MockObject $output_handler;
	private SessionRepositoryInterface&MockObject $session_repository;
	private ReplRunner $runner;

	protected function setUp(): void
	{
		$this->agent = $this->createMock(AgentInterface::class);
		$this->output_handler = $this->createMock(OutputHandlerInterface::class);
		$this->session_repository = $this->createMock(SessionRepositoryInterface::class);

		$this->runner = new ReplRunner(
			$this->agent,
			$this->output_handler,
			$this->session_repository
		);
	}

	public function test_constructor_createsInstance(): void
	{
		$this->assertInstanceOf(ReplRunner::class, $this->runner);
	}

	public function test_getPrompt_returnsDefaultPrompt(): void
	{
		$this->assertSame(ReplRunner::DEFAULT_PROMPT, $this->runner->getPrompt());
	}

	public function test_setPrompt_changesPrompt(): void
	{
		$this->runner->setPrompt('Custom> ');

		$this->assertSame('Custom> ', $this->runner->getPrompt());
	}

	public function test_isRunning_returnsFalseInitially(): void
	{
		$this->assertFalse($this->runner->isRunning());
	}

	public function test_stop_setsRunningToFalse(): void
	{
		// We can't test the full loop, but we can test that stop() works.
		$this->runner->stop();

		$this->assertFalse($this->runner->isRunning());
	}

	public function test_isDebugEnabled_returnsFalseByDefault(): void
	{
		$this->assertFalse($this->runner->isDebugEnabled());
	}

	public function test_setDebugEnabled_changesDebugState(): void
	{
		$this->runner->setDebugEnabled(true);

		$this->assertTrue($this->runner->isDebugEnabled());

		$this->runner->setDebugEnabled(false);

		$this->assertFalse($this->runner->isDebugEnabled());
	}

	public function test_registerCommand_addsCommandHandler(): void
	{
		$handler = fn(string $args): bool => true;

		$this->runner->registerCommand('test', $handler);

		$commands = $this->runner->getRegisteredCommands();
		$this->assertContains('test', $commands);
	}

	public function test_registerCommand_normalizesToLowercase(): void
	{
		$handler = fn(string $args): bool => true;

		$this->runner->registerCommand('TEST', $handler);

		$commands = $this->runner->getRegisteredCommands();
		$this->assertContains('test', $commands);
	}

	public function test_unregisterCommand_removesCommandHandler(): void
	{
		$handler = fn(string $args): bool => true;

		$this->runner->registerCommand('test', $handler);
		$this->runner->unregisterCommand('test');

		$commands = $this->runner->getRegisteredCommands();
		$this->assertNotContains('test', $commands);
	}

	public function test_getRegisteredCommands_returnsEmptyArrayInitially(): void
	{
		$commands = $this->runner->getRegisteredCommands();

		$this->assertSame([], $commands);
	}

	public function test_getRegisteredCommands_returnsAllRegisteredCommands(): void
	{
		$handler = fn(string $args): bool => true;

		$this->runner->registerCommand('command1', $handler);
		$this->runner->registerCommand('command2', $handler);

		$commands = $this->runner->getRegisteredCommands();

		$this->assertCount(2, $commands);
		$this->assertContains('command1', $commands);
		$this->assertContains('command2', $commands);
	}

	public function test_constants_haveExpectedValues(): void
	{
		$this->assertSame('You: ', ReplRunner::DEFAULT_PROMPT);
		$this->assertSame('/', ReplRunner::COMMAND_PREFIX);
	}

	public function test_isAutoSaveEnabled_returnsTrueByDefault(): void
	{
		$this->assertTrue($this->runner->isAutoSaveEnabled());
	}

	public function test_setAutoSaveEnabled_changesAutoSaveState(): void
	{
		$this->runner->setAutoSaveEnabled(false);

		$this->assertFalse($this->runner->isAutoSaveEnabled());

		$this->runner->setAutoSaveEnabled(true);

		$this->assertTrue($this->runner->isAutoSaveEnabled());
	}
}
