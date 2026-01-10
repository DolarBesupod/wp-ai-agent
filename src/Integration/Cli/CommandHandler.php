<?php

declare(strict_types=1);

namespace PhpCliAgent\Integration\Cli;

use PhpCliAgent\Core\Contracts\AgentInterface;
use PhpCliAgent\Core\Contracts\OutputHandlerInterface;
use PhpCliAgent\Core\Contracts\SessionRepositoryInterface;
use PhpCliAgent\Core\Contracts\ToolRegistryInterface;
use PhpCliAgent\Core\Exceptions\SessionNotFoundException;
use PhpCliAgent\Core\ValueObjects\SessionId;

/**
 * Handles built-in CLI commands (starting with /).
 *
 * Provides a comprehensive set of commands for session management, tool listing,
 * conversation control, and application configuration. Commands are case-insensitive
 * and support subcommands with arguments.
 *
 * @since n.e.x.t
 */
final class CommandHandler
{
	/**
	 * Command prefix.
	 *
	 * @var string
	 */
	public const COMMAND_PREFIX = '/';

	/**
	 * Agent for session management.
	 *
	 * @var AgentInterface
	 */
	private AgentInterface $agent;

	/**
	 * Output handler for displaying results.
	 *
	 * @var OutputHandlerInterface
	 */
	private OutputHandlerInterface $output_handler;

	/**
	 * Session repository for listing and resuming sessions.
	 *
	 * @var SessionRepositoryInterface
	 */
	private SessionRepositoryInterface $session_repository;

	/**
	 * Tool registry for listing available tools.
	 *
	 * @var ToolRegistryInterface
	 */
	private ToolRegistryInterface $tool_registry;

	/**
	 * The current model name.
	 *
	 * @var string
	 */
	private string $current_model = 'claude-3-sonnet';

	/**
	 * Custom command handlers.
	 *
	 * @var array<string, callable>
	 */
	private array $custom_handlers = [];

	/**
	 * Creates a new CommandHandler instance.
	 *
	 * @param AgentInterface             $agent              Agent for session management.
	 * @param OutputHandlerInterface     $output_handler     Output handler for displaying results.
	 * @param SessionRepositoryInterface $session_repository Session repository for session operations.
	 * @param ToolRegistryInterface      $tool_registry      Tool registry for listing tools.
	 */
	public function __construct(
		AgentInterface $agent,
		OutputHandlerInterface $output_handler,
		SessionRepositoryInterface $session_repository,
		ToolRegistryInterface $tool_registry
	) {
		$this->agent = $agent;
		$this->output_handler = $output_handler;
		$this->session_repository = $session_repository;
		$this->tool_registry = $tool_registry;
	}

	/**
	 * Handles user input that may be a command.
	 *
	 * @param string $input The user input.
	 *
	 * @return CommandResult The result indicating whether the command was handled.
	 */
	public function handle(string $input): CommandResult
	{
		$input = trim($input);

		if (!$this->isCommand($input)) {
			return CommandResult::notHandled();
		}

		return $this->processCommand($input);
	}

	/**
	 * Checks if input is a command (starts with /).
	 *
	 * @param string $input The user input.
	 *
	 * @return bool True if input is a command.
	 */
	public function isCommand(string $input): bool
	{
		return str_starts_with($input, self::COMMAND_PREFIX);
	}

	/**
	 * Registers a custom command handler.
	 *
	 * The handler receives the command arguments and should return a CommandResult.
	 *
	 * @param string   $command The command name (without / prefix).
	 * @param callable $handler Handler: fn(string $args): CommandResult.
	 *
	 * @return void
	 */
	public function registerCommand(string $command, callable $handler): void
	{
		$this->custom_handlers[strtolower($command)] = $handler;
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
		unset($this->custom_handlers[strtolower($command)]);
	}

	/**
	 * Returns the current model name.
	 *
	 * @return string
	 */
	public function getCurrentModel(): string
	{
		return $this->current_model;
	}

	/**
	 * Sets the current model name.
	 *
	 * @param string $model The model name.
	 *
	 * @return void
	 */
	public function setCurrentModel(string $model): void
	{
		$this->current_model = $model;
	}

	/**
	 * Processes a command input.
	 *
	 * @param string $input The command input including the / prefix.
	 *
	 * @return CommandResult The processing result.
	 */
	private function processCommand(string $input): CommandResult
	{
		$command_string = substr($input, strlen(self::COMMAND_PREFIX));
		$parsed = $this->parseCommand($command_string);
		$command = $parsed['command'];
		$arguments = $parsed['arguments'];

		// Handle built-in commands.
		return match ($command) {
			'help', '?' => $this->handleHelp(),
			'quit', 'exit', 'q' => $this->handleQuit(),
			'clear' => $this->handleClear(),
			'session' => $this->handleSession($arguments),
			'tools' => $this->handleTools(),
			'model' => $this->handleModel($arguments),
			default => $this->handleCustomOrUnknown($command, $arguments),
		};
	}

	/**
	 * Parses a command string into command and arguments.
	 *
	 * @param string $command_string The command string without / prefix.
	 *
	 * @return array{command: string, arguments: string}
	 */
	private function parseCommand(string $command_string): array
	{
		$parts = explode(' ', $command_string, 2);
		$command = strtolower(trim($parts[0]));
		$arguments = isset($parts[1]) ? trim($parts[1]) : '';

		return [
			'command' => $command,
			'arguments' => $arguments,
		];
	}

	/**
	 * Handles the /help command.
	 *
	 * @return CommandResult
	 */
	private function handleHelp(): CommandResult
	{
		$help = <<<HELP

Available commands:
  /help, /?              Show this help message
  /quit, /exit, /q       Save session and exit
  /clear                 Clear conversation history
  /session               Show current session info
  /session list          List all saved sessions
  /session resume <id>   Resume a saved session
  /tools                 List available tools
  /model [name]          Show or switch AI model (future)

Type your message and press Enter to chat with the agent.

HELP;

		// Add custom commands if any are registered.
		if (count($this->custom_handlers) > 0) {
			$custom_help = "\nCustom commands:\n";
			foreach (array_keys($this->custom_handlers) as $cmd) {
				$custom_help .= sprintf("  /%s\n", $cmd);
			}
			$help .= $custom_help;
		}

		$this->output_handler->writeLine($help);

		return CommandResult::handled();
	}

	/**
	 * Handles the /quit, /exit, /q commands.
	 *
	 * @return CommandResult
	 */
	private function handleQuit(): CommandResult
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
			}
		}

		$this->output_handler->writeLine('Goodbye!');

		return CommandResult::exit();
	}

	/**
	 * Handles the /clear command.
	 *
	 * @return CommandResult
	 */
	private function handleClear(): CommandResult
	{
		$session = $this->agent->getCurrentSession();

		if ($session === null) {
			$this->output_handler->writeWarning('No active session to clear.');
			return CommandResult::handled();
		}

		$session->clearMessages();
		$this->output_handler->writeSuccess('Conversation history cleared.');

		return CommandResult::handled();
	}

	/**
	 * Handles the /session command and subcommands.
	 *
	 * @param string $arguments The subcommand and arguments.
	 *
	 * @return CommandResult
	 */
	private function handleSession(string $arguments): CommandResult
	{
		$parsed = $this->parseCommand($arguments);
		$subcommand = $parsed['command'];
		$sub_arguments = $parsed['arguments'];

		if ($subcommand === '') {
			return $this->handleSessionInfo();
		}

		return match ($subcommand) {
			'list' => $this->handleSessionList(),
			'resume' => $this->handleSessionResume($sub_arguments),
			default => $this->handleSessionUnknown($subcommand),
		};
	}

	/**
	 * Shows current session information.
	 *
	 * @return CommandResult
	 */
	private function handleSessionInfo(): CommandResult
	{
		$session = $this->agent->getCurrentSession();

		if ($session === null) {
			$this->output_handler->writeWarning('No active session.');
			return CommandResult::handled();
		}

		$metadata = $session->getMetadata();
		$info = sprintf(
			"\nSession Information:\n" .
			"  ID:         %s\n" .
			"  Created:    %s\n" .
			"  Updated:    %s\n" .
			"  Messages:   %d\n" .
			"  Directory:  %s\n",
			$session->getId()->toString(),
			$metadata->getCreatedAt()->format('Y-m-d H:i:s'),
			$metadata->getUpdatedAt()->format('Y-m-d H:i:s'),
			$session->getMessageCount(),
			$metadata->getWorkingDirectory()
		);

		$title = $metadata->getTitle();
		if ($title !== null) {
			$info .= sprintf("  Title:      %s\n", $title);
		}

		$this->output_handler->writeLine($info);

		return CommandResult::handled();
	}

	/**
	 * Lists all saved sessions.
	 *
	 * @return CommandResult
	 */
	private function handleSessionList(): CommandResult
	{
		$sessions = $this->session_repository->listWithMetadata();

		if (count($sessions) === 0) {
			$this->output_handler->writeLine("\nNo saved sessions found.\n");
			return CommandResult::handled();
		}

		$output = "\nSaved sessions:\n";
		$output .= str_repeat('-', 60) . "\n";

		foreach ($sessions as $session_data) {
			$id = $session_data['id']->toString();
			$metadata = $session_data['metadata'];

			$title = $metadata->getTitle() ?? '(untitled)';
			$created = $metadata->getCreatedAt()->format('Y-m-d H:i');
			$updated = $metadata->getUpdatedAt()->format('Y-m-d H:i');

			// Truncate title if too long.
			if (strlen($title) > 30) {
				$title = substr($title, 0, 27) . '...';
			}

			$output .= sprintf(
				"  %-12s  %-30s  %s\n",
				$id,
				$title,
				$updated
			);
		}

		$output .= str_repeat('-', 60) . "\n";
		$output .= sprintf("Total: %d session(s)\n", count($sessions));
		$output .= "\nUse '/session resume <id>' to resume a session.\n";

		$this->output_handler->writeLine($output);

		return CommandResult::handled();
	}

	/**
	 * Resumes a saved session.
	 *
	 * @param string $session_id_string The session ID to resume.
	 *
	 * @return CommandResult
	 */
	private function handleSessionResume(string $session_id_string): CommandResult
	{
		if ($session_id_string === '') {
			$this->output_handler->writeError('Usage: /session resume <session-id>');
			return CommandResult::handled();
		}

		try {
			$session_id = SessionId::fromString($session_id_string);
			$this->agent->resumeSession($session_id);

			$session = $this->agent->getCurrentSession();
			$message_count = $session !== null ? $session->getMessageCount() : 0;

			$this->output_handler->writeSuccess(
				sprintf('Resumed session: %s (%d messages)', $session_id_string, $message_count)
			);
		} catch (SessionNotFoundException $exception) {
			$this->output_handler->writeError(
				sprintf('Session not found: %s', $session_id_string)
			);
		} catch (\InvalidArgumentException $exception) {
			$this->output_handler->writeError(
				sprintf('Invalid session ID format: %s', $session_id_string)
			);
		} catch (\Throwable $exception) {
			$this->output_handler->writeError(
				sprintf('Failed to resume session: %s', $exception->getMessage())
			);
		}

		return CommandResult::handled();
	}

	/**
	 * Handles unknown session subcommand.
	 *
	 * @param string $subcommand The unknown subcommand.
	 *
	 * @return CommandResult
	 */
	private function handleSessionUnknown(string $subcommand): CommandResult
	{
		$this->output_handler->writeError(
			sprintf(
				"Unknown session subcommand: %s\n" .
				"Available subcommands: list, resume <id>",
				$subcommand
			)
		);

		return CommandResult::handled();
	}

	/**
	 * Handles the /tools command.
	 *
	 * @return CommandResult
	 */
	private function handleTools(): CommandResult
	{
		$tools = $this->tool_registry->all();

		if (count($tools) === 0) {
			$this->output_handler->writeLine("\nNo tools registered.\n");
			return CommandResult::handled();
		}

		$output = "\nAvailable tools:\n";
		$output .= str_repeat('-', 60) . "\n";

		foreach ($tools as $name => $tool) {
			$description = $tool->getDescription();

			// Truncate description if too long.
			if (strlen($description) > 45) {
				$description = substr($description, 0, 42) . '...';
			}

			$output .= sprintf("  %-15s  %s\n", $name, $description);
		}

		$output .= str_repeat('-', 60) . "\n";
		$output .= sprintf("Total: %d tool(s)\n", count($tools));

		$this->output_handler->writeLine($output);

		return CommandResult::handled();
	}

	/**
	 * Handles the /model command.
	 *
	 * @param string $model_name The model name to switch to (or empty to show current).
	 *
	 * @return CommandResult
	 */
	private function handleModel(string $model_name): CommandResult
	{
		if ($model_name === '') {
			$this->output_handler->writeLine(
				sprintf("\nCurrent model: %s\n", $this->current_model)
			);
			return CommandResult::handled();
		}

		// Model switching is a future feature.
		$this->output_handler->writeWarning(
			sprintf(
				"Model switching is not yet implemented.\n" .
				"Current model: %s\n" .
				"Requested: %s",
				$this->current_model,
				$model_name
			)
		);

		return CommandResult::handled();
	}

	/**
	 * Handles custom command or returns unknown command result.
	 *
	 * @param string $command   The command name.
	 * @param string $arguments The command arguments.
	 *
	 * @return CommandResult
	 */
	private function handleCustomOrUnknown(string $command, string $arguments): CommandResult
	{
		if (isset($this->custom_handlers[$command])) {
			try {
				$result = call_user_func($this->custom_handlers[$command], $arguments);

				if ($result instanceof CommandResult) {
					return $result;
				}

				// Support legacy boolean return for backward compatibility.
				if (is_bool($result)) {
					return $result ? CommandResult::handled() : CommandResult::exit();
				}

				return CommandResult::handled();
			} catch (\Throwable $exception) {
				$this->output_handler->writeError(
					sprintf('Command error: %s', $exception->getMessage())
				);
				return CommandResult::handled();
			}
		}

		$this->output_handler->writeError(
			sprintf('Unknown command: /%s. Type /help for available commands.', $command)
		);

		return CommandResult::handled();
	}
}
