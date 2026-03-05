<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Command;

/**
 * Immutable value object providing typed access to command frontmatter configuration.
 *
 * This class wraps the raw frontmatter array and provides type-safe
 * accessors for common fields used in command definitions.
 *
 * @since 0.1.0
 */
final class CommandConfig
{
	/**
	 * The raw frontmatter data.
	 *
	 * @var array<string, mixed>
	 */
	private array $data;

	/**
	 * Creates a new CommandConfig instance.
	 *
	 * @param array<string, mixed> $data The frontmatter data.
	 */
	private function __construct(array $data)
	{
		$this->data = $data;
	}

	/**
	 * Creates a CommandConfig from a frontmatter array.
	 *
	 * @param array<string, mixed> $frontmatter The frontmatter data array.
	 *
	 * @return self
	 *
	 * @since 0.1.0
	 */
	public static function fromFrontmatter(array $frontmatter): self
	{
		return new self($frontmatter);
	}

	/**
	 * Returns the description field.
	 *
	 * @return string|null The description value, or null if not set or invalid.
	 *
	 * @since 0.1.0
	 */
	public function getDescription(): ?string
	{
		$description = $this->data['description'] ?? null;

		return is_string($description) ? $description : null;
	}

	/**
	 * Returns the argument hint field.
	 *
	 * This provides a hint about expected arguments for the command,
	 * e.g., "<file>" or "<message>".
	 *
	 * @return string|null The argument hint, or null if not set or invalid.
	 *
	 * @since 0.1.0
	 */
	public function getArgumentHint(): ?string
	{
		$hint = $this->data['argument_hint'] ?? null;

		return is_string($hint) ? $hint : null;
	}

	/**
	 * Returns the allowed tools field.
	 *
	 * @return array<int, string>|null List of allowed tool names, or null if not set.
	 *
	 * @since 0.1.0
	 */
	public function getAllowedTools(): ?array
	{
		$tools = $this->data['allowed_tools'] ?? null;

		if (!is_array($tools)) {
			return null;
		}

		return array_values(array_filter($tools, 'is_string'));
	}

	/**
	 * Returns the model field.
	 *
	 * @return string|null The model identifier, or null if not set or invalid.
	 *
	 * @since 0.1.0
	 */
	public function getModel(): ?string
	{
		$model = $this->data['model'] ?? null;

		return is_string($model) ? $model : null;
	}

	/**
	 * Returns a value by key.
	 *
	 * @param string $key     The key to look up.
	 * @param mixed  $default Default value if key doesn't exist.
	 *
	 * @return mixed The value for the key, or the default if not found.
	 *
	 * @since 0.1.0
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
	 *
	 * @since 0.1.0
	 */
	public function has(string $key): bool
	{
		return array_key_exists($key, $this->data);
	}

	/**
	 * Returns the raw frontmatter data as an array.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 0.1.0
	 */
	public function toArray(): array
	{
		return $this->data;
	}

	/**
	 * Checks if the configuration is empty.
	 *
	 * @return bool True if no configuration data is present.
	 *
	 * @since 0.1.0
	 */
	public function isEmpty(): bool
	{
		return count($this->data) === 0;
	}
}
