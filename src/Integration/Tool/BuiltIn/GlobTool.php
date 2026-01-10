<?php

declare(strict_types=1);

namespace PhpCliAgent\Integration\Tool\BuiltIn;

use PhpCliAgent\Core\Tool\AbstractTool;
use PhpCliAgent\Core\ValueObjects\ToolResult;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

/**
 * Tool for finding files by pattern matching.
 *
 * Supports glob patterns including ** for recursive directory matching.
 * Returns a list of matching file paths sorted by modification time.
 *
 * @since n.e.x.t
 */
class GlobTool extends AbstractTool
{
	/**
	 * Maximum number of files to return.
	 */
	private const MAX_RESULTS = 1000;

	/**
	 * Returns the unique name of the tool.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return 'glob';
	}

	/**
	 * Returns a description of what the tool does.
	 *
	 * @return string
	 */
	public function getDescription(): string
	{
		return 'Find files matching a glob pattern. '
			. 'Supports ** for recursive directory matching. '
			. 'Returns matching file paths sorted by modification time.';
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
					'description' => 'Glob pattern to match (e.g., **/*.php, src/*.txt)',
				],
				'path' => [
					'type' => 'string',
					'description' => 'Base directory to search in (defaults to current directory)',
				],
			],
			'required' => ['pattern'],
		];
	}

	/**
	 * Glob operations are safe and do not require confirmation.
	 *
	 * @return bool
	 */
	public function requiresConfirmation(): bool
	{
		return false;
	}

	/**
	 * Executes the glob operation.
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

		if ($pattern === '') {
			return $this->failure('Pattern cannot be empty');
		}

		$base_path = $this->resolveBasePath($path);
		if ($base_path === null) {
			return $this->failure(
				sprintf('Base path does not exist or is not a directory: %s', $path)
			);
		}

		return $this->findMatchingFiles($base_path, $pattern);
	}

	/**
	 * Resolves and validates the base path.
	 *
	 * @param string $path The requested base path.
	 *
	 * @return string|null The resolved path or null if invalid.
	 */
	private function resolveBasePath(string $path): ?string
	{
		if ($path === '') {
			$cwd = getcwd();
			return $cwd !== false ? $cwd : null;
		}

		$real_path = realpath($path);
		if ($real_path === false || !is_dir($real_path)) {
			return null;
		}

		return $real_path;
	}

	/**
	 * Finds files matching the pattern.
	 *
	 * @param string $base_path The base directory.
	 * @param string $pattern   The glob pattern.
	 *
	 * @return ToolResult
	 */
	private function findMatchingFiles(string $base_path, string $pattern): ToolResult
	{
		$matches = [];

		if (strpos($pattern, '**') !== false) {
			$matches = $this->findRecursive($base_path, $pattern);
		} else {
			$matches = $this->findSimple($base_path, $pattern);
		}

		if (count($matches) === 0) {
			return $this->success('No files found matching pattern.', [
				'pattern' => $pattern,
				'base_path' => $base_path,
				'count' => 0,
			]);
		}

		usort($matches, function (string $a, string $b): int {
			$mtime_a = filemtime($a);
			$mtime_b = filemtime($b);

			if ($mtime_a === false || $mtime_b === false) {
				return 0;
			}

			return $mtime_b - $mtime_a;
		});

		$truncated = false;
		if (count($matches) > self::MAX_RESULTS) {
			$matches = array_slice($matches, 0, self::MAX_RESULTS);
			$truncated = true;
		}

		$output = implode("\n", $matches);
		if ($truncated) {
			$output .= sprintf("\n\n[Results truncated to %d files]", self::MAX_RESULTS);
		}

		return $this->success($output, [
			'pattern' => $pattern,
			'base_path' => $base_path,
			'count' => count($matches),
			'truncated' => $truncated,
			'files' => $matches,
		]);
	}

	/**
	 * Finds files using recursive directory iteration for ** patterns.
	 *
	 * @param string $base_path The base directory.
	 * @param string $pattern   The glob pattern with **.
	 *
	 * @return array<int, string>
	 */
	private function findRecursive(string $base_path, string $pattern): array
	{
		$matches = [];
		$regex = $this->patternToRegex($pattern);

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator(
					$base_path,
					FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS
				),
				RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ($iterator as $file) {
				if (!$file->isFile()) {
					continue;
				}

				$relative_path = $this->getRelativePath($base_path, $file->getPathname());

				if (preg_match($regex, $relative_path)) {
					$matches[] = $file->getPathname();
				}
			}
		} catch (\Exception $e) {
			// Ignore permission errors and continue with what we found.
		}

		return $matches;
	}

	/**
	 * Finds files using simple glob() for non-recursive patterns.
	 *
	 * @param string $base_path The base directory.
	 * @param string $pattern   The glob pattern.
	 *
	 * @return array<int, string>
	 */
	private function findSimple(string $base_path, string $pattern): array
	{
		$full_pattern = rtrim($base_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $pattern;
		$results = glob($full_pattern);

		if ($results === false) {
			return [];
		}

		return array_filter($results, 'is_file');
	}

	/**
	 * Converts a glob pattern to a regular expression.
	 *
	 * @param string $pattern The glob pattern.
	 *
	 * @return string The regex pattern.
	 */
	private function patternToRegex(string $pattern): string
	{
		$regex = preg_quote($pattern, '#');

		$regex = str_replace('\*\*', '.*', $regex);

		$regex = str_replace('\*', '[^/]*', $regex);

		$regex = str_replace('\?', '.', $regex);

		return '#^' . $regex . '$#';
	}

	/**
	 * Gets the relative path from base to target.
	 *
	 * @param string $base_path   The base directory.
	 * @param string $target_path The target file path.
	 *
	 * @return string The relative path.
	 */
	private function getRelativePath(string $base_path, string $target_path): string
	{
		$base = rtrim($base_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		if (strpos($target_path, $base) === 0) {
			return substr($target_path, strlen($base));
		}

		return $target_path;
	}
}
