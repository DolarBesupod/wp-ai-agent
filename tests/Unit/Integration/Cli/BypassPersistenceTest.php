<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Integration\Cli;

use PhpCliAgent\Integration\Cli\BypassPersistence;
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
	private string $php_cli_agent_dir;

	protected function setUp(): void
	{
		$this->temp_dir = sys_get_temp_dir() . '/bypass_persistence_test_' . uniqid();
		mkdir($this->temp_dir, 0755, true);
		$this->php_cli_agent_dir = $this->temp_dir . '/.php-cli-agent';
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

	#[Test]
	public function constructor_createsDirectory_ifNotExists(): void
	{
		$non_existent_dir = $this->temp_dir . '/subdir/nested';

		new BypassPersistence($non_existent_dir);

		$this->assertDirectoryExists($non_existent_dir);
	}

	#[Test]
	public function load_returnsEmptyArray_whenFileNotExists(): void
	{
		$persistence = new BypassPersistence($this->temp_dir);

		$result = $persistence->load();

		$this->assertSame([], $result);
	}

	#[Test]
	public function save_createsStateFile_withToolNames(): void
	{
		$persistence = new BypassPersistence($this->temp_dir);
		$tools = ['tool_a', 'tool_b'];

		$result = $persistence->save($tools);

		$this->assertTrue($result);
		$this->assertFileExists($this->temp_dir . '/bypass_state.json');
	}

	#[Test]
	public function save_andLoad_roundtrips_toolNames(): void
	{
		$persistence = new BypassPersistence($this->temp_dir);
		$tools = ['tool_a', 'tool_b', 'tool_c'];

		$persistence->save($tools);
		$loaded = $persistence->load();

		$this->assertSame($tools, $loaded);
	}

	#[Test]
	public function save_deduplicates_toolNames(): void
	{
		$persistence = new BypassPersistence($this->temp_dir);
		$tools = ['tool_a', 'tool_b', 'tool_a', 'tool_c', 'tool_b'];

		$persistence->save($tools);
		$loaded = $persistence->load();

		$this->assertSame(['tool_a', 'tool_b', 'tool_c'], $loaded);
	}

	#[Test]
	public function addBypass_addsToolToList(): void
	{
		$persistence = new BypassPersistence($this->temp_dir);

		$persistence->addBypass('new_tool');
		$loaded = $persistence->load();

		$this->assertContains('new_tool', $loaded);
	}

	#[Test]
	public function addBypass_normalizesToLowercase(): void
	{
		$persistence = new BypassPersistence($this->temp_dir);

		$persistence->addBypass('MyTool_Name');
		$loaded = $persistence->load();

		$this->assertContains('mytool_name', $loaded);
	}

	#[Test]
	public function addBypass_doesNotDuplicate_existingTool(): void
	{
		$persistence = new BypassPersistence($this->temp_dir);
		$persistence->save(['tool_a']);

		$persistence->addBypass('tool_a');
		$loaded = $persistence->load();

		$this->assertCount(1, $loaded);
		$this->assertSame(['tool_a'], $loaded);
	}

	#[Test]
	public function removeBypass_removesToolFromList(): void
	{
		$persistence = new BypassPersistence($this->temp_dir);
		$persistence->save(['tool_a', 'tool_b', 'tool_c']);

		$persistence->removeBypass('tool_b');
		$loaded = $persistence->load();

		$this->assertSame(['tool_a', 'tool_c'], $loaded);
	}

	#[Test]
	public function removeBypass_normalizesToLowercase(): void
	{
		$persistence = new BypassPersistence($this->temp_dir);
		$persistence->save(['mytool']);

		$persistence->removeBypass('MyTool');
		$loaded = $persistence->load();

		$this->assertSame([], $loaded);
	}

	#[Test]
	public function removeBypass_doesNotFail_whenToolNotInList(): void
	{
		$persistence = new BypassPersistence($this->temp_dir);
		$persistence->save(['tool_a']);

		$result = $persistence->removeBypass('nonexistent');
		$loaded = $persistence->load();

		$this->assertTrue($result);
		$this->assertSame(['tool_a'], $loaded);
	}

	#[Test]
	public function clear_removesAllTools(): void
	{
		$persistence = new BypassPersistence($this->temp_dir);
		$persistence->save(['tool_a', 'tool_b', 'tool_c']);

		$persistence->clear();
		$loaded = $persistence->load();

		$this->assertSame([], $loaded);
	}

	#[Test]
	public function getStateFilePath_returnsCorrectPath(): void
	{
		$persistence = new BypassPersistence($this->temp_dir);

		$path = $persistence->getStatFilePath();

		$this->assertSame($this->temp_dir . '/bypass_state.json', $path);
	}

	#[Test]
	public function load_returnsEmptyArray_whenFileContainsInvalidJson(): void
	{
		$persistence = new BypassPersistence($this->temp_dir);
		file_put_contents($this->temp_dir . '/bypass_state.json', 'not valid json');

		$result = $persistence->load();

		$this->assertSame([], $result);
	}

	#[Test]
	public function load_returnsEmptyArray_whenFileContainsNonArray(): void
	{
		$persistence = new BypassPersistence($this->temp_dir);
		file_put_contents($this->temp_dir . '/bypass_state.json', '"just a string"');

		$result = $persistence->load();

		$this->assertSame([], $result);
	}

	#[Test]
	public function load_filtersNonStringValues(): void
	{
		$persistence = new BypassPersistence($this->temp_dir);
		$data = [
			'bypassed_tools' => ['valid_tool', 123, null, 'another_tool', ['array']],
		];
		file_put_contents($this->temp_dir . '/bypass_state.json', json_encode($data));

		$result = $persistence->load();

		$this->assertSame(['valid_tool', 'another_tool'], $result);
	}

	#[Test]
	public function stateFile_includesUpdatedAt_timestamp(): void
	{
		$persistence = new BypassPersistence($this->temp_dir);

		$persistence->save(['tool_a']);
		$content = file_get_contents($this->temp_dir . '/bypass_state.json');
		$data = json_decode((string) $content, true);

		$this->assertArrayHasKey('updated_at', $data);
		$this->assertIsString($data['updated_at']);
	}

	#[Test]
	public function persistence_worksAcrossInstances(): void
	{
		$persistence1 = new BypassPersistence($this->temp_dir);
		$persistence1->addBypass('tool_a');
		$persistence1->addBypass('tool_b');

		// Create new instance (simulating new session).
		$persistence2 = new BypassPersistence($this->temp_dir);
		$loaded = $persistence2->load();

		$this->assertSame(['tool_a', 'tool_b'], $loaded);
	}

	// -------------------------------------------------------------------------
	// Tests for .php-cli-agent folder and factory method
	// -------------------------------------------------------------------------

	#[Test]
	public function forWorkingDirectory_createsPhpCliAgentFolder_whenItDoesNotExist(): void
	{
		$persistence = BypassPersistence::forWorkingDirectory($this->temp_dir);

		$this->assertDirectoryExists($this->php_cli_agent_dir);
		$this->assertStringContainsString(
			'.php-cli-agent/bypass_state.json',
			$persistence->getStatFilePath()
		);
	}

	#[Test]
	public function forWorkingDirectory_usesExistingPhpCliAgentFolder(): void
	{
		// Create the folder first.
		mkdir($this->php_cli_agent_dir, 0755, true);

		$persistence = BypassPersistence::forWorkingDirectory($this->temp_dir);

		$this->assertSame(
			$this->php_cli_agent_dir . '/bypass_state.json',
			$persistence->getStatFilePath()
		);
	}

	#[Test]
	public function forWorkingDirectory_createsStateFile_inPhpCliAgentFolder(): void
	{
		$persistence = BypassPersistence::forWorkingDirectory($this->temp_dir);

		$persistence->addBypass('test_tool');

		$this->assertFileExists($this->php_cli_agent_dir . '/bypass_state.json');
	}

	#[Test]
	public function forWorkingDirectory_migratesOldStateFile_fromSessionPath(): void
	{
		// Create old state file in a legacy location.
		$old_session_path = $this->temp_dir . '/sessions';
		mkdir($old_session_path, 0755, true);
		$old_state_file = $old_session_path . '/bypass_state.json';
		$old_data = [
			'bypassed_tools' => ['tool_from_old_location'],
			'updated_at' => date('c'),
		];
		file_put_contents($old_state_file, json_encode($old_data, JSON_PRETTY_PRINT));

		// Create persistence with migration from old path.
		$persistence = BypassPersistence::forWorkingDirectory($this->temp_dir, $old_session_path);

		// Verify data was migrated.
		$loaded = $persistence->load();
		$this->assertContains('tool_from_old_location', $loaded);

		// Verify old file was removed.
		$this->assertFileDoesNotExist($old_state_file);

		// Verify new file exists.
		$this->assertFileExists($this->php_cli_agent_dir . '/bypass_state.json');
	}

	#[Test]
	public function forWorkingDirectory_doesNotMigrate_whenNewFileAlreadyExists(): void
	{
		// Create existing state in new location.
		mkdir($this->php_cli_agent_dir, 0755, true);
		$new_data = [
			'bypassed_tools' => ['tool_in_new_location'],
			'updated_at' => date('c'),
		];
		file_put_contents(
			$this->php_cli_agent_dir . '/bypass_state.json',
			json_encode($new_data, JSON_PRETTY_PRINT)
		);

		// Create old state file.
		$old_session_path = $this->temp_dir . '/sessions';
		mkdir($old_session_path, 0755, true);
		$old_state_file = $old_session_path . '/bypass_state.json';
		$old_data = [
			'bypassed_tools' => ['tool_from_old_location'],
			'updated_at' => date('c'),
		];
		file_put_contents($old_state_file, json_encode($old_data, JSON_PRETTY_PRINT));

		// Create persistence - should NOT migrate because new file exists.
		$persistence = BypassPersistence::forWorkingDirectory($this->temp_dir, $old_session_path);

		// Verify new location data is used (not migrated).
		$loaded = $persistence->load();
		$this->assertContains('tool_in_new_location', $loaded);
		$this->assertNotContains('tool_from_old_location', $loaded);

		// Old file should still exist (not deleted since we didn't migrate).
		$this->assertFileExists($old_state_file);
	}

	#[Test]
	public function forWorkingDirectory_handlesNullOldPath(): void
	{
		$persistence = BypassPersistence::forWorkingDirectory($this->temp_dir, null);

		$this->assertDirectoryExists($this->php_cli_agent_dir);
		$persistence->addBypass('test_tool');
		$this->assertFileExists($this->php_cli_agent_dir . '/bypass_state.json');
	}

	#[Test]
	public function forWorkingDirectory_handlesNonExistentOldPath(): void
	{
		$non_existent_path = $this->temp_dir . '/non_existent_sessions';

		$persistence = BypassPersistence::forWorkingDirectory($this->temp_dir, $non_existent_path);

		// Should work normally without errors.
		$persistence->addBypass('test_tool');
		$loaded = $persistence->load();
		$this->assertContains('test_tool', $loaded);
	}

	#[Test]
	public function forWorkingDirectory_getDefaultPath_returnsPhpCliAgentPath(): void
	{
		$default_path = BypassPersistence::getDefaultPath($this->temp_dir);

		$this->assertSame($this->php_cli_agent_dir, $default_path);
	}

	#[Test]
	public function forWorkingDirectory_preservesAllBypassesWhenMigrating(): void
	{
		// Create old state file with multiple tools.
		$old_session_path = $this->temp_dir . '/sessions';
		mkdir($old_session_path, 0755, true);
		$old_state_file = $old_session_path . '/bypass_state.json';
		$old_data = [
			'bypassed_tools' => ['tool_a', 'tool_b', 'tool_c'],
			'updated_at' => date('c'),
		];
		file_put_contents($old_state_file, json_encode($old_data, JSON_PRETTY_PRINT));

		// Create persistence with migration.
		$persistence = BypassPersistence::forWorkingDirectory($this->temp_dir, $old_session_path);

		// Verify all tools were migrated.
		$loaded = $persistence->load();
		$this->assertCount(3, $loaded);
		$this->assertContains('tool_a', $loaded);
		$this->assertContains('tool_b', $loaded);
		$this->assertContains('tool_c', $loaded);
	}
}
