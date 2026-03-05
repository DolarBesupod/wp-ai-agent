<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Core\Exceptions;

/**
 * Exception thrown when configuration is invalid or missing.
 *
 * @since n.e.x.t
 */
class ConfigurationException extends AgentException
{
	/**
	 * Creates an exception for missing configuration key.
	 *
	 * @param string $key The missing key.
	 *
	 * @return self
	 */
	public static function missingKey(string $key): self
	{
		return new self(sprintf('Required configuration key "%s" is missing.', $key));
	}

	/**
	 * Creates an exception for invalid configuration value.
	 *
	 * @param string $key    The configuration key.
	 * @param string $reason The reason the value is invalid.
	 *
	 * @return self
	 */
	public static function invalidValue(string $key, string $reason): self
	{
		return new self(
			sprintf('Invalid configuration value for "%s": %s', $key, $reason)
		);
	}

	/**
	 * Creates an exception for file loading failures.
	 *
	 * @param string          $path     The file path.
	 * @param string          $reason   The reason for the failure.
	 * @param \Throwable|null $previous Optional previous exception.
	 *
	 * @return self
	 */
	public static function fileLoadFailed(string $path, string $reason, ?\Throwable $previous = null): self
	{
		return new self(
			sprintf('Failed to load configuration from "%s": %s', $path, $reason),
			0,
			$previous
		);
	}

	/**
	 * Creates an exception for missing environment variable.
	 *
	 * @param string $variable_name The environment variable name.
	 *
	 * @return self
	 */
	public static function missingEnvironmentVariable(string $variable_name): self
	{
		return new self(
			sprintf(
				'Environment variable "%s" is not set. '
				. 'Please set it in your environment or .env file.',
				$variable_name
			)
		);
	}

	/**
	 * Creates an exception for JSON parsing errors.
	 *
	 * @param string $path    The file path.
	 * @param string $message The JSON error message.
	 *
	 * @return self
	 *
	 * @since n.e.x.t
	 */
	public static function jsonParseError(string $path, string $message): self
	{
		return new self(
			sprintf('Failed to parse JSON configuration file "%s": %s', $path, $message)
		);
	}

	/**
	 * Creates an exception for schema validation type errors.
	 *
	 * @param string $property      The property path (e.g., "provider.max_tokens").
	 * @param string $expected_type The expected type.
	 * @param string $actual_type   The actual type of the value.
	 *
	 * @return self
	 *
	 * @since n.e.x.t
	 */
	public static function schemaTypeError(string $property, string $expected_type, string $actual_type): self
	{
		return new self(
			sprintf(
				'Invalid configuration: "%s" must be %s, got %s',
				$property,
				$expected_type,
				$actual_type
			)
		);
	}

	/**
	 * Creates an exception for schema validation minimum value errors.
	 *
	 * @param string    $property The property path (e.g., "max_turns").
	 * @param int|float $minimum  The minimum allowed value.
	 * @param int|float $actual   The actual value.
	 *
	 * @return self
	 *
	 * @since n.e.x.t
	 */
	public static function schemaMinimumError(string $property, int|float $minimum, int|float $actual): self
	{
		return new self(
			sprintf(
				'Invalid configuration: "%s" must have minimum value of %s, got %s',
				$property,
				(string) $minimum,
				(string) $actual
			)
		);
	}
}
