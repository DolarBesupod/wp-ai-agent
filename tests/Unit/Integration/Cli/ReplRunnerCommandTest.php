<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Integration\Cli;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PhpCliAgent\Core\Contracts\AgentInterface;
use PhpCliAgent\Core\Contracts\OutputHandlerInterface;
use PhpCliAgent\Core\Contracts\SessionInterface;
use PhpCliAgent\Core\Contracts\SessionRepositoryInterface;
use PhpCliAgent\Core\ValueObjects\SessionId;
use PhpCliAgent\Integration\Cli\ReplRunner;

/**
 * Tests for ReplRunner command handling using reflection.
 *
 * @covers \PhpCliAgent\Integration\Cli\ReplRunner
 */
final class ReplRunnerCommandTest extends TestCase
{
	private AgentInterface&MockObject $agent;
	private OutputHandlerInterface&MockObject $output_handler;
	private SessionRepositoryInterface&MockObject $session_repository;
	private ReplRunner $runner;
	private ReplRunnerTestHelper $helper;

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

		$this->helper = new ReplRunnerTestHelper($this->runner);
	}

	public function test_isCommand_returnsTrueForSlashPrefix(): void
	{
		$this->assertTrue($this->helper->isCommand('/quit'));
		$this->assertTrue($this->helper->isCommand('/help'));
		$this->assertTrue($this->helper->isCommand('/custom arg1 arg2'));
	}

	public function test_isCommand_returnsFalseForNonCommands(): void
	{
		$this->assertFalse($this->helper->isCommand('hello world'));
		$this->assertFalse($this->helper->isCommand('say /something'));
		$this->assertFalse($this->helper->isCommand(''));
	}

	public function test_handleCommand_quit_returnsFalseToStopLoop(): void
	{
		$session = $this->createMock(SessionInterface::class);
		$session->method('getId')->willReturn(SessionId::fromString('test-session'));

		$this->agent->method('getCurrentSession')->willReturn($session);
		$this->session_repository->expects($this->once())->method('save')->with($session);
		$this->output_handler->expects($this->atLeastOnce())->method('writeStatus');
		$this->output_handler->expects($this->atLeastOnce())->method('writeLine');

		$result = $this->helper->handleCommand('/quit');

		$this->assertFalse($result);
	}

	public function test_handleCommand_exit_returnsFalseToStopLoop(): void
	{
		$this->agent->method('getCurrentSession')->willReturn(null);
		$this->output_handler->expects($this->atLeastOnce())->method('writeLine');

		$result = $this->helper->handleCommand('/exit');

		$this->assertFalse($result);
	}

	public function test_handleCommand_q_returnsFalseToStopLoop(): void
	{
		$this->agent->method('getCurrentSession')->willReturn(null);
		$this->output_handler->expects($this->atLeastOnce())->method('writeLine');

		$result = $this->helper->handleCommand('/q');

		$this->assertFalse($result);
	}

	public function test_handleCommand_help_returnsTrueToContinueLoop(): void
	{
		$this->output_handler->expects($this->atLeastOnce())->method('writeLine');

		$result = $this->helper->handleCommand('/help');

		$this->assertTrue($result);
	}

	public function test_handleCommand_questionMark_returnsTrueToContinueLoop(): void
	{
		$this->output_handler->expects($this->atLeastOnce())->method('writeLine');

		$result = $this->helper->handleCommand('/?');

		$this->assertTrue($result);
	}

	public function test_handleCommand_unknown_writesErrorAndContinues(): void
	{
		$this->output_handler->expects($this->once())
			->method('writeError')
			->with($this->stringContains('Unknown command: /unknown'));

		$result = $this->helper->handleCommand('/unknown');

		$this->assertTrue($result);
	}

	public function test_handleCommand_customHandler_callsHandler(): void
	{
		$handler_called = false;
		$received_args = null;

		$this->runner->registerCommand('custom', function (string $args) use (&$handler_called, &$received_args): bool {
			$handler_called = true;
			$received_args = $args;
			return true;
		});

		$result = $this->helper->handleCommand('/custom arg1 arg2');

		$this->assertTrue($result);
		$this->assertTrue($handler_called);
		$this->assertSame('arg1 arg2', $received_args);
	}

	public function test_handleCommand_customHandler_returnsFalseToExit(): void
	{
		$this->runner->registerCommand('exit-custom', fn(string $args): bool => false);

		$result = $this->helper->handleCommand('/exit-custom');

		$this->assertFalse($result);
	}

	public function test_handleCommand_customHandler_exceptionIsHandled(): void
	{
		$this->runner->registerCommand('error', function (string $args): bool {
			throw new \RuntimeException('Handler error');
		});

		$this->output_handler->expects($this->once())
			->method('writeError')
			->with($this->stringContains('Handler error'));

		$result = $this->helper->handleCommand('/error');

		$this->assertTrue($result);
	}

	public function test_handleCommand_customHandler_exceptionShowsDebugWhenEnabled(): void
	{
		$this->runner->setDebugEnabled(true);

		$this->runner->registerCommand('error', function (string $args): bool {
			throw new \RuntimeException('Handler error');
		});

		$this->output_handler->expects($this->once())->method('writeError');
		$this->output_handler->expects($this->once())->method('writeDebug');

		$this->helper->handleCommand('/error');
	}

	public function test_handleCommand_caseInsensitive(): void
	{
		$this->output_handler->expects($this->atLeastOnce())->method('writeLine');

		$result = $this->helper->handleCommand('/HELP');

		$this->assertTrue($result);
	}

	public function test_handleCommand_withNoArguments(): void
	{
		$received_args = 'not-set';

		$this->runner->registerCommand('noargs', function (string $args) use (&$received_args): bool {
			$received_args = $args;
			return true;
		});

		$this->helper->handleCommand('/noargs');

		$this->assertSame('', $received_args);
	}

	public function test_handleShutdown_savesSessionWhenPresent(): void
	{
		$session = $this->createMock(SessionInterface::class);
		$session->method('getId')->willReturn(SessionId::fromString('save-test'));

		$this->agent->method('getCurrentSession')->willReturn($session);

		$this->session_repository->expects($this->once())
			->method('save')
			->with($session);

		$this->output_handler->expects($this->once())
			->method('writeStatus')
			->with($this->stringContains('Session saved: save-test'));

		$this->helper->handleShutdown();
	}

	public function test_handleShutdown_handlesNoSession(): void
	{
		$this->agent->method('getCurrentSession')->willReturn(null);

		$this->session_repository->expects($this->never())->method('save');

		$this->output_handler->expects($this->once())
			->method('writeLine')
			->with('Goodbye!');

		$this->helper->handleShutdown();
	}

	public function test_handleShutdown_handlesSaveError(): void
	{
		$session = $this->createMock(SessionInterface::class);
		$session->method('getId')->willReturn(SessionId::fromString('error-test'));

		$this->agent->method('getCurrentSession')->willReturn($session);

		$this->session_repository->method('save')
			->willThrowException(new \RuntimeException('Save failed'));

		$this->output_handler->expects($this->once())
			->method('writeError')
			->with($this->stringContains('Failed to save session'));

		$this->helper->handleShutdown();
	}

	public function test_handleShutdown_showsDebugOnSaveErrorWhenEnabled(): void
	{
		$session = $this->createMock(SessionInterface::class);
		$session->method('getId')->willReturn(SessionId::fromString('debug-test'));

		$this->agent->method('getCurrentSession')->willReturn($session);

		$this->session_repository->method('save')
			->willThrowException(new \RuntimeException('Save failed'));

		$this->runner->setDebugEnabled(true);

		$this->output_handler->expects($this->once())->method('writeError');
		$this->output_handler->expects($this->once())->method('writeDebug');

		$this->helper->handleShutdown();
	}

	public function test_processUserMessage_sendsMessageToAgent(): void
	{
		$this->agent->expects($this->once())
			->method('sendMessage')
			->with('Hello, agent!');

		$this->output_handler->expects($this->once())->method('writeLine')->with('');

		$this->helper->processUserMessage('Hello, agent!');
	}

	public function test_processUserMessage_handlesAgentException(): void
	{
		$this->agent->method('sendMessage')
			->willThrowException(new \RuntimeException('Agent error'));

		$this->output_handler->expects($this->once())
			->method('writeError')
			->with($this->stringContains('Agent error'));

		$this->helper->processUserMessage('Hello');
	}

	public function test_processUserMessage_showsDebugOnExceptionWhenEnabled(): void
	{
		$this->runner->setDebugEnabled(true);

		$this->agent->method('sendMessage')
			->willThrowException(new \RuntimeException('Agent error'));

		$this->output_handler->expects($this->once())->method('writeError');
		$this->output_handler->expects($this->once())->method('writeDebug');

		$this->helper->processUserMessage('Hello');
	}

	public function test_displayWelcome_outputsWelcomeMessage(): void
	{
		$lines = [];
		$this->output_handler->method('writeLine')
			->willReturnCallback(function (string $line) use (&$lines): void {
				$lines[] = $line;
			});

		$this->helper->displayWelcome();

		$combined = implode("\n", $lines);
		$this->assertStringContainsString('Welcome', $combined);
		$this->assertStringContainsString('/help', $combined);
		$this->assertStringContainsString('/quit', $combined);
	}

	public function test_displayHelp_showsBuiltInCommands(): void
	{
		$output = '';
		$this->output_handler->method('writeLine')
			->willReturnCallback(function (string $line) use (&$output): void {
				$output .= $line;
			});

		$this->helper->displayHelp();

		$this->assertStringContainsString('/help', $output);
		$this->assertStringContainsString('/quit', $output);
		$this->assertStringContainsString('/exit', $output);
	}

	public function test_displayHelp_showsCustomCommands(): void
	{
		$this->runner->registerCommand('custom1', fn(string $args): bool => true);
		$this->runner->registerCommand('custom2', fn(string $args): bool => true);

		$output = '';
		$this->output_handler->method('writeLine')
			->willReturnCallback(function (string $line) use (&$output): void {
				$output .= $line;
			});

		$this->helper->displayHelp();

		$this->assertStringContainsString('custom1', $output);
		$this->assertStringContainsString('custom2', $output);
	}
}

/**
 * Helper class to invoke private methods via reflection.
 */
final class ReplRunnerTestHelper
{
	private ReplRunner $runner;

	public function __construct(ReplRunner $runner)
	{
		$this->runner = $runner;
	}

	public function isCommand(string $input): bool
	{
		return $this->invokeMethod('isCommand', [$input]);
	}

	public function handleCommand(string $input): bool
	{
		return $this->invokeMethod('handleCommand', [$input]);
	}

	public function handleShutdown(): void
	{
		$this->invokeMethod('handleShutdown', []);
	}

	public function processUserMessage(string $message): void
	{
		$this->invokeMethod('processUserMessage', [$message]);
	}

	public function displayWelcome(): void
	{
		$this->invokeMethod('displayWelcome', []);
	}

	public function displayHelp(): void
	{
		$this->invokeMethod('displayHelp', []);
	}

	/**
	 * @param array<int, mixed> $args
	 */
	private function invokeMethod(string $method_name, array $args): mixed
	{
		$reflection = new \ReflectionMethod(ReplRunner::class, $method_name);

		return $reflection->invoke($this->runner, ...$args);
	}
}
