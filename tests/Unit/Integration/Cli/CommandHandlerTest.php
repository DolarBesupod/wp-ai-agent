<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Integration\Cli;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WpAiAgent\Core\Command\Command;
use WpAiAgent\Core\Command\CommandConfig;
use WpAiAgent\Core\Command\CommandExecutionResult;
use WpAiAgent\Core\Contracts\AgentInterface;
use WpAiAgent\Core\Contracts\CommandExecutorInterface;
use WpAiAgent\Core\Contracts\CommandRegistryInterface;
use WpAiAgent\Core\Contracts\OutputHandlerInterface;
use WpAiAgent\Core\Contracts\SessionInterface;
use WpAiAgent\Core\Contracts\SessionMetadataInterface;
use WpAiAgent\Core\Contracts\SessionRepositoryInterface;
use WpAiAgent\Core\Contracts\ToolInterface;
use WpAiAgent\Core\Contracts\ToolRegistryInterface;
use WpAiAgent\Core\Exceptions\SessionNotFoundException;
use WpAiAgent\Core\ValueObjects\ArgumentList;
use WpAiAgent\Core\ValueObjects\SessionId;
use WpAiAgent\Integration\Cli\CommandHandler;
use WpAiAgent\Integration\Cli\CommandResult;

/**
 * Tests for CommandHandler.
 *
 * @covers \WpAiAgent\Integration\Cli\CommandHandler
 */
final class CommandHandlerTest extends TestCase
{
	private AgentInterface&MockObject $agent;
	private OutputHandlerInterface&MockObject $output_handler;
	private SessionRepositoryInterface&MockObject $session_repository;
	private ToolRegistryInterface&MockObject $tool_registry;
	private CommandHandler $handler;

	protected function setUp(): void
	{
		$this->agent = $this->createMock(AgentInterface::class);
		$this->output_handler = $this->createMock(OutputHandlerInterface::class);
		$this->session_repository = $this->createMock(SessionRepositoryInterface::class);
		$this->tool_registry = $this->createMock(ToolRegistryInterface::class);

		$this->handler = new CommandHandler(
			$this->agent,
			$this->output_handler,
			$this->session_repository,
			$this->tool_registry
		);
	}

	public function test_isCommand_returnsTrueForSlashPrefix(): void
	{
		$this->assertTrue($this->handler->isCommand('/help'));
		$this->assertTrue($this->handler->isCommand('/quit'));
		$this->assertTrue($this->handler->isCommand('/session list'));
	}

	public function test_isCommand_returnsFalseForNonCommands(): void
	{
		$this->assertFalse($this->handler->isCommand('hello'));
		$this->assertFalse($this->handler->isCommand(''));
		$this->assertFalse($this->handler->isCommand('say /something'));
	}

	public function test_handle_nonCommand_returnsNotHandled(): void
	{
		$result = $this->handler->handle('hello world');

		$this->assertFalse($result->wasHandled());
		$this->assertTrue($result->shouldContinue());
	}

	public function test_handle_help_displaysHelpAndContinues(): void
	{
		$this->output_handler->expects($this->once())
			->method('writeLine')
			->with($this->stringContains('/help'));

		$result = $this->handler->handle('/help');

		$this->assertTrue($result->wasHandled());
		$this->assertTrue($result->shouldContinue());
	}

	public function test_handle_questionMark_displaysHelp(): void
	{
		$this->output_handler->expects($this->once())
			->method('writeLine')
			->with($this->stringContains('Available commands'));

		$result = $this->handler->handle('/?');

		$this->assertTrue($result->wasHandled());
		$this->assertTrue($result->shouldContinue());
	}

	public function test_handle_quit_savesSessionAndExits(): void
	{
		$session = $this->createMock(SessionInterface::class);
		$session->method('getId')->willReturn(SessionId::fromString('test-session'));

		$this->agent->method('getCurrentSession')->willReturn($session);

		$this->session_repository->expects($this->once())
			->method('save')
			->with($session);

		$this->output_handler->expects($this->once())
			->method('writeStatus')
			->with($this->stringContains('Session saved: test-session'));

		$result = $this->handler->handle('/quit');

		$this->assertTrue($result->wasHandled());
		$this->assertFalse($result->shouldContinue());
	}

	public function test_handle_exit_savesSessionAndExits(): void
	{
		$this->agent->method('getCurrentSession')->willReturn(null);

		$this->output_handler->expects($this->atLeastOnce())
			->method('writeLine')
			->with('Goodbye!');

		$result = $this->handler->handle('/exit');

		$this->assertTrue($result->wasHandled());
		$this->assertFalse($result->shouldContinue());
	}

	public function test_handle_q_exitsRepl(): void
	{
		$this->agent->method('getCurrentSession')->willReturn(null);

		$result = $this->handler->handle('/q');

		$this->assertFalse($result->shouldContinue());
	}

	public function test_handle_quit_handlesSessionSaveError(): void
	{
		$session = $this->createMock(SessionInterface::class);
		$session->method('getId')->willReturn(SessionId::fromString('test-session'));

		$this->agent->method('getCurrentSession')->willReturn($session);

		$this->session_repository->method('save')
			->willThrowException(new \RuntimeException('Save failed'));

		$this->output_handler->expects($this->once())
			->method('writeError')
			->with($this->stringContains('Failed to save session'));

		$result = $this->handler->handle('/quit');

		$this->assertFalse($result->shouldContinue());
	}

	public function test_handle_clear_clearsMessages(): void
	{
		$session = $this->createMock(SessionInterface::class);
		$session->expects($this->once())->method('clearMessages');

		$this->agent->method('getCurrentSession')->willReturn($session);

		$this->output_handler->expects($this->once())
			->method('writeSuccess')
			->with('Conversation history cleared.');

		$result = $this->handler->handle('/clear');

		$this->assertTrue($result->wasHandled());
		$this->assertTrue($result->shouldContinue());
	}

	public function test_handle_clear_warnsWhenNoSession(): void
	{
		$this->agent->method('getCurrentSession')->willReturn(null);

		$this->output_handler->expects($this->once())
			->method('writeWarning')
			->with('No active session to clear.');

		$result = $this->handler->handle('/clear');

		$this->assertTrue($result->wasHandled());
	}

	public function test_handle_session_showsSessionInfo(): void
	{
		$metadata = $this->createMock(SessionMetadataInterface::class);
		$metadata->method('getCreatedAt')
			->willReturn(new \DateTimeImmutable('2024-01-15 10:00:00'));
		$metadata->method('getUpdatedAt')
			->willReturn(new \DateTimeImmutable('2024-01-15 12:00:00'));
		$metadata->method('getWorkingDirectory')->willReturn('/home/user');
		$metadata->method('getTitle')->willReturn('Test Session');

		$session = $this->createMock(SessionInterface::class);
		$session->method('getId')->willReturn(SessionId::fromString('abc123'));
		$session->method('getMessageCount')->willReturn(5);
		$session->method('getMetadata')->willReturn($metadata);

		$this->agent->method('getCurrentSession')->willReturn($session);

		$output = '';
		$this->output_handler->method('writeLine')
			->willReturnCallback(function (string $line) use (&$output): void {
				$output .= $line;
			});

		$result = $this->handler->handle('/session');

		$this->assertTrue($result->wasHandled());
		$this->assertStringContainsString('abc123', $output);
		$this->assertStringContainsString('5', $output);
		$this->assertStringContainsString('Test Session', $output);
	}

	public function test_handle_session_warnsWhenNoSession(): void
	{
		$this->agent->method('getCurrentSession')->willReturn(null);

		$this->output_handler->expects($this->once())
			->method('writeWarning')
			->with('No active session.');

		$result = $this->handler->handle('/session');

		$this->assertTrue($result->wasHandled());
	}

	public function test_handle_sessionList_showsSessions(): void
	{
		$metadata1 = $this->createMock(SessionMetadataInterface::class);
		$metadata1->method('getTitle')->willReturn('First session');
		$metadata1->method('getCreatedAt')
			->willReturn(new \DateTimeImmutable('2024-01-15 10:00:00'));
		$metadata1->method('getUpdatedAt')
			->willReturn(new \DateTimeImmutable('2024-01-15 12:00:00'));

		$metadata2 = $this->createMock(SessionMetadataInterface::class);
		$metadata2->method('getTitle')->willReturn(null);
		$metadata2->method('getCreatedAt')
			->willReturn(new \DateTimeImmutable('2024-01-14 09:00:00'));
		$metadata2->method('getUpdatedAt')
			->willReturn(new \DateTimeImmutable('2024-01-14 11:00:00'));

		$this->session_repository->method('listWithMetadata')->willReturn([
			['id' => SessionId::fromString('session-1'), 'metadata' => $metadata1],
			['id' => SessionId::fromString('session-2'), 'metadata' => $metadata2],
		]);

		$output = '';
		$this->output_handler->method('writeLine')
			->willReturnCallback(function (string $line) use (&$output): void {
				$output .= $line;
			});

		$result = $this->handler->handle('/session list');

		$this->assertTrue($result->wasHandled());
		$this->assertStringContainsString('Recent Sessions:', $output);
		$this->assertStringContainsString('session', $output);
		$this->assertStringContainsString('First session', $output);
		$this->assertStringContainsString('(untitled)', $output);
		$this->assertStringContainsString('Total: 2 session(s)', $output);
		$this->assertStringContainsString("Use '/session resume <id>' to continue", $output);
	}

	public function test_handle_sessionList_showsMessageWhenEmpty(): void
	{
		$this->session_repository->method('listWithMetadata')->willReturn([]);

		$this->output_handler->expects($this->once())
			->method('writeLine')
			->with($this->stringContains('No saved sessions found'));

		$result = $this->handler->handle('/session list');

		$this->assertTrue($result->wasHandled());
	}

	public function test_handle_sessionResume_resumesSession(): void
	{
		$session = $this->createMock(SessionInterface::class);
		$session->method('getMessageCount')->willReturn(10);

		$this->agent->expects($this->once())
			->method('resumeSession')
			->with($this->callback(function (SessionId $id): bool {
				return $id->toString() === 'abc123';
			}));

		$this->agent->method('getCurrentSession')->willReturn($session);

		$this->output_handler->expects($this->once())
			->method('writeSuccess')
			->with($this->stringContains('Resumed session: abc123'));

		$result = $this->handler->handle('/session resume abc123');

		$this->assertTrue($result->wasHandled());
		$this->assertTrue($result->shouldContinue());
	}

	public function test_handle_sessionResume_showsErrorForMissingId(): void
	{
		$this->output_handler->expects($this->once())
			->method('writeError')
			->with($this->stringContains('Usage:'));

		$result = $this->handler->handle('/session resume');

		$this->assertTrue($result->wasHandled());
	}

	public function test_handle_sessionResume_showsErrorForNotFound(): void
	{
		$session_id = SessionId::fromString('not-found');
		$this->agent->method('resumeSession')
			->willThrowException(new SessionNotFoundException($session_id));

		$this->output_handler->expects($this->once())
			->method('writeError')
			->with($this->stringContains('Session not found'));

		$result = $this->handler->handle('/session resume not-found');

		$this->assertTrue($result->wasHandled());
	}

	public function test_handle_sessionUnknownSubcommand_showsError(): void
	{
		$this->output_handler->expects($this->once())
			->method('writeError')
			->with($this->stringContains('Unknown session subcommand: unknown'));

		$result = $this->handler->handle('/session unknown');

		$this->assertTrue($result->wasHandled());
	}

	public function test_handle_tools_listsTools(): void
	{
		$tool1 = $this->createMock(ToolInterface::class);
		$tool1->method('getDescription')->willReturn('Reads files from disk');

		$tool2 = $this->createMock(ToolInterface::class);
		$tool2->method('getDescription')->willReturn('Executes shell commands');

		$this->tool_registry->method('all')->willReturn([
			'read_file' => $tool1,
			'bash' => $tool2,
		]);

		$output = '';
		$this->output_handler->method('writeLine')
			->willReturnCallback(function (string $line) use (&$output): void {
				$output .= $line;
			});

		$result = $this->handler->handle('/tools');

		$this->assertTrue($result->wasHandled());
		$this->assertStringContainsString('read_file', $output);
		$this->assertStringContainsString('bash', $output);
		$this->assertStringContainsString('Reads files from disk', $output);
		$this->assertStringContainsString('Total: 2 tool(s)', $output);
	}

	public function test_handle_tools_showsMessageWhenEmpty(): void
	{
		$this->tool_registry->method('all')->willReturn([]);

		$this->output_handler->expects($this->once())
			->method('writeLine')
			->with($this->stringContains('No tools registered'));

		$result = $this->handler->handle('/tools');

		$this->assertTrue($result->wasHandled());
	}

	public function test_handle_model_showsCurrentModel(): void
	{
		$this->output_handler->expects($this->once())
			->method('writeLine')
			->with($this->stringContains('Current model: claude-3-sonnet'));

		$result = $this->handler->handle('/model');

		$this->assertTrue($result->wasHandled());
	}

	public function test_handle_model_showsNotImplementedForSwitch(): void
	{
		$this->output_handler->expects($this->once())
			->method('writeWarning')
			->with($this->stringContains('Model switching is not yet implemented'));

		$result = $this->handler->handle('/model gpt-4');

		$this->assertTrue($result->wasHandled());
	}

	public function test_getCurrentModel_returnsCurrentModel(): void
	{
		$this->assertSame('claude-3-sonnet', $this->handler->getCurrentModel());
	}

	public function test_setCurrentModel_updatesModel(): void
	{
		$this->handler->setCurrentModel('gpt-4');
		$this->assertSame('gpt-4', $this->handler->getCurrentModel());
	}

	public function test_handle_unknownCommand_showsError(): void
	{
		$this->output_handler->expects($this->once())
			->method('writeError')
			->with($this->stringContains('Unknown command: /foobar'));

		$result = $this->handler->handle('/foobar');

		$this->assertTrue($result->wasHandled());
		$this->assertTrue($result->shouldContinue());
	}

	public function test_handle_caseInsensitive(): void
	{
		$this->output_handler->expects($this->once())
			->method('writeLine')
			->with($this->stringContains('Available commands'));

		$result = $this->handler->handle('/HELP');

		$this->assertTrue($result->wasHandled());
	}

	public function test_registerCommand_addsCustomHandler(): void
	{
		$handler_called = false;
		$received_args = null;

		$this->handler->registerCommand('custom', function (string $args) use (&$handler_called, &$received_args): CommandResult {
			$handler_called = true;
			$received_args = $args;
			return CommandResult::handled();
		});

		$result = $this->handler->handle('/custom arg1 arg2');

		$this->assertTrue($result->wasHandled());
		$this->assertTrue($handler_called);
		$this->assertSame('arg1 arg2', $received_args);
	}

	public function test_registerCommand_caseInsensitive(): void
	{
		$handler_called = false;

		$this->handler->registerCommand('UPPERCASE', function (string $args) use (&$handler_called): CommandResult {
			$handler_called = true;
			return CommandResult::handled();
		});

		$this->handler->handle('/uppercase');

		$this->assertTrue($handler_called);
	}

	public function test_unregisterCommand_removesHandler(): void
	{
		$this->handler->registerCommand('temporary', fn(string $args): CommandResult => CommandResult::handled());
		$this->handler->unregisterCommand('temporary');

		$this->output_handler->expects($this->once())
			->method('writeError')
			->with($this->stringContains('Unknown command: /temporary'));

		$this->handler->handle('/temporary');
	}

	public function test_customHandler_returningFalseExitsRepl(): void
	{
		$this->handler->registerCommand('bye', fn(string $args): CommandResult => CommandResult::exit());

		$result = $this->handler->handle('/bye');

		$this->assertFalse($result->shouldContinue());
	}

	public function test_customHandler_exceptionIsHandled(): void
	{
		$this->handler->registerCommand('error', function (string $args): CommandResult {
			throw new \RuntimeException('Handler error');
		});

		$this->output_handler->expects($this->once())
			->method('writeError')
			->with($this->stringContains('Handler error'));

		$result = $this->handler->handle('/error');

		$this->assertTrue($result->wasHandled());
		$this->assertTrue($result->shouldContinue());
	}

	public function test_customHandler_supportsBooleanReturnForBackwardCompatibility(): void
	{
		$this->handler->registerCommand('legacy', fn(string $args): bool => true);

		$result = $this->handler->handle('/legacy');

		$this->assertTrue($result->wasHandled());
		$this->assertTrue($result->shouldContinue());
	}

	public function test_customHandler_booleanFalseExits(): void
	{
		$this->handler->registerCommand('legacy-exit', fn(string $args): bool => false);

		$result = $this->handler->handle('/legacy-exit');

		$this->assertFalse($result->shouldContinue());
	}

	public function test_help_includesCustomCommands(): void
	{
		$this->handler->registerCommand('custom1', fn(string $args): CommandResult => CommandResult::handled());
		$this->handler->registerCommand('custom2', fn(string $args): CommandResult => CommandResult::handled());

		$output = '';
		$this->output_handler->method('writeLine')
			->willReturnCallback(function (string $line) use (&$output): void {
				$output .= $line;
			});

		$this->handler->handle('/help');

		$this->assertStringContainsString('custom1', $output);
		$this->assertStringContainsString('custom2', $output);
	}

	public function test_handle_trimsInput(): void
	{
		$this->output_handler->expects($this->once())
			->method('writeLine')
			->with($this->stringContains('Available commands'));

		$result = $this->handler->handle('  /help  ');

		$this->assertTrue($result->wasHandled());
	}

	public function test_sessionList_truncatesLongTitles(): void
	{
		$metadata = $this->createMock(SessionMetadataInterface::class);
		$metadata->method('getTitle')
			->willReturn('This is a very long session title that should be truncated');
		$metadata->method('getCreatedAt')
			->willReturn(new \DateTimeImmutable());
		$metadata->method('getUpdatedAt')
			->willReturn(new \DateTimeImmutable());

		$this->session_repository->method('listWithMetadata')->willReturn([
			['id' => SessionId::fromString('session-1'), 'metadata' => $metadata],
		]);

		$output = '';
		$this->output_handler->method('writeLine')
			->willReturnCallback(function (string $line) use (&$output): void {
				$output .= $line;
			});

		$this->handler->handle('/session list');

		// The title should be truncated with ...
		$this->assertStringContainsString('...', $output);
	}

	public function test_tools_truncatesLongDescriptions(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('getDescription')
			->willReturn('This is a very long tool description that should definitely be truncated to fit within the display width');

		$this->tool_registry->method('all')->willReturn([
			'long_tool' => $tool,
		]);

		$output = '';
		$this->output_handler->method('writeLine')
			->willReturnCallback(function (string $line) use (&$output): void {
				$output .= $line;
			});

		$this->handler->handle('/tools');

		// The description should be truncated with ...
		$this->assertStringContainsString('...', $output);
	}

	// =========================================================================
	// Tests for Command Registry Integration
	// =========================================================================

	public function test_handle_customCommand_fromRegistry_returnsInjectResult(): void
	{
		$command_registry = $this->createMock(CommandRegistryInterface::class);
		$command_executor = $this->createMock(CommandExecutorInterface::class);

		$command = new Command(
			'review',
			'Code review helper',
			'Review this code: $1',
			CommandConfig::fromFrontmatter([]),
			'/path/to/commands/review.md',
			'project'
		);

		$command_registry->method('has')->with('review')->willReturn(true);
		$command_registry->method('get')->with('review')->willReturn($command);

		$execution_result = CommandExecutionResult::success('Review this code: file.php');
		$command_executor->method('execute')
			->with($command, $this->isInstanceOf(ArgumentList::class))
			->willReturn($execution_result);

		$handler = new CommandHandler(
			$this->agent,
			$this->output_handler,
			$this->session_repository,
			$this->tool_registry,
			$command_registry,
			$command_executor
		);

		$result = $handler->handle('/review file.php');

		$this->assertTrue($result->wasHandled());
		$this->assertTrue($result->shouldContinue());
		$this->assertTrue($result->shouldInject());
		$this->assertSame('Review this code: file.php', $result->getInjectedContent());
	}

	public function test_handle_customCommand_fromRegistry_withNoInjection(): void
	{
		$command_registry = $this->createMock(CommandRegistryInterface::class);
		$command_executor = $this->createMock(CommandExecutorInterface::class);

		$command = new Command(
			'info',
			'Show information',
			'Some info content',
			CommandConfig::fromFrontmatter([]),
			'/path/to/commands/info.md'
		);

		$command_registry->method('has')->with('info')->willReturn(true);
		$command_registry->method('get')->with('info')->willReturn($command);

		$execution_result = CommandExecutionResult::success('Some info content', false);
		$command_executor->method('execute')->willReturn($execution_result);

		$handler = new CommandHandler(
			$this->agent,
			$this->output_handler,
			$this->session_repository,
			$this->tool_registry,
			$command_registry,
			$command_executor
		);

		$result = $handler->handle('/info');

		$this->assertTrue($result->wasHandled());
		$this->assertFalse($result->shouldInject());
	}

	public function test_handle_customCommand_fromRegistry_withDirectOutput(): void
	{
		$command_registry = $this->createMock(CommandRegistryInterface::class);
		$command_executor = $this->createMock(CommandExecutorInterface::class);

		$command = new Command(
			'status',
			'Show status',
			'Status content',
			CommandConfig::fromFrontmatter([])
		);

		$command_registry->method('has')->with('status')->willReturn(true);
		$command_registry->method('get')->with('status')->willReturn($command);

		$execution_result = CommandExecutionResult::directOutput('Current status: OK');
		$command_executor->method('execute')->willReturn($execution_result);

		$this->output_handler->expects($this->once())
			->method('writeLine')
			->with('Current status: OK');

		$handler = new CommandHandler(
			$this->agent,
			$this->output_handler,
			$this->session_repository,
			$this->tool_registry,
			$command_registry,
			$command_executor
		);

		$result = $handler->handle('/status');

		$this->assertTrue($result->wasHandled());
		$this->assertFalse($result->shouldInject());
	}

	public function test_handle_customCommand_fromRegistry_withError(): void
	{
		$command_registry = $this->createMock(CommandRegistryInterface::class);
		$command_executor = $this->createMock(CommandExecutorInterface::class);

		$command = new Command(
			'failing',
			'A failing command',
			'Content',
			CommandConfig::fromFrontmatter([])
		);

		$command_registry->method('has')->with('failing')->willReturn(true);
		$command_registry->method('get')->with('failing')->willReturn($command);

		$execution_result = CommandExecutionResult::failure('Something went wrong');
		$command_executor->method('execute')->willReturn($execution_result);

		$this->output_handler->expects($this->once())
			->method('writeError')
			->with($this->stringContains('Something went wrong'));

		$handler = new CommandHandler(
			$this->agent,
			$this->output_handler,
			$this->session_repository,
			$this->tool_registry,
			$command_registry,
			$command_executor
		);

		$result = $handler->handle('/failing');

		$this->assertTrue($result->wasHandled());
		$this->assertFalse($result->shouldInject());
	}

	public function test_handle_builtInCommand_takesPrecedenceOverRegistry(): void
	{
		$command_registry = $this->createMock(CommandRegistryInterface::class);
		$command_executor = $this->createMock(CommandExecutorInterface::class);

		// Registry has a command named 'help' but built-in should take precedence
		$command_registry->expects($this->never())->method('has');
		$command_registry->expects($this->never())->method('get');
		$command_executor->expects($this->never())->method('execute');

		$this->output_handler->expects($this->once())
			->method('writeLine')
			->with($this->stringContains('Available commands'));

		$handler = new CommandHandler(
			$this->agent,
			$this->output_handler,
			$this->session_repository,
			$this->tool_registry,
			$command_registry,
			$command_executor
		);

		$result = $handler->handle('/help');

		$this->assertTrue($result->wasHandled());
	}

	public function test_handle_unknownCommand_whenNotInRegistry(): void
	{
		$command_registry = $this->createMock(CommandRegistryInterface::class);
		$command_executor = $this->createMock(CommandExecutorInterface::class);

		$command_registry->method('has')->with('notfound')->willReturn(false);

		$this->output_handler->expects($this->once())
			->method('writeError')
			->with($this->stringContains('Unknown command: /notfound'));

		$handler = new CommandHandler(
			$this->agent,
			$this->output_handler,
			$this->session_repository,
			$this->tool_registry,
			$command_registry,
			$command_executor
		);

		$result = $handler->handle('/notfound');

		$this->assertTrue($result->wasHandled());
		$this->assertFalse($result->shouldInject());
	}

	public function test_handle_customCommand_passesArgumentsCorrectly(): void
	{
		$command_registry = $this->createMock(CommandRegistryInterface::class);
		$command_executor = $this->createMock(CommandExecutorInterface::class);

		$command = new Command(
			'commit',
			'Create commit',
			'Create a commit with message: $1',
			CommandConfig::fromFrontmatter([])
		);

		$command_registry->method('has')->with('commit')->willReturn(true);
		$command_registry->method('get')->with('commit')->willReturn($command);

		$command_executor->expects($this->once())
			->method('execute')
			->with(
				$command,
				$this->callback(function (ArgumentList $args): bool {
					return $args->getRaw() === 'fix: bug in parser'
						&& $args->get(1) === 'fix:'
						&& $args->get(2) === 'bug'
						&& $args->get(3) === 'in'
						&& $args->get(4) === 'parser';
				})
			)
			->willReturn(CommandExecutionResult::success('Create a commit with message: fix: bug in parser'));

		$handler = new CommandHandler(
			$this->agent,
			$this->output_handler,
			$this->session_repository,
			$this->tool_registry,
			$command_registry,
			$command_executor
		);

		$result = $handler->handle('/commit fix: bug in parser');

		$this->assertTrue($result->shouldInject());
	}

	public function test_help_includesCustomCommandsFromRegistry(): void
	{
		$command_registry = $this->createMock(CommandRegistryInterface::class);
		$command_executor = $this->createMock(CommandExecutorInterface::class);

		$review_command = new Command(
			'review',
			'Code review helper',
			'Review content',
			CommandConfig::fromFrontmatter([]),
			'/path/to/review.md',
			'project'
		);

		$commit_command = new Command(
			'commit',
			'Create git commit',
			'Commit content',
			CommandConfig::fromFrontmatter([]),
			'/home/.config/commands/commit.md',
			'user'
		);

		$command_registry->method('getCustomCommands')->willReturn([
			'review' => $review_command,
			'commit' => $commit_command,
		]);

		$output = '';
		$this->output_handler->method('writeLine')
			->willReturnCallback(function (string $line) use (&$output): void {
				$output .= $line;
			});

		$handler = new CommandHandler(
			$this->agent,
			$this->output_handler,
			$this->session_repository,
			$this->tool_registry,
			$command_registry,
			$command_executor
		);

		$handler->handle('/help');

		$this->assertStringContainsString('Custom commands:', $output);
		$this->assertStringContainsString('/review', $output);
		$this->assertStringContainsString('Code review helper', $output);
		$this->assertStringContainsString('(project)', $output);
		$this->assertStringContainsString('/commit', $output);
		$this->assertStringContainsString('Create git commit', $output);
		$this->assertStringContainsString('(user)', $output);
	}

	public function test_help_noCustomCommandsSectionWhenRegistryEmpty(): void
	{
		$command_registry = $this->createMock(CommandRegistryInterface::class);
		$command_executor = $this->createMock(CommandExecutorInterface::class);

		$command_registry->method('getCustomCommands')->willReturn([]);

		$output = '';
		$this->output_handler->method('writeLine')
			->willReturnCallback(function (string $line) use (&$output): void {
				$output .= $line;
			});

		$handler = new CommandHandler(
			$this->agent,
			$this->output_handler,
			$this->session_repository,
			$this->tool_registry,
			$command_registry,
			$command_executor
		);

		$handler->handle('/help');

		// Should still show built-in commands but not custom commands section
		$this->assertStringContainsString('Available commands:', $output);
		$this->assertStringNotContainsString('Custom commands:', $output);
	}

	public function test_constructor_withoutOptionalDependencies_stillWorks(): void
	{
		// This tests backward compatibility - handler works without registry/executor
		$handler = new CommandHandler(
			$this->agent,
			$this->output_handler,
			$this->session_repository,
			$this->tool_registry
		);

		$result = $handler->handle('/help');

		$this->assertTrue($result->wasHandled());
	}
}
