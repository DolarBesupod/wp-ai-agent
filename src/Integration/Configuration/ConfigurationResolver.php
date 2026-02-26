<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\Configuration;

use WpAiAgent\Core\Configuration\McpServerConfiguration;
use WpAiAgent\Core\Contracts\ConfigurationInterface;
use WpAiAgent\Core\Exceptions\ConfigurationException;

/**
 * Resolves configuration from multiple sources with priority chain.
 *
 * Priority order (highest to lowest):
 * 1. Environment variables
 * 2. .wp-ai-agent/settings.json + .wp-ai-agent/mcp.json
 * 3. Built-in defaults
 *
 * @since n.e.x.t
 */
final class ConfigurationResolver
{
	/**
	 * The JSON configuration loader.
	 *
	 * @var JsonConfigurationLoader
	 */
	private JsonConfigurationLoader $json_loader;

	/**
	 * The MCP JSON loader.
	 *
	 * @var McpJsonLoader
	 */
	private McpJsonLoader $mcp_loader;

	/**
	 * Custom environment variable provider for testing.
	 *
	 * @var callable|null
	 */
	private $env_provider;

	/**
	 * Configuration sources tracking.
	 *
	 * @var array<string, string>
	 */
	private array $configuration_sources = [];

	/**
	 * Creates a new configuration resolver.
	 *
	 * @param JsonConfigurationLoader|null $json_loader  Optional JSON loader for testing.
	 * @param McpJsonLoader|null           $mcp_loader   Optional MCP loader for testing.
	 * @param callable|null                $env_provider Optional env provider for testing.
	 *
	 * @since n.e.x.t
	 */
	public function __construct(
		?JsonConfigurationLoader $json_loader = null,
		?McpJsonLoader $mcp_loader = null,
		?callable $env_provider = null
	) {
		$this->json_loader = $json_loader ?? new JsonConfigurationLoader();
		$this->mcp_loader = $mcp_loader ?? new McpJsonLoader();
		$this->env_provider = $env_provider;
	}

	/**
	 * Resolves the configuration from all sources.
	 *
	 * @param string|null $working_dir The working directory for config lookup.
	 *
	 * @return ConfigurationInterface The resolved configuration.
	 *
	 * @throws ConfigurationException If configuration is invalid.
	 *
	 * @since n.e.x.t
	 */
	public function resolve(?string $working_dir = null): ConfigurationInterface
	{
		$working_dir = $working_dir ?? getcwd();
		if ($working_dir === false) {
			$working_dir = '.';
		}

		// Reset sources tracking
		$this->configuration_sources = [];

		// Start with defaults
		$config = $this->getDefaultConfiguration();
		$this->trackSources($config, 'default');

		// Load settings.json if it exists
		if ($this->json_loader->fileExists($working_dir)) {
			$json_config = $this->json_loader->load($working_dir);
			$config = $this->mergeConfigurations($config, $json_config);
			$this->trackSources($json_config, 'json');
		}

		// Apply environment variable overrides (highest priority)
		$config = $this->applyEnvironmentOverrides($config);

		return $this->createConfiguration($config, $working_dir);
	}

	/**
	 * Gets MCP server configurations from all sources.
	 *
	 * @param string|null $working_dir The working directory for config lookup.
	 *
	 * @return array<McpServerConfiguration> Array of MCP server configurations.
	 *
	 * @since n.e.x.t
	 */
	public function getMcpServers(?string $working_dir = null): array
	{
		$working_dir = $working_dir ?? getcwd();
		if ($working_dir === false) {
			$working_dir = '.';
		}

		$servers_by_name = [];

		// Load servers from mcp.json
		$json_servers = $this->mcp_loader->load($working_dir);
		foreach ($json_servers as $server) {
			if ($server->isEnabled()) {
				$servers_by_name[$server->getName()] = $server;
			}
		}

		return array_values($servers_by_name);
	}

	/**
	 * Gets the configuration sources tracking.
	 *
	 * @return array<string, string> Map of config key to source (env, json, default).
	 *
	 * @since n.e.x.t
	 */
	public function getConfigurationSources(): array
	{
		return $this->configuration_sources;
	}

	/**
	 * Gets an environment variable value.
	 *
	 * @param string $name The environment variable name.
	 *
	 * @return string|false The value or false if not set.
	 */
	private function getEnv(string $name): string|false
	{
		if ($this->env_provider !== null) {
			return ($this->env_provider)($name);
		}

		return getenv($name);
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
			'session_storage_path' => '.wp-ai-agent/sessions',
			'log_path' => '~/.wp-ai-agent/logs',
			'max_turns' => 100,
			'default_system_prompt' => '',
			'bypass_confirmation_tools' => ['think'],
			'debug' => false,
			'streaming' => true,
			'temperature' => 0.7,
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
	 * Applies environment variable overrides to the configuration.
	 *
	 * @param array<string, mixed> $config The configuration array.
	 *
	 * @return array<string, mixed> The configuration with env overrides applied.
	 */
	private function applyEnvironmentOverrides(array $config): array
	{
		// ANTHROPIC_API_KEY
		$api_key = $this->getEnv('ANTHROPIC_API_KEY');
		if ($api_key !== false && $api_key !== '') {
			$config['provider']['api_key'] = $api_key;
			$this->configuration_sources['api_key'] = 'env';
		}

		// AGENT_MODEL
		$model = $this->getEnv('AGENT_MODEL');
		if ($model !== false && $model !== '') {
			$config['provider']['model'] = $model;
			$this->configuration_sources['model'] = 'env';
		}

		// AGENT_MAX_TOKENS
		$max_tokens = $this->getEnv('AGENT_MAX_TOKENS');
		if ($max_tokens !== false && $max_tokens !== '') {
			$config['provider']['max_tokens'] = (int) $max_tokens;
			$this->configuration_sources['max_tokens'] = 'env';
		}

		// AGENT_MAX_ITERATIONS
		$max_iterations = $this->getEnv('AGENT_MAX_ITERATIONS');
		if ($max_iterations !== false && $max_iterations !== '') {
			$config['max_turns'] = (int) $max_iterations;
			$this->configuration_sources['max_iterations'] = 'env';
		}

		// AGENT_DEBUG
		$debug = $this->getEnv('AGENT_DEBUG');
		if ($debug !== false && $debug !== '') {
			$config['debug'] = (bool) $debug;
			$this->configuration_sources['debug'] = 'env';
		}

		// AGENT_STREAMING
		$streaming = $this->getEnv('AGENT_STREAMING');
		if ($streaming !== false && $streaming !== '') {
			$config['streaming'] = (bool) $streaming;
			$this->configuration_sources['streaming'] = 'env';
		}

		// AGENT_TEMPERATURE
		$temperature = $this->getEnv('AGENT_TEMPERATURE');
		if ($temperature !== false && $temperature !== '') {
			$config['temperature'] = (float) $temperature;
			$this->configuration_sources['temperature'] = 'env';
		}

		// AGENT_SESSION_PATH
		$session_path = $this->getEnv('AGENT_SESSION_PATH');
		if ($session_path !== false && $session_path !== '') {
			$config['session_storage_path'] = $session_path;
			$this->configuration_sources['session_storage_path'] = 'env';
		}

		return $config;
	}

	/**
	 * Tracks the source of configuration values.
	 *
	 * This method updates the source tracker when a higher priority source
	 * provides a configuration value, overwriting lower priority sources.
	 *
	 * @param array<string, mixed> $config The configuration array.
	 * @param string               $source The source identifier.
	 *
	 * @return void
	 */
	private function trackSources(array $config, string $source): void
	{
		foreach ($config as $key => $value) {
			if ($key === 'provider' && is_array($value)) {
				foreach (array_keys($value) as $subkey) {
					// Always update to the latest source that provides the value
					$this->configuration_sources[$subkey] = $source;
				}
			} else {
				// Always update to the latest source that provides the value
				$this->configuration_sources[$key] = $source;
			}
		}
	}

	/**
	 * Creates a configuration object from the resolved configuration array.
	 *
	 * @param array<string, mixed> $config      The configuration array.
	 * @param string               $working_dir The working directory for relative paths.
	 *
	 * @return ConfigurationInterface The configuration object.
	 */
	private function createConfiguration(array $config, string $working_dir): ConfigurationInterface
	{
		// Expand tilde in paths
		$home = $this->getEnv('HOME');
		if ($home === false) {
			$home = $this->getEnv('USERPROFILE');
		}

		// Process session_storage_path
		if (isset($config['session_storage_path']) && is_string($config['session_storage_path'])) {
			$path = $config['session_storage_path'];
			if ($home !== false && $home !== '' && strpos($path, '~') === 0) {
				$config['session_storage_path'] = $home . substr($path, 1);
			} elseif (strpos($path, '/') !== 0 && strpos($path, '~') !== 0) {
				// Relative path - make it absolute based on working directory
				$config['session_storage_path'] = rtrim($working_dir, '/') . '/' . $path;
			}
		}

		// Process log_path
		if (isset($config['log_path']) && is_string($config['log_path'])) {
			$path = $config['log_path'];
			if ($home !== false && $home !== '' && strpos($path, '~') === 0) {
				$config['log_path'] = $home . substr($path, 1);
			} elseif (strpos($path, '/') !== 0 && strpos($path, '~') !== 0) {
				// Relative path - make it absolute based on working directory
				$config['log_path'] = rtrim($working_dir, '/') . '/' . $path;
			}
		}

		return new ResolvedConfiguration($config);
	}
}
