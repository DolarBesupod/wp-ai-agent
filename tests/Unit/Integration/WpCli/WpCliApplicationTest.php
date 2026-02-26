<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Integration\WpCli;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WpAiAgent\Core\Contracts\AgentInterface;
use WpAiAgent\Core\Contracts\AiAdapterInterface;
use WpAiAgent\Core\Contracts\ConfigurationInterface;
use WpAiAgent\Core\Contracts\SessionRepositoryInterface;
use WpAiAgent\Core\ValueObjects\SessionId;
use WpAiAgent\Integration\WpCli\WpCliApplication;
use WpAiAgent\Integration\WpCli\WpCliConfirmationHandler;
use WpAiAgent\Integration\WpCli\WpCliOutputHandler;

/**
 * Unit tests for WpCliApplication.
 *
 * WP_CLI is a static class only available in a real WP-CLI runtime. The stub
 * defined in tests/Stubs/WpCliStub.php (loaded by tests/bootstrap.php) records
 * every static call so tests can assert output routing without a live runtime.
 *
 * The --yolo flag tests (test_ask_withYoloFlag_setsAutoConfirm and
 * test_chat_withYoloFlag_setsAutoConfirm) run in-process because the flag is
 * checked before the REPL loop, and STDIN in the PHPUnit process is at EOF so
 * the loop exits immediately.
 *
 * The REPL command tests (/yolo on, /yolo off, bare /yolo) spawn a subprocess
 * via proc_open() that pipes the commands through STDIN. This is the only
 * reliable way to test fgets(STDIN) behavior without modifying production code.
 *
 * @covers \WpAiAgent\Integration\WpCli\WpCliApplication
 *
 * @since n.e.x.t
 */
final class WpCliApplicationTest extends TestCase
{
	/**
	 * Resets the WP_CLI stub state before each test to ensure isolation.
	 */
	protected function setUp(): void
	{
		\WP_CLI::$calls = [];
		\WP_CLI::$confirm_throws = false;
	}

	/**
	 * Builds a WpCliApplication with all dependencies mocked.
	 *
	 * @param AgentInterface&MockObject $agent The agent mock to inject.
	 *
	 * @return WpCliApplication
	 */
	private function makeApp(MockObject&AgentInterface $agent): WpCliApplication
	{
		return new WpCliApplication(
			$this->createMock(ConfigurationInterface::class),
			$agent,
			new WpCliOutputHandler(),
			new WpCliConfirmationHandler(),
			$this->createMock(SessionRepositoryInterface::class),
			$this->createMock(AiAdapterInterface::class),
		);
	}

	/**
	 * Tests that ask() calls startSession(), sendMessage(), and endSession()
	 * in the correct order when no --session arg is given.
	 */
	public function test_ask_callsSendMessageThenEndSession(): void
	{
		$agent = $this->createMock(AgentInterface::class);

		$agent->expects($this->once())
			->method('startSession')
			->willReturn(SessionId::fromString('test-session-id'));

		$agent->expects($this->once())
			->method('sendMessage')
			->with('hello world');

		$agent->expects($this->once())
			->method('endSession');

		$app = $this->makeApp($agent);
		$app->ask('hello world', []);
	}

	/**
	 * Tests that ask() calls resumeSession() instead of startSession() when a
	 * --session arg is present.
	 */
	public function test_ask_withSessionArg_callsResumeSessionNotStartSession(): void
	{
		$agent = $this->createMock(AgentInterface::class);

		$agent->expects($this->never())
			->method('startSession');

		$agent->expects($this->once())
			->method('resumeSession')
			->with($this->callback(
				static fn (SessionId $id): bool => $id->toString() === 'abc-123'
			));

		$agent->expects($this->once())
			->method('sendMessage')
			->with('follow up');

		$agent->expects($this->once())
			->method('endSession');

		$app = $this->makeApp($agent);
		$app->ask('follow up', ['session' => 'abc-123']);
	}

	/**
	 * Tests that ask() enables debug mode on the output handler when the
	 * --debug flag is set in assoc_args.
	 */
	public function test_ask_withDebugFlag_enablesDebugOnOutputHandler(): void
	{
		$agent = $this->createMock(AgentInterface::class);
		$agent->method('startSession')->willReturn(SessionId::fromString('sess'));
		$agent->method('sendMessage');
		$agent->method('endSession');

		$output_handler = new WpCliOutputHandler();
		$this->assertFalse($output_handler->isDebugEnabled(), 'Debug should be off by default');

		$app = new WpCliApplication(
			$this->createMock(ConfigurationInterface::class),
			$agent,
			$output_handler,
			new WpCliConfirmationHandler(),
			$this->createMock(SessionRepositoryInterface::class),
			$this->createMock(AiAdapterInterface::class),
		);

		$app->ask('test', ['debug' => true]);

		$this->assertTrue(
			$output_handler->isDebugEnabled(),
			'ask() must enable debug on the output handler when --debug is passed'
		);
	}

	/**
	 * Tests that WpCliApplication can be instantiated and getAgent() returns
	 * the injected agent instance.
	 */
	public function test_getAgent_returnsInjectedAgent(): void
	{
		$agent = $this->createMock(AgentInterface::class);

		$app = $this->makeApp($agent);

		$this->assertSame($agent, $app->getAgent());
	}

	/**
	 * Tests that ask() with no --session arg calls startSession() exactly once.
	 */
	public function test_ask_withNoSession_callsStartSessionOnce(): void
	{
		$agent = $this->createMock(AgentInterface::class);

		$agent->expects($this->once())
			->method('startSession')
			->willReturn(SessionId::fromString('new-session'));

		$agent->method('sendMessage');
		$agent->method('endSession');

		$app = $this->makeApp($agent);
		$app->ask('anything', []);
	}

	/**
	 * Tests that getOutputHandler() returns the injected WpCliOutputHandler.
	 */
	public function test_getOutputHandler_returnsInjectedOutputHandler(): void
	{
		$agent  = $this->createMock(AgentInterface::class);
		$output = new WpCliOutputHandler();

		$app = new WpCliApplication(
			$this->createMock(ConfigurationInterface::class),
			$agent,
			$output,
			new WpCliConfirmationHandler(),
			$this->createMock(SessionRepositoryInterface::class),
			$this->createMock(AiAdapterInterface::class),
		);

		$this->assertSame($output, $app->getOutputHandler());
	}

	// -----------------------------------------------------------------------
	// --yolo flag tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that ask() with the --yolo flag calls setAutoConfirm(true) on the
	 * confirmation handler before sending the message.
	 */
	public function test_ask_withYoloFlag_setsAutoConfirm(): void
	{
		// Arrange
		$agent = $this->createMock(AgentInterface::class);
		$agent->method('startSession')->willReturn(SessionId::fromString('sess'));
		$agent->method('sendMessage');
		$agent->method('endSession');

		$confirmation_handler = new WpCliConfirmationHandler();
		$this->assertFalse($confirmation_handler->isAutoConfirm(), 'auto-confirm must start as false');

		$app = new WpCliApplication(
			$this->createMock(ConfigurationInterface::class),
			$agent,
			new WpCliOutputHandler(),
			$confirmation_handler,
			$this->createMock(SessionRepositoryInterface::class),
			$this->createMock(AiAdapterInterface::class),
		);

		// Act
		$app->ask('list files', ['yolo' => true]);

		// Assert
		$this->assertTrue(
			$confirmation_handler->isAutoConfirm(),
			'ask() must call setAutoConfirm(true) when --yolo flag is passed'
		);
	}

	/**
	 * Tests that chat() with the --yolo flag calls setAutoConfirm(true) on the
	 * confirmation handler before entering the REPL loop.
	 *
	 * STDIN in the PHPUnit process is at EOF (piped, non-interactive), so the
	 * REPL loop exits immediately after the flag is processed.
	 */
	public function test_chat_withYoloFlag_setsAutoConfirm(): void
	{
		// Arrange
		$agent = $this->createMock(AgentInterface::class);
		$agent->method('startSession')->willReturn(SessionId::fromString('sess'));
		$agent->method('endSession');

		$confirmation_handler = new WpCliConfirmationHandler();
		$this->assertFalse($confirmation_handler->isAutoConfirm(), 'auto-confirm must start as false');

		$app = new WpCliApplication(
			$this->createMock(ConfigurationInterface::class),
			$agent,
			new WpCliOutputHandler(),
			$confirmation_handler,
			$this->createMock(SessionRepositoryInterface::class),
			$this->createMock(AiAdapterInterface::class),
		);

		// Act: STDIN is at EOF in the test runner, so the loop will break immediately.
		$app->chat(['yolo' => true]);

		// Assert
		$this->assertTrue(
			$confirmation_handler->isAutoConfirm(),
			'chat() must call setAutoConfirm(true) when --yolo flag is passed'
		);
	}

	// -----------------------------------------------------------------------
	// REPL /yolo command tests (subprocess-based)
	// -----------------------------------------------------------------------

	/**
	 * Tests that the /yolo on REPL command enables auto-confirm and outputs a
	 * success message.
	 *
	 * Spawns a PHP subprocess with the REPL input piped via stdin so that
	 * chat() can read it through the global STDIN constant.
	 */
	public function test_chat_yoloOnCommand_enablesAutoConfirm(): void
	{
		$result = $this->runChatWithStdin("/yolo on\n/quit\n");

		$this->assertTrue(
			$result['auto_confirm'],
			'/yolo on must enable auto-confirm'
		);
		$this->assertStringContainsString(
			'Auto-confirm enabled',
			$result['success_message'],
			'/yolo on must print the enabled success message'
		);
	}

	/**
	 * Tests that the /yolo off REPL command disables auto-confirm and outputs
	 * a success message.
	 *
	 * Starts the session with auto-confirm already on (via --yolo flag), then
	 * sends /yolo off to disable it.
	 */
	public function test_chat_yoloOffCommand_disablesAutoConfirm(): void
	{
		// Start with yolo enabled via the flag, then disable via REPL.
		$result = $this->runChatWithStdin("/yolo off\n/quit\n", ['yolo' => true]);

		$this->assertFalse(
			$result['auto_confirm'],
			'/yolo off must disable auto-confirm'
		);
		$this->assertStringContainsString(
			'Auto-confirm disabled',
			$result['success_message'],
			'/yolo off must print the disabled success message'
		);
	}

	/**
	 * Tests that the bare /yolo REPL command (without a suffix) enables
	 * auto-confirm, treating it as equivalent to /yolo on.
	 */
	public function test_chat_bareYoloCommand_enablesAutoConfirm(): void
	{
		$result = $this->runChatWithStdin("/yolo\n/quit\n");

		$this->assertTrue(
			$result['auto_confirm'],
			'bare /yolo must enable auto-confirm (treated as /yolo on)'
		);
		$this->assertStringContainsString(
			'Auto-confirm enabled',
			$result['success_message'],
			'bare /yolo must print the enabled success message'
		);
	}

	// -----------------------------------------------------------------------
	// REPL /new command tests (subprocess-based)
	// -----------------------------------------------------------------------

	/**
	 * Tests that the /new REPL command clears the session message history and
	 * outputs a success message.
	 */
	public function test_chat_newCommand_clearsSessionMessages(): void
	{
		$result = $this->runChatWithStdin("/new\n/quit\n");

		$this->assertSame(
			0,
			$result['message_count'],
			'/new must clear all messages from the session'
		);
		$this->assertStringContainsString(
			'Context cleared',
			$result['success_message'],
			'/new must print a success message confirming context was cleared'
		);
	}

	/**
	 * Tests that the /new REPL command clears history even after messages have
	 * been sent to the agent.
	 *
	 * MinimalAgentStub::sendMessage() is a no-op, but the agent loop in
	 * WpCliApplication still calls it, and the session's message count is
	 * checked after /new to confirm the clear.
	 */
	public function test_chat_newCommand_afterMessages_clearsHistory(): void
	{
		$result = $this->runChatWithStdin("Hello agent\n/new\n/quit\n");

		$this->assertSame(
			0,
			$result['message_count'],
			'/new after sending messages must result in zero messages'
		);
		$this->assertStringContainsString(
			'Context cleared',
			$result['success_message'],
			'/new must print the context cleared success message'
		);
	}

	// -----------------------------------------------------------------------
	// REPL /model command tests (subprocess-based)
	// -----------------------------------------------------------------------

	/**
	 * Tests that the /model REPL command with no arguments displays the
	 * current model name via WP_CLI::line().
	 */
	public function test_chat_modelCommand_noArgs_displaysCurrentModel(): void
	{
		$result = $this->runChatWithStdin("/model\n/quit\n");

		$line_output = implode("\n", $result['line_messages']);
		$this->assertStringContainsString(
			'Current model:',
			$line_output,
			'/model must display the current model name'
		);
		$this->assertStringContainsString(
			'claude-sonnet-4-20250514',
			$line_output,
			'/model must display the default model from MinimalAiAdapterStub'
		);
	}

	/**
	 * Tests that the /model REPL command with an argument switches the model
	 * and outputs a success message.
	 */
	public function test_chat_modelCommand_withArg_switchesModel(): void
	{
		$result = $this->runChatWithStdin("/model claude-opus-4-20250514\n/quit\n");

		$this->assertSame(
			'claude-opus-4-20250514',
			$result['current_model'],
			'/model <name> must switch the adapter model'
		);
		$this->assertStringContainsString(
			'Model switched to claude-opus-4-20250514',
			$result['success_message'],
			'/model <name> must print a success message'
		);
	}

	/**
	 * Tests that /model after a switch displays the new model name, not the
	 * original default.
	 */
	public function test_chat_modelCommand_afterSwitch_displaysNewModel(): void
	{
		$result = $this->runChatWithStdin("/model claude-opus-4-20250514\n/model\n/quit\n");

		$line_output = implode("\n", $result['line_messages']);
		$this->assertStringContainsString(
			'Current model: claude-opus-4-20250514',
			$line_output,
			'/model after switch must display the new model name'
		);
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Runs chat() in a subprocess with the given stdin input and returns the
	 * observed state as an associative array.
	 *
	 * The subprocess is the dedicated helper script
	 * `tests/Helpers/run_chat_with_stdin.php`. It serializes the result to a
	 * temporary JSON file so the parent process can assert on it without
	 * dealing with process output parsing.
	 *
	 * @param string               $stdin_input The lines to feed into the REPL.
	 * @param array<string, mixed> $assoc_args  Optional assoc_args for chat().
	 *
	 * @return array{
	 *     auto_confirm: bool,
	 *     success_message: string,
	 *     message_count: int,
	 *     current_model: string,
	 *     line_messages: array<int, string>
	 * } The observed state.
	 */
	private function runChatWithStdin(string $stdin_input, array $assoc_args = []): array
	{
		$result_file  = tempnam(sys_get_temp_dir(), 'wp_ai_agent_test_');
		$assoc_json   = (string) json_encode($assoc_args);
		$project_root = dirname(__DIR__, 4);
		$helper       = $project_root . '/tests/Helpers/run_chat_with_stdin.php';

		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];

		$process = proc_open(
			'php ' . escapeshellarg($helper)
				. ' ' . escapeshellarg($result_file)
				. ' ' . escapeshellarg($assoc_json),
			$descriptors,
			$pipes,
			$project_root
		);

		$this->assertIsResource($process, 'proc_open() must return a valid resource');

		fwrite($pipes[0], $stdin_input);
		fclose($pipes[0]);

		stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);

		$json = file_get_contents($result_file);
		unlink($result_file);

		$this->assertIsString($json, 'Subprocess must write a non-empty result JSON file');

		/**
		 * @var array{
		 *     auto_confirm: bool,
		 *     success_message: string,
		 *     message_count: int,
		 *     current_model: string,
		 *     line_messages: array<int, string>
		 * } $data
		 */
		$data = json_decode((string) $json, true);

		return $data;
	}
}
