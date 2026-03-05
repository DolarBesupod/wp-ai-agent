<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Credential;

/**
 * Backed string enum representing supported authentication modes.
 *
 * @since n.e.x.t
 */
enum AuthMode: string
{
	case API_KEY = 'api_key';
	case SUBSCRIPTION = 'subscription';

	/**
	 * Creates an AuthMode from a string value.
	 *
	 * Wraps the native `from()` method with a more descriptive exception message.
	 *
	 * @param string $value The backing string value.
	 *
	 * @return self
	 *
	 * @throws \ValueError If the value does not match any case.
	 *
	 * @since n.e.x.t
	 */
	public static function fromString(string $value): self
	{
		try {
			return self::from($value);
		} catch (\ValueError) {
			$validValues = implode(', ', array_map(
				static fn(self $case): string => $case->value,
				self::cases()
			));

			throw new \ValueError(
				"Invalid auth mode \"{$value}\". Valid values are: {$validValues}"
			);
		}
	}
}
