<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\Configuration;

/**
 * Writes updates to the settings.json file.
 *
 * Handles atomic writes to the permissions section while preserving
 * existing structure and formatting.
 *
 * @since n.e.x.t
 */
final class SettingsWriter
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
	 * Adds a tool to the permissions.allow list.
	 *
	 * Creates the settings file if it does not exist.
	 *
	 * @param string      $tool_name   The tool name to add.
	 * @param string|null $working_dir The working directory for config lookup.
	 *
	 * @return bool True if the operation succeeded, false otherwise.
	 *
	 * @since n.e.x.t
	 */
	public function addAllowedTool(string $tool_name, ?string $working_dir = null): bool
	{
		$working_dir = $this->resolveWorkingDir($working_dir);
		if ($working_dir === null) {
			return false;
		}

		$config = $this->loadCurrentSettings($working_dir);
		$normalized_tool = strtolower($tool_name);

		// Initialize permissions structure if needed.
		if (!isset($config['permissions'])) {
			$config['permissions'] = [];
		}
		if (!isset($config['permissions']['allow']) || !is_array($config['permissions']['allow'])) {
			$config['permissions']['allow'] = [];
		}

		// Check if tool already exists.
		$existing_tools = array_map('strtolower', $config['permissions']['allow']);
		if (in_array($normalized_tool, $existing_tools, true)) {
			return true;
		}

		// Add the tool.
		$config['permissions']['allow'][] = $normalized_tool;

		return $this->writeSettings($config, $working_dir);
	}

	/**
	 * Removes a tool from the permissions.allow list.
	 *
	 * @param string      $tool_name   The tool name to remove.
	 * @param string|null $working_dir The working directory for config lookup.
	 *
	 * @return bool True if the operation succeeded, false otherwise.
	 *
	 * @since n.e.x.t
	 */
	public function removeAllowedTool(string $tool_name, ?string $working_dir = null): bool
	{
		$working_dir = $this->resolveWorkingDir($working_dir);
		if ($working_dir === null) {
			return false;
		}

		$settings_path = $this->getSettingsPath($working_dir);
		if (!file_exists($settings_path)) {
			return true;
		}

		$config = $this->loadCurrentSettings($working_dir);
		$normalized_tool = strtolower($tool_name);

		if (!isset($config['permissions']['allow']) || !is_array($config['permissions']['allow'])) {
			return true;
		}

		// Filter out the tool (case-insensitive).
		$config['permissions']['allow'] = array_values(
			array_filter(
				$config['permissions']['allow'],
				static fn ($t): bool => is_string($t) && strtolower($t) !== $normalized_tool
			)
		);

		return $this->writeSettings($config, $working_dir);
	}

	/**
	 * Gets all tools from the permissions.allow list.
	 *
	 * @param string|null $working_dir The working directory for config lookup.
	 *
	 * @return array<int, string> List of allowed tools.
	 *
	 * @since n.e.x.t
	 */
	public function getAllowedTools(?string $working_dir = null): array
	{
		$working_dir = $this->resolveWorkingDir($working_dir);
		if ($working_dir === null) {
			return [];
		}

		$config = $this->loadCurrentSettings($working_dir);

		if (!isset($config['permissions']['allow']) || !is_array($config['permissions']['allow'])) {
			return [];
		}

		return array_values(array_filter($config['permissions']['allow'], 'is_string'));
	}

	/**
	 * Clears all tools from the permissions.allow list.
	 *
	 * @param string|null $working_dir The working directory for config lookup.
	 *
	 * @return bool True if the operation succeeded, false otherwise.
	 *
	 * @since n.e.x.t
	 */
	public function clearAllowedTools(?string $working_dir = null): bool
	{
		$working_dir = $this->resolveWorkingDir($working_dir);
		if ($working_dir === null) {
			return false;
		}

		$settings_path = $this->getSettingsPath($working_dir);
		if (!file_exists($settings_path)) {
			return true;
		}

		$config = $this->loadCurrentSettings($working_dir);

		if (!isset($config['permissions'])) {
			return true;
		}

		$config['permissions']['allow'] = [];

		return $this->writeSettings($config, $working_dir);
	}

	/**
	 * Gets the path to the settings.json file.
	 *
	 * @param string $working_dir The working directory.
	 *
	 * @return string The full path to the settings.json file.
	 *
	 * @since n.e.x.t
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
	 * Gets the path to the config folder.
	 *
	 * @param string $working_dir The working directory.
	 *
	 * @return string The full path to the config folder.
	 *
	 * @since n.e.x.t
	 */
	public function getConfigFolderPath(string $working_dir): string
	{
		return rtrim($working_dir, DIRECTORY_SEPARATOR)
			. DIRECTORY_SEPARATOR
			. self::CONFIG_FOLDER;
	}

	/**
	 * Resolves the working directory.
	 *
	 * @param string|null $working_dir The working directory or null for current.
	 *
	 * @return string|null The resolved working directory or null on failure.
	 */
	private function resolveWorkingDir(?string $working_dir): ?string
	{
		if ($working_dir !== null) {
			return $working_dir;
		}

		$cwd = getcwd();
		return $cwd === false ? null : $cwd;
	}

	/**
	 * Loads current settings from the settings.json file.
	 *
	 * Returns an empty array if the file does not exist or is invalid.
	 *
	 * @param string $working_dir The working directory.
	 *
	 * @return array<string, mixed> The current settings.
	 */
	private function loadCurrentSettings(string $working_dir): array
	{
		$settings_path = $this->getSettingsPath($working_dir);

		if (!file_exists($settings_path)) {
			return [];
		}

		$content = file_get_contents($settings_path);
		if ($content === false) {
			return [];
		}

		$config = json_decode($content, true);
		if (!is_array($config)) {
			return [];
		}

		return $config;
	}

	/**
	 * Writes settings to the settings.json file.
	 *
	 * Creates the config folder if it does not exist.
	 *
	 * @param array<string, mixed> $config      The configuration to write.
	 * @param string               $working_dir The working directory.
	 *
	 * @return bool True if the write succeeded, false otherwise.
	 */
	private function writeSettings(array $config, string $working_dir): bool
	{
		$config_folder = $this->getConfigFolderPath($working_dir);

		// Create directory if it does not exist.
		if (!is_dir($config_folder)) {
			if (!mkdir($config_folder, 0755, true)) {
				return false;
			}
		}

		$settings_path = $this->getSettingsPath($working_dir);
		$json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

		if ($json === false) {
			return false;
		}

		return file_put_contents($settings_path, $json . "\n") !== false;
	}
}
