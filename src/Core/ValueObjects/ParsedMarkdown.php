<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\ValueObjects;

/**
 * Immutable value object representing parsed markdown content.
 *
 * Contains the parsed YAML frontmatter (as an associative array) and
 * the remaining body content (as a string).
 *
 * @since n.e.x.t
 */
final class ParsedMarkdown
{
	/**
	 * The parsed frontmatter data.
	 *
	 * @var array<string, mixed>
	 */
	private array $frontmatter;

	/**
	 * The body content after the frontmatter.
	 *
	 * @var string
	 */
	private string $body;

	/**
	 * Creates a new ParsedMarkdown instance.
	 *
	 * @param array<string, mixed> $frontmatter The parsed YAML frontmatter data.
	 * @param string               $body        The body content after the frontmatter.
	 */
	public function __construct(array $frontmatter, string $body)
	{
		$this->frontmatter = $frontmatter;
		$this->body = $body;
	}

	/**
	 * Returns the parsed frontmatter data.
	 *
	 * @return array<string, mixed>
	 */
	public function getFrontmatter(): array
	{
		return $this->frontmatter;
	}

	/**
	 * Returns the body content.
	 *
	 * @return string
	 */
	public function getBody(): string
	{
		return $this->body;
	}

	/**
	 * Checks if the parsed content has frontmatter.
	 *
	 * @return bool True if frontmatter is present and non-empty.
	 */
	public function hasFrontmatter(): bool
	{
		return count($this->frontmatter) > 0;
	}

	/**
	 * Checks if the parsed content has a body.
	 *
	 * @return bool True if body is present and non-empty after trimming.
	 */
	public function hasBody(): bool
	{
		return trim($this->body) !== '';
	}

	/**
	 * Returns a frontmatter value by key.
	 *
	 * @param string $key     The frontmatter key.
	 * @param mixed  $default Default value if key doesn't exist.
	 *
	 * @return mixed The value for the key, or the default if not found.
	 */
	public function get(string $key, mixed $default = null): mixed
	{
		return $this->frontmatter[$key] ?? $default;
	}

	/**
	 * Checks if a frontmatter key exists.
	 *
	 * @param string $key The frontmatter key to check.
	 *
	 * @return bool True if the key exists in the frontmatter.
	 */
	public function has(string $key): bool
	{
		return array_key_exists($key, $this->frontmatter);
	}
}
