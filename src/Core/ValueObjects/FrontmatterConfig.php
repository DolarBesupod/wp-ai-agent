<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\ValueObjects;

/**
 * Immutable value object providing typed access to frontmatter fields.
 *
 * This class wraps the raw frontmatter array and provides type-safe
 * accessors for common frontmatter fields used in skill definitions.
 *
 * @since 0.1.0
 */
final class FrontmatterConfig
{
	/**
	 * The raw frontmatter data.
	 *
	 * @var array<string, mixed>
	 */
	private array $data;

	/**
	 * Creates a new FrontmatterConfig instance.
	 *
	 * @param array<string, mixed> $data The frontmatter data.
	 */
	private function __construct(array $data)
	{
		$this->data = $data;
	}

	/**
	 * Creates a FrontmatterConfig from an array.
	 *
	 * @param array<string, mixed> $data The frontmatter data array.
	 *
	 * @return self
	 */
	public static function fromArray(array $data): self
	{
		return new self($data);
	}

	/**
	 * Returns the name field.
	 *
	 * @return string|null The name value, or null if not set.
	 */
	public function getName(): ?string
	{
		$name = $this->data['name'] ?? null;
		return is_string($name) ? $name : null;
	}

	/**
	 * Returns the description field.
	 *
	 * @return string|null The description value, or null if not set.
	 */
	public function getDescription(): ?string
	{
		$description = $this->data['description'] ?? null;
		return is_string($description) ? $description : null;
	}

	/**
	 * Returns the allowed_tools field.
	 *
	 * @return array<int, string> List of allowed tool names.
	 */
	public function getAllowedTools(): array
	{
		$tools = $this->data['allowed_tools'] ?? [];
		return is_array($tools) ? array_values(array_filter($tools, 'is_string')) : [];
	}

	/**
	 * Returns the disallowed_tools field.
	 *
	 * @return array<int, string> List of disallowed tool names.
	 */
	public function getDisallowedTools(): array
	{
		$tools = $this->data['disallowed_tools'] ?? [];
		return is_array($tools) ? array_values(array_filter($tools, 'is_string')) : [];
	}

	/**
	 * Checks if a specific tool is allowed.
	 *
	 * Logic:
	 * - If disallowed_tools contains the tool, return false
	 * - If allowed_tools is empty (no restrictions), return true
	 * - If allowed_tools contains the tool, return true
	 * - Otherwise, return false
	 *
	 * @param string $tool_name The tool name to check.
	 *
	 * @return bool True if the tool is allowed.
	 */
	public function isToolAllowed(string $tool_name): bool
	{
		$disallowed = $this->getDisallowedTools();
		if (in_array($tool_name, $disallowed, true)) {
			return false;
		}

		$allowed = $this->getAllowedTools();
		if (count($allowed) === 0) {
			// No restrictions, all tools allowed
			return true;
		}

		return in_array($tool_name, $allowed, true);
	}

	/**
	 * Returns a value by key.
	 *
	 * @param string $key     The key to look up.
	 * @param mixed  $default Default value if key doesn't exist.
	 *
	 * @return mixed The value for the key, or the default if not found.
	 */
	public function get(string $key, mixed $default = null): mixed
	{
		return $this->data[$key] ?? $default;
	}

	/**
	 * Checks if a key exists.
	 *
	 * @param string $key The key to check.
	 *
	 * @return bool True if the key exists.
	 */
	public function has(string $key): bool
	{
		return array_key_exists($key, $this->data);
	}

	/**
	 * Returns the raw frontmatter data as an array.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return $this->data;
	}
}
