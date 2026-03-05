<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Core\ValueObjects;

/**
 * Value object representing a session identifier.
 *
 * @since n.e.x.t
 */
final class SessionId
{
	private string $value;

	/**
	 * Creates a new SessionId instance.
	 *
	 * @param string $value The session identifier value.
	 */
	public function __construct(string $value)
	{
		if (trim($value) === '') {
			throw new \InvalidArgumentException('Session ID cannot be empty.');
		}

		$this->value = $value;
	}

	/**
	 * Generates a new unique session identifier.
	 *
	 * @return self A new SessionId with a generated UUID-like value.
	 */
	public static function generate(): self
	{
		return new self(bin2hex(random_bytes(16)));
	}

	/**
	 * Creates a SessionId from an existing string value.
	 *
	 * @param string $value The session ID string.
	 *
	 * @return self
	 */
	public static function fromString(string $value): self
	{
		return new self($value);
	}

	/**
	 * Returns the string representation of the session ID.
	 *
	 * @return string
	 */
	public function toString(): string
	{
		return $this->value;
	}

	/**
	 * Returns the string representation of the session ID.
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		return $this->value;
	}

	/**
	 * Checks equality with another SessionId.
	 *
	 * @param SessionId $other The other session ID to compare.
	 *
	 * @return bool True if both session IDs have the same value.
	 */
	public function equals(SessionId $other): bool
	{
		return $this->value === $other->value;
	}
}
