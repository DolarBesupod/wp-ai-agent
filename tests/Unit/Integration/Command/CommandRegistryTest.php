<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Integration\Command;

use Automattic\Automattic\WpAiAgent\Core\Command\Command;
use Automattic\Automattic\WpAiAgent\Core\Command\CommandConfig;
use Automattic\Automattic\WpAiAgent\Core\Contracts\CommandLoaderInterface;
use Automattic\Automattic\WpAiAgent\Core\Contracts\CommandRegistryInterface;
use Automattic\Automattic\WpAiAgent\Core\Contracts\SettingsDiscoveryInterface;
use Automattic\Automattic\WpAiAgent\Integration\Command\CommandRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CommandRegistry.
 *
 * @covers \Automattic\WpAiAgent\Integration\Command\CommandRegistry
 */
final class CommandRegistryTest extends TestCase
{
	/**
	 * The command loader mock.
	 *
	 * @var CommandLoaderInterface&MockObject
	 */
	private CommandLoaderInterface $loader;

	/**
	 * The settings discovery mock.
	 *
	 * @var SettingsDiscoveryInterface&MockObject
	 */
	private SettingsDiscoveryInterface $discovery;

	/**
	 * Sets up the test fixture.
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->loader = $this->createMock(CommandLoaderInterface::class);
		$this->discovery = $this->createMock(SettingsDiscoveryInterface::class);
	}

	/**
	 * Tests that constructor creates instance correctly.
	 */
	public function test_constructor_createsInstance(): void
	{
		$registry = new CommandRegistry($this->loader, $this->discovery);

		$this->assertInstanceOf(CommandRegistryInterface::class, $registry);
	}

	/**
	 * Tests that register adds command to registry.
	 */
	public function test_register_addsCommandToRegistry(): void
	{
		$command = $this->createCommand('test', 'Test command', 'Test body');

		$registry = new CommandRegistry($this->loader, $this->discovery);
		$registry->register($command);

		$this->assertTrue($registry->has('test'));
		$this->assertSame($command, $registry->get('test'));
	}

	/**
	 * Tests that get returns null for non-existent command.
	 */
	public function test_get_withNonExistentCommand_returnsNull(): void
	{
		$registry = new CommandRegistry($this->loader, $this->discovery);

		$result = $registry->get('non-existent');

		$this->assertNull($result);
	}

	/**
	 * Tests that has returns false for non-existent command.
	 */
	public function test_has_withNonExistentCommand_returnsFalse(): void
	{
		$registry = new CommandRegistry($this->loader, $this->discovery);

		$result = $registry->has('non-existent');

		$this->assertFalse($result);
	}

	/**
	 * Tests that all returns all registered commands.
	 */
	public function test_all_returnsAllRegisteredCommands(): void
	{
		$command1 = $this->createCommand('cmd1', 'Command 1', 'Body 1');
		$command2 = $this->createCommand('cmd2', 'Command 2', 'Body 2');

		$registry = new CommandRegistry($this->loader, $this->discovery);
		$registry->register($command1);
		$registry->register($command2);

		$all = $registry->all();

		$this->assertCount(2, $all);
		$this->assertArrayHasKey('cmd1', $all);
		$this->assertArrayHasKey('cmd2', $all);
	}

	/**
	 * Tests that all returns empty array when no commands registered.
	 */
	public function test_all_withNoCommands_returnsEmptyArray(): void
	{
		$registry = new CommandRegistry($this->loader, $this->discovery);

		$all = $registry->all();

		$this->assertSame([], $all);
	}

	/**
	 * Tests that register overwrites existing command with same name.
	 */
	public function test_register_withSameName_overwritesExisting(): void
	{
		$command1 = $this->createCommand('test', 'First version', 'First body');
		$command2 = $this->createCommand('test', 'Second version', 'Second body');

		$registry = new CommandRegistry($this->loader, $this->discovery);
		$registry->register($command1);
		$registry->register($command2);

		$result = $registry->get('test');

		$this->assertSame($command2, $result);
		$this->assertSame('Second version', $result->getDescription());
	}

	/**
	 * Tests that discover loads commands from discovery.
	 */
	public function test_discover_loadsCommandsFromDiscovery(): void
	{
		$discovered_files = [
			'review' => '/project/.wp-ai-agent/commands/review.md',
			'commit' => '/project/.wp-ai-agent/commands/commit.md',
		];

		$this->discovery
			->expects($this->once())
			->method('discover')
			->with('commands', 'md')
			->willReturn($discovered_files);

		$review_cmd = $this->createCommand('review', 'Review code', 'Review body');
		$commit_cmd = $this->createCommand('commit', 'Create commit', 'Commit body');

		$this->loader
			->expects($this->exactly(2))
			->method('load')
			->willReturnCallback(function (string $filepath) use ($review_cmd, $commit_cmd) {
				return match ($filepath) {
					'/project/.wp-ai-agent/commands/review.md' => $review_cmd,
					'/project/.wp-ai-agent/commands/commit.md' => $commit_cmd,
					default => throw new \RuntimeException('Unexpected filepath'),
				};
			});

		$registry = new CommandRegistry($this->loader, $this->discovery);
		$registry->discover();

		$this->assertTrue($registry->has('review'));
		$this->assertTrue($registry->has('commit'));
	}

	/**
	 * Tests that discover handles empty discovery result.
	 */
	public function test_discover_withNoFiles_registersNoCommands(): void
	{
		$this->discovery
			->method('discover')
			->willReturn([]);

		$registry = new CommandRegistry($this->loader, $this->discovery);
		$registry->discover();

		$this->assertSame([], $registry->all());
	}

	/**
	 * Tests that discover uses namespaced command names.
	 */
	public function test_discover_withNamespacedCommand_usesNamespacedName(): void
	{
		$discovered_files = [
			'review' => '/project/.wp-ai-agent/commands/frontend/review.md',
		];

		$this->discovery
			->method('discover')
			->willReturn($discovered_files);

		$command = $this->createCommand('review', 'Frontend review', 'Review body', 'frontend');

		$this->loader
			->method('load')
			->willReturn($command);

		$registry = new CommandRegistry($this->loader, $this->discovery);
		$registry->discover();

		// Command should be accessible with namespaced name
		$this->assertTrue($registry->has('frontend:review'));
		$this->assertSame($command, $registry->get('frontend:review'));
	}

	/**
	 * Tests that discover handles commands without namespace.
	 */
	public function test_discover_withRootLevelCommand_usesSimpleName(): void
	{
		$discovered_files = [
			'simple' => '/project/.wp-ai-agent/commands/simple.md',
		];

		$this->discovery
			->method('discover')
			->willReturn($discovered_files);

		$command = $this->createCommand('simple', 'Simple command', 'Simple body', null);

		$this->loader
			->method('load')
			->willReturn($command);

		$registry = new CommandRegistry($this->loader, $this->discovery);
		$registry->discover();

		$this->assertTrue($registry->has('simple'));
		$this->assertSame($command, $registry->get('simple'));
	}

	/**
	 * Tests that discover can be called multiple times.
	 */
	public function test_discover_calledMultipleTimes_mergesCommands(): void
	{
		$this->discovery
			->method('discover')
			->willReturn([
				'cmd1' => '/project/.wp-ai-agent/commands/cmd1.md',
			]);

		$command = $this->createCommand('cmd1', 'Command 1', 'Body 1');

		$this->loader
			->method('load')
			->willReturn($command);

		$registry = new CommandRegistry($this->loader, $this->discovery);
		$registry->discover();
		$registry->discover();

		// Should still have only one command
		$this->assertCount(1, $registry->all());
	}

	/**
	 * Tests that getCustomCommands returns only file-loaded commands.
	 */
	public function test_getCustomCommands_returnsOnlyFileLoadedCommands(): void
	{
		$file_cmd = $this->createCommand(
			'file-cmd',
			'File command',
			'File body',
			null,
			'/project/.wp-ai-agent/commands/file-cmd.md'
		);
		$builtin_cmd = $this->createCommand('builtin-cmd', 'Built-in command', 'Built-in body');

		$registry = new CommandRegistry($this->loader, $this->discovery);
		$registry->register($file_cmd);
		$registry->register($builtin_cmd);

		$custom = $registry->getCustomCommands();

		$this->assertCount(1, $custom);
		$this->assertArrayHasKey('file-cmd', $custom);
		$this->assertArrayNotHasKey('builtin-cmd', $custom);
	}

	/**
	 * Tests that getCustomCommands returns empty array when no custom commands.
	 */
	public function test_getCustomCommands_withNoCustomCommands_returnsEmptyArray(): void
	{
		$builtin_cmd = $this->createCommand('builtin-cmd', 'Built-in command', 'Built-in body');

		$registry = new CommandRegistry($this->loader, $this->discovery);
		$registry->register($builtin_cmd);

		$custom = $registry->getCustomCommands();

		$this->assertSame([], $custom);
	}

	/**
	 * Tests that get works with namespaced names.
	 */
	public function test_get_withNamespacedName_returnsCommand(): void
	{
		$command = $this->createCommand('review', 'Frontend review', 'Review body', 'frontend');

		$registry = new CommandRegistry($this->loader, $this->discovery);
		$registry->register($command);

		// Should be accessible via namespaced name
		$result = $registry->get('frontend:review');

		$this->assertSame($command, $result);
	}

	/**
	 * Tests that has works with namespaced names.
	 */
	public function test_has_withNamespacedName_returnsTrue(): void
	{
		$command = $this->createCommand('review', 'Frontend review', 'Review body', 'frontend');

		$registry = new CommandRegistry($this->loader, $this->discovery);
		$registry->register($command);

		$this->assertTrue($registry->has('frontend:review'));
		$this->assertFalse($registry->has('review'));
	}

	/**
	 * Tests that register with namespaced command uses full name as key.
	 */
	public function test_register_withNamespacedCommand_usesFullNameAsKey(): void
	{
		$command = $this->createCommand('button', 'Button component', 'Button body', 'frontend/components');

		$registry = new CommandRegistry($this->loader, $this->discovery);
		$registry->register($command);

		$all = $registry->all();

		$this->assertArrayHasKey('frontend/components:button', $all);
		$this->assertCount(1, $all);
	}

	/**
	 * Tests that project commands override user commands with same name.
	 */
	public function test_discover_projectOverridesUser(): void
	{
		// Discovery returns files in order: user first, then project (project wins)
		$discovered_files = [
			'shared' => '/project/.wp-ai-agent/commands/shared.md', // Project version
		];

		$this->discovery
			->method('discover')
			->willReturn($discovered_files);

		$command = $this->createCommand(
			'shared',
			'Project version',
			'Project body',
			null,
			'/project/.wp-ai-agent/commands/shared.md'
		);

		$this->loader
			->method('load')
			->willReturn($command);

		$registry = new CommandRegistry($this->loader, $this->discovery);
		$registry->discover();

		$result = $registry->get('shared');

		$this->assertSame('Project version', $result->getDescription());
	}

	/**
	 * Tests that discover handles load errors gracefully by skipping failed files.
	 */
	public function test_discover_withLoadError_skipsFailedFileAndContinues(): void
	{
		$discovered_files = [
			'good' => '/project/.wp-ai-agent/commands/good.md',
			'bad' => '/project/.wp-ai-agent/commands/bad.md',
		];

		$this->discovery
			->method('discover')
			->willReturn($discovered_files);

		$good_cmd = $this->createCommand('good', 'Good command', 'Good body');

		$this->loader
			->method('load')
			->willReturnCallback(function (string $filepath) use ($good_cmd) {
				if (str_contains($filepath, 'bad')) {
					throw new \RuntimeException('Failed to load');
				}
				return $good_cmd;
			});

		$registry = new CommandRegistry($this->loader, $this->discovery);
		$registry->discover();

		$this->assertTrue($registry->has('good'));
		$this->assertFalse($registry->has('bad'));
	}

	/**
	 * Tests that discover with deeply nested namespace formats correctly.
	 */
	public function test_discover_withDeeplyNestedNamespace_formatsCorrectly(): void
	{
		$discovered_files = [
			'deep' => '/project/.wp-ai-agent/commands/a/b/c/deep.md',
		];

		$this->discovery
			->method('discover')
			->willReturn($discovered_files);

		$command = $this->createCommand('deep', 'Deep command', 'Deep body', 'a/b/c');

		$this->loader
			->method('load')
			->willReturn($command);

		$registry = new CommandRegistry($this->loader, $this->discovery);
		$registry->discover();

		$this->assertTrue($registry->has('a/b/c:deep'));
	}

	/**
	 * Creates a command instance for testing.
	 *
	 * @param string      $name        The command name.
	 * @param string      $description The command description.
	 * @param string      $body        The command body.
	 * @param string|null $namespace   The command namespace.
	 * @param string|null $filepath    The source file path.
	 *
	 * @return Command
	 */
	private function createCommand(
		string $name,
		string $description,
		string $body,
		?string $namespace = null,
		?string $filepath = null
	): Command {
		return new Command(
			$name,
			$description,
			$body,
			CommandConfig::fromFrontmatter([]),
			$filepath,
			$namespace
		);
	}
}
