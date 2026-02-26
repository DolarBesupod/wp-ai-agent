<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\Settings;

use WpAiAgent\Core\Contracts\ArgumentSubstitutorInterface;
use WpAiAgent\Core\ValueObjects\ArgumentList;

/**
 * Substitutes argument placeholders in content.
 *
 * Replaces $1, $2, $3, etc. placeholders with positional arguments
 * and $ARGUMENTS with the full raw argument string.
 *
 * @since n.e.x.t
 */
final class ArgumentSubstitutor implements ArgumentSubstitutorInterface
{
	/**
	 * Pattern to match positional argument placeholders ($1 through $9).
	 *
	 * Only matches single-digit placeholders to avoid ambiguity with
	 * currency amounts like $100. Uses a negative lookahead to ensure
	 * the digit is not followed by another digit.
	 */
	private const POSITIONAL_SINGLE_DIGIT_PATTERN = '/\$([1-9])(?![0-9])/';

	/**
	 * Pattern to match multi-digit positional placeholders ($10 and above).
	 *
	 * Only replaces these if an argument at that position actually exists.
	 * This avoids treating "$100" as a placeholder when there's no 100th argument.
	 */
	private const POSITIONAL_MULTI_DIGIT_PATTERN = '/\$([1-9][0-9]+)(?![0-9])/';

	/**
	 * The placeholder for all arguments.
	 */
	private const ARGUMENTS_PLACEHOLDER = '$ARGUMENTS';

	/**
	 * Substitutes argument placeholders in content.
	 *
	 * Replaces:
	 * - $1, $2, $3, etc. with corresponding positional arguments
	 * - $ARGUMENTS with the full raw argument string
	 *
	 * Missing positional arguments are replaced with empty strings.
	 *
	 * @param string       $content   The content containing placeholders.
	 * @param ArgumentList $arguments The arguments to substitute.
	 *
	 * @return string The content with placeholders replaced.
	 *
	 * @since n.e.x.t
	 */
	public function substitute(string $content, ArgumentList $arguments): string
	{
		// First, replace $ARGUMENTS with the raw argument string
		$result = str_replace(self::ARGUMENTS_PLACEHOLDER, $arguments->getRaw(), $content);

		// Replace single-digit placeholders ($1 through $9)
		// These are always replaced, with missing arguments becoming empty strings
		$result = preg_replace_callback(
			self::POSITIONAL_SINGLE_DIGIT_PATTERN,
			static function (array $matches) use ($arguments): string {
				$position = (int) $matches[1];
				$value = $arguments->get($position);

				return $value ?? '';
			},
			$result
		);

		if ($result === null) {
			return $content;
		}

		// Replace multi-digit placeholders ($10 and above)
		// Only replace if the argument at that position exists
		$result = preg_replace_callback(
			self::POSITIONAL_MULTI_DIGIT_PATTERN,
			static function (array $matches) use ($arguments): string {
				$position = (int) $matches[1];
				$value = $arguments->get($position);

				// Only replace if argument exists, otherwise leave placeholder unchanged
				if ($value === null) {
					return $matches[0]; // Return the original match unchanged
				}

				return $value;
			},
			$result
		);

		return $result ?? $content;
	}
}
