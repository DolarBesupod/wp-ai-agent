<?php

declare(strict_types=1);

namespace PhpCliAgent\Integration\Cli;

use PhpCliAgent\Core\Contracts\OutputHandlerInterface;
use PhpCliAgent\Core\ValueObjects\ToolResult;

/**
 * CLI-specific output handler with ANSI color support and formatting.
 *
 * Implements OutputHandlerInterface for command-line environments, providing
 * colored output for errors, warnings, and success messages, as well as
 * formatted boxes for tool results.
 *
 * @since n.e.x.t
 */
final class CliOutputHandler implements OutputHandlerInterface
{
	/**
	 * ANSI escape code for red text (errors).
	 *
	 * @var string
	 */
	public const COLOR_RED = "\033[31m";

	/**
	 * ANSI escape code for green text (success).
	 *
	 * @var string
	 */
	public const COLOR_GREEN = "\033[32m";

	/**
	 * ANSI escape code for yellow text (warnings).
	 *
	 * @var string
	 */
	public const COLOR_YELLOW = "\033[33m";

	/**
	 * ANSI escape code for cyan text (tool results).
	 *
	 * @var string
	 */
	public const COLOR_CYAN = "\033[36m";

	/**
	 * ANSI escape code for bold text.
	 *
	 * @var string
	 */
	public const STYLE_BOLD = "\033[1m";

	/**
	 * ANSI escape code for dim text.
	 *
	 * @var string
	 */
	public const STYLE_DIM = "\033[2m";

	/**
	 * ANSI escape code to reset all styles.
	 *
	 * @var string
	 */
	public const RESET = "\033[0m";

	/**
	 * Box drawing characters for tool result formatting.
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
	 * Minimum width for tool result boxes.
	 *
	 * @var int
	 */
	private const BOX_MIN_WIDTH = 40;

	/**
	 * Maximum width for tool result boxes.
	 *
	 * @var int
	 */
	private const BOX_MAX_WIDTH = 80;

	/**
	 * The output stream (e.g., STDOUT).
	 *
	 * @var resource
	 */
	private $output_stream;

	/**
	 * The error stream (e.g., STDERR).
	 *
	 * @var resource
	 */
	private $error_stream;

	/**
	 * Whether ANSI colors are enabled.
	 *
	 * @var bool
	 */
	private bool $colors_enabled;

	/**
	 * Whether debug output is enabled.
	 *
	 * @var bool
	 */
	private bool $debug_enabled = false;

	/**
	 * Creates a new CliOutputHandler instance.
	 *
	 * @param resource|null $output_stream  The output stream (default: STDOUT).
	 * @param resource|null $error_stream   The error stream (default: STDERR).
	 * @param bool|null     $colors_enabled Whether to enable colors (default: auto-detect).
	 */
	public function __construct(
		$output_stream = null,
		$error_stream = null,
		?bool $colors_enabled = null
	) {
		$this->output_stream = $output_stream ?? STDOUT;
		$this->error_stream = $error_stream ?? STDERR;
		$this->colors_enabled = $colors_enabled ?? $this->detectColorSupport();
	}

	/**
	 * Writes text to the output without a newline.
	 *
	 * Used for progressive/streaming output.
	 *
	 * @param string $text The text to write.
	 *
	 * @return void
	 */
	public function write(string $text): void
	{
		$this->writeToStream($this->output_stream, $text);
	}

	/**
	 * Writes text followed by a newline.
	 *
	 * @param string $text The text to write.
	 *
	 * @return void
	 */
	public function writeLine(string $text): void
	{
		$this->writeToStream($this->output_stream, $text . PHP_EOL);
	}

	/**
	 * Writes an error message in red.
	 *
	 * @param string $text The error message.
	 *
	 * @return void
	 */
	public function writeError(string $text): void
	{
		$output = $this->colorize($text, self::COLOR_RED);
		$this->writeToStream($this->error_stream, $output . PHP_EOL);
	}

	/**
	 * Writes a success message in green.
	 *
	 * @param string $text The success message.
	 *
	 * @return void
	 */
	public function writeSuccess(string $text): void
	{
		$output = $this->colorize($text, self::COLOR_GREEN);
		$this->writeToStream($this->output_stream, $output . PHP_EOL);
	}

	/**
	 * Writes a warning message in yellow.
	 *
	 * @param string $text The warning message.
	 *
	 * @return void
	 */
	public function writeWarning(string $text): void
	{
		$output = $this->colorize($text, self::COLOR_YELLOW);
		$this->writeToStream($this->output_stream, $output . PHP_EOL);
	}

	/**
	 * Writes a tool execution result in a formatted box.
	 *
	 * @param string     $tool_name The name of the executed tool.
	 * @param ToolResult $result    The execution result.
	 *
	 * @return void
	 */
	public function writeToolResult(string $tool_name, ToolResult $result): void
	{
		$status = $result->isSuccess() ? 'SUCCESS' : 'FAILED';
		$status_color = $result->isSuccess() ? self::COLOR_GREEN : self::COLOR_RED;

		$title = sprintf('[%s] %s', $tool_name, $status);
		$content = $result->getOutput();

		if (!$result->isSuccess() && $result->getError() !== null) {
			$content = $result->getError();
		}

		$this->writeBox($title, $content, $status_color);
	}

	/**
	 * Writes the assistant's response text.
	 *
	 * @param string $text The assistant's response.
	 *
	 * @return void
	 */
	public function writeAssistantResponse(string $text): void
	{
		$this->writeLine($text);
	}

	/**
	 * Writes a streaming chunk of the assistant's response.
	 *
	 * Used for real-time streaming output without newlines.
	 *
	 * @param string $chunk The text chunk.
	 *
	 * @return void
	 */
	public function writeStreamChunk(string $chunk): void
	{
		$this->write($chunk);
	}

	/**
	 * Writes a status message in dim text.
	 *
	 * @param string $status The status message.
	 *
	 * @return void
	 */
	public function writeStatus(string $status): void
	{
		$output = $this->colorize($status, self::STYLE_DIM);
		$this->writeToStream($this->output_stream, $output . PHP_EOL);
	}

	/**
	 * Writes a debug message.
	 *
	 * Only outputs if debug mode is enabled.
	 *
	 * @param string $message The debug message.
	 *
	 * @return void
	 */
	public function writeDebug(string $message): void
	{
		if (!$this->debug_enabled) {
			return;
		}

		$output = $this->colorize('[DEBUG] ' . $message, self::STYLE_DIM);
		$this->writeToStream($this->error_stream, $output . PHP_EOL);
	}

	/**
	 * Clears the current line.
	 *
	 * Useful for updating status messages in place.
	 *
	 * @return void
	 */
	public function clearLine(): void
	{
		if ($this->colors_enabled) {
			// Move cursor to beginning of line and clear.
			$this->writeToStream($this->output_stream, "\r\033[K");
		} else {
			$this->writeToStream($this->output_stream, "\r" . str_repeat(' ', 80) . "\r");
		}
	}

	/**
	 * Sets whether debug output is enabled.
	 *
	 * @param bool $enabled Whether to show debug output.
	 *
	 * @return void
	 */
	public function setDebugEnabled(bool $enabled): void
	{
		$this->debug_enabled = $enabled;
	}

	/**
	 * Checks if debug output is enabled.
	 *
	 * @return bool
	 */
	public function isDebugEnabled(): bool
	{
		return $this->debug_enabled;
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

		// Check for common CI environment variables that support colors.
		$ci_env_vars = ['GITHUB_ACTIONS', 'GITLAB_CI', 'CI', 'TRAVIS'];
		foreach ($ci_env_vars as $var) {
			if (getenv($var) !== false) {
				return true;
			}
		}

		// Default to no colors if we can't detect.
		return false;
	}

	/**
	 * Applies ANSI color/style codes to text.
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

		return $code . $text . self::RESET;
	}

	/**
	 * Writes a formatted box with a title and content.
	 *
	 * @param string $title        The box title.
	 * @param string $content      The box content.
	 * @param string $border_color The ANSI color for the border.
	 *
	 * @return void
	 */
	private function writeBox(string $title, string $content, string $border_color): void
	{
		$lines = $this->wrapContent($content);
		$content_width = $this->calculateBoxWidth($title, $lines);
		$inner_width = $content_width - 2;

		$this->writeToStream($this->output_stream, PHP_EOL);

		// Top border with title.
		$title_display = ' ' . $title . ' ';
		$title_length = mb_strlen($title_display);
		$remaining = $inner_width - $title_length;
		$left_padding = 2;
		$right_padding = max(0, $remaining - $left_padding);

		$top_line = $this->colorize(
			self::BOX_CHARS['top_left']
			. str_repeat(self::BOX_CHARS['horizontal'], $left_padding)
			. $title_display
			. str_repeat(self::BOX_CHARS['horizontal'], $right_padding)
			. self::BOX_CHARS['top_right'],
			$border_color
		);
		$this->writeToStream($this->output_stream, $top_line . PHP_EOL);

		// Content lines.
		foreach ($lines as $line) {
			$line_length = mb_strlen($line);
			$padding = $inner_width - $line_length;
			$padded_line = $line . str_repeat(' ', max(0, $padding));

			$output_line = $this->colorize(self::BOX_CHARS['vertical'], $border_color)
				. ' ' . $padded_line . ' '
				. $this->colorize(self::BOX_CHARS['vertical'], $border_color);

			$this->writeToStream($this->output_stream, $output_line . PHP_EOL);
		}

		// Bottom border.
		$bottom_line = $this->colorize(
			self::BOX_CHARS['bottom_left']
			. str_repeat(self::BOX_CHARS['horizontal'], $inner_width + 2)
			. self::BOX_CHARS['bottom_right'],
			$border_color
		);
		$this->writeToStream($this->output_stream, $bottom_line . PHP_EOL);
	}

	/**
	 * Wraps content into lines that fit within the box.
	 *
	 * @param string $content The content to wrap.
	 *
	 * @return array<int, string> Array of wrapped lines.
	 */
	private function wrapContent(string $content): array
	{
		if ($content === '') {
			return ['(no output)'];
		}

		$max_line_width = self::BOX_MAX_WIDTH - 4;
		$lines = [];

		$raw_lines = explode("\n", $content);

		foreach ($raw_lines as $raw_line) {
			$raw_line = rtrim($raw_line, "\r");

			if ($raw_line === '') {
				$lines[] = '';
				continue;
			}

			if (mb_strlen($raw_line) <= $max_line_width) {
				$lines[] = $raw_line;
			} else {
				$wrapped = wordwrap($raw_line, $max_line_width, "\n", true);
				$wrapped_lines = explode("\n", $wrapped);
				foreach ($wrapped_lines as $wrapped_line) {
					$lines[] = $wrapped_line;
				}
			}
		}

		return $lines;
	}

	/**
	 * Calculates the appropriate box width based on content.
	 *
	 * @param string             $title The box title.
	 * @param array<int, string> $lines The content lines.
	 *
	 * @return int The calculated box width.
	 */
	private function calculateBoxWidth(string $title, array $lines): int
	{
		$max_content_length = mb_strlen($title) + 6;

		foreach ($lines as $line) {
			$line_length = mb_strlen($line);
			if ($line_length > $max_content_length) {
				$max_content_length = $line_length;
			}
		}

		$width = $max_content_length + 4;

		return max(self::BOX_MIN_WIDTH, min(self::BOX_MAX_WIDTH, $width));
	}

	/**
	 * Writes to the specified stream.
	 *
	 * @param resource $stream The stream to write to.
	 * @param string   $text   The text to write.
	 *
	 * @return void
	 */
	private function writeToStream($stream, string $text): void
	{
		fwrite($stream, $text);
	}
}
