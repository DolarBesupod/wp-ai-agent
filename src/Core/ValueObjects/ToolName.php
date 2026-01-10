<?php

declare(strict_types=1);

namespace PhpCliAgent\Core\ValueObjects;

use InvalidArgumentException;

/**
 * Value object representing a tool name.
 *
 * Tool names must be lowercase with underscores, e.g., "read_file", "execute_bash".
 *
 * @since n.e.x.t
 */
final class ToolName
{
	private string $value;

	/**
	 * Creates a new ToolName instance.
	 *
	 * @param string $value The tool name value.
	 *
	 * @throws InvalidArgumentException If the name is empty or invalid.
	 */
	public function __construct(string $value)
	{
		$trimmed = trim($value);

		if ($trimmed === '') {
			throw new InvalidArgumentException('Tool name cannot be empty.');
		}

		if (!preg_match('/^[a-z][a-z0-9_]*$/', $trimmed)) {
			throw new InvalidArgumentException(
				sprintf(
					'Tool name "%s" is invalid. Names must start with a lowercase letter and contain only lowercase letters, numbers, and underscores.',
					$value
				)
			);
		}

		$this->value = $trimmed;
	}

	/**
	 * Creates a ToolName from an existing string value.
	 *
	 * @param string $value The tool name string.
	 *
	 * @return self
	 */
	public static function fromString(string $value): self
	{
		return new self($value);
	}

	/**
	 * Returns the string representation of the tool name.
	 *
	 * @return string
	 */
	public function toString(): string
	{
		return $this->value;
	}

	/**
	 * Returns the string representation of the tool name.
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		return $this->value;
	}

	/**
	 * Checks equality with another ToolName.
	 *
	 * @param ToolName $other The other tool name to compare.
	 *
	 * @return bool True if both tool names have the same value.
	 */
	public function equals(ToolName $other): bool
	{
		return $this->value === $other->value;
	}
}
