<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Exceptions;

/**
 * Exception thrown when parsing fails.
 *
 * @since n.e.x.t
 */
class ParseException extends AgentException
{
	/**
	 * Creates an exception for invalid frontmatter YAML.
	 *
	 * @param string          $message  The error message from the YAML parser.
	 * @param \Throwable|null $previous Optional previous exception.
	 *
	 * @return self
	 */
	public static function invalidFrontmatter(string $message, ?\Throwable $previous = null): self
	{
		return new self(
			sprintf('Failed to parse frontmatter: %s', $message),
			0,
			$previous
		);
	}

	/**
	 * Creates an exception for a file that cannot be found.
	 *
	 * @param string $path The file path.
	 *
	 * @return self
	 */
	public static function fileNotFound(string $path): self
	{
		return new self(
			sprintf('File not found: %s', $path)
		);
	}

	/**
	 * Creates an exception for a file that cannot be read.
	 *
	 * @param string          $path     The file path.
	 * @param \Throwable|null $previous Optional previous exception.
	 *
	 * @return self
	 */
	public static function fileNotReadable(string $path, ?\Throwable $previous = null): self
	{
		return new self(
			sprintf('Cannot read file: %s', $path),
			0,
			$previous
		);
	}
}
