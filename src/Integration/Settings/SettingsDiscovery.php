<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\Settings;

use Automattic\WpAiAgent\Core\Contracts\SettingsDiscoveryInterface;

/**
 * Discovers configuration files in .wp-ai-agent directories.
 *
 * Searches for configuration files in both project-level and user-level
 * .wp-ai-agent directories. Files discovered in the project directory override
 * files with the same name from the user directory.
 *
 * @since 0.1.0
 */
final class SettingsDiscovery implements SettingsDiscoveryInterface
{
	/**
	 * The name of the settings directory.
	 */
	private const SETTINGS_DIR = '.wp-ai-agent';

	/**
	 * The project root directory path.
	 *
	 * @var string
	 */
	private string $project_root;

	/**
	 * The user home directory path.
	 *
	 * @var string
	 */
	private string $user_home;

	/**
	 * Creates a new SettingsDiscovery instance.
	 *
	 * @param string      $project_root The project root directory path.
	 * @param string|null $user_home    The user home directory path. If null, uses HOME environment variable.
	 *
	 * @since 0.1.0
	 */
	public function __construct(string $project_root, ?string $user_home = null)
	{
		$this->project_root = $project_root;
		$this->user_home = $user_home ?? $this->resolveUserHome();
	}

	/**
	 * Discovers all files of a specific type.
	 *
	 * Searches for files in both user and project .wp-ai-agent directories.
	 * Files in the project directory override files with the same name
	 * from the user directory.
	 *
	 * @param string $type      The type of files to discover (commands, skills, agents).
	 * @param string $extension The file extension to filter by (default: md).
	 *
	 * @return array<string, string> Map of file name (without extension) to absolute file path.
	 *
	 * @since 0.1.0
	 */
	public function discover(string $type, string $extension = 'md'): array
	{
		$files = [];

		// First, discover user-level files (lower priority)
		$user_dir = $this->getUserSettingsPath() . '/' . $type;
		$files = $this->discoverInDirectory($user_dir, $extension, $files);

		// Then, discover project-level files (higher priority, overrides user)
		$project_settings_path = $this->getProjectSettingsPath();
		if ($project_settings_path !== null) {
			$project_dir = $project_settings_path . '/' . $type;
			$files = $this->discoverInDirectory($project_dir, $extension, $files);
		}

		return $files;
	}

	/**
	 * Gets the project .wp-ai-agent directory path.
	 *
	 * Returns the path to the project's .wp-ai-agent directory if it exists,
	 * or null if the directory does not exist.
	 *
	 * @return string|null The absolute path to the project .wp-ai-agent directory, or null.
	 *
	 * @since 0.1.0
	 */
	public function getProjectSettingsPath(): ?string
	{
		$path = $this->project_root . '/' . self::SETTINGS_DIR;

		return is_dir($path) ? $path : null;
	}

	/**
	 * Gets the user .wp-ai-agent directory path.
	 *
	 * Returns the path to the user's .wp-ai-agent directory (typically ~/.wp-ai-agent).
	 * The directory may or may not exist.
	 *
	 * @return string The absolute path to the user .wp-ai-agent directory.
	 *
	 * @since 0.1.0
	 */
	public function getUserSettingsPath(): string
	{
		return $this->user_home . '/' . self::SETTINGS_DIR;
	}

	/**
	 * Discovers files in a specific directory.
	 *
	 * @param string                $directory The directory to search.
	 * @param string                $extension The file extension to filter by.
	 * @param array<string, string> $files     The existing files map to merge into.
	 *
	 * @return array<string, string> The updated files map.
	 */
	private function discoverInDirectory(string $directory, string $extension, array $files): array
	{
		if (! is_dir($directory)) {
			return $files;
		}

		$items = scandir($directory);
		if ($items === false) {
			return $files;
		}

		$extension_lower = strtolower($extension);

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$full_path = $directory . '/' . $item;

			// Skip directories
			if (is_dir($full_path)) {
				continue;
			}

			// Check extension (case-insensitive)
			$item_extension = $this->getFileExtension($item);
			if (strtolower($item_extension) !== $extension_lower) {
				continue;
			}

			// Extract name without extension
			$name = $this->getFileNameWithoutExtension($item);

			// Add or override in the files map
			$files[$name] = $full_path;
		}

		return $files;
	}

	/**
	 * Gets the file extension from a filename.
	 *
	 * @param string $filename The filename.
	 *
	 * @return string The file extension (without the leading dot).
	 */
	private function getFileExtension(string $filename): string
	{
		$last_dot_pos = strrpos($filename, '.');

		if ($last_dot_pos === false || $last_dot_pos === 0) {
			return '';
		}

		return substr($filename, $last_dot_pos + 1);
	}

	/**
	 * Gets the filename without its extension.
	 *
	 * Handles filenames with multiple dots correctly by only removing
	 * the last extension.
	 *
	 * @param string $filename The filename.
	 *
	 * @return string The filename without the extension.
	 */
	private function getFileNameWithoutExtension(string $filename): string
	{
		$last_dot_pos = strrpos($filename, '.');

		if ($last_dot_pos === false || $last_dot_pos === 0) {
			return $filename;
		}

		return substr($filename, 0, $last_dot_pos);
	}

	/**
	 * Resolves the user home directory from environment.
	 *
	 * @return string The user home directory path.
	 */
	private function resolveUserHome(): string
	{
		$home = $_SERVER['HOME'] ?? $_ENV['HOME'] ?? getenv('HOME');

		if ($home === false || $home === '') {
			// Fallback for Windows
			$home = $_SERVER['USERPROFILE'] ?? $_ENV['USERPROFILE'] ?? getenv('USERPROFILE');

			if ($home === false || $home === '') {
				// Last resort fallback
				$home = '/tmp';
			}
		}

		return (string) $home;
	}
}
