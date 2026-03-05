<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\Logging;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * PSR-3 compliant file logger.
 *
 * Writes log messages to a file with structured formatting,
 * log level filtering, and automatic file rotation based on size.
 *
 * @since 0.1.0
 */
final class FileLogger extends AbstractLogger
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
	 * Default maximum file size before rotation (10 MB).
	 *
	 * @var int
	 */
	private const DEFAULT_MAX_FILE_SIZE = 10 * 1024 * 1024;

	/**
	 * Default number of rotated files to keep.
	 *
	 * @var int
	 */
	private const DEFAULT_MAX_FILES = 5;

	/**
	 * Path to the log file.
	 *
	 * @var string
	 */
	private string $file_path;

	/**
	 * Minimum log level to record.
	 *
	 * @var string
	 */
	private string $min_level;

	/**
	 * Maximum file size in bytes before rotation.
	 *
	 * @var int
	 */
	private int $max_file_size;

	/**
	 * Maximum number of rotated files to keep.
	 *
	 * @var int
	 */
	private int $max_files;

	/**
	 * Date format for log entries.
	 *
	 * @var string
	 */
	private string $date_format;

	/**
	 * Keys to redact from context.
	 *
	 * @var array<int, string>
	 */
	private array $redacted_keys = [
		'password',
		'api_key',
		'apiKey',
		'token',
		'secret',
		'credential',
		'credit_card',
		'authorization',
	];

	/**
	 * Creates a new FileLogger instance.
	 *
	 * @param string $file_path     Path to the log file.
	 * @param string $min_level     Minimum log level (default: debug).
	 * @param int    $max_file_size Maximum file size in bytes (default: 10 MB).
	 * @param int $max_files Maximum number of rotated files (default: 5).
	 * @param string $date_format   Date format for log entries.
	 *
	 * @throws \RuntimeException If the log directory cannot be created.
	 */
	public function __construct(
		string $file_path,
		string $min_level = LogLevel::DEBUG,
		int $max_file_size = self::DEFAULT_MAX_FILE_SIZE,
		int $max_files = self::DEFAULT_MAX_FILES,
		string $date_format = 'Y-m-d H:i:s.u'
	) {
		$this->file_path = $file_path;
		$this->min_level = $min_level;
		$this->max_file_size = $max_file_size;
		$this->max_files = $max_files;
		$this->date_format = $date_format;

		$this->ensureDirectoryExists();
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

		$this->rotateIfNeeded();

		$formatted = $this->formatMessage($level_string, (string) $message, $context);

		$this->writeToFile($formatted);
	}

	/**
	 * Adds a key to the list of redacted keys.
	 *
	 * @param string $key The key to redact.
	 *
	 * @return void
	 */
	public function addRedactedKey(string $key): void
	{
		if (!in_array($key, $this->redacted_keys, true)) {
			$this->redacted_keys[] = $key;
		}
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
	 * Gets the log file path.
	 *
	 * @return string
	 */
	public function getFilePath(): string
	{
		return $this->file_path;
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
		$timestamp = $this->getTimestamp();
		$interpolated = $this->interpolate($message, $context);
		$sanitized_context = $this->sanitizeContext($context);

		$level_upper = strtoupper($level);
		$context_json = '';

		if (count($sanitized_context) > 0) {
			$json = json_encode($sanitized_context, JSON_UNESCAPED_SLASHES);
			$context_json = $json !== false ? ' ' . $json : '';
		}

		return sprintf(
			"[%s] %s: %s%s\n",
			$timestamp,
			$level_upper,
			$interpolated,
			$context_json
		);
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
				'%s: %s in %s:%d',
				get_class($value),
				$value->getMessage(),
				$value->getFile(),
				$value->getLine()
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

		if (is_resource($value)) {
			return sprintf('[resource %s]', get_resource_type($value));
		}

		return '[unknown]';
	}

	/**
	 * Sanitizes context by redacting sensitive keys.
	 *
	 * @param array<mixed> $context The context to sanitize.
	 *
	 * @return array<mixed>
	 */
	private function sanitizeContext(array $context): array
	{
		$sanitized = [];

		foreach ($context as $key => $value) {
			if (!is_string($key)) {
				continue;
			}

			$lower_key = strtolower($key);
			$is_redacted = false;

			foreach ($this->redacted_keys as $redacted_key) {
				if (stripos($lower_key, strtolower($redacted_key)) !== false) {
					$is_redacted = true;
					break;
				}
			}

			if ($is_redacted) {
				$sanitized[$key] = '[REDACTED]';
			} elseif (is_array($value)) {
				$sanitized[$key] = $this->sanitizeContext($value);
			} elseif ($value instanceof \Throwable) {
				$sanitized[$key] = [
					'class' => get_class($value),
					'message' => $value->getMessage(),
					'code' => $value->getCode(),
					'file' => $value->getFile(),
					'line' => $value->getLine(),
				];
			} else {
				$sanitized[$key] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Gets the current timestamp.
	 *
	 * @return string
	 */
	private function getTimestamp(): string
	{
		$microtime = microtime(true);
		$microseconds = sprintf('%06d', ($microtime - floor($microtime)) * 1000000);
		$datetime = new \DateTime(date('Y-m-d H:i:s', (int) $microtime));

		return $datetime->format(str_replace('u', $microseconds, $this->date_format));
	}

	/**
	 * Rotates the log file if it exceeds the maximum size.
	 *
	 * @return void
	 */
	private function rotateIfNeeded(): void
	{
		if (!file_exists($this->file_path)) {
			return;
		}

		$file_size = filesize($this->file_path);

		if ($file_size === false || $file_size < $this->max_file_size) {
			return;
		}

		// Delete oldest file if we're at max.
		$oldest_file = sprintf('%s.%d', $this->file_path, $this->max_files);
		if (file_exists($oldest_file)) {
			unlink($oldest_file);
		}

		// Rotate existing files.
		for ($i = $this->max_files - 1; $i >= 1; $i--) {
			$current = sprintf('%s.%d', $this->file_path, $i);
			$next = sprintf('%s.%d', $this->file_path, $i + 1);

			if (file_exists($current)) {
				rename($current, $next);
			}
		}

		// Move current file to .1.
		rename($this->file_path, sprintf('%s.1', $this->file_path));
	}

	/**
	 * Writes content to the log file.
	 *
	 * @param string $content The content to write.
	 *
	 * @return void
	 */
	private function writeToFile(string $content): void
	{
		file_put_contents($this->file_path, $content, FILE_APPEND | LOCK_EX);
	}

	/**
	 * Ensures the log directory exists.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException If the directory cannot be created.
	 */
	private function ensureDirectoryExists(): void
	{
		$directory = dirname($this->file_path);

		if (is_dir($directory)) {
			return;
		}

		if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
			throw new \RuntimeException(
				sprintf('Failed to create log directory: %s', $directory)
			);
		}
	}
}
