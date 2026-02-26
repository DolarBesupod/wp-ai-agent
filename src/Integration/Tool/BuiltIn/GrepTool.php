<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\Tool\BuiltIn;

use WpAiAgent\Core\Tool\AbstractTool;
use WpAiAgent\Core\ValueObjects\ToolResult;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

/**
 * Tool for searching file contents using regex patterns.
 *
 * Searches for matching patterns in files and returns results
 * in file:line format for easy navigation.
 *
 * @since n.e.x.t
 */
class GrepTool extends AbstractTool
{
	/**
	 * Maximum number of matches to return.
	 */
	private const MAX_MATCHES = 500;

	/**
	 * Maximum line length to include in output.
	 */
	private const MAX_LINE_LENGTH = 500;

	/**
	 * Number of bytes to sample for binary detection.
	 */
	private const BINARY_CHECK_BYTES = 8192;

	/**
	 * Returns the unique name of the tool.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return 'grep';
	}

	/**
	 * Returns a description of what the tool does.
	 *
	 * @return string
	 */
	public function getDescription(): string
	{
		return 'Search for a regex pattern in file contents. '
			. 'Returns matching lines with file:line prefix. '
			. 'Supports case-insensitive search.';
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
				'pattern' => [
					'type' => 'string',
					'description' => 'Regex pattern to search for',
				],
				'path' => [
					'type' => 'string',
					'description' => 'File or directory to search in (defaults to current directory)',
				],
				'case_insensitive' => [
					'type' => 'boolean',
					'description' => 'Whether to ignore case (default: false)',
				],
			],
			'required' => ['pattern'],
		];
	}

	/**
	 * Grep operations are safe and do not require confirmation.
	 *
	 * @return bool
	 */
	public function requiresConfirmation(): bool
	{
		return false;
	}

	/**
	 * Executes the grep operation.
	 *
	 * @param array<string, mixed> $arguments The tool arguments.
	 *
	 * @return ToolResult
	 */
	public function execute(array $arguments): ToolResult
	{
		$missing = $this->validateRequiredArguments($arguments, ['pattern']);
		if (count($missing) > 0) {
			return $this->failure('Missing required argument: pattern');
		}

		$pattern = $this->getStringArgument($arguments, 'pattern');
		$path = $this->getStringArgument($arguments, 'path', '');
		$case_insensitive = $this->getBoolArgument($arguments, 'case_insensitive', false);

		if ($pattern === '') {
			return $this->failure('Pattern cannot be empty');
		}

		$regex = $this->buildRegex($pattern, $case_insensitive);
		if ($regex === null) {
			return $this->failure(
				sprintf('Invalid regex pattern: %s', $pattern)
			);
		}

		$target_path = $this->resolvePath($path);
		if ($target_path === null) {
			return $this->failure(
				sprintf('Path does not exist: %s', $path === '' ? '(current directory)' : $path)
			);
		}

		return $this->searchFiles($target_path, $regex, $pattern);
	}

	/**
	 * Builds the regex pattern with proper delimiters.
	 *
	 * @param string $pattern          The user-provided pattern.
	 * @param bool   $case_insensitive Whether to make case-insensitive.
	 *
	 * @return string|null The complete regex or null if invalid.
	 */
	private function buildRegex(string $pattern, bool $case_insensitive): ?string
	{
		$regex = '/' . str_replace('/', '\/', $pattern) . '/';
		if ($case_insensitive) {
			$regex .= 'i';
		}

		if (@preg_match($regex, '') === false) {
			return null;
		}

		return $regex;
	}

	/**
	 * Resolves the target path.
	 *
	 * @param string $path The requested path.
	 *
	 * @return string|null The resolved path or null if invalid.
	 */
	private function resolvePath(string $path): ?string
	{
		if ($path === '') {
			$cwd = getcwd();
			return $cwd !== false ? $cwd : null;
		}

		$real_path = realpath($path);
		if ($real_path === false) {
			return null;
		}

		return $real_path;
	}

	/**
	 * Searches files for the pattern.
	 *
	 * @param string $target_path The file or directory to search.
	 * @param string $regex       The compiled regex.
	 * @param string $pattern     The original pattern for output.
	 *
	 * @return ToolResult
	 */
	private function searchFiles(string $target_path, string $regex, string $pattern): ToolResult
	{
		$matches = [];
		$files_searched = 0;
		$total_matches = 0;
		$truncated = false;

		if (is_file($target_path)) {
			$file_matches = $this->searchFile($target_path, $regex);
			$matches = array_merge($matches, $file_matches);
			$files_searched = 1;
			$total_matches = count($file_matches);
		} else {
			$result = $this->searchDirectory($target_path, $regex);
			$matches = $result['matches'];
			$files_searched = $result['files_searched'];
			$total_matches = $result['total_matches'];
			$truncated = $result['truncated'];
		}

		if (count($matches) === 0) {
			return $this->success('No matches found.', [
				'pattern' => $pattern,
				'path' => $target_path,
				'files_searched' => $files_searched,
				'match_count' => 0,
			]);
		}

		$output = implode("\n", $matches);
		if ($truncated) {
			$output .= sprintf("\n\n[Results truncated to %d matches]", self::MAX_MATCHES);
		}

		return $this->success($output, [
			'pattern' => $pattern,
			'path' => $target_path,
			'files_searched' => $files_searched,
			'match_count' => $total_matches,
			'truncated' => $truncated,
		]);
	}

	/**
	 * Searches a single file for the pattern.
	 *
	 * @param string $file_path The file to search.
	 * @param string $regex     The compiled regex.
	 *
	 * @return array<int, string>
	 */
	private function searchFile(string $file_path, string $regex): array
	{
		if (!is_readable($file_path)) {
			return [];
		}

		if ($this->isBinaryFile($file_path)) {
			return [];
		}

		$matches = [];
		$handle = fopen($file_path, 'r');

		if ($handle === false) {
			return [];
		}

		$line_number = 0;

		while (($line = fgets($handle)) !== false) {
			$line_number++;

			if (preg_match($regex, $line)) {
				$line = rtrim($line, "\r\n");

				if (strlen($line) > self::MAX_LINE_LENGTH) {
					$line = substr($line, 0, self::MAX_LINE_LENGTH) . '...';
				}

				$matches[] = sprintf('%s:%d:%s', $file_path, $line_number, $line);
			}
		}

		fclose($handle);

		return $matches;
	}

	/**
	 * Searches a directory recursively for the pattern.
	 *
	 * @param string $directory_path The directory to search.
	 * @param string $regex          The compiled regex.
	 *
	 * @return array{matches: array<int, string>, files_searched: int, total_matches: int, truncated: bool}
	 */
	private function searchDirectory(string $directory_path, string $regex): array
	{
		$all_matches = [];
		$files_searched = 0;
		$total_matches = 0;
		$truncated = false;

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator(
					$directory_path,
					FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS
				),
				RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ($iterator as $file) {
				if (!$file->isFile()) {
					continue;
				}

				if ($this->shouldSkipFile($file->getPathname())) {
					continue;
				}

				$files_searched++;
				$file_matches = $this->searchFile($file->getPathname(), $regex);
				$total_matches += count($file_matches);

				foreach ($file_matches as $match) {
					if (count($all_matches) >= self::MAX_MATCHES) {
						$truncated = true;
						break 2;
					}
					$all_matches[] = $match;
				}
			}
		} catch (\Exception $e) {
			// Ignore permission errors and continue with what we found.
		}

		return [
			'matches' => $all_matches,
			'files_searched' => $files_searched,
			'total_matches' => $total_matches,
			'truncated' => $truncated,
		];
	}

	/**
	 * Determines if a file should be skipped during search.
	 *
	 * @param string $file_path The file path.
	 *
	 * @return bool True if the file should be skipped.
	 */
	private function shouldSkipFile(string $file_path): bool
	{
		$skip_directories = [
			'/.git/',
			'/node_modules/',
			'/vendor/',
			'/.svn/',
			'/.hg/',
		];

		foreach ($skip_directories as $skip) {
			if (strpos($file_path, $skip) !== false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Detects if a file is binary by checking for null bytes.
	 *
	 * @param string $file_path The file path to check.
	 *
	 * @return bool True if the file appears to be binary.
	 */
	private function isBinaryFile(string $file_path): bool
	{
		$file_size = filesize($file_path);
		if ($file_size === false || $file_size === 0) {
			return false;
		}

		$handle = fopen($file_path, 'rb');
		if ($handle === false) {
			return false;
		}

		$bytes_to_read = min($file_size, self::BINARY_CHECK_BYTES);
		$sample = fread($handle, $bytes_to_read);
		fclose($handle);

		if ($sample === false || $sample === '') {
			return false;
		}

		if (strpos($sample, "\0") !== false) {
			return true;
		}

		return false;
	}
}
