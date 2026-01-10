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
}
