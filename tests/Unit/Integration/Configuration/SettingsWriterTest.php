<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Integration\Configuration;

use Automattic\Automattic\WpAiAgent\Integration\Configuration\SettingsWriter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SettingsWriter.
 *
 * @covers \Automattic\WpAiAgent\Integration\Configuration\SettingsWriter
 */
final class SettingsWriterTest extends TestCase
{
	/**
	 * Temporary directory for test files.
	 *
	 * @var string
	 */
	private string $temp_dir;

	/**
	 * Sets up the test fixture.
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->temp_dir = sys_get_temp_dir() . '/settings_writer_test_' . uniqid();
		mkdir($this->temp_dir, 0755, true);
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
	 * Tests that addAllowedTool creates settings file if it does not exist.
	 */
	public function test_addAllowedTool_createsSettingsFileIfNotExists(): void
	{
		$writer = new SettingsWriter();

		$result = $writer->addAllowedTool('test_tool', $this->temp_dir);

		$this->assertTrue($result);
		$this->assertFileExists($this->getSettingsPath());

		$content = $this->readSettings();
		$this->assertArrayHasKey('permissions', $content);
		$this->assertArrayHasKey('allow', $content['permissions']);
		$this->assertContains('test_tool', $content['permissions']['allow']);
	}

	/**
	 * Tests that addAllowedTool appends to existing permissions.
	 */
	public function test_addAllowedTool_appendsToExistingPermissions(): void
	{
		$this->createSettingsFile([
			'permissions' => [
				'allow' => ['existing_tool'],
			],
		]);
		$writer = new SettingsWriter();

		$result = $writer->addAllowedTool('new_tool', $this->temp_dir);

		$this->assertTrue($result);

		$content = $this->readSettings();
		$this->assertContains('existing_tool', $content['permissions']['allow']);
		$this->assertContains('new_tool', $content['permissions']['allow']);
	}

	/**
	 * Tests that addAllowedTool does not duplicate tools.
	 */
	public function test_addAllowedTool_doesNotDuplicateTools(): void
	{
		$this->createSettingsFile([
			'permissions' => [
				'allow' => ['test_tool'],
			],
		]);
		$writer = new SettingsWriter();

		$result = $writer->addAllowedTool('test_tool', $this->temp_dir);

		$this->assertTrue($result);

		$content = $this->readSettings();
		$tool_count = array_count_values($content['permissions']['allow'])['test_tool'] ?? 0;
		$this->assertSame(1, $tool_count);
	}

	/**
	 * Tests that addAllowedTool normalizes tool names to lowercase.
	 */
	public function test_addAllowedTool_normalizesToLowercase(): void
	{
		$writer = new SettingsWriter();

		$writer->addAllowedTool('TEST_TOOL', $this->temp_dir);

		$content = $this->readSettings();
		$this->assertContains('test_tool', $content['permissions']['allow']);
	}

	/**
	 * Tests that addAllowedTool preserves other settings.
	 */
	public function test_addAllowedTool_preservesOtherSettings(): void
	{
		$this->createSettingsFile([
			'provider' => [
				'model' => 'test-model',
			],
			'max_turns' => 50,
		]);
		$writer = new SettingsWriter();

		$writer->addAllowedTool('new_tool', $this->temp_dir);

		$content = $this->readSettings();
		$this->assertSame('test-model', $content['provider']['model']);
		$this->assertSame(50, $content['max_turns']);
	}

	/**
	 * Tests that removeAllowedTool removes tool from permissions.
	 */
	public function test_removeAllowedTool_removesFromPermissions(): void
	{
		$this->createSettingsFile([
			'permissions' => [
				'allow' => ['tool1', 'tool2', 'tool3'],
			],
		]);
		$writer = new SettingsWriter();

		$result = $writer->removeAllowedTool('tool2', $this->temp_dir);

		$this->assertTrue($result);

		$content = $this->readSettings();
		$this->assertContains('tool1', $content['permissions']['allow']);
		$this->assertNotContains('tool2', $content['permissions']['allow']);
		$this->assertContains('tool3', $content['permissions']['allow']);
	}

	/**
	 * Tests that removeAllowedTool is case-insensitive.
	 */
	public function test_removeAllowedTool_isCaseInsensitive(): void
	{
		$this->createSettingsFile([
			'permissions' => [
				'allow' => ['test_tool'],
			],
		]);
		$writer = new SettingsWriter();

		$result = $writer->removeAllowedTool('TEST_TOOL', $this->temp_dir);

		$this->assertTrue($result);

		$content = $this->readSettings();
		$this->assertEmpty($content['permissions']['allow']);
	}

	/**
	 * Tests that removeAllowedTool returns true when file does not exist.
	 */
	public function test_removeAllowedTool_returnsTrueWhenFileNotExists(): void
	{
		$writer = new SettingsWriter();

		$result = $writer->removeAllowedTool('test_tool', $this->temp_dir);

		$this->assertTrue($result);
	}

	/**
	 * Tests that removeAllowedTool preserves other settings.
	 */
	public function test_removeAllowedTool_preservesOtherSettings(): void
	{
		$this->createSettingsFile([
			'provider' => [
				'model' => 'test-model',
			],
			'permissions' => [
				'allow' => ['test_tool'],
			],
		]);
		$writer = new SettingsWriter();

		$writer->removeAllowedTool('test_tool', $this->temp_dir);

		$content = $this->readSettings();
		$this->assertSame('test-model', $content['provider']['model']);
	}

	/**
	 * Tests that getAllowedTools returns list of allowed tools.
	 */
	public function test_getAllowedTools_returnsListOfTools(): void
	{
		$this->createSettingsFile([
			'permissions' => [
				'allow' => ['tool1', 'tool2', 'tool3'],
			],
		]);
		$writer = new SettingsWriter();

		$tools = $writer->getAllowedTools($this->temp_dir);

		$this->assertSame(['tool1', 'tool2', 'tool3'], $tools);
	}

	/**
	 * Tests that getAllowedTools returns empty array when no permissions.
	 */
	public function test_getAllowedTools_returnsEmptyArrayWhenNoPermissions(): void
	{
		$this->createSettingsFile([
			'provider' => ['model' => 'test'],
		]);
		$writer = new SettingsWriter();

		$tools = $writer->getAllowedTools($this->temp_dir);

		$this->assertSame([], $tools);
	}

	/**
	 * Tests that getAllowedTools returns empty array when file does not exist.
	 */
	public function test_getAllowedTools_returnsEmptyArrayWhenFileNotExists(): void
	{
		$writer = new SettingsWriter();

		$tools = $writer->getAllowedTools($this->temp_dir);

		$this->assertSame([], $tools);
	}

	/**
	 * Tests that getAllowedTools filters non-string values.
	 */
	public function test_getAllowedTools_filtersNonStringValues(): void
	{
		$this->createSettingsFile([
			'permissions' => [
				'allow' => ['tool1', 123, null, 'tool2', true],
			],
		]);
		$writer = new SettingsWriter();

		$tools = $writer->getAllowedTools($this->temp_dir);

		$this->assertSame(['tool1', 'tool2'], $tools);
	}

	/**
	 * Tests that clearAllowedTools clears all permissions.
	 */
	public function test_clearAllowedTools_clearsAllPermissions(): void
	{
		$this->createSettingsFile([
			'permissions' => [
				'allow' => ['tool1', 'tool2', 'tool3'],
			],
		]);
		$writer = new SettingsWriter();

		$result = $writer->clearAllowedTools($this->temp_dir);

		$this->assertTrue($result);

		$content = $this->readSettings();
		$this->assertSame([], $content['permissions']['allow']);
	}

	/**
	 * Tests that clearAllowedTools preserves other settings.
	 */
	public function test_clearAllowedTools_preservesOtherSettings(): void
	{
		$this->createSettingsFile([
			'provider' => [
				'model' => 'test-model',
			],
			'permissions' => [
				'allow' => ['tool1'],
				'deny' => ['tool2'],
			],
		]);
		$writer = new SettingsWriter();

		$writer->clearAllowedTools($this->temp_dir);

		$content = $this->readSettings();
		$this->assertSame('test-model', $content['provider']['model']);
	}

	/**
	 * Tests that clearAllowedTools returns true when file does not exist.
	 */
	public function test_clearAllowedTools_returnsTrueWhenFileNotExists(): void
	{
		$writer = new SettingsWriter();

		$result = $writer->clearAllowedTools($this->temp_dir);

		$this->assertTrue($result);
	}

	/**
	 * Tests that getSettingsPath returns correct path.
	 */
	public function test_getSettingsPath_returnsCorrectPath(): void
	{
		$writer = new SettingsWriter();

		$path = $writer->getSettingsPath('/project/dir');

		$this->assertSame('/project/dir/.wp-ai-agent/settings.json', $path);
	}

	/**
	 * Tests that getConfigFolderPath returns correct path.
	 */
	public function test_getConfigFolderPath_returnsCorrectPath(): void
	{
		$writer = new SettingsWriter();

		$path = $writer->getConfigFolderPath('/project/dir');

		$this->assertSame('/project/dir/.wp-ai-agent', $path);
	}

	/**
	 * Tests that addAllowedTool creates config folder if not exists.
	 */
	public function test_addAllowedTool_createsConfigFolderIfNotExists(): void
	{
		$writer = new SettingsWriter();

		$writer->addAllowedTool('test_tool', $this->temp_dir);

		$this->assertDirectoryExists($this->temp_dir . '/.wp-ai-agent');
	}

	/**
	 * Tests that multiple operations work correctly.
	 */
	public function test_multipleOperations_workCorrectly(): void
	{
		$writer = new SettingsWriter();

		// Add first tool.
		$writer->addAllowedTool('tool1', $this->temp_dir);

		// Add second tool.
		$writer->addAllowedTool('tool2', $this->temp_dir);

		// Remove first tool.
		$writer->removeAllowedTool('tool1', $this->temp_dir);

		// Add third tool.
		$writer->addAllowedTool('tool3', $this->temp_dir);

		$tools = $writer->getAllowedTools($this->temp_dir);

		$this->assertNotContains('tool1', $tools);
		$this->assertContains('tool2', $tools);
		$this->assertContains('tool3', $tools);
	}

	/**
	 * Gets the path to the settings.json file.
	 *
	 * @return string
	 */
	private function getSettingsPath(): string
	{
		return $this->temp_dir . '/.wp-ai-agent/settings.json';
	}

	/**
	 * Reads the current settings file.
	 *
	 * @return array<string, mixed>
	 */
	private function readSettings(): array
	{
		$content = file_get_contents($this->getSettingsPath());
		if ($content === false) {
			return [];
		}

		$data = json_decode($content, true);
		return is_array($data) ? $data : [];
	}

	/**
	 * Creates a settings file with the given content.
	 *
	 * @param array<string, mixed> $content The JSON content as an array.
	 */
	private function createSettingsFile(array $content): void
	{
		$settings_dir = $this->temp_dir . '/.wp-ai-agent';
		if (!is_dir($settings_dir)) {
			mkdir($settings_dir, 0755, true);
		}
		file_put_contents(
			$settings_dir . '/settings.json',
			json_encode($content, JSON_PRETTY_PRINT)
		);
	}

	/**
	 * Recursively removes a directory.
	 *
	 * @param string $dir The directory to remove.
	 */
	private function removeDirectory(string $dir): void
	{
		if (!is_dir($dir)) {
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
