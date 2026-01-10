<?php

declare(strict_types=1);

namespace PhpCliAgent\Integration\Configuration;

use PhpCliAgent\Core\Configuration\AgentConfiguration;
use PhpCliAgent\Core\Exceptions\ConfigurationException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads and merges configuration from YAML files.
 *
 * Supports loading from multiple sources with proper merging:
 * 1. Default configuration
 * 2. User configuration (~/.php-cli-agent/agent.yaml)
 * 3. Project configuration (./agent.yaml)
 * 4. Command-line override (--config=path)
 *
 * Later sources override earlier ones.
 *
 * @since n.e.x.t
 */
final class YamlConfigurationLoader
{
	/**
	 * The default user configuration path.
	 */
	private const USER_CONFIG_PATH = '~/.php-cli-agent/agent.yaml';

	/**
	 * The default project configuration file name.
	 */
	private const PROJECT_CONFIG_FILE = 'agent.yaml';

	/**
	 * The environment variable resolver.
	 *
	 * @var EnvConfigurationLoader
	 */
	private EnvConfigurationLoader $env_loader;

	/**
	 * Creates a new YAML configuration loader.
	 *
	 * @param EnvConfigurationLoader|null $env_loader Optional environment loader for testing.
	 */
	public function __construct(?EnvConfigurationLoader $env_loader = null)
	{
		$this->env_loader = $env_loader ?? new EnvConfigurationLoader();
	}

	/**
	 * Loads configuration from multiple sources.
	 *
	 * @param string|null $config_path    Optional path to a specific configuration file.
	 * @param string|null $working_dir    The working directory for project config lookup.
	 * @param bool        $skip_user_config Whether to skip loading user configuration.
	 *
	 * @return AgentConfiguration The merged configuration.
	 *
	 * @throws ConfigurationException If configuration loading fails.
	 */
	public function load(
		?string $config_path = null,
		?string $working_dir = null,
		bool $skip_user_config = false
	): AgentConfiguration {
		$merged_config = $this->getDefaultConfiguration();

		// Load user configuration (~/.php-cli-agent/agent.yaml)
		if (! $skip_user_config) {
			$user_config_path = $this->env_loader->expandTilde(self::USER_CONFIG_PATH);
			if (file_exists($user_config_path)) {
				$user_config = $this->loadYamlFile($user_config_path);
				$merged_config = $this->mergeConfigurations($merged_config, $user_config);
			}
		}

		// Load project configuration (./agent.yaml)
		$working_dir = $working_dir ?? getcwd();
		if ($working_dir !== false) {
			$project_config_path = rtrim($working_dir, DIRECTORY_SEPARATOR)
				. DIRECTORY_SEPARATOR
				. self::PROJECT_CONFIG_FILE;

			if (file_exists($project_config_path)) {
				$project_config = $this->loadYamlFile($project_config_path);
				$merged_config = $this->mergeConfigurations($merged_config, $project_config);
			}
		}

		// Load explicit configuration path (--config=path override)
		if ($config_path !== null) {
			$expanded_path = $this->env_loader->expandTilde($config_path);

			if (! file_exists($expanded_path)) {
				throw ConfigurationException::fileLoadFailed(
					$config_path,
					'File does not exist'
				);
			}

			$explicit_config = $this->loadYamlFile($expanded_path);
			$merged_config = $this->mergeConfigurations($merged_config, $explicit_config);
		}

		// Resolve environment variables in the final merged config
		$resolved_config = $this->env_loader->resolve($merged_config);

		// Expand tilde in path configurations
		$resolved_config = $this->expandPathConfigurations($resolved_config);

		return AgentConfiguration::fromArray($resolved_config);
	}

	/**
	 * Loads a configuration array from a YAML file.
	 *
	 * @param string $path The path to the YAML file.
	 *
	 * @return array<string, mixed> The parsed configuration array.
	 *
	 * @throws ConfigurationException If the file cannot be parsed.
	 */
	public function loadYamlFile(string $path): array
	{
		$expanded_path = $this->env_loader->expandTilde($path);

		if (! file_exists($expanded_path)) {
			throw ConfigurationException::fileLoadFailed($path, 'File does not exist');
		}

		if (! is_readable($expanded_path)) {
			throw ConfigurationException::fileLoadFailed($path, 'File is not readable');
		}

		$content = file_get_contents($expanded_path);
		if ($content === false) {
			throw ConfigurationException::fileLoadFailed($path, 'Failed to read file contents');
		}

		try {
			$parsed = Yaml::parse($content);
		} catch (ParseException $exception) {
			throw ConfigurationException::yamlParseError($path, $exception);
		}

		if (! is_array($parsed) || ! $this->isAssociativeArray($parsed)) {
			throw ConfigurationException::fileLoadFailed(
				$path,
				'YAML file must contain a mapping at the root level'
			);
		}

		return $parsed;
	}

	/**
	 * Gets the list of configuration file paths that would be checked.
	 *
	 * @param string|null $working_dir The working directory.
	 *
	 * @return array<string> List of potential configuration paths.
	 */
	public function getConfigSearchPaths(?string $working_dir = null): array
	{
		$paths = [];

		// User config
		$paths[] = $this->env_loader->expandTilde(self::USER_CONFIG_PATH);

		// Project config
		$working_dir = $working_dir ?? getcwd();
		if ($working_dir !== false) {
			$paths[] = rtrim($working_dir, DIRECTORY_SEPARATOR)
				. DIRECTORY_SEPARATOR
				. self::PROJECT_CONFIG_FILE;
		}

		return $paths;
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
			'session_storage_path' => '~/.php-cli-agent/sessions',
			'log_path' => '~/.php-cli-agent/logs',
			'max_turns' => 100,
			'default_system_prompt' => '',
			'bypass_confirmation_tools' => ['think'],
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
