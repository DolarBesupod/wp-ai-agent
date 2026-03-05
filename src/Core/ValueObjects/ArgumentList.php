<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Core\ValueObjects;

/**
 * Value object representing a parsed list of command arguments.
 *
 * Parses argument strings respecting quoted values, where both single and
 * double quotes can be used to group words into a single argument.
 *
 * @since n.e.x.t
 */
final class ArgumentList
{
	/**
	 * The original raw argument string.
	 *
	 * @var string
	 */
	private string $raw;

	/**
	 * The parsed arguments array (0-indexed internally).
	 *
	 * @var array<int, string>
	 */
	private array $arguments;

	/**
	 * Creates a new ArgumentList instance.
	 *
	 * @param string             $raw       The original argument string.
	 * @param array<int, string> $arguments The parsed arguments array.
	 */
	private function __construct(string $raw, array $arguments)
	{
		$this->raw = $raw;
		$this->arguments = $arguments;
	}

	/**
	 * Creates an ArgumentList from a raw argument string.
	 *
	 * Parses the string respecting quoted values. Both single and double
	 * quotes can be used to group words into a single argument.
	 *
	 * @param string $arguments The raw argument string to parse.
	 *
	 * @return self
	 *
	 * @since n.e.x.t
	 */
	public static function fromString(string $arguments): self
	{
		$parsed = self::parseArguments($arguments);

		return new self($arguments, $parsed);
	}

	/**
	 * Gets an argument by position (1-based index).
	 *
	 * @param int $position The 1-based position of the argument.
	 *
	 * @return string|null The argument value, or null if not found.
	 *
	 * @since n.e.x.t
	 */
	public function get(int $position): ?string
	{
		if ($position < 1) {
			return null;
		}

		$index = $position - 1;

		return $this->arguments[$index] ?? null;
	}

	/**
	 * Gets all parsed arguments as an array.
	 *
	 * @return array<int, string> The parsed arguments (0-indexed).
	 *
	 * @since n.e.x.t
	 */
	public function getAll(): array
	{
		return $this->arguments;
	}

	/**
	 * Gets the original raw argument string.
	 *
	 * @return string The original input string.
	 *
	 * @since n.e.x.t
	 */
	public function getRaw(): string
	{
		return $this->raw;
	}

	/**
	 * Gets the number of parsed arguments.
	 *
	 * @return int The count of arguments.
	 *
	 * @since n.e.x.t
	 */
	public function count(): int
	{
		return count($this->arguments);
	}

	/**
	 * Checks if the argument list is empty.
	 *
	 * @return bool True if no arguments are present.
	 *
	 * @since n.e.x.t
	 */
	public function isEmpty(): bool
	{
		return $this->count() === 0;
	}

	/**
	 * Parses the argument string into an array of arguments.
	 *
	 * Respects quoted strings (both single and double quotes) as single
	 * arguments. Handles escaped quotes within quoted strings.
	 *
	 * @param string $input The input string to parse.
	 *
	 * @return array<int, string> The parsed arguments.
	 */
	private static function parseArguments(string $input): array
	{
		$arguments = [];
		$current = '';
		$in_quotes = false;
		$quote_char = '';
		$has_content = false; // Track if we've started collecting an argument
		$length = strlen($input);
		$i = 0;

		while ($i < $length) {
			$char = $input[$i];

			if ($in_quotes) {
				// Check for escape sequence
				if ($char === '\\' && $i + 1 < $length) {
					$next_char = $input[$i + 1];
					if ($next_char === $quote_char) {
						// Escaped quote - add the quote character
						$current .= $next_char;
						$i += 2;
						continue;
					}
				}

				if ($char === $quote_char) {
					// End of quoted section
					$in_quotes = false;
					$quote_char = '';
				} else {
					$current .= $char;
				}
			} else {
				if ($char === '"' || $char === "'") {
					// Start of quoted section
					$in_quotes = true;
					$quote_char = $char;
					$has_content = true; // A quoted section starts an argument
				} elseif ($char === ' ' || $char === "\t") {
					// Whitespace - end of current argument
					if ($has_content) {
						$arguments[] = $current;
						$current = '';
						$has_content = false;
					}
				} else {
					$current .= $char;
					$has_content = true;
				}
			}

			$i++;
		}

		// Add final argument if any
		if ($has_content) {
			$arguments[] = $current;
		}

		return $arguments;
	}
}
