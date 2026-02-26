<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\Cli\Command;

use WpAiAgent\Core\Contracts\OutputHandlerInterface;
use WpAiAgent\Integration\Cli\CommandResult;

/**
 * Command to initialize the .wp-ai-agent/ configuration folder.
 *
 * Creates the configuration directory and generates default settings.json
 * and mcp.json files. Prompts for confirmation if files already exist
 * unless the --force flag is provided.
 *
 * @since n.e.x.t
 */
final class InitCommand
{
	/**
	 * The name of the configuration directory.
	 *
	 * @var string
	 */
	public const CONFIG_DIRECTORY = '.wp-ai-agent';

	/**
	 * Default settings configuration.
	 *
	 * @var array<string, mixed>
	 */
	private const DEFAULT_SETTINGS = [
		'provider' => [
			'type' => 'anthropic',
			'model' => 'claude-sonnet-4-20250514',
			'max_tokens' => 8192,
		],
		'max_turns' => 100,
		'bypass_confirmation_tools' => ['think', 'read_file', 'glob', 'grep'],
		'debug' => false,
		'streaming' => true,
	];

	/**
	 * Default MCP configuration.
	 *
	 * @var array<string, mixed>
	 */
	private const DEFAULT_MCP = [
		'mcpServers' => [],
	];

	/**
	 * Output handler for displaying messages.
	 *
	 * @var OutputHandlerInterface
	 */
	private OutputHandlerInterface $output_handler;

	/**
	 * Base directory for initialization.
	 *
	 * @var string
	 */
	private string $base_directory;

	/**
	 * Input stream for reading user responses.
	 *
	 * @var resource|null
	 */
	private $input_stream;

	/**
	 * Creates a new InitCommand instance.
	 *
	 * @param OutputHandlerInterface $output_handler Output handler for messages.
	 * @param string|null            $base_directory Base directory (default: current working directory).
	 * @param resource|null          $input_stream   Input stream for confirmation prompts.
	 */
	public function __construct(
		OutputHandlerInterface $output_handler,
		?string $base_directory = null,
		$input_stream = null
	) {
		$this->output_handler = $output_handler;
		$cwd = getcwd();
		$this->base_directory = $base_directory ?? ($cwd !== false ? $cwd : '.');
		$this->input_stream = $input_stream;
	}

	/**
	 * Returns the command name.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return 'init';
	}

	/**
	 * Returns the command description.
	 *
	 * @return string
	 */
	public function getDescription(): string
	{
		return 'Initialize the .wp-ai-agent configuration folder';
	}

	/**
	 * Returns the command usage information.
	 *
	 * @return string
	 */
	public function getUsage(): string
	{
		return <<<USAGE
Usage: agent init [options]

Options:
  --force, -f    Overwrite existing files without prompting

Description:
  Creates the .wp-ai-agent/ directory in the current project root
  with default configuration files:
  - settings.json: Agent settings (model, max_tokens, etc.)
  - mcp.json: MCP server configuration
USAGE;
	}

	/**
	 * Executes the init command.
	 *
	 * @param array<int, string> $arguments Command arguments.
	 *
	 * @return CommandResult
	 */
	public function execute(array $arguments): CommandResult
	{
		$force = $this->hasForceFlag($arguments);

		$config_dir = $this->base_directory . '/' . self::CONFIG_DIRECTORY;

		// Check if directory exists.
		if (is_dir($config_dir) && !$force) {
			if (!$this->promptForOverwrite()) {
				$this->output_handler->writeLine('Initialization cancelled.');
				return CommandResult::handled();
			}
		}

		try {
			$this->createConfigDirectory($config_dir);
			$this->writeSettingsFile($config_dir);
			$this->writeMcpFile($config_dir);
			$this->updateGitignore();

			$this->output_handler->writeSuccess('Configuration initialized successfully!');
			$this->displayNextSteps();

			return CommandResult::handled();
		} catch (\Throwable $exception) {
			$this->output_handler->writeError('Failed to initialize configuration: ' . $exception->getMessage());
			return CommandResult::handled();
		}
	}

	/**
	 * Checks if the force flag is present in arguments.
	 *
	 * @param array<int, string> $arguments Command arguments.
	 *
	 * @return bool
	 */
	private function hasForceFlag(array $arguments): bool
	{
		return in_array('--force', $arguments, true) || in_array('-f', $arguments, true);
	}

	/**
	 * Prompts the user for confirmation to overwrite existing files.
	 *
	 * @return bool True if user confirms, false otherwise.
	 */
	private function promptForOverwrite(): bool
	{
		$this->output_handler->write(
			sprintf(
				'%s/ already exists. Overwrite configuration files? [y/N]: ',
				self::CONFIG_DIRECTORY
			)
		);

		$input_stream = $this->input_stream ?? STDIN;
		$response = fgets($input_stream);

		if ($response === false) {
			return false;
		}

		return strtolower(trim($response)) === 'y';
	}

	/**
	 * Creates the configuration directory if it doesn't exist.
	 *
	 * @param string $config_dir Path to configuration directory.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException If directory creation fails.
	 */
	private function createConfigDirectory(string $config_dir): void
	{
		if (!is_dir($config_dir)) {
			if (!mkdir($config_dir, 0755, true)) {
				throw new \RuntimeException(sprintf('Could not create directory: %s', $config_dir));
			}
		}
	}

	/**
	 * Writes the settings.json file with default configuration.
	 *
	 * @param string $config_dir Path to configuration directory.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException If file write fails.
	 */
	private function writeSettingsFile(string $config_dir): void
	{
		$settings_path = $config_dir . '/settings.json';
		$json = json_encode(self::DEFAULT_SETTINGS, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

		if ($json === false) {
			throw new \RuntimeException('Failed to encode settings.json');
		}

		if (file_put_contents($settings_path, $json . "\n") === false) {
			throw new \RuntimeException(sprintf('Failed to write file: %s', $settings_path));
		}
	}

	/**
	 * Writes the mcp.json file with default configuration.
	 *
	 * @param string $config_dir Path to configuration directory.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException If file write fails.
	 */
	private function writeMcpFile(string $config_dir): void
	{
		$mcp_path = $config_dir . '/mcp.json';
		$json = json_encode(self::DEFAULT_MCP, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

		if ($json === false) {
			throw new \RuntimeException('Failed to encode mcp.json');
		}

		if (file_put_contents($mcp_path, $json . "\n") === false) {
			throw new \RuntimeException(sprintf('Failed to write file: %s', $mcp_path));
		}
	}

	/**
	 * Updates the .gitignore file to include the configuration directory.
	 *
	 * Creates the .gitignore file if it doesn't exist. Appends the
	 * .wp-ai-agent/ entry if not already present. Handles cases where
	 * the file exists with or without a trailing newline.
	 *
	 * @since n.e.x.t
	 *
	 * @return void
	 */
	private function updateGitignore(): void
	{
		$gitignore_path = $this->base_directory . '/.gitignore';
		$entry = self::CONFIG_DIRECTORY . '/';

		// Check if .gitignore exists.
		if (!file_exists($gitignore_path)) {
			file_put_contents($gitignore_path, $entry . "\n");
			return;
		}

		// Read existing content.
		$content = file_get_contents($gitignore_path);
		if ($content === false) {
			return;
		}

		// Check if entry already exists (with or without trailing slash).
		if ($this->gitignoreContainsEntry($content)) {
			return;
		}

		// Append entry with proper newline handling.
		$needs_newline = $content !== '' && !str_ends_with($content, "\n");
		$new_content = $content . ($needs_newline ? "\n" : '') . $entry . "\n";

		file_put_contents($gitignore_path, $new_content);
	}

	/**
	 * Checks if the .gitignore content already contains the config directory entry.
	 *
	 * Matches both ".wp-ai-agent" and ".wp-ai-agent/" formats.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $content The .gitignore content.
	 *
	 * @return bool True if entry exists, false otherwise.
	 */
	private function gitignoreContainsEntry(string $content): bool
	{
		$lines = explode("\n", $content);

		foreach ($lines as $line) {
			$line = trim($line);
			// Match ".wp-ai-agent" or ".wp-ai-agent/".
			if ($line === self::CONFIG_DIRECTORY || $line === self::CONFIG_DIRECTORY . '/') {
				return true;
			}
		}

		return false;
	}

	/**
	 * Displays the next steps message after successful initialization.
	 *
	 * @return void
	 */
	private function displayNextSteps(): void
	{
		$next_steps = <<<STEPS

Next steps:
  1. Edit .wp-ai-agent/settings.json to configure your agent
  2. Add MCP servers to .wp-ai-agent/mcp.json
  3. Run 'php agent' to start the agent

STEPS;

		$this->output_handler->writeLine($next_steps);
	}
}
