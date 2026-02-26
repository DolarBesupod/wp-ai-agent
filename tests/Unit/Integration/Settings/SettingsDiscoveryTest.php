<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Integration\Settings;

use WpAiAgent\Core\Contracts\SettingsDiscoveryInterface;
use WpAiAgent\Integration\Settings\SettingsDiscovery;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SettingsDiscovery.
 *
 * @covers \WpAiAgent\Integration\Settings\SettingsDiscovery
 */
final class SettingsDiscoveryTest extends TestCase
{
	/**
	 * Temporary directory for test files.
	 *
	 * @var string
	 */
	private string $temp_dir;

	/**
	 * Mock project directory.
	 *
	 * @var string
	 */
	private string $project_dir;

	/**
	 * Mock user home directory.
	 *
	 * @var string
	 */
	private string $user_home;

	/**
	 * Sets up the test fixture.
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->temp_dir = sys_get_temp_dir() . '/settings_discovery_test_' . uniqid();
		mkdir($this->temp_dir, 0755, true);

		$this->project_dir = $this->temp_dir . '/project';
		mkdir($this->project_dir, 0755, true);

		$this->user_home = $this->temp_dir . '/home';
		mkdir($this->user_home, 0755, true);
	}

	/**
	 * Tears down the test fixture.
	 */
	protected function tearDown(): void
	{
		$this->removeDirectory($this->temp_dir);

		parent::tearDown();
	}

	/**
	 * Tests that constructor creates instance correctly.
	 */
	public function test_constructor_createsInstance(): void
	{
		$discovery = new SettingsDiscovery($this->project_dir, $this->user_home);

		$this->assertInstanceOf(SettingsDiscoveryInterface::class, $discovery);
	}

	/**
	 * Tests that discover returns empty array when no .wp-ai-agent directories exist.
	 */
	public function test_discover_withNoClaudeDirectories_returnsEmptyArray(): void
	{
		$discovery = new SettingsDiscovery($this->project_dir, $this->user_home);

		$result = $discovery->discover('commands');

		$this->assertSame([], $result);
	}

	/**
	 * Tests that discover finds files in project .wp-ai-agent directory.
	 */
	public function test_discover_withProjectFiles_returnsProjectFiles(): void
	{
		$this->createProjectFile('commands', 'test-command.md', '# Test Command');

		$discovery = new SettingsDiscovery($this->project_dir, $this->user_home);

		$result = $discovery->discover('commands');

		$this->assertCount(1, $result);
		$this->assertArrayHasKey('test-command', $result);
		$this->assertSame(
			$this->project_dir . '/.wp-ai-agent/commands/test-command.md',
			$result['test-command']
		);
	}

	/**
	 * Tests that discover finds files in user .wp-ai-agent directory.
	 */
	public function test_discover_withUserFiles_returnsUserFiles(): void
	{
		$this->createUserFile('commands', 'user-command.md', '# User Command');

		$discovery = new SettingsDiscovery($this->project_dir, $this->user_home);

		$result = $discovery->discover('commands');

		$this->assertCount(1, $result);
		$this->assertArrayHasKey('user-command', $result);
		$this->assertSame(
			$this->user_home . '/.wp-ai-agent/commands/user-command.md',
			$result['user-command']
		);
	}

	/**
	 * Tests that project files override user files with the same name.
	 */
	public function test_discover_withSameNameInBothLocations_projectOverridesUser(): void
	{
		$this->createUserFile('commands', 'shared-command.md', '# User Shared');
		$this->createProjectFile('commands', 'shared-command.md', '# Project Shared');

		$discovery = new SettingsDiscovery($this->project_dir, $this->user_home);

		$result = $discovery->discover('commands');

		$this->assertCount(1, $result);
		$this->assertArrayHasKey('shared-command', $result);
		// Project should override user
		$this->assertSame(
			$this->project_dir . '/.wp-ai-agent/commands/shared-command.md',
			$result['shared-command']
		);
	}

	/**
	 * Tests that discover merges files from both locations.
	 */
	public function test_discover_withFilesInBothLocations_mergesBoth(): void
	{
		$this->createUserFile('commands', 'user-only.md', '# User Only');
		$this->createProjectFile('commands', 'project-only.md', '# Project Only');

		$discovery = new SettingsDiscovery($this->project_dir, $this->user_home);

		$result = $discovery->discover('commands');

		$this->assertCount(2, $result);
		$this->assertArrayHasKey('user-only', $result);
		$this->assertArrayHasKey('project-only', $result);
	}

	/**
	 * Tests that discover handles missing project directory gracefully.
	 */
	public function test_discover_withMissingProjectClaudeDir_returnsOnlyUserFiles(): void
	{
		$this->createUserFile('skills', 'user-skill.md', '# User Skill');
		// No project .wp-ai-agent directory

		$discovery = new SettingsDiscovery($this->project_dir, $this->user_home);

		$result = $discovery->discover('skills');

		$this->assertCount(1, $result);
		$this->assertArrayHasKey('user-skill', $result);
	}

	/**
	 * Tests that discover handles missing user directory gracefully.
	 */
	public function test_discover_withMissingUserClaudeDir_returnsOnlyProjectFiles(): void
	{
		$this->createProjectFile('agents', 'project-agent.md', '# Project Agent');
		// No user .wp-ai-agent directory

		$discovery = new SettingsDiscovery($this->project_dir, $this->user_home);

		$result = $discovery->discover('agents');

		$this->assertCount(1, $result);
		$this->assertArrayHasKey('project-agent', $result);
	}

	/**
	 * Tests that discover filters by file extension.
	 */
	public function test_discover_withDifferentExtensions_filtersCorrectly(): void
	{
		$this->createProjectFile('commands', 'command.md', '# Markdown');
		$this->createProjectFile('commands', 'config.json', '{}');
		$this->createProjectFile('commands', 'script.sh', '#!/bin/bash');

		$discovery = new SettingsDiscovery($this->project_dir, $this->user_home);

		$md_result = $discovery->discover('commands', 'md');
		$json_result = $discovery->discover('commands', 'json');

		$this->assertCount(1, $md_result);
		$this->assertArrayHasKey('command', $md_result);

		$this->assertCount(1, $json_result);
		$this->assertArrayHasKey('config', $json_result);
	}

	/**
	 * Tests that discover works with different subfolder types.
	 */
	public function test_discover_withDifferentTypes_worksForAllTypes(): void
	{
		$this->createProjectFile('commands', 'cmd.md', '# Command');
		$this->createProjectFile('skills', 'skill.md', '# Skill');
		$this->createProjectFile('agents', 'agent.md', '# Agent');

		$discovery = new SettingsDiscovery($this->project_dir, $this->user_home);

		$commands = $discovery->discover('commands');
		$skills = $discovery->discover('skills');
		$agents = $discovery->discover('agents');

		$this->assertCount(1, $commands);
		$this->assertCount(1, $skills);
		$this->assertCount(1, $agents);

		$this->assertArrayHasKey('cmd', $commands);
		$this->assertArrayHasKey('skill', $skills);
		$this->assertArrayHasKey('agent', $agents);
	}

	/**
	 * Tests that discover handles files with dots in name correctly.
	 */
	public function test_discover_withDotsInFilename_extractsNameCorrectly(): void
	{
		$this->createProjectFile('commands', 'my.special.command.md', '# Special');

		$discovery = new SettingsDiscovery($this->project_dir, $this->user_home);

		$result = $discovery->discover('commands');

		$this->assertArrayHasKey('my.special.command', $result);
	}

	/**
	 * Tests that discover ignores subdirectories.
	 */
	public function test_discover_withSubdirectories_ignoresSubdirectories(): void
	{
		$this->createProjectFile('commands', 'valid.md', '# Valid');
		$subdir = $this->project_dir . '/.wp-ai-agent/commands/subdir';
		mkdir($subdir, 0755, true);
		file_put_contents($subdir . '/nested.md', '# Nested');

		$discovery = new SettingsDiscovery($this->project_dir, $this->user_home);

		$result = $discovery->discover('commands');

		$this->assertCount(1, $result);
		$this->assertArrayHasKey('valid', $result);
		$this->assertArrayNotHasKey('nested', $result);
	}

	/**
	 * Tests that discover handles empty directories.
	 */
	public function test_discover_withEmptyDirectory_returnsEmptyArray(): void
	{
		mkdir($this->project_dir . '/.wp-ai-agent/commands', 0755, true);

		$discovery = new SettingsDiscovery($this->project_dir, $this->user_home);

		$result = $discovery->discover('commands');

		$this->assertSame([], $result);
	}

	/**
	 * Tests that getProjectSettingsPath returns correct path when .wp-ai-agent exists.
	 */
	public function test_getProjectSettingsPath_withExistingDir_returnsPath(): void
	{
		mkdir($this->project_dir . '/.wp-ai-agent', 0755, true);

		$discovery = new SettingsDiscovery($this->project_dir, $this->user_home);

		$result = $discovery->getProjectSettingsPath();

		$this->assertSame($this->project_dir . '/.wp-ai-agent', $result);
	}

	/**
	 * Tests that getProjectSettingsPath returns null when .wp-ai-agent does not exist.
	 */
	public function test_getProjectSettingsPath_withNoDir_returnsNull(): void
	{
		$discovery = new SettingsDiscovery($this->project_dir, $this->user_home);

		$result = $discovery->getProjectSettingsPath();

		$this->assertNull($result);
	}

	/**
	 * Tests that getUserSettingsPath returns correct path.
	 */
	public function test_getUserSettingsPath_returnsPath(): void
	{
		$discovery = new SettingsDiscovery($this->project_dir, $this->user_home);

		$result = $discovery->getUserSettingsPath();

		$this->assertSame($this->user_home . '/.wp-ai-agent', $result);
	}

	/**
	 * Tests that discover handles files with uppercase extension.
	 */
	public function test_discover_withUppercaseExtension_matchesCaseInsensitive(): void
	{
		$this->createProjectFile('commands', 'uppercase.MD', '# Uppercase');

		$discovery = new SettingsDiscovery($this->project_dir, $this->user_home);

		$result = $discovery->discover('commands', 'md');

		$this->assertCount(1, $result);
		$this->assertArrayHasKey('uppercase', $result);
	}

	/**
	 * Tests that discover works with multiple files in both locations.
	 */
	public function test_discover_withMultipleFilesInBothLocations_mergesCorrectly(): void
	{
		// User files
		$this->createUserFile('commands', 'user-cmd1.md', '# User 1');
		$this->createUserFile('commands', 'user-cmd2.md', '# User 2');
		$this->createUserFile('commands', 'shared.md', '# User Shared');

		// Project files
		$this->createProjectFile('commands', 'project-cmd1.md', '# Project 1');
		$this->createProjectFile('commands', 'shared.md', '# Project Shared');

		$discovery = new SettingsDiscovery($this->project_dir, $this->user_home);

		$result = $discovery->discover('commands');

		$this->assertCount(4, $result);
		$this->assertArrayHasKey('user-cmd1', $result);
		$this->assertArrayHasKey('user-cmd2', $result);
		$this->assertArrayHasKey('project-cmd1', $result);
		$this->assertArrayHasKey('shared', $result);

		// Shared should be project version
		$this->assertStringContainsString('/project/', $result['shared']);
	}

	/**
	 * Creates a file in the project .wp-ai-agent directory.
	 *
	 * @param string $type     The subfolder type (commands, skills, agents).
	 * @param string $filename The filename.
	 * @param string $content  The file content.
	 */
	private function createProjectFile(string $type, string $filename, string $content): void
	{
		$dir = $this->project_dir . '/.wp-ai-agent/' . $type;
		if (! is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
		file_put_contents($dir . '/' . $filename, $content);
	}

	/**
	 * Creates a file in the user .wp-ai-agent directory.
	 *
	 * @param string $type     The subfolder type (commands, skills, agents).
	 * @param string $filename The filename.
	 * @param string $content  The file content.
	 */
	private function createUserFile(string $type, string $filename, string $content): void
	{
		$dir = $this->user_home . '/.wp-ai-agent/' . $type;
		if (! is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
		file_put_contents($dir . '/' . $filename, $content);
	}

	/**
	 * Recursively removes a directory.
	 *
	 * @param string $dir The directory to remove.
	 */
	private function removeDirectory(string $dir): void
	{
		if (! is_dir($dir)) {
			return;
		}

		$items = scandir($dir);
		if ($items === false) {
			return;
		}

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$path = $dir . '/' . $item;
			if (is_dir($path)) {
				$this->removeDirectory($path);
			} else {
				unlink($path);
			}
		}

		rmdir($dir);
	}
}
