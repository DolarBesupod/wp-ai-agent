<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\Configuration;

use Automattic\WpAiAgent\Core\Exceptions\ConfigurationException;

/**
 * Resolves environment variable placeholders in configuration values.
 *
 * Supports ${ENV_VAR} syntax for environment variable resolution and
 * ~ (tilde) expansion for home directory paths.
 *
 * @since 0.1.0
 */
final class EnvConfigurationLoader
{
	/**
	 * The regex pattern to match ${ENV_VAR} placeholders.
	 */
	private const ENV_VAR_PATTERN = '/\$\{([A-Z_][A-Z0-9_]*)\}/';

	/**
	 * Whether to throw on missing environment variables.
	 *
	 * @var bool
	 */
	private bool $strict_mode;

	/**
	 * Custom environment variable provider for testing.
	 *
	 * @var callable|null
	 */
	private $env_provider;

	/**
	 * Creates a new environment configuration loader.
	 *
	 * @param bool $strict_mode Whether to throw on missing environment variables.
	 * @param callable|null $env_provider Custom environment variable provider (for testing).
	 */
	public function __construct(bool $strict_mode = true, ?callable $env_provider = null)
	{
		$this->strict_mode = $strict_mode;
		$this->env_provider = $env_provider;
	}

	/**
	 * Resolves all environment variable placeholders in a configuration array.
	 *
	 * @param array<string, mixed> $config The configuration array to resolve.
	 *
	 * @return array<string, mixed> The resolved configuration array.
	 *
	 * @throws ConfigurationException If a required environment variable is missing (in strict mode).
	 */
	public function resolve(array $config): array
	{
		return $this->resolveRecursive($config);
	}

	/**
	 * Resolves a single string value with environment variable placeholders.
	 *
	 * @param string $value The value to resolve.
	 *
	 * @return string The resolved value.
	 *
	 * @throws ConfigurationException If a required environment variable is missing (in strict mode).
	 */
	public function resolveString(string $value): string
	{
		return $this->resolveValue($value);
	}

	/**
	 * Expands tilde (~) to the user's home directory.
	 *
	 * @param string $path The path to expand.
	 *
	 * @return string The expanded path.
	 */
	public function expandTilde(string $path): string
	{
		if (strpos($path, '~') !== 0) {
			return $path;
		}

		$home = $this->getEnv('HOME');
		if ($home === null) {
			$home = $this->getEnv('USERPROFILE');
		}

		if ($home === null) {
			return $path;
		}

		return $home . substr($path, 1);
	}

	/**
	 * Gets an environment variable value.
	 *
	 * @param string $name The environment variable name.
	 *
	 * @return string|null The value or null if not set.
	 */
	private function getEnv(string $name): ?string
	{
		if ($this->env_provider !== null) {
			$value = ($this->env_provider)($name);
			return $value === false ? null : $value;
		}

		$value = getenv($name);
		return $value === false ? null : $value;
	}

	/**
	 * Recursively resolves environment variables in a configuration array.
	 *
	 * @param array<string, mixed> $config The configuration array.
	 *
	 * @return array<string, mixed> The resolved configuration.
	 *
	 * @throws ConfigurationException If a required environment variable is missing.
	 */
	private function resolveRecursive(array $config): array
	{
		$resolved = [];

		foreach ($config as $key => $value) {
			if (is_array($value)) {
				$resolved[$key] = $this->resolveRecursive($value);
			} elseif (is_string($value)) {
				$resolved[$key] = $this->resolveValue($value);
			} else {
				$resolved[$key] = $value;
			}
		}

		return $resolved;
	}

	/**
	 * Resolves environment variable placeholders in a string value.
	 *
	 * @param string $value The value to resolve.
	 *
	 * @return string The resolved value.
	 *
	 * @throws ConfigurationException If a required environment variable is missing.
	 */
	private function resolveValue(string $value): string
	{
		$result = preg_replace_callback(
			self::ENV_VAR_PATTERN,
			function (array $matches): string {
				$var_name = $matches[1];
				$env_value = $this->getEnv($var_name);

				if ($env_value === null) {
					if ($this->strict_mode) {
						throw ConfigurationException::missingEnvironmentVariable($var_name);
					}
					return $matches[0];
				}

				return $env_value;
			},
			$value
		);

		return $result ?? $value;
	}
}
