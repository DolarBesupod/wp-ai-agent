<?php

declare(strict_types=1);

namespace PhpCliAgent\Integration\Cli;

/**
 * Persists tool bypass state to a JSON file.
 *
 * Stores runtime bypass additions (from user entering "a" at confirmation prompts)
 * so they persist across sessions. The state file is stored in the .php-cli-agent
 * folder in the working directory, separate from the user's configuration files.
 *
 * @since n.e.x.t
 */
final class BypassPersistence
{
	/**
	 * Default state file name.
	 *
	 * @var string
	 */
	private const STATE_FILE_NAME = 'bypass_state.json';

	/**
	 * Default folder name for bypass state storage.
	 *
	 * @var string
	 */
	private const DEFAULT_FOLDER_NAME = '.php-cli-agent';

	/**
	 * Path to the state file.
	 *
	 * @var string
	 */
	private string $state_file_path;

	/**
	 * Creates a new BypassPersistence instance.
	 *
	 * @param string $storage_path The directory to store the state file.
	 *
	 * @since n.e.x.t
	 */
	public function __construct(string $storage_path)
	{
		$expanded_path = $this->expandTilde($storage_path);
		$this->ensureDirectory($expanded_path);
		$this->state_file_path = rtrim($expanded_path, DIRECTORY_SEPARATOR)
			. DIRECTORY_SEPARATOR
			. self::STATE_FILE_NAME;
	}

	/**
	 * Creates a BypassPersistence instance for a working directory.
	 *
	 * This factory method creates the persistence instance with the bypass state
	 * stored in .php-cli-agent/bypass_state.json within the working directory.
	 * It also handles migration from an old session storage path if provided.
	 *
	 * @param string      $working_dir      The working directory path.
	 * @param string|null $old_session_path Optional old session storage path for migration.
	 *
	 * @return self The configured BypassPersistence instance.
	 *
	 * @since n.e.x.t
	 */
	public static function forWorkingDirectory(string $working_dir, ?string $old_session_path = null): self
	{
		$new_path = self::getDefaultPath($working_dir);
		$instance = new self($new_path);

		// Attempt migration from old location if needed.
		if ($old_session_path !== null) {
			$instance->migrateFromOldPath($old_session_path);
		}

		return $instance;
	}

	/**
	 * Gets the default storage path for bypass state in a working directory.
	 *
	 * @param string $working_dir The working directory path.
	 *
	 * @return string The default path (.php-cli-agent folder in working directory).
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
	 * Loads bypassed tool names from the state file.
	 *
	 * @return array<int, string> List of tool names that are bypassed.
	 */
	public function load(): array
	{
		if (!file_exists($this->state_file_path)) {
			return [];
		}

		$content = file_get_contents($this->state_file_path);
		if ($content === false) {
			return [];
		}

		$data = json_decode($content, true);
		if (!is_array($data)) {
			return [];
		}

		$bypasses = $data['bypassed_tools'] ?? [];
		if (!is_array($bypasses)) {
			return [];
		}

		return array_values(array_filter($bypasses, 'is_string'));
	}

	/**
	 * Saves bypassed tool names to the state file.
	 *
	 * @param array<int, string> $tool_names List of tool names to persist.
	 *
	 * @return bool True if save succeeded, false otherwise.
	 */
	public function save(array $tool_names): bool
	{
		$data = [
			'bypassed_tools' => array_values(array_unique($tool_names)),
			'updated_at' => date('c'),
		];

		$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			return false;
		}

		return file_put_contents($this->state_file_path, $json) !== false;
	}

	/**
	 * Adds a tool to the persisted bypass list.
	 *
	 * @param string $tool_name The tool name to add.
	 *
	 * @return bool True if save succeeded, false otherwise.
	 */
	public function addBypass(string $tool_name): bool
	{
		$current = $this->load();
		$normalized = strtolower($tool_name);

		if (!in_array($normalized, $current, true)) {
			$current[] = $normalized;
		}

		return $this->save($current);
	}

	/**
	 * Removes a tool from the persisted bypass list.
	 *
	 * @param string $tool_name The tool name to remove.
	 *
	 * @return bool True if save succeeded, false otherwise.
	 */
	public function removeBypass(string $tool_name): bool
	{
		$current = $this->load();
		$normalized = strtolower($tool_name);

		$filtered = array_filter(
			$current,
			static fn (string $name): bool => $name !== $normalized
		);

		return $this->save(array_values($filtered));
	}

	/**
	 * Clears all persisted bypasses.
	 *
	 * @return bool True if save succeeded, false otherwise.
	 */
	public function clear(): bool
	{
		return $this->save([]);
	}

	/**
	 * Returns the path to the state file.
	 *
	 * @return string
	 */
	public function getStatFilePath(): string
	{
		return $this->state_file_path;
	}

	/**
	 * Expands tilde to home directory in a path.
	 *
	 * @param string $path The path to expand.
	 *
	 * @return string The expanded path.
	 */
	private function expandTilde(string $path): string
	{
		if (str_starts_with($path, '~/')) {
			$home = getenv('HOME');
			if ($home !== false && $home !== '') {
				return $home . substr($path, 1);
			}
		}

		return $path;
	}

	/**
	 * Ensures the storage directory exists.
	 *
	 * @param string $path The directory path.
	 *
	 * @return void
	 */
	private function ensureDirectory(string $path): void
	{
		if (!is_dir($path)) {
			mkdir($path, 0755, true);
		}
	}

	/**
	 * Migrates bypass state from an old storage path to the current location.
	 *
	 * If the old state file exists and the new state file does not exist,
	 * this method copies the old file content to the new location and
	 * deletes the old file.
	 *
	 * @param string $old_path The old storage directory path.
	 *
	 * @return bool True if migration occurred, false otherwise.
	 *
	 * @since n.e.x.t
	 */
	private function migrateFromOldPath(string $old_path): bool
	{
		$expanded_old_path = $this->expandTilde($old_path);
		$old_state_file = rtrim($expanded_old_path, DIRECTORY_SEPARATOR)
			. DIRECTORY_SEPARATOR
			. self::STATE_FILE_NAME;

		// Only migrate if old file exists and new file does not exist.
		if (!file_exists($old_state_file) || file_exists($this->state_file_path)) {
			return false;
		}

		// Read old state file.
		$content = file_get_contents($old_state_file);
		if ($content === false) {
			return false;
		}

		// Write to new location.
		if (file_put_contents($this->state_file_path, $content) === false) {
			return false;
		}

		// Delete old file.
		unlink($old_state_file);

		return true;
	}
}
