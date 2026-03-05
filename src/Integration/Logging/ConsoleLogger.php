<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\Logging;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * PSR-3 compliant console logger.
 *
 * Writes log messages to STDOUT/STDERR with ANSI color support,
 * optional timestamps, and log level filtering.
 *
 * @since 0.1.0
 */
final class ConsoleLogger extends AbstractLogger
{
	/**
	 * Log level priority values for filtering.
	 *
	 * @var array<string, int>
	 */
	private const LEVEL_PRIORITIES = [
		LogLevel::EMERGENCY => 800,
		LogLevel::ALERT => 700,
		LogLevel::CRITICAL => 600,
		LogLevel::ERROR => 500,
		LogLevel::WARNING => 400,
		LogLevel::NOTICE => 300,
		LogLevel::INFO => 200,
		LogLevel::DEBUG => 100,
	];

	/**
	 * ANSI color codes for log levels.
	 *
	 * @var array<string, string>
	 */
	private const LEVEL_COLORS = [
		LogLevel::EMERGENCY => "\033[1;37;41m", // White on red background
		LogLevel::ALERT => "\033[1;37;41m",     // White on red background
		LogLevel::CRITICAL => "\033[1;31m",     // Bold red
		LogLevel::ERROR => "\033[31m",          // Red
		LogLevel::WARNING => "\033[33m",        // Yellow
		LogLevel::NOTICE => "\033[36m",         // Cyan
		LogLevel::INFO => "\033[32m",           // Green
		LogLevel::DEBUG => "\033[90m",          // Gray
	];

	/**
	 * ANSI reset code.
	 *
	 * @var string
	 */
	private const RESET = "\033[0m";

	/**
	 * Dim style for timestamps.
	 *
	 * @var string
	 */
	private const DIM = "\033[2m";

	/**
	 * Whether to output to STDERR for error levels.
	 *
	 * @var bool
	 */
	private bool $use_stderr;

	/**
	 * Whether to use ANSI colors.
	 *
	 * @var bool
	 */
	private bool $use_colors;

	/**
	 * Whether to show timestamps.
	 *
	 * @var bool
	 */
	private bool $show_timestamps;

	/**
	 * Minimum log level to display.
	 *
	 * @var string
	 */
	private string $min_level;

	/**
	 * Date format for timestamps.
	 *
	 * @var string
	 */
	private string $date_format;

	/**
	 * The output stream (STDOUT).
	 *
	 * @var resource
	 */
	private $output_stream;

	/**
	 * The error stream (STDERR).
	 *
	 * @var resource
	 */
	private $error_stream;

	/**
	 * Creates a new ConsoleLogger instance.
	 *
	 * @param string $min_level Minimum log level (default: debug).
	 * @param bool $use_colors Whether to use ANSI colors (default: auto-detect).
	 * @param bool          $show_timestamps Whether to show timestamps (default: false).
	 * @param bool $use_stderr Use STDERR for error levels (default: true).
	 * @param string $date_format Date format for timestamps.
	 * @param resource|null $output_stream   Custom output stream.
	 * @param resource|null $error_stream    Custom error stream.
	 */
	public function __construct(
		string $min_level = LogLevel::DEBUG,
		bool $use_colors = true,
		bool $show_timestamps = false,
		bool $use_stderr = true,
		string $date_format = 'H:i:s',
		$output_stream = null,
		$error_stream = null
	) {
		$this->min_level = $min_level;
		$this->show_timestamps = $show_timestamps;
		$this->use_stderr = $use_stderr;
		$this->date_format = $date_format;
		$this->output_stream = $output_stream ?? STDOUT;
		$this->error_stream = $error_stream ?? STDERR;
		$this->use_colors = $use_colors && $this->detectColorSupport();
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed $level The log level.
	 * @param string       $message The log message.
	 * @param array<mixed> $context The log context.
	 *
	 * @return void
	 */
	public function log($level, $message, array $context = [])
	{
		$level_string = (string) $level;

		if (!$this->shouldLog($level_string)) {
			return;
		}

		$formatted = $this->formatMessage($level_string, (string) $message, $context);

		$this->writeToStream($level_string, $formatted);
	}

	/**
	 * Sets the minimum log level.
	 *
	 * @param string $level The minimum level.
	 *
	 * @return void
	 */
	public function setMinLevel(string $level): void
	{
		$this->min_level = $level;
	}

	/**
	 * Enables or disables color output.
	 *
	 * @param bool $enabled Whether to use colors.
	 *
	 * @return void
	 */
	public function setColorsEnabled(bool $enabled): void
	{
		$this->use_colors = $enabled;
	}

	/**
	 * Enables or disables timestamps.
	 *
	 * @param bool $enabled Whether to show timestamps.
	 *
	 * @return void
	 */
	public function setTimestampsEnabled(bool $enabled): void
	{
		$this->show_timestamps = $enabled;
	}

	/**
	 * Checks if colors are enabled.
	 *
	 * @return bool
	 */
	public function isColorsEnabled(): bool
	{
		return $this->use_colors;
	}

	/**
	 * Determines if a log level should be recorded.
	 *
	 * @param string $level The log level.
	 *
	 * @return bool
	 */
	private function shouldLog(string $level): bool
	{
		$level_priority = self::LEVEL_PRIORITIES[$level] ?? 0;
		$min_priority = self::LEVEL_PRIORITIES[$this->min_level] ?? 0;

		return $level_priority >= $min_priority;
	}

	/**
	 * Formats a log message.
	 *
	 * @param string $level The log level.
	 * @param string       $message The log message.
	 * @param array<mixed> $context The log context.
	 *
	 * @return string
	 */
	private function formatMessage(string $level, string $message, array $context): string
	{
		$interpolated = $this->interpolate($message, $context);
		$parts = [];

		if ($this->show_timestamps) {
			$timestamp = date($this->date_format);
			if ($this->use_colors) {
				$parts[] = self::DIM . $timestamp . self::RESET;
			} else {
				$parts[] = $timestamp;
			}
		}

		$level_upper = strtoupper($level);

		if ($this->use_colors) {
			$color = self::LEVEL_COLORS[$level] ?? '';
			$parts[] = sprintf('%s[%s]%s', $color, $level_upper, self::RESET);
			$parts[] = $interpolated;
		} else {
			$parts[] = sprintf('[%s]', $level_upper);
			$parts[] = $interpolated;
		}

		return implode(' ', $parts) . PHP_EOL;
	}

	/**
	 * Interpolates context values into the message placeholders.
	 *
	 * @param string       $message The message with placeholders.
	 * @param array<mixed> $context The context values.
	 *
	 * @return string
	 */
	private function interpolate(string $message, array $context): string
	{
		$replace = [];

		foreach ($context as $key => $value) {
			if (is_string($key)) {
				$replace['{' . $key . '}'] = $this->stringify($value);
			}
		}

		return strtr($message, $replace);
	}

	/**
	 * Converts a value to a string representation.
	 *
	 * @param mixed $value The value to convert.
	 *
	 * @return string
	 */
	private function stringify(mixed $value): string
	{
		if ($value === null) {
			return 'null';
		}

		if (is_bool($value)) {
			return $value ? 'true' : 'false';
		}

		if (is_scalar($value)) {
			return (string) $value;
		}

		if ($value instanceof \Throwable) {
			return sprintf(
				'%s: %s',
				get_class($value),
				$value->getMessage()
			);
		}

		if (is_object($value)) {
			if (method_exists($value, '__toString')) {
				return (string) $value;
			}

			return sprintf('[object %s]', get_class($value));
		}

		if (is_array($value)) {
			$json = json_encode($value, JSON_UNESCAPED_SLASHES);
			return $json !== false ? $json : '[array]';
		}

		return '[unknown]';
	}

	/**
	 * Writes to the appropriate stream based on log level.
	 *
	 * @param string $level   The log level.
	 * @param string $message The formatted message.
	 *
	 * @return void
	 */
	private function writeToStream(string $level, string $message): void
	{
		$is_error_level = in_array(
			$level,
			[LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR],
			true
		);

		$stream = ($is_error_level && $this->use_stderr)
			? $this->error_stream
			: $this->output_stream;

		fwrite($stream, $message);
	}

	/**
	 * Detects whether the terminal supports ANSI colors.
	 *
	 * @return bool
	 */
	private function detectColorSupport(): bool
	{
		// Check for NO_COLOR environment variable.
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

		// Default to true if we can't detect.
		return true;
	}
}
