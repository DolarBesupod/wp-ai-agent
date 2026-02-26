<?php

declare(strict_types=1);

namespace WpAiAgent\Core\Contracts;

/**
 * Interface for discovering configuration files in .wp-ai-agent directories.
 *
 * The discovery service finds configuration files (commands, skills, agents)
 * in both project-level and user-level .wp-ai-agent directories. Files discovered
 * in the project directory override files with the same name from the user
 * directory.
 *
 * Directory structure:
 * - Project: {project_root}/.wp-ai-agent/{type}/
 * - User: ~/.wp-ai-agent/{type}/
 *
 * @since n.e.x.t
 */
interface SettingsDiscoveryInterface
{
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
	 */
	public function discover(string $type, string $extension = 'md'): array;

	/**
	 * Gets the project .wp-ai-agent directory path.
	 *
	 * Returns the path to the project's .wp-ai-agent directory if it exists,
	 * or null if the directory does not exist.
	 *
	 * @return string|null The absolute path to the project .wp-ai-agent directory, or null.
	 */
	public function getProjectSettingsPath(): ?string;

	/**
	 * Gets the user .wp-ai-agent directory path.
	 *
	 * Returns the path to the user's .wp-ai-agent directory (typically ~/.wp-ai-agent).
	 * The directory may or may not exist.
	 *
	 * @return string The absolute path to the user .wp-ai-agent directory.
	 */
	public function getUserSettingsPath(): string;
}
