<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Integration\Cli;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PhpCliAgent\Core\Contracts\AgentInterface;
use PhpCliAgent\Core\Contracts\OutputHandlerInterface;
use PhpCliAgent\Core\Contracts\SessionInterface;
use PhpCliAgent\Core\Contracts\SessionMetadataInterface;
use PhpCliAgent\Core\Contracts\SessionRepositoryInterface;
use PhpCliAgent\Core\Contracts\ToolInterface;
use PhpCliAgent\Core\Contracts\ToolRegistryInterface;
use PhpCliAgent\Core\Exceptions\SessionNotFoundException;
use PhpCliAgent\Core\ValueObjects\SessionId;
use PhpCliAgent\Integration\Cli\CommandHandler;
use PhpCliAgent\Integration\Cli\CommandResult;

/**
 * Tests for CommandHandler.
 *
 * @covers \PhpCliAgent\Integration\Cli\CommandHandler
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
		$this->assertStringContainsString('session-1', $output);
		$this->assertStringContainsString('session-2', $output);
		$this->assertStringContainsString('First session', $output);
		$this->assertStringContainsString('(untitled)', $output);
		$this->assertStringContainsString('Total: 2 session(s)', $output);
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
}
