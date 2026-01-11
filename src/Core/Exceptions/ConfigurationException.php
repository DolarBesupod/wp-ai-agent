<?php

declare(strict_types=1);

namespace PhpCliAgent\Core\Exceptions;

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
	 * Creates an exception for YAML parsing errors.
	 *
	 * @param string          $path     The file path.
	 * @param \Throwable|null $previous The parsing exception.
	 *
	 * @return self
	 */
	public static function yamlParseError(string $path, ?\Throwable $previous = null): self
	{
		$message = $previous !== null
			? $previous->getMessage()
			: 'Unknown parsing error';

		return new self(
			sprintf('Failed to parse YAML configuration file "%s": %s', $path, $message),
			0,
			$previous
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
}
