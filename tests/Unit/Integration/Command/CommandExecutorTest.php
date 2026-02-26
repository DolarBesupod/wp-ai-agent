<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Integration\Command;

use PHPUnit\Framework\TestCase;
use WpAiAgent\Core\Command\Command;
use WpAiAgent\Core\Command\CommandConfig;
use WpAiAgent\Core\Command\CommandExecutionResult;
use WpAiAgent\Core\Contracts\ArgumentSubstitutorInterface;
use WpAiAgent\Core\Contracts\BashCommandExpanderInterface;
use WpAiAgent\Core\Contracts\FileReferenceExpanderInterface;
use WpAiAgent\Core\ValueObjects\ArgumentList;
use WpAiAgent\Integration\Command\CommandExecutor;
use RuntimeException;

/**
 * Tests for CommandExecutor.
 *
 * @covers \WpAiAgent\Integration\Command\CommandExecutor
 */
final class CommandExecutorTest extends TestCase
{
	private ArgumentSubstitutorInterface $argument_substitutor;
	private FileReferenceExpanderInterface $file_reference_expander;
	private BashCommandExpanderInterface $bash_command_expander;
	private CommandExecutor $executor;

	protected function setUp(): void
	{
		$this->argument_substitutor = $this->createMock(ArgumentSubstitutorInterface::class);
		$this->file_reference_expander = $this->createMock(FileReferenceExpanderInterface::class);
		$this->bash_command_expander = $this->createMock(BashCommandExpanderInterface::class);

		$this->executor = new CommandExecutor(
			$this->argument_substitutor,
			$this->file_reference_expander,
			$this->bash_command_expander
		);
	}

	public function test_execute_withNoExpansions_returnsOriginalContent(): void
	{
		$command = $this->createCommand('Simple content with no placeholders');
		$arguments = ArgumentList::fromString('');

		$this->argument_substitutor
			->method('substitute')
			->with('Simple content with no placeholders', $arguments)
			->willReturn('Simple content with no placeholders');

		$this->file_reference_expander
			->method('expand')
			->with('Simple content with no placeholders', '/path/to')
			->willReturn('Simple content with no placeholders');

		$this->bash_command_expander
			->method('expand')
			->with('Simple content with no placeholders', '/path/to')
			->willReturn('Simple content with no placeholders');

		$result = $this->executor->execute($command, $arguments);

		$this->assertTrue($result->isSuccess());
		$this->assertSame('Simple content with no placeholders', $result->getExpandedContent());
		$this->assertTrue($result->shouldInjectIntoConversation());
	}

	public function test_execute_withArgumentPlaceholders_substitutesArguments(): void
	{
		$command = $this->createCommand('Fix issue #$1 in $2');
		$arguments = ArgumentList::fromString('123 main-branch');

		$this->argument_substitutor
			->method('substitute')
			->with('Fix issue #$1 in $2', $arguments)
			->willReturn('Fix issue #123 in main-branch');

		$this->file_reference_expander
			->method('expand')
			->willReturn('Fix issue #123 in main-branch');

		$this->bash_command_expander
			->method('expand')
			->willReturn('Fix issue #123 in main-branch');

		$result = $this->executor->execute($command, $arguments);

		$this->assertTrue($result->isSuccess());
		$this->assertSame('Fix issue #123 in main-branch', $result->getExpandedContent());
	}

	public function test_execute_withFileReference_expandsFileContent(): void
	{
		$command = $this->createCommand('Context: @README.md');
		$arguments = ArgumentList::fromString('');

		$this->argument_substitutor
			->method('substitute')
			->willReturn('Context: @README.md');

		$this->file_reference_expander
			->method('expand')
			->with('Context: @README.md', '/path/to')
			->willReturn("Context: ```md\n# README\nThis is the readme.\n```");

		$this->bash_command_expander
			->method('expand')
			->willReturn("Context: ```md\n# README\nThis is the readme.\n```");

		$result = $this->executor->execute($command, $arguments);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('# README', $result->getExpandedContent());
	}

	public function test_execute_withBashCommand_expandsCommandOutput(): void
	{
		$command = $this->createCommand('Status: !`git status`');
		$arguments = ArgumentList::fromString('');

		$this->argument_substitutor
			->method('substitute')
			->willReturn('Status: !`git status`');

		$this->file_reference_expander
			->method('expand')
			->willReturn('Status: !`git status`');

		$this->bash_command_expander
			->method('expand')
			->with('Status: !`git status`', '/path/to')
			->willReturn("Status: ```\nOn branch main\nnothing to commit\n```");

		$result = $this->executor->execute($command, $arguments);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('On branch main', $result->getExpandedContent());
	}

	public function test_execute_appliesExpansionsInCorrectOrder(): void
	{
		$command = $this->createCommand('Fix issue #$1\n\nContext: @README.md\n\nStatus: !`git status`');
		$arguments = ArgumentList::fromString('123');

		// Step 1: Argument substitution
		$this->argument_substitutor
			->expects($this->once())
			->method('substitute')
			->with('Fix issue #$1\n\nContext: @README.md\n\nStatus: !`git status`', $arguments)
			->willReturn('Fix issue #123\n\nContext: @README.md\n\nStatus: !`git status`');

		// Step 2: File reference expansion (after argument substitution)
		$this->file_reference_expander
			->expects($this->once())
			->method('expand')
			->with('Fix issue #123\n\nContext: @README.md\n\nStatus: !`git status`', '/path/to')
			->willReturn("Fix issue #123\n\nContext: ```md\n# README\n```\n\nStatus: !`git status`");

		// Step 3: Bash command expansion (after file reference expansion)
		$this->bash_command_expander
			->expects($this->once())
			->method('expand')
			->with("Fix issue #123\n\nContext: ```md\n# README\n```\n\nStatus: !`git status`", '/path/to')
			->willReturn("Fix issue #123\n\nContext: ```md\n# README\n```\n\nStatus: ```\nOn branch main\n```");

		$result = $this->executor->execute($command, $arguments);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('Fix issue #123', $result->getExpandedContent());
		$this->assertStringContainsString('# README', $result->getExpandedContent());
		$this->assertStringContainsString('On branch main', $result->getExpandedContent());
	}

	public function test_execute_withFileReferenceError_includesErrorInlineAndContinues(): void
	{
		$command = $this->createCommand('Context: @nonexistent.md\n\nStatus: !`git status`');
		$arguments = ArgumentList::fromString('');

		$this->argument_substitutor
			->method('substitute')
			->willReturn('Context: @nonexistent.md\n\nStatus: !`git status`');

		$this->file_reference_expander
			->method('expand')
			->willThrowException(new RuntimeException('File not found: nonexistent.md'));

		$this->bash_command_expander
			->method('expand')
			->willReturn("Context: [File not found: nonexistent.md]\n\nStatus: ```\nOn branch main\n```");

		$result = $this->executor->execute($command, $arguments);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('[File not found: nonexistent.md]', $result->getExpandedContent());
	}

	public function test_execute_withBashCommandError_includesErrorInlineAndContinues(): void
	{
		$command = $this->createCommand('Status: !`invalid-command`');
		$arguments = ArgumentList::fromString('');

		$this->argument_substitutor
			->method('substitute')
			->willReturn('Status: !`invalid-command`');

		$this->file_reference_expander
			->method('expand')
			->willReturn('Status: !`invalid-command`');

		$this->bash_command_expander
			->method('expand')
			->willThrowException(new RuntimeException('Command failed: invalid-command'));

		$result = $this->executor->execute($command, $arguments);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('[Command failed: invalid-command]', $result->getExpandedContent());
	}

	public function test_execute_withMultipleErrors_collectsAllErrors(): void
	{
		$command = $this->createCommand('@missing.md and !`bad-cmd`');
		$arguments = ArgumentList::fromString('');

		$this->argument_substitutor
			->method('substitute')
			->willReturn('@missing.md and !`bad-cmd`');

		$this->file_reference_expander
			->method('expand')
			->willThrowException(new RuntimeException('File not found: missing.md'));

		$this->bash_command_expander
			->method('expand')
			->willThrowException(new RuntimeException('Command failed: bad-cmd'));

		$result = $this->executor->execute($command, $arguments);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('[File not found: missing.md]', $result->getExpandedContent());
		$this->assertStringContainsString('[Command failed: bad-cmd]', $result->getExpandedContent());
	}

	public function test_execute_withBuiltInCommand_usesCurrentWorkingDirectory(): void
	{
		$command = new Command(
			name: 'builtin',
			description: 'A built-in command',
			body: 'Status: !`pwd`',
			config: CommandConfig::fromFrontmatter([]),
			filepath: null, // Built-in command has no filepath
			namespace: null
		);
		$arguments = ArgumentList::fromString('');

		$this->argument_substitutor
			->method('substitute')
			->willReturn('Status: !`pwd`');

		// For built-in commands, should use getcwd() as base path
		$this->file_reference_expander
			->method('expand')
			->willReturnCallback(function (string $content, string $base_path): string {
				$this->assertSame(getcwd(), $base_path);
				return $content;
			});

		$this->bash_command_expander
			->method('expand')
			->willReturnCallback(function (string $content, string $working_dir): string {
				$this->assertSame(getcwd(), $working_dir);
				return "Status: ```\n" . getcwd() . "\n```";
			});

		$result = $this->executor->execute($command, $arguments);

		$this->assertTrue($result->isSuccess());
	}

	public function test_execute_usesCommandFileDirectoryAsBasePath(): void
	{
		$command = new Command(
			name: 'test',
			description: 'A test command',
			body: '@./relative.md',
			config: CommandConfig::fromFrontmatter([]),
			filepath: '/home/user/.config/commands/test.md',
			namespace: 'project'
		);
		$arguments = ArgumentList::fromString('');

		$this->argument_substitutor
			->method('substitute')
			->willReturn('@./relative.md');

		// Should use command file's directory as base path
		$this->file_reference_expander
			->method('expand')
			->willReturnCallback(function (string $content, string $base_path): string {
				$this->assertSame('/home/user/.config/commands', $base_path);
				return 'Expanded content';
			});

		$this->bash_command_expander
			->method('expand')
			->willReturnCallback(function (string $content, string $working_dir): string {
				$this->assertSame('/home/user/.config/commands', $working_dir);
				return $content;
			});

		$result = $this->executor->execute($command, $arguments);

		$this->assertTrue($result->isSuccess());
	}

	public function test_execute_returnsResultReadyForInjection(): void
	{
		$command = $this->createCommand('Simple prompt');
		$arguments = ArgumentList::fromString('');

		$this->argument_substitutor
			->method('substitute')
			->willReturn('Simple prompt');

		$this->file_reference_expander
			->method('expand')
			->willReturn('Simple prompt');

		$this->bash_command_expander
			->method('expand')
			->willReturn('Simple prompt');

		$result = $this->executor->execute($command, $arguments);

		$this->assertInstanceOf(CommandExecutionResult::class, $result);
		$this->assertTrue($result->isSuccess());
		$this->assertTrue($result->shouldInjectIntoConversation());
		$this->assertSame('Simple prompt', $result->getExpandedContent());
	}

	public function test_execute_withEmptyBody_returnsEmptyExpandedContent(): void
	{
		$command = $this->createCommand('');
		$arguments = ArgumentList::fromString('');

		$this->argument_substitutor
			->method('substitute')
			->willReturn('');

		$this->file_reference_expander
			->method('expand')
			->willReturn('');

		$this->bash_command_expander
			->method('expand')
			->willReturn('');

		$result = $this->executor->execute($command, $arguments);

		$this->assertTrue($result->isSuccess());
		$this->assertSame('', $result->getExpandedContent());
	}

	public function test_execute_withArgumentsVariable_substitutesFullArgumentString(): void
	{
		$command = $this->createCommand('User said: $ARGUMENTS');
		$arguments = ArgumentList::fromString('hello world "quoted string"');

		$this->argument_substitutor
			->method('substitute')
			->with('User said: $ARGUMENTS', $arguments)
			->willReturn('User said: hello world "quoted string"');

		$this->file_reference_expander
			->method('expand')
			->willReturn('User said: hello world "quoted string"');

		$this->bash_command_expander
			->method('expand')
			->willReturn('User said: hello world "quoted string"');

		$result = $this->executor->execute($command, $arguments);

		$this->assertTrue($result->isSuccess());
		$this->assertSame('User said: hello world "quoted string"', $result->getExpandedContent());
	}

	public function test_execute_withMixedContent_processesAllTypes(): void
	{
		$body = 'Fixing: $ARGUMENTS' . "\n\n" . '@CONTEXT.md' . "\n\n" . 'Current state: !`git diff --stat`';
		$command = $this->createCommand($body);
		$arguments = ArgumentList::fromString('bug in login');

		$afterArgs = "Fixing: bug in login\n\n@CONTEXT.md\n\nCurrent state: !" . '`git diff --stat`';
		$afterFiles = "Fixing: bug in login\n\n```md\nContext info\n```\n\nCurrent state: !" . '`git diff --stat`';
		$afterBash = "Fixing: bug in login\n\n```md\nContext info\n```\n\nCurrent state: ```\n1 file changed\n```";

		$this->argument_substitutor
			->method('substitute')
			->willReturn($afterArgs);

		$this->file_reference_expander
			->method('expand')
			->willReturn($afterFiles);

		$this->bash_command_expander
			->method('expand')
			->willReturn($afterBash);

		$result = $this->executor->execute($command, $arguments);

		$this->assertTrue($result->isSuccess());
		$this->assertSame($afterBash, $result->getExpandedContent());
	}

	/**
	 * Creates a Command instance for testing.
	 *
	 * @param string $body The command body content.
	 *
	 * @return Command
	 */
	private function createCommand(string $body): Command
	{
		return new Command(
			name: 'test',
			description: 'A test command',
			body: $body,
			config: CommandConfig::fromFrontmatter([]),
			filepath: '/path/to/command.md',
			namespace: 'test'
		);
	}
}
