<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Integration\Cli\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PhpCliAgent\Core\Contracts\OutputHandlerInterface;
use PhpCliAgent\Integration\Cli\Command\InitCommand;

/**
 * Tests for InitCommand.
 *
 * @covers \PhpCliAgent\Integration\Cli\Command\InitCommand
 */
final class InitCommandTest extends TestCase
{
	private OutputHandlerInterface&MockObject $output_handler;
	private string $temp_dir;

	protected function setUp(): void
	{
		$this->output_handler = $this->createMock(OutputHandlerInterface::class);

		// Create a temporary directory for testing.
		$this->temp_dir = sys_get_temp_dir() . '/php-cli-agent-test-' . uniqid();
		mkdir($this->temp_dir, 0755, true);
	}

	protected function tearDown(): void
	{
		// Clean up temporary directory.
		$this->recursiveDelete($this->temp_dir);
	}

	/**
	 * Given .php-cli-agent/ does not exist
	 * When init command runs
	 * Then directory is created
	 * And settings.json is created with defaults
	 * And mcp.json is created with empty servers
	 */
	public function test_execute_withNoExistingDirectory_createsAllFiles(): void
	{
		$command = new InitCommand($this->output_handler, $this->temp_dir);

		$this->output_handler->expects($this->once())
			->method('writeSuccess')
			->with($this->stringContains('initialized successfully'));

		$result = $command->execute([]);

		$this->assertTrue($result->wasHandled());
		$this->assertTrue($result->shouldContinue());

		// Verify directory was created.
		$config_dir = $this->temp_dir . '/.php-cli-agent';
		$this->assertDirectoryExists($config_dir);

		// Verify settings.json was created with defaults.
		$settings_path = $config_dir . '/settings.json';
		$this->assertFileExists($settings_path);
		$settings_content = file_get_contents($settings_path);
		$this->assertIsString($settings_content);
		$settings = json_decode($settings_content, true);
		$this->assertIsArray($settings);
		$this->assertArrayHasKey('provider', $settings);
		$this->assertSame('anthropic', $settings['provider']['type']);
		$this->assertSame('claude-sonnet-4-20250514', $settings['provider']['model']);
		$this->assertSame(8192, $settings['provider']['max_tokens']);
		$this->assertSame(100, $settings['max_turns']);
		$this->assertSame(['think', 'read_file', 'glob', 'grep'], $settings['bypass_confirmation_tools']);
		$this->assertFalse($settings['debug']);
		$this->assertTrue($settings['streaming']);

		// Verify mcp.json was created with empty servers.
		$mcp_path = $config_dir . '/mcp.json';
		$this->assertFileExists($mcp_path);
		$mcp_content = file_get_contents($mcp_path);
		$this->assertIsString($mcp_content);
		$mcp = json_decode($mcp_content, true);
		$this->assertIsArray($mcp);
		$this->assertArrayHasKey('mcpServers', $mcp);
		$this->assertSame([], $mcp['mcpServers']);
	}

	/**
	 * Given .php-cli-agent/ already exists
	 * When init command runs without --force
	 * Then user is prompted for confirmation
	 */
	public function test_execute_withExistingDirectory_andNoForce_promptsForConfirmation(): void
	{
		// Create existing directory.
		$config_dir = $this->temp_dir . '/.php-cli-agent';
		mkdir($config_dir, 0755, true);
		file_put_contents($config_dir . '/settings.json', '{"old": true}');

		// Create a mock input stream that returns 'n' for no.
		$input_stream = fopen('php://memory', 'r+');
		$this->assertIsResource($input_stream);
		fwrite($input_stream, "n\n");
		rewind($input_stream);

		$command = new InitCommand($this->output_handler, $this->temp_dir, $input_stream);

		$this->output_handler->expects($this->once())
			->method('write')
			->with($this->stringContains('already exists'));

		$this->output_handler->expects($this->once())
			->method('writeLine')
			->with($this->stringContains('cancelled'));

		$result = $command->execute([]);

		$this->assertTrue($result->wasHandled());
		$this->assertTrue($result->shouldContinue());

		// Verify existing files were not overwritten.
		$settings_content = file_get_contents($config_dir . '/settings.json');
		$this->assertIsString($settings_content);
		$settings = json_decode($settings_content, true);
		$this->assertIsArray($settings);
		$this->assertTrue($settings['old']);

		fclose($input_stream);
	}

	/**
	 * Given .php-cli-agent/ already exists and user confirms
	 * When init command runs without --force
	 * Then files are overwritten
	 */
	public function test_execute_withExistingDirectory_andUserConfirms_overwritesFiles(): void
	{
		// Create existing directory.
		$config_dir = $this->temp_dir . '/.php-cli-agent';
		mkdir($config_dir, 0755, true);
		file_put_contents($config_dir . '/settings.json', '{"old": true}');

		// Create a mock input stream that returns 'y' for yes.
		$input_stream = fopen('php://memory', 'r+');
		$this->assertIsResource($input_stream);
		fwrite($input_stream, "y\n");
		rewind($input_stream);

		$command = new InitCommand($this->output_handler, $this->temp_dir, $input_stream);

		$this->output_handler->expects($this->once())
			->method('writeSuccess')
			->with($this->stringContains('initialized successfully'));

		$result = $command->execute([]);

		$this->assertTrue($result->wasHandled());
		$this->assertTrue($result->shouldContinue());

		// Verify files were overwritten with defaults.
		$settings_content = file_get_contents($config_dir . '/settings.json');
		$this->assertIsString($settings_content);
		$settings = json_decode($settings_content, true);
		$this->assertIsArray($settings);
		$this->assertArrayNotHasKey('old', $settings);
		$this->assertArrayHasKey('provider', $settings);

		fclose($input_stream);
	}

	/**
	 * Given .php-cli-agent/ already exists
	 * When init command runs with --force
	 * Then files are overwritten without prompt
	 */
	public function test_execute_withExistingDirectory_andForce_overwritesWithoutPrompt(): void
	{
		// Create existing directory.
		$config_dir = $this->temp_dir . '/.php-cli-agent';
		mkdir($config_dir, 0755, true);
		file_put_contents($config_dir . '/settings.json', '{"old": true}');
		file_put_contents($config_dir . '/mcp.json', '{"mcpServers": {"old": {}}}');

		$command = new InitCommand($this->output_handler, $this->temp_dir);

		$this->output_handler->expects($this->never())
			->method('write');

		$this->output_handler->expects($this->once())
			->method('writeSuccess')
			->with($this->stringContains('initialized successfully'));

		$result = $command->execute(['--force']);

		$this->assertTrue($result->wasHandled());

		// Verify files were overwritten.
		$settings_content = file_get_contents($config_dir . '/settings.json');
		$this->assertIsString($settings_content);
		$settings = json_decode($settings_content, true);
		$this->assertIsArray($settings);
		$this->assertArrayNotHasKey('old', $settings);
		$this->assertArrayHasKey('provider', $settings);

		$mcp_content = file_get_contents($config_dir . '/mcp.json');
		$this->assertIsString($mcp_content);
		$mcp = json_decode($mcp_content, true);
		$this->assertIsArray($mcp);
		$this->assertSame([], $mcp['mcpServers']);
	}

	public function test_execute_withShortForceFlag_overwritesWithoutPrompt(): void
	{
		// Create existing directory.
		$config_dir = $this->temp_dir . '/.php-cli-agent';
		mkdir($config_dir, 0755, true);
		file_put_contents($config_dir . '/settings.json', '{"old": true}');

		$command = new InitCommand($this->output_handler, $this->temp_dir);

		$result = $command->execute(['-f']);

		$this->assertTrue($result->wasHandled());

		// Verify files were overwritten.
		$settings_content = file_get_contents($config_dir . '/settings.json');
		$this->assertIsString($settings_content);
		$settings = json_decode($settings_content, true);
		$this->assertIsArray($settings);
		$this->assertArrayNotHasKey('old', $settings);
	}

	public function test_execute_displaysNextSteps(): void
	{
		$command = new InitCommand($this->output_handler, $this->temp_dir);

		$written_lines = [];
		$this->output_handler->method('writeLine')
			->willReturnCallback(function (string $line) use (&$written_lines): void {
				$written_lines[] = $line;
			});

		$command->execute([]);

		$output = implode("\n", $written_lines);
		$this->assertStringContainsString('Next steps', $output);
		$this->assertStringContainsString('settings.json', $output);
		$this->assertStringContainsString('mcp.json', $output);
	}

	public function test_getName_returnsInit(): void
	{
		$command = new InitCommand($this->output_handler, $this->temp_dir);
		$this->assertSame('init', $command->getName());
	}

	public function test_getDescription_returnsDescription(): void
	{
		$command = new InitCommand($this->output_handler, $this->temp_dir);
		$this->assertStringContainsString('Initialize', $command->getDescription());
	}

	public function test_getUsage_returnsUsageString(): void
	{
		$command = new InitCommand($this->output_handler, $this->temp_dir);
		$usage = $command->getUsage();
		$this->assertStringContainsString('init', $usage);
		$this->assertStringContainsString('--force', $usage);
	}

	public function test_execute_withError_writesErrorMessage(): void
	{
		// Create a read-only directory to cause permission error.
		$config_dir = $this->temp_dir . '/.php-cli-agent';
		mkdir($config_dir, 0555, true);

		$command = new InitCommand($this->output_handler, $this->temp_dir);

		$this->output_handler->expects($this->once())
			->method('writeError')
			->with($this->stringContains('Failed'));

		$result = $command->execute(['--force']);

		$this->assertTrue($result->wasHandled());

		// Clean up - restore write permissions.
		chmod($config_dir, 0755);
	}

	public function test_execute_createsFormattedJsonFiles(): void
	{
		$command = new InitCommand($this->output_handler, $this->temp_dir);
		$command->execute([]);

		$config_dir = $this->temp_dir . '/.php-cli-agent';

		// Verify JSON is pretty-printed (has indentation).
		$settings_content = file_get_contents($config_dir . '/settings.json');
		$this->assertIsString($settings_content);
		$this->assertStringContainsString("\n", $settings_content);
		$this->assertStringContainsString('    ', $settings_content);

		$mcp_content = file_get_contents($config_dir . '/mcp.json');
		$this->assertIsString($mcp_content);
		$this->assertStringContainsString("\n", $mcp_content);
	}

	/**
	 * Given .gitignore does not exist
	 * When init command runs
	 * Then .gitignore is created with ".php-cli-agent/" entry
	 */
	public function test_execute_withNoGitignore_createsGitignoreWithEntry(): void
	{
		$command = new InitCommand($this->output_handler, $this->temp_dir);

		$result = $command->execute([]);

		$this->assertTrue($result->wasHandled());

		// Verify .gitignore was created.
		$gitignore_path = $this->temp_dir . '/.gitignore';
		$this->assertFileExists($gitignore_path);

		$content = file_get_contents($gitignore_path);
		$this->assertIsString($content);
		$this->assertStringContainsString('.php-cli-agent/', $content);
	}

	/**
	 * Given .gitignore exists without .php-cli-agent entry
	 * When init command runs
	 * Then ".php-cli-agent/" is appended to .gitignore
	 */
	public function test_execute_withExistingGitignore_withoutEntry_appendsEntry(): void
	{
		// Create existing .gitignore with other content.
		$gitignore_path = $this->temp_dir . '/.gitignore';
		file_put_contents($gitignore_path, "vendor/\nnode_modules/\n");

		$command = new InitCommand($this->output_handler, $this->temp_dir);

		$result = $command->execute([]);

		$this->assertTrue($result->wasHandled());

		$content = file_get_contents($gitignore_path);
		$this->assertIsString($content);

		// Original content should still be present.
		$this->assertStringContainsString('vendor/', $content);
		$this->assertStringContainsString('node_modules/', $content);

		// New entry should be added.
		$this->assertStringContainsString('.php-cli-agent/', $content);
	}

	/**
	 * Given .gitignore already contains ".php-cli-agent/"
	 * When init command runs
	 * Then .gitignore is not modified
	 */
	public function test_execute_withExistingGitignore_withEntry_doesNotModify(): void
	{
		// Create existing .gitignore with the entry already present.
		$gitignore_path = $this->temp_dir . '/.gitignore';
		$original_content = "vendor/\n.php-cli-agent/\nnode_modules/\n";
		file_put_contents($gitignore_path, $original_content);

		$command = new InitCommand($this->output_handler, $this->temp_dir);

		$result = $command->execute([]);

		$this->assertTrue($result->wasHandled());

		$content = file_get_contents($gitignore_path);
		$this->assertIsString($content);

		// Content should be unchanged.
		$this->assertSame($original_content, $content);
	}

	/**
	 * Given .gitignore exists with no trailing newline
	 * When init command runs
	 * Then ".php-cli-agent/" is appended on a new line
	 */
	public function test_execute_withGitignore_withoutTrailingNewline_appendsOnNewLine(): void
	{
		// Create .gitignore without trailing newline.
		$gitignore_path = $this->temp_dir . '/.gitignore';
		file_put_contents($gitignore_path, 'vendor/');

		$command = new InitCommand($this->output_handler, $this->temp_dir);

		$result = $command->execute([]);

		$this->assertTrue($result->wasHandled());

		$content = file_get_contents($gitignore_path);
		$this->assertIsString($content);

		// Entry should be on its own line, not appended to previous entry.
		$this->assertStringContainsString("vendor/\n.php-cli-agent/", $content);
	}

	/**
	 * Given .gitignore contains entry with different format (e.g., ".php-cli-agent" without slash)
	 * When init command runs
	 * Then .gitignore is not modified (matches both formats)
	 */
	public function test_execute_withExistingGitignore_withEntryWithoutSlash_doesNotModify(): void
	{
		// Create .gitignore with entry without trailing slash.
		$gitignore_path = $this->temp_dir . '/.gitignore';
		$original_content = "vendor/\n.php-cli-agent\n";
		file_put_contents($gitignore_path, $original_content);

		$command = new InitCommand($this->output_handler, $this->temp_dir);

		$result = $command->execute([]);

		$this->assertTrue($result->wasHandled());

		$content = file_get_contents($gitignore_path);
		$this->assertIsString($content);

		// Content should be unchanged since .php-cli-agent matches.
		$this->assertSame($original_content, $content);
	}

	/**
	 * Recursively deletes a directory and its contents.
	 */
	private function recursiveDelete(string $path): void
	{
		if (!file_exists($path)) {
			return;
		}

		if (is_dir($path)) {
			$iterator = new \RecursiveDirectoryIterator(
				$path,
				\RecursiveDirectoryIterator::SKIP_DOTS
			);
			$files = new \RecursiveIteratorIterator(
				$iterator,
				\RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ($files as $file) {
				if ($file->isDir()) {
					rmdir($file->getPathname());
				} else {
					unlink($file->getPathname());
				}
			}
			rmdir($path);
		} else {
			unlink($path);
		}
	}
}
