<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Integration\Cli;

use WpAiAgent\Integration\Cli\BypassPersistence;
use WpAiAgent\Integration\Configuration\SettingsWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for BypassPersistence class.
 *
 * @since n.e.x.t
 */
#[CoversClass(BypassPersistence::class)]
final class BypassPersistenceTest extends TestCase
{
	private string $temp_dir;
	private string $wp_ai_agent_dir;

	protected function setUp(): void
	{
		$this->temp_dir = sys_get_temp_dir() . '/bypass_persistence_test_' . uniqid();
		mkdir($this->temp_dir, 0755, true);
		$this->wp_ai_agent_dir = $this->temp_dir . '/.wp-ai-agent';
	}

	protected function tearDown(): void
	{
		$this->cleanupDirectory($this->temp_dir);
	}

	/**
	 * Recursively cleans up a directory.
	 *
	 * @param string $dir The directory to clean up.
	 */
	private function cleanupDirectory(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}

		$items = glob($dir . '/{,.}*', GLOB_BRACE);
		if ($items === false) {
			return;
		}

		foreach ($items as $item) {
			$basename = basename($item);
			if ($basename === '.' || $basename === '..') {
				continue;
			}

			if (is_dir($item)) {
				$this->cleanupDirectory($item);
			} else {
				unlink($item);
			}
		}
		rmdir($dir);
	}

	/**
	 * Creates a BypassPersistence instance for testing.
	 */
	private function createPersistence(): BypassPersistence
	{
		return BypassPersistence::forWorkingDirectory($this->temp_dir);
	}

	/**
	 * Creates a settings file with the given permissions.
	 *
	 * @param array<int, string> $tools List of tools to allow.
	 */
	private function createSettingsWithPermissions(array $tools): void
	{
		if (!is_dir($this->wp_ai_agent_dir)) {
			mkdir($this->wp_ai_agent_dir, 0755, true);
		}

		$data = [
			'permissions' => [
				'allow' => $tools,
			],
		];

		file_put_contents(
			$this->wp_ai_agent_dir . '/settings.json',
			json_encode($data, JSON_PRETTY_PRINT)
		);
	}

	/**
	 * Reads the current settings file.
	 *
	 * @return array<string, mixed>
	 */
	private function readSettings(): array
	{
		$path = $this->wp_ai_agent_dir . '/settings.json';
		if (!file_exists($path)) {
			return [];
		}

		$content = file_get_contents($path);
		if ($content === false) {
			return [];
		}

		$data = json_decode($content, true);
		return is_array($data) ? $data : [];
	}

	// -------------------------------------------------------------------------
	// Constructor and Factory Tests
	// -------------------------------------------------------------------------

	#[Test]
	public function constructor_acceptsSettingsWriterAndWorkingDir(): void
	{
		$settings_writer = new SettingsWriter();
		$persistence = new BypassPersistence($settings_writer, $this->temp_dir);

		$this->assertInstanceOf(BypassPersistence::class, $persistence);
	}

	#[Test]
	public function forWorkingDirectory_createsInstance(): void
	{
		$persistence = BypassPersistence::forWorkingDirectory($this->temp_dir);

		$this->assertInstanceOf(BypassPersistence::class, $persistence);
	}

	// -------------------------------------------------------------------------
	// Load Tests
	// -------------------------------------------------------------------------

	#[Test]
	public function load_returnsEmptyArray_whenNoSettingsFile(): void
	{
		$persistence = $this->createPersistence();

		$result = $persistence->load();

		$this->assertSame([], $result);
	}

	#[Test]
	public function load_returnsToolsFromPermissionsAllow(): void
	{
		$this->createSettingsWithPermissions(['tool_a', 'tool_b']);
		$persistence = $this->createPersistence();

		$result = $persistence->load();

		$this->assertSame(['tool_a', 'tool_b'], $result);
	}

	// -------------------------------------------------------------------------
	// Save Tests
	// -------------------------------------------------------------------------

	#[Test]
	public function save_storesToolsInPermissionsAllow(): void
	{
		$persistence = $this->createPersistence();
		$tools = ['tool_a', 'tool_b'];

		$result = $persistence->save($tools);

		$this->assertTrue($result);

		$settings = $this->readSettings();
		$this->assertArrayHasKey('permissions', $settings);
		$this->assertArrayHasKey('allow', $settings['permissions']);
		$this->assertContains('tool_a', $settings['permissions']['allow']);
		$this->assertContains('tool_b', $settings['permissions']['allow']);
	}

	#[Test]
	public function save_andLoad_roundtripsToolNames(): void
	{
		$persistence = $this->createPersistence();
		$tools = ['tool_a', 'tool_b', 'tool_c'];

		$persistence->save($tools);
		$loaded = $persistence->load();

		$this->assertSame($tools, $loaded);
	}

	// -------------------------------------------------------------------------
	// AddBypass Tests
	// -------------------------------------------------------------------------

	#[Test]
	public function addBypass_addsToolToList(): void
	{
		$persistence = $this->createPersistence();

		$persistence->addBypass('new_tool');
		$loaded = $persistence->load();

		$this->assertContains('new_tool', $loaded);
	}

	#[Test]
	public function addBypass_normalizesToLowercase(): void
	{
		$persistence = $this->createPersistence();

		$persistence->addBypass('MyTool_Name');
		$loaded = $persistence->load();

		$this->assertContains('mytool_name', $loaded);
	}

	#[Test]
	public function addBypass_doesNotDuplicateExistingTool(): void
	{
		$this->createSettingsWithPermissions(['tool_a']);
		$persistence = $this->createPersistence();

		$persistence->addBypass('tool_a');
		$loaded = $persistence->load();

		$tool_count = array_count_values($loaded)['tool_a'] ?? 0;
		$this->assertSame(1, $tool_count);
	}

	// -------------------------------------------------------------------------
	// RemoveBypass Tests
	// -------------------------------------------------------------------------

	#[Test]
	public function removeBypass_removesToolFromList(): void
	{
		$this->createSettingsWithPermissions(['tool_a', 'tool_b', 'tool_c']);
		$persistence = $this->createPersistence();

		$persistence->removeBypass('tool_b');
		$loaded = $persistence->load();

		$this->assertContains('tool_a', $loaded);
		$this->assertNotContains('tool_b', $loaded);
		$this->assertContains('tool_c', $loaded);
	}

	#[Test]
	public function removeBypass_normalizesToLowercase(): void
	{
		$this->createSettingsWithPermissions(['mytool']);
		$persistence = $this->createPersistence();

		$persistence->removeBypass('MyTool');
		$loaded = $persistence->load();

		$this->assertSame([], $loaded);
	}

	#[Test]
	public function removeBypass_doesNotFailWhenToolNotInList(): void
	{
		$this->createSettingsWithPermissions(['tool_a']);
		$persistence = $this->createPersistence();

		$result = $persistence->removeBypass('nonexistent');
		$loaded = $persistence->load();

		$this->assertTrue($result);
		$this->assertSame(['tool_a'], $loaded);
	}

	// -------------------------------------------------------------------------
	// Clear Tests
	// -------------------------------------------------------------------------

	#[Test]
	public function clear_removesAllTools(): void
	{
		$this->createSettingsWithPermissions(['tool_a', 'tool_b', 'tool_c']);
		$persistence = $this->createPersistence();

		$persistence->clear();
		$loaded = $persistence->load();

		$this->assertSame([], $loaded);
	}

	// -------------------------------------------------------------------------
	// Path Tests
	// -------------------------------------------------------------------------

	#[Test]
	public function getSettingsFilePath_returnsCorrectPath(): void
	{
		$persistence = $this->createPersistence();

		$path = $persistence->getSettingsFilePath();

		$this->assertSame($this->wp_ai_agent_dir . '/settings.json', $path);
	}

	#[Test]
	public function getDefaultPath_returnsWpAiAgentPath(): void
	{
		$default_path = BypassPersistence::getDefaultPath($this->temp_dir);

		$this->assertSame($this->wp_ai_agent_dir, $default_path);
	}

	#[Test]
	public function getLegacyFilePath_returnsCorrectPath(): void
	{
		$legacy_path = BypassPersistence::getLegacyFilePath($this->temp_dir);

		$this->assertSame($this->wp_ai_agent_dir . '/bypass_state.json', $legacy_path);
	}

	// -------------------------------------------------------------------------
	// Cross-Instance Persistence Tests
	// -------------------------------------------------------------------------

	#[Test]
	public function persistence_worksAcrossInstances(): void
	{
		$persistence1 = $this->createPersistence();
		$persistence1->addBypass('tool_a');
		$persistence1->addBypass('tool_b');

		// Create new instance (simulating new session).
		$persistence2 = $this->createPersistence();
		$loaded = $persistence2->load();

		$this->assertContains('tool_a', $loaded);
		$this->assertContains('tool_b', $loaded);
	}

	// -------------------------------------------------------------------------
	// Legacy Migration Tests
	// -------------------------------------------------------------------------

	#[Test]
	public function migrateFromLegacyFile_migratesToolsToPermissionsAllow(): void
	{
		// Create legacy bypass_state.json file.
		if (!is_dir($this->wp_ai_agent_dir)) {
			mkdir($this->wp_ai_agent_dir, 0755, true);
		}
		$legacy_data = [
			'bypassed_tools' => ['legacy_tool_a', 'legacy_tool_b'],
			'updated_at' => date('c'),
		];
		file_put_contents(
			$this->wp_ai_agent_dir . '/bypass_state.json',
			json_encode($legacy_data, JSON_PRETTY_PRINT)
		);

		// Create persistence - should trigger migration.
		$persistence = BypassPersistence::forWorkingDirectory($this->temp_dir);

		// Verify tools were migrated.
		$loaded = $persistence->load();
		$this->assertContains('legacy_tool_a', $loaded);
		$this->assertContains('legacy_tool_b', $loaded);

		// Verify legacy file was deleted.
		$this->assertFileDoesNotExist($this->wp_ai_agent_dir . '/bypass_state.json');

		// Verify settings file was created.
		$this->assertFileExists($this->wp_ai_agent_dir . '/settings.json');
	}

	#[Test]
	public function migrateFromLegacyFile_returnsFalseWhenNoLegacyFile(): void
	{
		$settings_writer = new SettingsWriter();
		$persistence = new BypassPersistence($settings_writer, $this->temp_dir);

		$result = $persistence->migrateFromLegacyFile();

		$this->assertFalse($result);
	}

	#[Test]
	public function migrateFromLegacyFile_deletesInvalidLegacyFile(): void
	{
		// Create invalid legacy file.
		if (!is_dir($this->wp_ai_agent_dir)) {
			mkdir($this->wp_ai_agent_dir, 0755, true);
		}
		file_put_contents(
			$this->wp_ai_agent_dir . '/bypass_state.json',
			'invalid json content'
		);

		$settings_writer = new SettingsWriter();
		$persistence = new BypassPersistence($settings_writer, $this->temp_dir);

		$result = $persistence->migrateFromLegacyFile();

		$this->assertFalse($result);
		$this->assertFileDoesNotExist($this->wp_ai_agent_dir . '/bypass_state.json');
	}

	#[Test]
	public function migrateFromLegacyFile_normalizesToLowercase(): void
	{
		// Create legacy file with mixed case tools.
		if (!is_dir($this->wp_ai_agent_dir)) {
			mkdir($this->wp_ai_agent_dir, 0755, true);
		}
		$legacy_data = [
			'bypassed_tools' => ['MyTool', 'ANOTHER_TOOL'],
			'updated_at' => date('c'),
		];
		file_put_contents(
			$this->wp_ai_agent_dir . '/bypass_state.json',
			json_encode($legacy_data, JSON_PRETTY_PRINT)
		);

		$persistence = BypassPersistence::forWorkingDirectory($this->temp_dir);
		$loaded = $persistence->load();

		$this->assertContains('mytool', $loaded);
		$this->assertContains('another_tool', $loaded);
	}
}
