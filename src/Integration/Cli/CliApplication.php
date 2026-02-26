<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\Cli;

use WpAiAgent\Core\Contracts\AgentInterface;
use WpAiAgent\Core\Contracts\CommandExecutorInterface;
use WpAiAgent\Core\Contracts\CommandRegistryInterface;
use WpAiAgent\Core\Contracts\ConfigurationInterface;
use WpAiAgent\Core\Contracts\OutputHandlerInterface;
use WpAiAgent\Core\ValueObjects\ArgumentList;
use WpAiAgent\Core\ValueObjects\SessionId;
use WpAiAgent\Integration\Cli\Command\InitCommand;
use WpAiAgent\Integration\Mcp\McpClientManager;

/**
 * CLI application entry point.
 *
 * Handles command line argument parsing, displays help and version information,
 * loads configuration, and initializes the REPL runner for interactive sessions.
 *
 * @since n.e.x.t
 */
final class CliApplication
{
	/**
	 * The current application version.
	 *
	 * @var string
	 */
	public const VERSION = '0.1.0';

	/**
	 * The application name.
	 *
	 * @var string
	 */
	public const NAME = 'PHP CLI Agent';

	/**
	 * Exit code for success.
	 *
	 * @var int
	 */
	public const EXIT_SUCCESS = 0;

	/**
	 * Exit code for general errors.
	 *
	 * @var int
	 */
	public const EXIT_ERROR = 1;

	/**
	 * Exit code for invalid arguments.
	 *
	 * @var int
	 */
	public const EXIT_INVALID_ARGS = 2;

	private ConfigurationInterface $configuration;
	private AgentInterface $agent;
	private OutputHandlerInterface $output_handler;

	/**
	 * MCP client manager to keep connections alive.
	 *
	 * This property holds a reference to prevent garbage collection
	 * of the manager and its connected clients during the application lifecycle.
	 *
	 * @var McpClientManager|null
	 */
	private ?McpClientManager $mcp_client_manager = null;

	/**
	 * Command registry for custom slash commands.
	 *
	 * @var CommandRegistryInterface|null
	 */
	private ?CommandRegistryInterface $command_registry = null;

	/**
	 * Command executor for processing custom commands.
	 *
	 * @var CommandExecutorInterface|null
	 */
	private ?CommandExecutorInterface $command_executor = null;

	/**
	 * Parsed command line arguments.
	 *
	 * @var array{
	 *     config: string|null,
	 *     session: string|null,
	 *     subcommand: string|null,
	 *     no_save: bool,
	 *     help: bool,
	 *     version: bool,
	 *     debug: bool,
	 *     force: bool
	 * }
	 */
	private array $parsed_args = [
		'config' => null,
		'session' => null,
		'subcommand' => null,
		'no_save' => false,
		'help' => false,
		'version' => false,
		'debug' => false,
		'force' => false,
	];

	/**
	 * Creates a new CliApplication instance.
	 *
	 * @param ConfigurationInterface        $configuration       The application configuration.
	 * @param AgentInterface                $agent               The agent instance.
	 * @param OutputHandlerInterface        $output_handler      The output handler.
	 * @param McpClientManager|null         $mcp_client_manager  Optional MCP client manager to keep alive.
	 * @param CommandRegistryInterface|null $command_registry    Optional command registry for custom commands.
	 * @param CommandExecutorInterface|null $command_executor    Optional command executor for custom commands.
	 */
	public function __construct(
		ConfigurationInterface $configuration,
		AgentInterface $agent,
		OutputHandlerInterface $output_handler,
		?McpClientManager $mcp_client_manager = null,
		?CommandRegistryInterface $command_registry = null,
		?CommandExecutorInterface $command_executor = null
	) {
		$this->configuration = $configuration;
		$this->agent = $agent;
		$this->output_handler = $output_handler;
		$this->mcp_client_manager = $mcp_client_manager;
		$this->command_registry = $command_registry;
		$this->command_executor = $command_executor;
	}

	/**
	 * Runs the application with the given command line arguments.
	 *
	 * @param array<int, string> $argv The command line arguments.
	 *
	 * @return int The exit code.
	 */
	public function run(array $argv): int
	{
		try {
			$this->parseArguments($argv);

			if ($this->parsed_args['help']) {
				$this->showHelp();
				return self::EXIT_SUCCESS;
			}

			if ($this->parsed_args['version']) {
				$this->showVersion();
				return self::EXIT_SUCCESS;
			}

			// Handle init subcommand before loading agent configuration.
			if ($this->parsed_args['subcommand'] === 'init') {
				return $this->runInitCommand();
			}

			// Load custom config if specified.
			if ($this->parsed_args['config'] !== null) {
				$this->configuration->loadFromFile($this->parsed_args['config']);
			}

			// Enable debug mode if flag is set.
			if ($this->parsed_args['debug']) {
				$this->output_handler->setDebugEnabled(true);
			}

			// Configure auto-save based on --no-save flag.
			if ($this->parsed_args['no_save'] && method_exists($this->agent, 'setAutoSave')) {
				$this->agent->setAutoSave(false);
			}

			// Start or resume session.
			if ($this->parsed_args['session'] !== null) {
				$session_id = SessionId::fromString($this->parsed_args['session']);
				$this->agent->resumeSession($session_id);
				$this->output_handler->writeStatus(
					sprintf('Resumed session: %s', $session_id->toString())
				);
			} else {
				$session_id = $this->agent->startSession();
				$this->output_handler->writeStatus(
					sprintf('Started new session: %s', $session_id->toString())
				);
			}

			// Start interactive REPL.
			return $this->runRepl();
		} catch (\WpAiAgent\Core\Exceptions\ConfigurationException $exception) {
			$this->output_handler->writeError('Configuration error: ' . $exception->getMessage());
			return self::EXIT_ERROR;
		} catch (\WpAiAgent\Core\Exceptions\SessionNotFoundException $exception) {
			$this->output_handler->writeError('Session not found: ' . $exception->getMessage());
			return self::EXIT_ERROR;
		} catch (\InvalidArgumentException $exception) {
			$this->output_handler->writeError('Invalid argument: ' . $exception->getMessage());
			return self::EXIT_INVALID_ARGS;
		} catch (\Throwable $exception) {
			$this->output_handler->writeError('Fatal error: ' . $exception->getMessage());
			if ($this->parsed_args['debug']) {
				$this->output_handler->writeDebug($exception->getTraceAsString());
			}
			return self::EXIT_ERROR;
		}
	}

	/**
	 * Parses command line arguments.
	 *
	 * @param array<int, string> $argv The command line arguments.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If an unknown option is provided.
	 */
	public function parseArguments(array $argv): void
	{
		// Skip the script name (first element).
		$args = array_slice($argv, 1);

		foreach ($args as $arg) {
			if ($arg === '--help' || $arg === '-h') {
				$this->parsed_args['help'] = true;
				continue;
			}

			if ($arg === '--version' || $arg === '-v') {
				$this->parsed_args['version'] = true;
				continue;
			}

			if ($arg === '--no-save') {
				$this->parsed_args['no_save'] = true;
				continue;
			}

			if ($arg === '--debug' || $arg === '-d') {
				$this->parsed_args['debug'] = true;
				continue;
			}

			if ($arg === '--force' || $arg === '-f') {
				$this->parsed_args['force'] = true;
				continue;
			}

			if (str_starts_with($arg, '--config=')) {
				$this->parsed_args['config'] = substr($arg, 9);
				continue;
			}

			if (str_starts_with($arg, '--session=')) {
				$this->parsed_args['session'] = substr($arg, 10);
				continue;
			}

			// Handle short options with values.
			if (str_starts_with($arg, '-c')) {
				$value = substr($arg, 2);
				if ($value === '') {
					throw new \InvalidArgumentException('Option -c requires a value');
				}
				$this->parsed_args['config'] = $value;
				continue;
			}

			if (str_starts_with($arg, '-s')) {
				$value = substr($arg, 2);
				if ($value === '') {
					throw new \InvalidArgumentException('Option -s requires a value');
				}
				$this->parsed_args['session'] = $value;
				continue;
			}

			// Unknown option.
			if (str_starts_with($arg, '-')) {
				throw new \InvalidArgumentException(sprintf('Unknown option: %s', $arg));
			}

			// Handle subcommands (non-option arguments).
			if ($this->parsed_args['subcommand'] === null) {
				$this->parsed_args['subcommand'] = $arg;
			}
		}
	}

	/**
	 * Displays help information.
	 *
	 * @return void
	 */
	public function showHelp(): void
	{
		$help = <<<HELP
{$this->getFullVersion()}

Usage: agent [command] [options]

Commands:
  init                     Initialize the .wp-ai-agent configuration folder

Options:
  --config=PATH, -cPATH    Load configuration from the specified file
  --session=ID, -sID       Resume an existing session by ID
  --no-save                Don't persist session to disk
  --force, -f              Force overwrite (for init command)
  --debug, -d              Enable debug output
  --help, -h               Show this help message
  --version, -v            Show version information

Examples:
  agent                    Start a new interactive session
  agent init               Initialize configuration folder
  agent init --force       Initialize and overwrite existing files
  agent --session=abc123   Resume session 'abc123'
  agent --config=config.json   Use custom configuration file
  agent --no-save          Start session without saving to disk

HELP;

		$this->output_handler->writeLine($help);
	}

	/**
	 * Displays version information.
	 *
	 * @return void
	 */
	public function showVersion(): void
	{
		$this->output_handler->writeLine($this->getFullVersion());
	}

	/**
	 * Returns the full version string.
	 *
	 * @return string
	 */
	public function getFullVersion(): string
	{
		return sprintf('%s v%s', self::NAME, self::VERSION);
	}

	/**
	 * Returns the parsed command line arguments.
	 *
	 * @return array{
	 *     config: string|null,
	 *     session: string|null,
	 *     subcommand: string|null,
	 *     no_save: bool,
	 *     help: bool,
	 *     version: bool,
	 *     debug: bool,
	 *     force: bool
	 * }
	 */
	public function getParsedArgs(): array
	{
		return $this->parsed_args;
	}

	/**
	 * Returns the agent instance.
	 *
	 * @return AgentInterface
	 */
	public function getAgent(): AgentInterface
	{
		return $this->agent;
	}

	/**
	 * Returns the configuration instance.
	 *
	 * @return ConfigurationInterface
	 */
	public function getConfiguration(): ConfigurationInterface
	{
		return $this->configuration;
	}

	/**
	 * Returns the output handler instance.
	 *
	 * @return OutputHandlerInterface
	 */
	public function getOutputHandler(): OutputHandlerInterface
	{
		return $this->output_handler;
	}

	/**
	 * Returns the MCP client manager instance.
	 *
	 * @return McpClientManager|null The MCP client manager, or null if not configured.
	 */
	public function getMcpClientManager(): ?McpClientManager
	{
		return $this->mcp_client_manager;
	}

	/**
	 * Runs the interactive REPL loop.
	 *
	 * This is a placeholder that will be replaced by ReplRunner integration.
	 * For now, it reads input from STDIN and processes it through the agent.
	 *
	 * @return int The exit code.
	 */
	private function runRepl(): int
	{
		$this->output_handler->writeLine('');
		$this->output_handler->writeLine('Type your message and press Enter. Type /quit to exit.');
		$this->output_handler->writeLine('');

		while (true) {
			$this->output_handler->write('> ');

			$input = $this->readLine();

			if ($input === false) {
				// EOF reached.
				break;
			}

			$input = trim($input);

			if ($input === '') {
				continue;
			}

			// Handle quit command.
			if ($input === '/quit' || $input === '/exit' || $input === '/q') {
				$this->output_handler->writeLine('Goodbye!');
				break;
			}

			// Handle help command.
			if ($input === '/help' || $input === '/?') {
				$this->showReplHelp();
				continue;
			}

			// Handle custom slash commands.
			if (str_starts_with($input, '/')) {
				$result = $this->handleCustomCommand($input);
				if ($result !== null) {
					// Custom command was handled - either show output or inject into conversation.
					if ($result !== '') {
						try {
							$this->agent->sendMessage($result);
						} catch (\Throwable $exception) {
							$this->output_handler->writeError('Error: ' . $exception->getMessage());
							if ($this->parsed_args['debug']) {
								$this->output_handler->writeDebug($exception->getTraceAsString());
							}
						}
					}
					$this->output_handler->writeLine('');
					continue;
				}
				// Unknown command - show error.
				$this->output_handler->writeError("Unknown command: {$input}");
				$this->output_handler->writeLine("Type /help to see available commands.");
				$this->output_handler->writeLine('');
				continue;
			}

			try {
				$this->agent->sendMessage($input);
			} catch (\Throwable $exception) {
				$this->output_handler->writeError('Error: ' . $exception->getMessage());
				if ($this->parsed_args['debug']) {
					$this->output_handler->writeDebug($exception->getTraceAsString());
				}
			}

			$this->output_handler->writeLine('');
		}

		$this->agent->endSession();

		return self::EXIT_SUCCESS;
	}

	/**
	 * Reads a line from standard input.
	 *
	 * @return string|false The input line or false on EOF.
	 */
	private function readLine(): string|false
	{
		if (function_exists('readline')) {
			$line = readline();
			if ($line !== false && $line !== '') {
				readline_add_history($line);
			}
			return $line;
		}

		$result = fgets(STDIN);

		return $result !== false ? $result : false;
	}

	/**
	 * Shows help for REPL commands.
	 *
	 * @return void
	 */
	private function showReplHelp(): void
	{
		$help = <<<HELP

Available commands:
  /help, /?    Show this help
  /quit, /q    Exit the agent
  /exit        Exit the agent

HELP;

		$this->output_handler->writeLine($help);

		// Add custom commands from registry.
		if ($this->command_registry !== null) {
			$custom_commands = $this->command_registry->getCustomCommands();
			if (\count($custom_commands) > 0) {
				$this->output_handler->writeLine('Custom commands:');
				foreach ($custom_commands as $name => $command) {
					$description = $command->getDescription();
					$description_text = $description !== '' ? $description : 'No description';
					$this->output_handler->writeLine(\sprintf('  /%s    %s', $name, $description_text));
				}
				$this->output_handler->writeLine('');
			}
		}

		$this->output_handler->writeLine('Just type your message and press Enter to interact with the agent.');
		$this->output_handler->writeLine('');
	}

	/**
	 * Handles a custom slash command from the registry.
	 *
	 * @param string $input The raw input including the leading slash.
	 *
	 * @return string|null The content to inject into conversation, empty string for direct output, or null if command not found.
	 *
	 * @since n.e.x.t
	 */
	private function handleCustomCommand(string $input): ?string
	{
		if ($this->command_registry === null || $this->command_executor === null) {
			return null;
		}

		// Parse command name and arguments from input like "/command arg1 arg2".
		$input = ltrim($input, '/');
		$parts = explode(' ', $input, 2);
		$command_name = $parts[0];
		$arguments_string = $parts[1] ?? '';

		// Check if command exists in registry.
		if (!$this->command_registry->has($command_name)) {
			return null;
		}

		$command = $this->command_registry->get($command_name);
		if ($command === null) {
			return null;
		}

		// Parse arguments and execute command.
		$arguments = ArgumentList::fromString($arguments_string);
		$result = $this->command_executor->execute($command, $arguments);

		// Handle execution result.
		if (!$result->isSuccess()) {
			$this->output_handler->writeError('Command error: ' . ($result->getError() ?? 'Unknown error'));
			return '';
		}

		// If command has direct output, show it and return empty string.
		if ($result->hasDirectOutput()) {
			$this->output_handler->writeLine($result->getDirectOutput() ?? '');
			return '';
		}

		// If command should inject into conversation, return the expanded content.
		if ($result->shouldInjectIntoConversation()) {
			return $result->getExpandedContent();
		}

		return '';
	}

	/**
	 * Runs the init command to create the configuration directory.
	 *
	 * Creates the .wp-ai-agent/ folder with default settings.json and mcp.json files.
	 * Passes the --force flag to InitCommand if provided.
	 *
	 * @since n.e.x.t
	 *
	 * @return int The exit code.
	 */
	private function runInitCommand(): int
	{
		$init_command = new InitCommand($this->output_handler);

		$arguments = [];
		if ($this->parsed_args['force']) {
			$arguments[] = '--force';
		}

		$init_command->execute($arguments);

		return self::EXIT_SUCCESS;
	}
}
