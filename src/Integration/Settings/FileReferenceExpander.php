<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\Settings;

use WpAiAgent\Core\Contracts\FileReferenceExpanderInterface;
use RuntimeException;

/**
 * Expands file references in content.
 *
 * Replaces @path references with the contents of the referenced files.
 * Supports three path formats:
 * - @./relative/path.md - Relative to the base path
 * - @~/path/from/home.md - Relative to user home directory
 * - @/absolute/path.md - Absolute path
 *
 * @since n.e.x.t
 */
final class FileReferenceExpander implements FileReferenceExpanderInterface
{
	/**
	 * Pattern to match file references.
	 *
	 * Matches @ at the start of a line (or after whitespace) followed by:
	 * - ./ for relative paths
	 * - ~/ for home directory paths
	 * - / for absolute paths
	 *
	 * The pattern captures the entire path until end of line or whitespace.
	 * Trailing horizontal whitespace (spaces/tabs) on the same line is consumed
	 * to provide clean expansion.
	 */
	private const FILE_REFERENCE_PATTERN = '/(?:^|(?<=\s))@(\.\/[^\s]+|~\/[^\s]+|\/[^\s]+)[ \t]*/m';

	/**
	 * The user home directory path.
	 *
	 * @var string
	 */
	private string $user_home;

	/**
	 * Creates a new FileReferenceExpander instance.
	 *
	 * @param string|null $user_home The user home directory path. If null, uses HOME environment variable.
	 *
	 * @since n.e.x.t
	 */
	public function __construct(?string $user_home = null)
	{
		$this->user_home = $user_home ?? $this->resolveUserHome();
	}

	/**
	 * Expands file references in content.
	 *
	 * Replaces @file references with the contents of the referenced files.
	 * Recursively expands references found within included files.
	 *
	 * @param string $content   The content containing @file references.
	 * @param string $base_path The base path for resolving relative references.
	 *
	 * @return string The content with file references replaced by file contents.
	 *
	 * @throws RuntimeException If a referenced file is not found or if a
	 *                          circular reference is detected.
	 *
	 * @since n.e.x.t
	 */
	public function expand(string $content, string $base_path): string
	{
		return $this->expandWithTracking($content, $base_path, []);
	}

	/**
	 * Expands file references while tracking visited files to detect circular references.
	 *
	 * @param string        $content      The content containing @file references.
	 * @param string        $base_path    The base path for resolving relative references.
	 * @param array<string> $visited_files Array of already visited file paths (normalized).
	 *
	 * @return string The content with file references replaced by file contents.
	 *
	 * @throws RuntimeException If a referenced file is not found or if a
	 *                          circular reference is detected.
	 */
	private function expandWithTracking(string $content, string $base_path, array $visited_files): string
	{
		return (string) preg_replace_callback(
			self::FILE_REFERENCE_PATTERN,
			function (array $matches) use ($base_path, $visited_files): string {
				$path_reference = trim($matches[1]);
				$absolute_path = $this->resolvePath($path_reference, $base_path);

				// Check for circular reference
				$normalized_path = $this->normalizePath($absolute_path);
				if (in_array($normalized_path, $visited_files, true)) {
					throw new RuntimeException(
						sprintf('Circular reference detected: %s', $absolute_path)
					);
				}

				// Check if file exists
				if (! is_file($absolute_path)) {
					throw new RuntimeException(
						sprintf('File not found: %s', $absolute_path)
					);
				}

				// Read file content
				$file_content = file_get_contents($absolute_path);
				if ($file_content === false) {
					throw new RuntimeException(
						sprintf('Unable to read file: %s', $absolute_path)
					);
				}

				// Track this file as visited
				$updated_visited = $visited_files;
				$updated_visited[] = $normalized_path;

				// Get the new base path for recursive expansion (directory of included file)
				$new_base_path = dirname($absolute_path);

				// Recursively expand any references in the included content
				return $this->expandWithTracking($file_content, $new_base_path, $updated_visited);
			},
			$content
		);
	}

	/**
	 * Resolves a path reference to an absolute path.
	 *
	 * @param string $path_reference The path reference (./relative, ~/home, or /absolute).
	 * @param string $base_path      The base path for resolving relative references.
	 *
	 * @return string The resolved absolute path.
	 */
	private function resolvePath(string $path_reference, string $base_path): string
	{
		// Handle home directory reference
		if (str_starts_with($path_reference, '~/')) {
			return $this->user_home . substr($path_reference, 1);
		}

		// Handle relative path reference
		if (str_starts_with($path_reference, './') || str_starts_with($path_reference, '../')) {
			return $base_path . '/' . $path_reference;
		}

		// Handle absolute path (starts with /)
		if (str_starts_with($path_reference, '/')) {
			return $path_reference;
		}

		// Default: treat as relative path
		return $base_path . '/' . $path_reference;
	}

	/**
	 * Normalizes a file path for comparison.
	 *
	 * Resolves . and .. segments and returns an absolute path.
	 *
	 * @param string $path The path to normalize.
	 *
	 * @return string The normalized path.
	 */
	private function normalizePath(string $path): string
	{
		$real_path = realpath($path);

		if ($real_path === false) {
			// If file doesn't exist yet, at least clean up the path manually
			return $this->cleanPath($path);
		}

		return $real_path;
	}

	/**
	 * Cleans a path by resolving . and .. segments.
	 *
	 * @param string $path The path to clean.
	 *
	 * @return string The cleaned path.
	 */
	private function cleanPath(string $path): string
	{
		$parts = explode('/', $path);
		$result = [];

		foreach ($parts as $part) {
			if ($part === '' || $part === '.') {
				continue;
			}

			if ($part === '..') {
				array_pop($result);
				continue;
			}

			$result[] = $part;
		}

		// Preserve leading slash for absolute paths
		$prefix = str_starts_with($path, '/') ? '/' : '';

		return $prefix . implode('/', $result);
	}

	/**
	 * Resolves the user home directory from environment.
	 *
	 * @return string The user home directory path.
	 */
	private function resolveUserHome(): string
	{
		$home = $_SERVER['HOME'] ?? $_ENV['HOME'] ?? getenv('HOME');

		if ($home === false || $home === '') {
			// Fallback for Windows
			$home = $_SERVER['USERPROFILE'] ?? $_ENV['USERPROFILE'] ?? getenv('USERPROFILE');

			if ($home === false || $home === '') {
				// Last resort fallback
				$home = '/tmp';
			}
		}

		return (string) $home;
	}
}
