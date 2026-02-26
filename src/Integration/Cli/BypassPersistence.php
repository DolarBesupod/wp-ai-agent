<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\Cli;

use WpAiAgent\Integration\Configuration\SettingsWriter;

/**
 * Persists tool bypass state to settings.json permissions.allow.
 *
 * Stores runtime bypass additions (from user entering "a" at confirmation prompts)
 * so they persist across sessions. The state is stored in the permissions.allow
 * array of the .wp-ai-agent/settings.json file in the working directory.
 *
 * @since n.e.x.t
 */
final class BypassPersistence
{
	/**
	 * Legacy state file name (for migration).
	 *
	 * @var string
	 */
	private const LEGACY_STATE_FILE = 'bypass_state.json';

	/**
	 * Default folder name for settings storage.
	 *
	 * @var string
	 */
	private const DEFAULT_FOLDER_NAME = '.wp-ai-agent';

	/**
	 * The settings writer for persisting to settings.json.
	 *
	 * @var SettingsWriter
	 */
	private SettingsWriter $settings_writer;

	/**
	 * The working directory.
	 *
	 * @var string
	 */
	private string $working_dir;

	/**
	 * Creates a new BypassPersistence instance.
	 *
	 * @param SettingsWriter $settings_writer The settings writer.
	 * @param string         $working_dir     The working directory.
	 *
	 * @since n.e.x.t
	 */
	public function __construct(SettingsWriter $settings_writer, string $working_dir)
	{
		$this->settings_writer = $settings_writer;
		$this->working_dir = $working_dir;
	}

	/**
	 * Creates a BypassPersistence instance for a working directory.
	 *
	 * This factory method creates the persistence instance and handles
	 * migration from the legacy bypass_state.json file if it exists.
	 *
	 * @param string      $working_dir      The working directory path.
	 * @param string|null $old_session_path Optional old session storage path (unused, kept for compatibility).
	 *
	 * @return self The configured BypassPersistence instance.
	 *
	 * @since n.e.x.t
	 */
	public static function forWorkingDirectory(string $working_dir, ?string $old_session_path = null): self
	{
		$settings_writer = new SettingsWriter();
		$instance = new self($settings_writer, $working_dir);

		// Migrate from legacy bypass_state.json if it exists.
		$instance->migrateFromLegacyFile();

		return $instance;
	}

	/**
	 * Gets the default storage path for bypass state in a working directory.
	 *
	 * @param string $working_dir The working directory path.
	 *
	 * @return string The default path (.wp-ai-agent folder in working directory).
	 *
	 * @since n.e.x.t
	 */
	public static function getDefaultPath(string $working_dir): string
	{
		return rtrim($working_dir, DIRECTORY_SEPARATOR)
			. DIRECTORY_SEPARATOR
			. self::DEFAULT_FOLDER_NAME;
	}

	/**
	 * Gets the path to the legacy bypass_state.json file.
	 *
	 * @param string $working_dir The working directory path.
	 *
	 * @return string The path to the legacy file.
	 *
	 * @since n.e.x.t
	 */
	public static function getLegacyFilePath(string $working_dir): string
	{
		return self::getDefaultPath($working_dir)
			. DIRECTORY_SEPARATOR
			. self::LEGACY_STATE_FILE;
	}

	/**
	 * Loads bypassed tool names from settings.json permissions.allow.
	 *
	 * @return array<int, string> List of tool names that are bypassed.
	 *
	 * @since n.e.x.t
	 */
	public function load(): array
	{
		return $this->settings_writer->getAllowedTools($this->working_dir);
	}

	/**
	 * Saves bypassed tool names to settings.json permissions.allow.
	 *
	 * @param array<int, string> $tool_names List of tool names to persist.
	 *
	 * @return bool True if save succeeded, false otherwise.
	 *
	 * @since n.e.x.t
	 */
	public function save(array $tool_names): bool
	{
		// Clear existing and add all new tools.
		if (!$this->settings_writer->clearAllowedTools($this->working_dir)) {
			return false;
		}

		foreach ($tool_names as $tool_name) {
			if (!is_string($tool_name)) {
				continue;
			}
			if (!$this->settings_writer->addAllowedTool($tool_name, $this->working_dir)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Adds a tool to the persisted bypass list.
	 *
	 * @param string $tool_name The tool name to add.
	 *
	 * @return bool True if save succeeded, false otherwise.
	 *
	 * @since n.e.x.t
	 */
	public function addBypass(string $tool_name): bool
	{
		return $this->settings_writer->addAllowedTool(
			strtolower($tool_name),
			$this->working_dir
		);
	}

	/**
	 * Removes a tool from the persisted bypass list.
	 *
	 * @param string $tool_name The tool name to remove.
	 *
	 * @return bool True if save succeeded, false otherwise.
	 *
	 * @since n.e.x.t
	 */
	public function removeBypass(string $tool_name): bool
	{
		return $this->settings_writer->removeAllowedTool(
			strtolower($tool_name),
			$this->working_dir
		);
	}

	/**
	 * Clears all persisted bypasses.
	 *
	 * @return bool True if save succeeded, false otherwise.
	 *
	 * @since n.e.x.t
	 */
	public function clear(): bool
	{
		return $this->settings_writer->clearAllowedTools($this->working_dir);
	}

	/**
	 * Returns the path to the settings.json file.
	 *
	 * @return string
	 *
	 * @since n.e.x.t
	 */
	public function getSettingsFilePath(): string
	{
		return $this->settings_writer->getSettingsPath($this->working_dir);
	}

	/**
	 * Migrates bypass state from legacy bypass_state.json to settings.json.
	 *
	 * If the legacy state file exists, reads its contents, adds all tools to
	 * permissions.allow, and deletes the legacy file.
	 *
	 * @return bool True if migration occurred, false otherwise.
	 *
	 * @since n.e.x.t
	 */
	public function migrateFromLegacyFile(): bool
	{
		$legacy_file = self::getLegacyFilePath($this->working_dir);

		if (!file_exists($legacy_file)) {
			return false;
		}

		$content = file_get_contents($legacy_file);
		if ($content === false) {
			return false;
		}

		$data = json_decode($content, true);
		if (!is_array($data) || !isset($data['bypassed_tools'])) {
			// Invalid format, just delete the file.
			unlink($legacy_file);
			return false;
		}

		$legacy_tools = $data['bypassed_tools'];
		if (!is_array($legacy_tools)) {
			unlink($legacy_file);
			return false;
		}

		// Add each legacy tool to permissions.allow.
		foreach ($legacy_tools as $tool) {
			if (is_string($tool)) {
				$this->settings_writer->addAllowedTool(strtolower($tool), $this->working_dir);
			}
		}

		// Delete the legacy file after successful migration.
		unlink($legacy_file);

		return true;
	}
}
