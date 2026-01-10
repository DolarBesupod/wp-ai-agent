<?php

declare(strict_types=1);

namespace PhpCliAgent\Integration\Cli;

use PhpCliAgent\Core\Contracts\AgentInterface;
use PhpCliAgent\Core\Contracts\OutputHandlerInterface;
use PhpCliAgent\Core\Contracts\SessionRepositoryInterface;

/**
 * Interactive Read-Eval-Print-Loop runner for the CLI agent.
 *
 * Handles the main interactive loop, reading user input, dispatching commands,
 * passing messages to the agent, and handling graceful termination via
 * signals or explicit quit commands.
 *
 * @since n.e.x.t
 */
final class ReplRunner
{
	/**
	 * Default user input prompt.
	 *
	 * @var string
	 */
	public const DEFAULT_PROMPT = 'You: ';

	/**
	 * Command prefix for REPL commands.
	 *
	 * @var string
	 */
	public const COMMAND_PREFIX = '/';

	/**
	 * Built-in quit commands.
	 *
	 * @var array<int, string>
	 */
	private const QUIT_COMMANDS = ['quit', 'exit', 'q'];

	/**
	 * Built-in help commands.
	 *
	 * @var array<int, string>
	 */
	private const HELP_COMMANDS = ['help', '?'];

	/**
	 * The agent instance.
	 *
	 * @var AgentInterface
	 */
	private AgentInterface $agent;

	/**
	 * The output handler.
	 *
	 * @var OutputHandlerInterface
	 */
	private OutputHandlerInterface $output_handler;

	/**
	 * The session repository for saving sessions.
	 *
	 * @var SessionRepositoryInterface
	 */
	private SessionRepositoryInterface $session_repository;

	/**
	 * The input prompt to display.
	 *
	 * @var string
	 */
	private string $prompt = self::DEFAULT_PROMPT;

	/**
	 * Whether the REPL is currently running.
	 *
	 * @var bool
	 */
	private bool $running = false;

	/**
	 * Whether debug mode is enabled.
	 *
	 * @var bool
	 */
	private bool $debug_enabled = false;

	/**
	 * Registered command handlers.
	 *
	 * @var array<string, callable>
	 */
	private array $command_handlers = [];

	/**
	 * Creates a new ReplRunner instance.
	 *
	 * @param AgentInterface             $agent              The agent to process messages.
	 * @param OutputHandlerInterface     $output_handler     The output handler.
	 * @param SessionRepositoryInterface $session_repository The session repository.
	 */
	public function __construct(
		AgentInterface $agent,
		OutputHandlerInterface $output_handler,
		SessionRepositoryInterface $session_repository
	) {
		$this->agent = $agent;
		$this->output_handler = $output_handler;
		$this->session_repository = $session_repository;
	}

	/**
	 * Runs the interactive REPL loop.
	 *
	 * @return int The exit code (0 for success).
	 */
	public function run(): int
	{
		$this->running = true;
		$this->setupSignalHandlers();
		$this->displayWelcome();

		while ($this->running) {
			$input = $this->readInput();

			if ($input === false) {
				// EOF reached (e.g., piped input ended).
				$this->handleShutdown();
				break;
			}

			$input = trim($input);

			if ($input === '') {
				continue;
			}

			if ($this->isCommand($input)) {
				$should_continue = $this->handleCommand($input);
				if (!$should_continue) {
					break;
				}
				continue;
			}

			$this->processUserMessage($input);
		}

		return 0;
	}

	/**
	 * Stops the REPL loop gracefully.
	 *
	 * @return void
	 */
	public function stop(): void
	{
		$this->running = false;
	}

	/**
	 * Checks if the REPL is currently running.
	 *
	 * @return bool
	 */
	public function isRunning(): bool
	{
		return $this->running;
	}

	/**
	 * Sets the input prompt.
	 *
	 * @param string $prompt The prompt string.
	 *
	 * @return void
	 */
	public function setPrompt(string $prompt): void
	{
		$this->prompt = $prompt;
	}

	/**
	 * Returns the current prompt.
	 *
	 * @return string
	 */
	public function getPrompt(): string
	{
		return $this->prompt;
	}

	/**
	 * Enables or disables debug mode.
	 *
	 * @param bool $enabled Whether debug mode should be enabled.
	 *
	 * @return void
	 */
	public function setDebugEnabled(bool $enabled): void
	{
		$this->debug_enabled = $enabled;
	}

	/**
	 * Checks if debug mode is enabled.
	 *
	 * @return bool
	 */
	public function isDebugEnabled(): bool
	{
		return $this->debug_enabled;
	}

	/**
	 * Registers a custom command handler.
	 *
	 * The handler receives the command arguments and should return a boolean
	 * indicating whether the REPL should continue (true) or exit (false).
	 *
	 * @param string   $command The command name (without the / prefix).
	 * @param callable $handler The handler function: fn(string $args): bool.
	 *
	 * @return void
	 */
	public function registerCommand(string $command, callable $handler): void
	{
		$this->command_handlers[strtolower($command)] = $handler;
	}

	/**
	 * Unregisters a custom command handler.
	 *
	 * @param string $command The command name to unregister.
	 *
	 * @return void
	 */
	public function unregisterCommand(string $command): void
	{
		unset($this->command_handlers[strtolower($command)]);
	}

	/**
	 * Returns all registered command names.
	 *
	 * @return array<int, string>
	 */
	public function getRegisteredCommands(): array
	{
		return array_keys($this->command_handlers);
	}

	/**
	 * Sets up signal handlers for graceful shutdown.
	 *
	 * @return void
	 */
	private function setupSignalHandlers(): void
	{
		if (!function_exists('pcntl_signal')) {
			$this->output_handler->writeDebug('Signal handling not available (pcntl extension not loaded)');
			return;
		}

		// @codeCoverageIgnoreStart
		pcntl_async_signals(true);

		pcntl_signal(SIGINT, function (int $signal): void {
			$this->handleSignal($signal);
		});

		pcntl_signal(SIGTERM, function (int $signal): void {
			$this->handleSignal($signal);
		});
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Handles incoming signals.
	 *
	 * @param int $signal The signal number.
	 *
	 * @return void
	 */
	private function handleSignal(int $signal): void
	{
		$this->output_handler->writeLine('');

		if ($signal === SIGINT) {
			$this->output_handler->writeStatus('Received interrupt signal (Ctrl+C)');
		} elseif ($signal === SIGTERM) {
			$this->output_handler->writeStatus('Received termination signal');
		}

		$this->handleShutdown();
		$this->running = false;
	}

	/**
	 * Handles graceful shutdown.
	 *
	 * Saves the current session before exiting.
	 *
	 * @return void
	 */
	private function handleShutdown(): void
	{
		$session = $this->agent->getCurrentSession();

		if ($session !== null) {
			try {
				$this->session_repository->save($session);
				$this->output_handler->writeStatus(
					sprintf('Session saved: %s', $session->getId()->toString())
				);
			} catch (\Throwable $exception) {
				$this->output_handler->writeError(
					'Failed to save session: ' . $exception->getMessage()
				);
				if ($this->debug_enabled) {
					$this->output_handler->writeDebug($exception->getTraceAsString());
				}
			}
		}

		$this->output_handler->writeLine('Goodbye!');
	}

	/**
	 * Displays the welcome message.
	 *
	 * @return void
	 */
	private function displayWelcome(): void
	{
		$this->output_handler->writeLine('');
		$this->output_handler->writeLine('Welcome to the PHP CLI Agent!');
		$this->output_handler->writeLine('Type your message and press Enter to chat.');
		$this->output_handler->writeLine('Type /help for available commands, or /quit to exit.');
		$this->output_handler->writeLine('');
	}

	/**
	 * Reads input from the user.
	 *
	 * Uses readline if available for better line editing support,
	 * otherwise falls back to fgets.
	 *
	 * @return string|false The input line or false on EOF.
	 */
	private function readInput(): string|false
	{
		$this->output_handler->write($this->prompt);

		if (function_exists('readline')) {
			// Clear any existing prompt since we wrote it manually.
			$line = readline('');

			if ($line !== false && $line !== '') {
				readline_add_history($line);
			}

			return $line;
		}

		$result = fgets(STDIN);

		return $result !== false ? $result : false;
	}

	/**
	 * Checks if the input is a command.
	 *
	 * @param string $input The user input.
	 *
	 * @return bool True if the input starts with the command prefix.
	 */
	private function isCommand(string $input): bool
	{
		return str_starts_with($input, self::COMMAND_PREFIX);
	}

	/**
	 * Handles a command input.
	 *
	 * @param string $input The command input (including the / prefix).
	 *
	 * @return bool True to continue the REPL, false to exit.
	 */
	private function handleCommand(string $input): bool
	{
		// Remove the command prefix.
		$command_string = substr($input, strlen(self::COMMAND_PREFIX));

		// Split into command and arguments.
		$parts = explode(' ', $command_string, 2);
		$command = strtolower(trim($parts[0]));
		$arguments = isset($parts[1]) ? trim($parts[1]) : '';

		// Handle built-in quit commands.
		if (in_array($command, self::QUIT_COMMANDS, true)) {
			$this->handleShutdown();
			return false;
		}

		// Handle built-in help commands.
		if (in_array($command, self::HELP_COMMANDS, true)) {
			$this->displayHelp();
			return true;
		}

		// Check for registered custom command handlers.
		if (isset($this->command_handlers[$command])) {
			try {
				return (bool) call_user_func($this->command_handlers[$command], $arguments);
			} catch (\Throwable $exception) {
				$this->output_handler->writeError(
					sprintf('Command error: %s', $exception->getMessage())
				);
				if ($this->debug_enabled) {
					$this->output_handler->writeDebug($exception->getTraceAsString());
				}
				return true;
			}
		}

		// Unknown command.
		$this->output_handler->writeError(
			sprintf('Unknown command: /%s. Type /help for available commands.', $command)
		);

		return true;
	}

	/**
	 * Displays the help message with available commands.
	 *
	 * @return void
	 */
	private function displayHelp(): void
	{
		$help = <<<HELP

Available commands:
  /help, /?     Show this help message
  /quit, /q     Save session and exit
  /exit         Save session and exit

HELP;

		// Add registered custom commands to help.
		$custom_commands = $this->getRegisteredCommands();
		if (count($custom_commands) > 0) {
			$help .= "\nCustom commands:\n";
			foreach ($custom_commands as $command) {
				$help .= sprintf("  /%s\n", $command);
			}
		}

		$help .= "\nType your message and press Enter to chat with the agent.\n";

		$this->output_handler->writeLine($help);
	}

	/**
	 * Processes a user message through the agent.
	 *
	 * @param string $message The user's message.
	 *
	 * @return void
	 */
	private function processUserMessage(string $message): void
	{
		try {
			$this->agent->sendMessage($message);
			$this->output_handler->writeLine('');
		} catch (\Throwable $exception) {
			$this->output_handler->writeError('Error: ' . $exception->getMessage());
			if ($this->debug_enabled) {
				$this->output_handler->writeDebug($exception->getTraceAsString());
			}
			$this->output_handler->writeLine('');
		}
	}
}
