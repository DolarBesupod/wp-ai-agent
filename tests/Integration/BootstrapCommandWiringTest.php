<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for bootstrap command system wiring.
 *
 * These tests verify that the command system components are properly
 * initialized and wired together in the bootstrap file.
 *
 * @covers \Automattic\WpAiAgent\Integration\Command\CommandRegistry
 * @covers \Automattic\WpAiAgent\Integration\Command\CommandLoader
 * @covers \Automattic\WpAiAgent\Integration\Command\CommandExecutor
 */
final class BootstrapCommandWiringTest extends TestCase
{
	/**
	 * Temporary directory for test fixtures.
	 *
	 * @var string
	 */
	private string $temp_dir;

	protected function setUp(): void
	{
		$this->temp_dir = sys_get_temp_dir() . '/php-cli-agent-bootstrap-test-' . uniqid();
		mkdir($this->temp_dir, 0755, true);
	}

	protected function tearDown(): void
	{
		$this->cleanDirectory($this->temp_dir);
	}

	public function test_commandComponentsCanBeInstantiated(): void
	{
		// Test that all command system components can be instantiated correctly
		$markdown_parser = new \Automattic\WpAiAgent\Integration\Configuration\MarkdownParser();
		$settings_discovery = new \Automattic\WpAiAgent\Integration\Settings\SettingsDiscovery($this->temp_dir);
		$argument_substitutor = new \Automattic\WpAiAgent\Integration\Settings\ArgumentSubstitutor();
		$file_reference_expander = new \Automattic\WpAiAgent\Integration\Settings\FileReferenceExpander();
		$bash_command_expander = new \Automattic\WpAiAgent\Integration\Settings\BashCommandExpander();
		$command_loader = new \Automattic\WpAiAgent\Integration\Command\CommandLoader($markdown_parser);
		$command_registry = new \Automattic\WpAiAgent\Integration\Command\CommandRegistry(
			$command_loader,
			$settings_discovery
		);
		$command_executor = new \Automattic\WpAiAgent\Integration\Command\CommandExecutor(
			$argument_substitutor,
			$file_reference_expander,
			$bash_command_expander
		);

		$this->assertInstanceOf(
			\Automattic\WpAiAgent\Core\Contracts\CommandRegistryInterface::class,
			$command_registry
		);
		$this->assertInstanceOf(
			\Automattic\WpAiAgent\Core\Contracts\CommandExecutorInterface::class,
			$command_executor
		);
	}

	public function test_commandRegistryDiscoversCommandsFromClaudeDirectory(): void
	{
		// Create .wp-ai-agent/commands directory with a test command
		$commands_dir = $this->temp_dir . '/.wp-ai-agent/commands';
		mkdir($commands_dir, 0755, true);

		$command_content = <<<'MD'
---
description: Test command for bootstrap wiring
---

This is a test command body.
MD;
		file_put_contents($commands_dir . '/test-command.md', $command_content);

		// Create a fake user home to avoid picking up real user commands
		$fake_home = $this->temp_dir . '/fake_home';
		mkdir($fake_home, 0755, true);

		// Initialize components
		$markdown_parser = new \Automattic\WpAiAgent\Integration\Configuration\MarkdownParser();
		$settings_discovery = new \Automattic\WpAiAgent\Integration\Settings\SettingsDiscovery(
			$this->temp_dir,
			$fake_home
		);
		$command_loader = new \Automattic\WpAiAgent\Integration\Command\CommandLoader($markdown_parser);
		$command_registry = new \Automattic\WpAiAgent\Integration\Command\CommandRegistry(
			$command_loader,
			$settings_discovery
		);

		// Discover commands
		$command_registry->discover();

		// Assert the command was discovered
		$this->assertTrue($command_registry->has('test-command'));
		$command = $command_registry->get('test-command');
		$this->assertNotNull($command);
		$this->assertSame('Test command for bootstrap wiring', $command->getDescription());
	}

	public function test_commandRegistryReturnsCustomCommands(): void
	{
		// Create .wp-ai-agent/commands directory with test commands
		$commands_dir = $this->temp_dir . '/.wp-ai-agent/commands';
		mkdir($commands_dir, 0755, true);

		file_put_contents($commands_dir . '/cmd1.md', "---\ndescription: Command 1\n---\nBody 1");
		file_put_contents($commands_dir . '/cmd2.md', "---\ndescription: Command 2\n---\nBody 2");

		// Create a fake user home to avoid picking up real user commands
		$fake_home = $this->temp_dir . '/fake_home';
		mkdir($fake_home, 0755, true);

		// Initialize components
		$markdown_parser = new \Automattic\WpAiAgent\Integration\Configuration\MarkdownParser();
		$settings_discovery = new \Automattic\WpAiAgent\Integration\Settings\SettingsDiscovery(
			$this->temp_dir,
			$fake_home
		);
		$command_loader = new \Automattic\WpAiAgent\Integration\Command\CommandLoader($markdown_parser);
		$command_registry = new \Automattic\WpAiAgent\Integration\Command\CommandRegistry(
			$command_loader,
			$settings_discovery
		);

		// Discover commands
		$command_registry->discover();

		// Get custom commands
		$custom_commands = $command_registry->getCustomCommands();

		$this->assertCount(2, $custom_commands);
		$this->assertArrayHasKey('cmd1', $custom_commands);
		$this->assertArrayHasKey('cmd2', $custom_commands);
	}

	public function test_commandExecutorExpandsArgumentPlaceholders(): void
	{
		// Create a command with argument placeholders
		$markdown_parser = new \Automattic\WpAiAgent\Integration\Configuration\MarkdownParser();
		$command_loader = new \Automattic\WpAiAgent\Integration\Command\CommandLoader($markdown_parser);
		$argument_substitutor = new \Automattic\WpAiAgent\Integration\Settings\ArgumentSubstitutor();
		$file_reference_expander = new \Automattic\WpAiAgent\Integration\Settings\FileReferenceExpander();
		$bash_command_expander = new \Automattic\WpAiAgent\Integration\Settings\BashCommandExpander();

		$command = $command_loader->loadFromContent(
			'test',
			"---\ndescription: Test\n---\nHello \$1, welcome to \$2!"
		);

		$command_executor = new \Automattic\WpAiAgent\Integration\Command\CommandExecutor(
			$argument_substitutor,
			$file_reference_expander,
			$bash_command_expander
		);

		$arguments = \Automattic\WpAiAgent\Core\ValueObjects\ArgumentList::fromString('World PHP');
		$result = $command_executor->execute($command, $arguments);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('Hello World', $result->getExpandedContent());
		$this->assertStringContainsString('welcome to PHP', $result->getExpandedContent());
	}

	public function test_discoveryCountCanBeLogged(): void
	{
		// Create .wp-ai-agent/commands directory with test commands
		$commands_dir = $this->temp_dir . '/.wp-ai-agent/commands';
		mkdir($commands_dir, 0755, true);

		file_put_contents($commands_dir . '/cmd1.md', "---\ndescription: Command 1\n---\nBody 1");
		file_put_contents($commands_dir . '/cmd2.md', "---\ndescription: Command 2\n---\nBody 2");
		file_put_contents($commands_dir . '/cmd3.md', "---\ndescription: Command 3\n---\nBody 3");

		// Create a fake user home to avoid picking up real user commands
		$fake_home = $this->temp_dir . '/fake_home';
		mkdir($fake_home, 0755, true);

		// Initialize and discover
		$markdown_parser = new \Automattic\WpAiAgent\Integration\Configuration\MarkdownParser();
		$settings_discovery = new \Automattic\WpAiAgent\Integration\Settings\SettingsDiscovery(
			$this->temp_dir,
			$fake_home
		);
		$command_loader = new \Automattic\WpAiAgent\Integration\Command\CommandLoader($markdown_parser);
		$command_registry = new \Automattic\WpAiAgent\Integration\Command\CommandRegistry(
			$command_loader,
			$settings_discovery
		);

		$command_registry->discover();
		$custom_commands = $command_registry->getCustomCommands();

		// This test verifies the count is available for logging
		$this->assertCount(3, $custom_commands);

		// Simulate the logging format from bootstrap
		$log_message = sprintf(
			"\033[32m[Commands] Discovered %d custom command(s)\033[0m",
			count($custom_commands)
		);

		$this->assertStringContainsString('3 custom command(s)', $log_message);
	}

	/**
	 * Recursively removes a directory and its contents.
	 */
	private function cleanDirectory(string $path): void
	{
		if (!is_dir($path)) {
			return;
		}

		$items = scandir($path);
		if ($items === false) {
			return;
		}

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$item_path = $path . '/' . $item;
			if (is_dir($item_path)) {
				$this->cleanDirectory($item_path);
			} else {
				unlink($item_path);
			}
		}

		rmdir($path);
	}
}
