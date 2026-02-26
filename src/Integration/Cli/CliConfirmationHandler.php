<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\Cli;

use WpAiAgent\Core\Contracts\ConfirmationHandlerInterface;

/**
 * CLI-specific confirmation handler with interactive prompts.
 *
 * Implements ConfirmationHandlerInterface for command-line environments,
 * displaying formatted tool execution requests and prompting the user
 * for confirmation with support for "always allow" and bypass lists.
 *
 * Supports optional persistence of bypass choices across sessions via
 * BypassPersistence.
 *
 * @since n.e.x.t
 */
final class CliConfirmationHandler implements ConfirmationHandlerInterface
{
	/**
	 * Default safe tools that bypass confirmation (read-only operations).
	 *
	 * @var array<int, string>
	 */
	private const DEFAULT_BYPASS_LIST = [
		'think',
		'read_file',
		'glob',
		'grep',
	];

	/**
	 * Box drawing characters for prompt formatting.
	 *
	 * @var array<string, string>
	 */
	private const BOX_CHARS = [
		'top_left' => '┌',
		'top_right' => '┐',
		'bottom_left' => '└',
		'bottom_right' => '┘',
		'horizontal' => '─',
		'vertical' => '│',
	];

	/**
	 * Width for the confirmation box.
	 *
	 * @var int
	 */
	private const BOX_WIDTH = 62;

	/**
	 * The output stream for displaying prompts.
	 *
	 * @var resource
	 */
	private $output_stream;

	/**
	 * The input stream for reading user responses.
	 *
	 * @var resource
	 */
	private $input_stream;

	/**
	 * Tools that should bypass confirmation for the current session.
	 *
	 * @var array<string, bool>
	 */
	private array $session_bypasses = [];

	/**
	 * Default bypass list (immutable safe tools).
	 *
	 * @var array<string, bool>
	 */
	private array $default_bypasses;

	/**
	 * Whether to auto-confirm all tool executions.
	 *
	 * @var bool
	 */
	private bool $auto_confirm = false;

	/**
	 * Whether ANSI colors are enabled.
	 *
	 * @var bool
	 */
	private bool $colors_enabled;

	/**
	 * Optional persistence for bypass state.
	 *
	 * @var BypassPersistence|null
	 */
	private ?BypassPersistence $persistence;

	/**
	 * Creates a new CliConfirmationHandler instance.
	 *
	 * @param resource|null          $output_stream  The output stream (default: STDOUT).
	 * @param resource|null          $input_stream   The input stream (default: STDIN).
	 * @param bool|null              $colors_enabled Whether to enable colors (default: auto-detect).
	 * @param array<int, string>     $default_bypass Additional tools to bypass by default.
	 * @param BypassPersistence|null $persistence    Optional persistence for bypass state.
	 */
	public function __construct(
		$output_stream = null,
		$input_stream = null,
		?bool $colors_enabled = null,
		array $default_bypass = [],
		?BypassPersistence $persistence = null
	) {
		$this->output_stream = $output_stream ?? STDOUT;
		$this->input_stream = $input_stream ?? STDIN;
		$this->colors_enabled = $colors_enabled ?? $this->detectColorSupport();
		$this->persistence = $persistence;

		$all_default_bypasses = array_merge(self::DEFAULT_BYPASS_LIST, $default_bypass);
		$this->default_bypasses = array_fill_keys($all_default_bypasses, true);

		// Load persisted bypasses if persistence is available.
		if ($this->persistence !== null) {
			$persisted = $this->persistence->load();
			foreach ($persisted as $tool_name) {
				$this->session_bypasses[strtolower($tool_name)] = true;
			}
		}
	}

	/**
	 * Requests confirmation from the user to execute a tool.
	 *
	 * @param string               $tool_name The name of the tool.
	 * @param array<string, mixed> $arguments The arguments that will be passed to the tool.
	 *
	 * @return bool True if the user confirms, false if denied.
	 */
	public function confirm(string $tool_name, array $arguments): bool
	{
		if ($this->auto_confirm) {
			return true;
		}

		if ($this->shouldBypass($tool_name)) {
			return true;
		}

		$this->displayConfirmationPrompt($tool_name, $arguments);
		$response = $this->readResponse();

		return $this->processResponse($response, $tool_name);
	}

	/**
	 * Checks if a tool should bypass confirmation.
	 *
	 * @param string $tool_name The name of the tool.
	 *
	 * @return bool True if confirmation should be bypassed.
	 */
	public function shouldBypass(string $tool_name): bool
	{
		$normalized_name = strtolower($tool_name);

		return isset($this->default_bypasses[$normalized_name])
			|| isset($this->session_bypasses[$normalized_name]);
	}

	/**
	 * Adds a tool to the bypass list.
	 *
	 * Tools on this list will execute without confirmation. If persistence
	 * is configured, the bypass is also saved for future sessions.
	 *
	 * @param string $tool_name The tool name to bypass.
	 *
	 * @return void
	 */
	public function addBypass(string $tool_name): void
	{
		$normalized_name = strtolower($tool_name);
		$this->session_bypasses[$normalized_name] = true;

		// Persist the bypass if persistence is available.
		if ($this->persistence !== null) {
			$this->persistence->addBypass($normalized_name);
		}
	}

	/**
	 * Removes a tool from the bypass list.
	 *
	 * If persistence is configured, also removes from persistent storage.
	 *
	 * @param string $tool_name The tool name.
	 *
	 * @return void
	 */
	public function removeBypass(string $tool_name): void
	{
		$normalized_name = strtolower($tool_name);
		unset($this->session_bypasses[$normalized_name]);

		// Remove from persistence if available.
		if ($this->persistence !== null) {
			$this->persistence->removeBypass($normalized_name);
		}
	}

	/**
	 * Returns all bypassed tool names.
	 *
	 * @return array<int, string>
	 */
	public function getBypasses(): array
	{
		$all_bypasses = array_merge(
			array_keys($this->default_bypasses),
			array_keys($this->session_bypasses)
		);

		return array_values(array_unique($all_bypasses));
	}

	/**
	 * Clears all bypass rules (except default safe tools).
	 *
	 * If persistence is configured, also clears persistent storage.
	 *
	 * @return void
	 */
	public function clearBypasses(): void
	{
		$this->session_bypasses = [];

		// Clear persistence if available.
		if ($this->persistence !== null) {
			$this->persistence->clear();
		}
	}

	/**
	 * Sets whether to auto-confirm all tool executions.
	 *
	 * @param bool $auto_confirm Whether to auto-confirm.
	 *
	 * @return void
	 */
	public function setAutoConfirm(bool $auto_confirm): void
	{
		$this->auto_confirm = $auto_confirm;
	}

	/**
	 * Checks if auto-confirm mode is enabled.
	 *
	 * @return bool
	 */
	public function isAutoConfirm(): bool
	{
		return $this->auto_confirm;
	}

	/**
	 * Returns the session bypass list (tools added via "always" response).
	 *
	 * @return array<int, string>
	 */
	public function getSessionBypasses(): array
	{
		return array_keys($this->session_bypasses);
	}

	/**
	 * Returns the default bypass list (safe read-only tools).
	 *
	 * @return array<int, string>
	 */
	public function getDefaultBypasses(): array
	{
		return array_keys($this->default_bypasses);
	}

	/**
	 * Checks if ANSI colors are enabled.
	 *
	 * @return bool
	 */
	public function isColorsEnabled(): bool
	{
		return $this->colors_enabled;
	}

	/**
	 * Enables or disables ANSI colors.
	 *
	 * @param bool $enabled Whether to enable colors.
	 *
	 * @return void
	 */
	public function setColorsEnabled(bool $enabled): void
	{
		$this->colors_enabled = $enabled;
	}

	/**
	 * Displays the confirmation prompt with tool details.
	 *
	 * @param string               $tool_name The tool name.
	 * @param array<string, mixed> $arguments The tool arguments.
	 *
	 * @return void
	 */
	private function displayConfirmationPrompt(string $tool_name, array $arguments): void
	{
		$inner_width = self::BOX_WIDTH - 2;
		$title = ' Tool Execution Request ';
		$title_padding_left = 2;
		$title_padding_right = $inner_width - strlen($title) - $title_padding_left;

		$this->writeLine('');

		// Top border with title.
		$top_border = self::BOX_CHARS['top_left']
			. str_repeat(self::BOX_CHARS['horizontal'], $title_padding_left)
			. $title
			. str_repeat(self::BOX_CHARS['horizontal'], max(0, $title_padding_right))
			. self::BOX_CHARS['top_right'];
		$this->writeLine($this->colorize($top_border, "\033[33m"));

		// Tool name line.
		$this->writeBoxLine('Tool: ' . $tool_name, $inner_width);

		// Arguments header.
		$this->writeBoxLine('Arguments:', $inner_width);

		// Format and display arguments as JSON.
		$json_arguments = json_encode($arguments, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		if ($json_arguments !== false) {
			$json_lines = explode("\n", $json_arguments);
			foreach ($json_lines as $json_line) {
				$this->writeBoxLine('  ' . $json_line, $inner_width);
			}
		}

		// Bottom border.
		$bottom_border = self::BOX_CHARS['bottom_left']
			. str_repeat(self::BOX_CHARS['horizontal'], $inner_width)
			. self::BOX_CHARS['bottom_right'];
		$this->writeLine($this->colorize($bottom_border, "\033[33m"));

		$this->writeLine('');
		$this->write('Execute? (y)es / (n)o / (a)lways allow this tool: ');
	}

	/**
	 * Writes a line inside the confirmation box.
	 *
	 * @param string $content     The content to display.
	 * @param int    $inner_width The inner width of the box.
	 *
	 * @return void
	 */
	private function writeBoxLine(string $content, int $inner_width): void
	{
		$content_length = mb_strlen($content);
		$padding = max(0, $inner_width - $content_length - 2);

		$line = $this->colorize(self::BOX_CHARS['vertical'], "\033[33m")
			. ' ' . $content . str_repeat(' ', $padding) . ' '
			. $this->colorize(self::BOX_CHARS['vertical'], "\033[33m");

		$this->writeLine($line);
	}

	/**
	 * Reads the user's response from input.
	 *
	 * @return string The trimmed, lowercase response.
	 */
	private function readResponse(): string
	{
		$response = fgets($this->input_stream);

		if ($response === false) {
			return '';
		}

		return strtolower(trim($response));
	}

	/**
	 * Processes the user's response.
	 *
	 * @param string $response  The user's response.
	 * @param string $tool_name The tool name.
	 *
	 * @return bool True if the tool should execute, false otherwise.
	 */
	private function processResponse(string $response, string $tool_name): bool
	{
		switch ($response) {
			case 'y':
			case 'yes':
				return true;

			case 'a':
			case 'always':
				$this->addBypass($tool_name);
				$this->writeLine(
					$this->colorize(
						sprintf('Tool "%s" will be auto-approved for this session.', $tool_name),
						"\033[32m"
					)
				);
				return true;

			case 'n':
			case 'no':
			default:
				$this->writeLine(
					$this->colorize('Tool execution skipped.', "\033[33m")
				);
				return false;
		}
	}

	/**
	 * Writes text to the output stream without a newline.
	 *
	 * @param string $text The text to write.
	 *
	 * @return void
	 */
	private function write(string $text): void
	{
		fwrite($this->output_stream, $text);
	}

	/**
	 * Writes text followed by a newline.
	 *
	 * @param string $text The text to write.
	 *
	 * @return void
	 */
	private function writeLine(string $text): void
	{
		fwrite($this->output_stream, $text . PHP_EOL);
	}

	/**
	 * Applies ANSI color codes to text.
	 *
	 * @param string $text The text to colorize.
	 * @param string $code The ANSI code to apply.
	 *
	 * @return string The colorized text.
	 */
	private function colorize(string $text, string $code): string
	{
		if (!$this->colors_enabled) {
			return $text;
		}

		return $code . $text . "\033[0m";
	}

	/**
	 * Detects whether the terminal supports ANSI colors.
	 *
	 * @return bool True if colors are supported.
	 */
	private function detectColorSupport(): bool
	{
		// Check for NO_COLOR environment variable (https://no-color.org/).
		if (getenv('NO_COLOR') !== false) {
			return false;
		}

		// Check for FORCE_COLOR environment variable.
		if (getenv('FORCE_COLOR') !== false) {
			return true;
		}

		// Check if output is a TTY.
		if (function_exists('posix_isatty') && is_resource($this->output_stream)) {
			return posix_isatty($this->output_stream);
		}

		// Check if stream is interactive (PHP 8.0+).
		if (function_exists('stream_isatty') && is_resource($this->output_stream)) {
			return stream_isatty($this->output_stream);
		}

		// Default to no colors if we can't detect.
		return false;
	}
}
