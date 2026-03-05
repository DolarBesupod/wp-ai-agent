<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\Configuration;

use Automattic\WpAiAgent\Core\Configuration\McpServerConfiguration;
use Automattic\WpAiAgent\Core\Exceptions\ConfigurationException;

/**
 * Loads MCP server configuration from JSON file in .wp-ai-agent folder.
 *
 * Supports loading from `.wp-ai-agent/mcp.json` in the working directory.
 * Parses `mcpServers` object into array of McpServerConfiguration.
 * Supports both HTTP and stdio transport types.
 * Environment variables using ${VAR_NAME} syntax are expanded.
 * Returns empty array if the file does not exist.
 *
 * @since 0.1.0
 */
final class McpJsonLoader
{
	/**
	 * The MCP configuration file name.
	 */
	private const MCP_FILE = 'mcp.json';

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
	 * Creates a new MCP JSON loader.
	 *
	 * @param EnvConfigurationLoader|null $env_loader Optional environment loader for testing.
	 *
	 * @since 0.1.0
	 */
	public function __construct(?EnvConfigurationLoader $env_loader = null)
	{
		$this->env_loader = $env_loader ?? new EnvConfigurationLoader();
	}

	/**
	 * Loads MCP server configurations from the mcp.json file.
	 *
	 * @param string|null $working_dir The working directory for config lookup.
	 *
	 * @return array<McpServerConfiguration> Array of server configurations.
	 *
	 * @throws ConfigurationException If the JSON file is invalid.
	 *
	 * @since 0.1.0
	 */
	public function load(?string $working_dir = null): array
	{
		$working_dir = $working_dir ?? getcwd();
		if ($working_dir === false) {
			return [];
		}

		$mcp_path = $this->getMcpPath($working_dir);

		if (! file_exists($mcp_path)) {
			return [];
		}

		$raw_config = $this->loadJsonFile($mcp_path);

		// Resolve environment variables in the raw config
		$resolved_config = $this->env_loader->resolve($raw_config);

		return $this->parseServers($resolved_config);
	}

	/**
	 * Returns the path to the mcp.json file.
	 *
	 * @param string $working_dir The working directory.
	 *
	 * @return string The full path to the mcp.json file.
	 *
	 * @since 0.1.0
	 */
	public function getMcpPath(string $working_dir): string
	{
		return rtrim($working_dir, DIRECTORY_SEPARATOR)
			. DIRECTORY_SEPARATOR
			. self::CONFIG_FOLDER
			. DIRECTORY_SEPARATOR
			. self::MCP_FILE;
	}

	/**
	 * Checks if the mcp.json file exists.
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

		return file_exists($this->getMcpPath($working_dir));
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
	 * Parses server configurations from the resolved config.
	 *
	 * @param array<string, mixed> $config The resolved configuration array.
	 *
	 * @return array<McpServerConfiguration> Array of server configurations.
	 *
	 * @since 0.1.0
	 */
	private function parseServers(array $config): array
	{
		if (! isset($config['mcpServers']) || ! is_array($config['mcpServers'])) {
			return [];
		}

		$servers = [];

		foreach ($config['mcpServers'] as $name => $server_config) {
			if (! is_string($name) || ! is_array($server_config)) {
				continue;
			}

			$servers[] = McpServerConfiguration::fromArray($name, $server_config);
		}

		return $servers;
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
}
