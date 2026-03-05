<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Contracts;

use RuntimeException;

/**
 * Interface for expanding file references in content.
 *
 * Commands and skills can reference external files using @path syntax:
 * - @./relative/path.md - Relative to the base path (usually command/skill file location)
 * - @~/path/from/home.md - Relative to user home directory
 * - @/absolute/path.md - Absolute path
 *
 * When expanded, the @path references are replaced with the actual file contents.
 *
 * @since n.e.x.t
 */
interface FileReferenceExpanderInterface
{
	/**
	 * Expands file references in content.
	 *
	 * Replaces @file references with the contents of the referenced files.
	 * Supports relative paths (@./), home directory paths (@~/), and
	 * absolute paths (@/). Recursively expands references found within
	 * included files.
	 *
	 * @param string $content   The content containing @file references.
	 * @param string $base_path The base path for resolving relative references.
	 *
	 * @return string The content with file references replaced by file contents.
	 *
	 * @throws RuntimeException If a referenced file is not found or if a
	 *                          circular reference is detected.
	 */
	public function expand(string $content, string $base_path): string;
}
