<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\Configuration;

use Automattic\WpAiAgent\Core\Exceptions\ConfigurationException;

/**
 * Loads configuration from JSON settings file in .wp-ai-agent folder.
 *
 * Supports loading from `.wp-ai-agent/settings.json` in the working directory.
 * Environment variables using ${VAR_NAME} syntax are expanded.
 * Returns default configuration if the file does not exist.
 * Validates configuration against JSON schema.
 *
 * @since 0.1.0
 */
final class JsonConfigurationLoader
{
	/**
	 * The settings file name.
	 */
	private const SETTINGS_FILE = 'settings.json';

	/**
	 * The configuration folder name.
	 */
	private const CONFIG_FOLDER = '.wp-ai-agent';

	/**
	 * The environment variable resolver.
	 *
	 * @var EnvConfigurationLoader
	 */
	private EnvConfigurationLoader $env_loader;

	/**
	 * The schema validator.
	 *
	 * @var SettingsSchemaValidator
	 */
	private SettingsSchemaValidator $validator;

	/**
	 * Creates a new JSON configuration loader.
	 *
	 * @param EnvConfigurationLoader|null     $env_loader Optional environment loader for testing.
	 * @param SettingsSchemaValidator|null $validator Optional schema validator for testing.
	 *
	 * @since 0.1.0
	 */
	public function __construct(
		?EnvConfigurationLoader $env_loader = null,
		?SettingsSchemaValidator $validator = null
	) {
		$this->env_loader = $env_loader ?? new EnvConfigurationLoader();
		$this->validator = $validator ?? new SettingsSchemaValidator();
	}

	/**
	 * Loads configuration from the settings.json file.
	 *
	 * @param string|null $working_dir The working directory for config lookup.
	 *
	 * @return array<string, mixed> The merged configuration array.
	 *
	 * @throws ConfigurationException If the JSON file is invalid.
	 *
	 * @since 0.1.0
	 */
	public function load(?string $working_dir = null): array
	{
		$merged_config = $this->getDefaultConfiguration();

		$working_dir = $working_dir ?? getcwd();
		if ($working_dir === false) {
			return $merged_config;
		}

		$settings_path = $this->getSettingsPath($working_dir);

		if (file_exists($settings_path)) {
			$file_config = $this->loadJsonFile($settings_path);
			$this->validator->validate($file_config);
			$merged_config = $this->mergeConfigurations($merged_config, $file_config);
		}

		// Resolve environment variables in the final merged config
		$resolved_config = $this->env_loader->resolve($merged_config);

		// Expand tilde in path configurations
		return $this->expandPathConfigurations($resolved_config);
	}

	/**
	 * Returns the path to the settings.json file.
	 *
	 * @param string $working_dir The working directory.
	 *
	 * @return string The full path to the settings.json file.
	 *
	 * @since 0.1.0
	 */
	public function getSettingsPath(string $working_dir): string
	{
		return rtrim($working_dir, DIRECTORY_SEPARATOR)
			. DIRECTORY_SEPARATOR
			. self::CONFIG_FOLDER
			. DIRECTORY_SEPARATOR
			. self::SETTINGS_FILE;
	}

	/**
	 * Checks if the settings.json file exists.
	 *
	 * @param string|null $working_dir The working directory for config lookup.
	 *
	 * @return bool True if the file exists, false otherwise.
	 *
	 * @since 0.1.0
	 */
	public function fileExists(?string $working_dir = null): bool
	{
		$working_dir = $working_dir ?? getcwd();
		if ($working_dir === false) {
			return false;
		}

		return file_exists($this->getSettingsPath($working_dir));
	}

	/**
	 * Loads and parses a JSON file.
	 *
	 * @param string $path The path to the JSON file.
	 *
	 * @return array<string, mixed> The parsed configuration array.
	 *
	 * @throws ConfigurationException If the file cannot be parsed or is invalid.
	 *
	 * @since 0.1.0
	 */
	private function loadJsonFile(string $path): array
	{
		$content = file_get_contents($path);
		if ($content === false) {
			throw ConfigurationException::fileLoadFailed($path, 'Failed to read file contents');
		}

		$parsed = json_decode($content, true);
		$json_error = json_last_error();

		if ($json_error !== JSON_ERROR_NONE) {
			throw ConfigurationException::jsonParseError($path, json_last_error_msg());
		}

		// Empty object {} is valid, but arrays [1, 2] are not
		if (! is_array($parsed)) {
			throw ConfigurationException::fileLoadFailed(
				$path,
				'JSON file must contain an object at the root level'
			);
		}

		// Only reject if it's a non-empty indexed array
		if ($parsed !== [] && ! $this->isAssociativeArray($parsed)) {
			throw ConfigurationException::fileLoadFailed(
				$path,
				'JSON file must contain an object at the root level'
			);
		}

		return $parsed;
	}

	/**
	 * Returns the default configuration values.
	 *
	 * @return array<string, mixed> The default configuration.
	 */
	private function getDefaultConfiguration(): array
	{
		return [
			'provider' => [
				'type' => 'anthropic',
				'model' => 'claude-sonnet-4-20250514',
				'max_tokens' => 8192,
			],
			'mcp_servers' => [],
			'session_storage_path' => '~/.wp-ai-agent/sessions',
			'log_path' => '~/.wp-ai-agent/logs',
			'max_turns' => 100,
			'default_system_prompt' => '',
			'debug' => false,
			'streaming' => true,
			'auto_confirm' => false,
			'permissions' => [
				'allow' => [],
				'deny' => [],
			],
		];
	}

	/**
	 * Merges two configuration arrays with deep merge for nested arrays.
	 *
	 * @param array<string, mixed> $base     The base configuration.
	 * @param array<string, mixed> $override The overriding configuration.
	 *
	 * @return array<string, mixed> The merged configuration.
	 */
	private function mergeConfigurations(array $base, array $override): array
	{
		$merged = $base;

		foreach ($override as $key => $value) {
			if (
				is_array($value)
				&& isset($merged[$key])
				&& is_array($merged[$key])
				&& $this->isAssociativeArray($value)
			) {
				// Deep merge for associative arrays
				$merged[$key] = $this->mergeConfigurations($merged[$key], $value);
			} else {
				// Replace for scalar values and indexed arrays
				$merged[$key] = $value;
			}
		}

		return $merged;
	}

	/**
	 * Checks if an array is associative.
	 *
	 * @param array<mixed> $array The array to check.
	 *
	 * @return bool True if associative, false if indexed.
	 */
	private function isAssociativeArray(array $array): bool
	{
		if ([] === $array) {
			return false;
		}

		return array_keys($array) !== range(0, count($array) - 1);
	}

	/**
	 * Expands tilde in path configuration values.
	 *
	 * @param array<string, mixed> $config The configuration array.
	 *
	 * @return array<string, mixed> The configuration with expanded paths.
	 */
	private function expandPathConfigurations(array $config): array
	{
		$path_keys = ['session_storage_path', 'log_path'];

		foreach ($path_keys as $key) {
			if (isset($config[$key]) && is_string($config[$key])) {
				$config[$key] = $this->env_loader->expandTilde($config[$key]);
			}
		}

		return $config;
	}
}
