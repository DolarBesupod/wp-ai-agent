<?php

declare(strict_types=1);

namespace PhpCliAgent\Integration\Tool\BuiltIn;

use PhpCliAgent\Core\Tool\AbstractTool;
use PhpCliAgent\Core\ValueObjects\ToolResult;

/**
 * Tool for writing content to files.
 *
 * Creates files and parent directories as needed.
 * Overwrites existing files with a warning in the output.
 * Validates that paths are not system-critical before writing.
 *
 * @since n.e.x.t
 */
class WriteFileTool extends AbstractTool
{
	/**
	 * System-critical paths that should not be written to.
	 *
	 * @var array<int, string>
	 */
	private const PROTECTED_PATHS = [
		'/etc',
		'/bin',
		'/sbin',
		'/usr/bin',
		'/usr/sbin',
		'/boot',
		'/lib',
		'/lib64',
		'/usr/lib',
		'/usr/lib64',
		'/sys',
		'/proc',
		'/dev',
	];

	/**
	 * System-critical files that should not be overwritten.
	 *
	 * @var array<int, string>
	 */
	private const PROTECTED_FILES = [
		'/etc/passwd',
		'/etc/shadow',
		'/etc/group',
		'/etc/sudoers',
		'/etc/hosts',
		'/etc/fstab',
		'/etc/ssh/sshd_config',
	];

	/**
	 * Returns the unique name of the tool.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return 'write_file';
	}

	/**
	 * Returns a description of what the tool does.
	 *
	 * @return string
	 */
	public function getDescription(): string
	{
		return 'Write content to a file. '
			. 'Creates parent directories if they do not exist. '
			. 'Overwrites existing files. '
			. 'Validates that the path is not system-critical.';
	}

	/**
	 * Returns the JSON Schema for the tool's parameters.
	 *
	 * @return array<string, mixed>
	 */
	public function getParametersSchema(): array
	{
		return [
			'type' => 'object',
			'properties' => [
				'file_path' => [
					'type' => 'string',
					'description' => 'Absolute path to write',
				],
				'content' => [
					'type' => 'string',
					'description' => 'Content to write',
				],
			],
			'required' => ['file_path', 'content'],
		];
	}

	/**
	 * Writing files always requires confirmation.
	 *
	 * @return bool
	 */
	public function requiresConfirmation(): bool
	{
		return true;
	}

	/**
	 * Executes the file write operation.
	 *
	 * @param array<string, mixed> $arguments The tool arguments.
	 *
	 * @return ToolResult
	 */
	public function execute(array $arguments): ToolResult
	{
		$missing = $this->validateRequiredArguments($arguments, ['file_path', 'content']);
		if (count($missing) > 0) {
			return $this->failure(
				sprintf('Missing required argument(s): %s', implode(', ', $missing))
			);
		}

		$file_path = $this->getStringArgument($arguments, 'file_path');
		$content = $this->getStringArgument($arguments, 'content');

		if ($file_path === '') {
			return $this->failure('File path cannot be empty');
		}

		$validation_error = $this->validatePath($file_path);
		if ($validation_error !== null) {
			return $this->failure($validation_error);
		}

		return $this->writeFile($file_path, $content);
	}

	/**
	 * Validates the file path for security.
	 *
	 * @param string $file_path The file path to validate.
	 *
	 * @return string|null Error message if validation fails, null otherwise.
	 */
	private function validatePath(string $file_path): ?string
	{
		if (!$this->isAbsolutePath($file_path)) {
			return 'File path must be absolute';
		}

		$normalized_path = $this->normalizePath($file_path);

		if ($this->isProtectedFile($normalized_path)) {
			return sprintf('Cannot write to protected system file: %s', $file_path);
		}

		if ($this->isInProtectedDirectory($normalized_path)) {
			return sprintf('Cannot write to protected system directory: %s', $file_path);
		}

		return null;
	}

	/**
	 * Checks if a path is absolute.
	 *
	 * @param string $path The path to check.
	 *
	 * @return bool
	 */
	private function isAbsolutePath(string $path): bool
	{
		if (PHP_OS_FAMILY === 'Windows') {
			return preg_match('/^[A-Z]:\\\\|^\\\\\\\\/', $path) === 1;
		}

		return strpos($path, '/') === 0;
	}

	/**
	 * Normalizes a path by resolving .. and . components.
	 *
	 * @param string $path The path to normalize.
	 *
	 * @return string
	 */
	private function normalizePath(string $path): string
	{
		$parts = explode('/', $path);
		$normalized = [];

		foreach ($parts as $part) {
			if ($part === '' || $part === '.') {
				continue;
			}

			if ($part === '..') {
				array_pop($normalized);
				continue;
			}

			$normalized[] = $part;
		}

		return '/' . implode('/', $normalized);
	}

	/**
	 * Checks if the path is a protected system file.
	 *
	 * @param string $normalized_path The normalized path.
	 *
	 * @return bool
	 */
	private function isProtectedFile(string $normalized_path): bool
	{
		foreach (self::PROTECTED_FILES as $protected_file) {
			if ($normalized_path === $protected_file) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if the path is within a protected directory.
	 *
	 * @param string $normalized_path The normalized path.
	 *
	 * @return bool
	 */
	private function isInProtectedDirectory(string $normalized_path): bool
	{
		foreach (self::PROTECTED_PATHS as $protected_path) {
			if ($normalized_path === $protected_path) {
				return true;
			}

			if (strpos($normalized_path, $protected_path . '/') === 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Writes content to the file, creating directories as needed.
	 *
	 * @param string $file_path The file path.
	 * @param string $content   The content to write.
	 *
	 * @return ToolResult
	 */
	private function writeFile(string $file_path, string $content): ToolResult
	{
		$directory = dirname($file_path);
		$file_existed = file_exists($file_path);
		$directories_created = false;

		if (!is_dir($directory)) {
			$directory_created = mkdir($directory, 0755, true);
			if (!$directory_created) {
				return $this->failure(
					sprintf('Failed to create directory: %s', $directory)
				);
			}
			$directories_created = true;
		}

		if (!is_writable($directory)) {
			return $this->failure(
				sprintf('Directory is not writable: %s', $directory)
			);
		}

		if ($file_existed && !is_writable($file_path)) {
			return $this->failure(
				sprintf('File is not writable: %s', $file_path)
			);
		}

		$bytes_written = file_put_contents($file_path, $content);
		if ($bytes_written === false) {
			return $this->failure(
				sprintf('Failed to write file: %s', $file_path)
			);
		}

		$message_parts = [];

		if ($file_existed) {
			$message_parts[] = 'Overwritten existing file';
		} else {
			$message_parts[] = 'Created new file';
		}

		if ($directories_created) {
			$message_parts[] = '(created parent directories)';
		}

		$message_parts[] = sprintf(': %s', $file_path);
		$message_parts[] = sprintf('(%d bytes)', $bytes_written);

		return $this->success(
			implode(' ', $message_parts),
			[
				'file_path' => $file_path,
				'bytes_written' => $bytes_written,
				'file_existed' => $file_existed,
				'directories_created' => $directories_created,
			]
		);
	}
}
