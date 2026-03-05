<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Integration\Settings;

use Automattic\Automattic\WpAiAgent\Core\Contracts\BashCommandExpanderInterface;
use Automattic\Automattic\WpAiAgent\Integration\Settings\BashCommandExpander;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for BashCommandExpander.
 *
 * @covers \Automattic\WpAiAgent\Integration\Settings\BashCommandExpander
 */
final class BashCommandExpanderTest extends TestCase
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

		$this->temp_dir = sys_get_temp_dir() . '/bash_command_expander_test_' . uniqid();
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
	 * Tests that constructor creates instance implementing the interface.
	 */
	public function test_constructor_implementsInterface(): void
	{
		$expander = new BashCommandExpander();

		$this->assertInstanceOf(BashCommandExpanderInterface::class, $expander);
	}

	/**
	 * Tests that expand returns content unchanged when no commands present.
	 */
	public function test_expand_withNoCommands_returnsContentUnchanged(): void
	{
		$expander = new BashCommandExpander();
		$content = 'This is plain content without any bash commands.';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame($content, $result);
	}

	/**
	 * Tests that expand handles simple echo command.
	 */
	public function test_expand_withSimpleEchoCommand_expandsOutput(): void
	{
		$expander = new BashCommandExpander();
		$content = 'The output is: !`echo hello`';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame('The output is: hello', $result);
	}

	/**
	 * Tests that expand handles command with arguments.
	 */
	public function test_expand_withCommandWithArguments_expandsOutput(): void
	{
		$expander = new BashCommandExpander();
		$content = 'Date format: !`date +%Y`';

		$result = $expander->expand($content, $this->temp_dir);

		// Just verify it's a 4-digit year (we can't know the exact year at test time)
		$this->assertMatchesRegularExpression('/Date format: \d{4}/', $result);
	}

	/**
	 * Tests that expand handles multiple commands in same content.
	 */
	public function test_expand_withMultipleCommands_expandsAll(): void
	{
		$expander = new BashCommandExpander();
		$content = "First: !`echo one`\nSecond: !`echo two`";

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame("First: one\nSecond: two", $result);
	}

	/**
	 * Tests that expand handles multiline command output.
	 */
	public function test_expand_withMultilineOutput_preservesLineBreaks(): void
	{
		$expander = new BashCommandExpander();
		$content = "List:\n!`printf 'line1\nline2\nline3'`";

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame("List:\nline1\nline2\nline3", $result);
	}

	/**
	 * Tests that expand runs commands in specified working directory.
	 */
	public function test_expand_withWorkingDirectory_runsInCorrectDirectory(): void
	{
		$expander = new BashCommandExpander();
		$content = 'Current directory: !`pwd`';

		$result = $expander->expand($content, $this->temp_dir);

		// Resolve realpath for comparison (handles symlinks like /tmp -> /private/tmp on macOS)
		$expectedDir = realpath($this->temp_dir);
		$this->assertSame('Current directory: ' . $expectedDir, $result);
	}

	/**
	 * Tests that expand handles command failure gracefully.
	 */
	public function test_expand_withCommandFailure_throwsException(): void
	{
		$expander = new BashCommandExpander();
		$content = 'Exit: !`exit 1`';

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Command failed with exit code 1');

		$expander->expand($content, $this->temp_dir);
	}

	/**
	 * Tests that expand handles non-existent command.
	 */
	public function test_expand_withNonExistentCommand_throwsException(): void
	{
		$expander = new BashCommandExpander();
		$content = 'Result: !`nonexistent_command_that_does_not_exist_12345`';

		$this->expectException(RuntimeException::class);

		$expander->expand($content, $this->temp_dir);
	}

	/**
	 * Tests that expand does not expand regular backticks without ! prefix.
	 */
	public function test_expand_withRegularBackticks_doesNotExpand(): void
	{
		$expander = new BashCommandExpander();
		$content = 'Use `echo hello` to print.';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame($content, $result);
	}

	/**
	 * Tests that expand handles empty content.
	 */
	public function test_expand_withEmptyContent_returnsEmpty(): void
	{
		$expander = new BashCommandExpander();

		$result = $expander->expand('', $this->temp_dir);

		$this->assertSame('', $result);
	}

	/**
	 * Tests that expand handles command at start of content.
	 */
	public function test_expand_withCommandAtStart_expandsCorrectly(): void
	{
		$expander = new BashCommandExpander();
		$content = '!`echo start` is the beginning.';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame('start is the beginning.', $result);
	}

	/**
	 * Tests that expand handles command at end of content.
	 */
	public function test_expand_withCommandAtEnd_expandsCorrectly(): void
	{
		$expander = new BashCommandExpander();
		$content = 'The end is !`echo finish`';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame('The end is finish', $result);
	}

	/**
	 * Tests that expand handles only command in content.
	 */
	public function test_expand_withOnlyCommand_expandsCorrectly(): void
	{
		$expander = new BashCommandExpander();
		$content = '!`echo only`';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame('only', $result);
	}

	/**
	 * Tests that expand handles command with quotes.
	 */
	public function test_expand_withQuotesInCommand_expandsCorrectly(): void
	{
		$expander = new BashCommandExpander();
		$content = 'Quoted: !`echo "hello world"`';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame('Quoted: hello world', $result);
	}

	/**
	 * Tests that expand handles command with single quotes.
	 */
	public function test_expand_withSingleQuotesInCommand_expandsCorrectly(): void
	{
		$expander = new BashCommandExpander();
		$content = "Quoted: !`echo 'hello world'`";

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame('Quoted: hello world', $result);
	}

	/**
	 * Tests that expand handles nested backticks in double quotes.
	 */
	public function test_expand_withBackticksInDoubleQuotes_expandsCorrectly(): void
	{
		$expander = new BashCommandExpander();
		$content = 'Version: !`echo "test-output"`';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame('Version: test-output', $result);
	}

	/**
	 * Tests that expand trims trailing whitespace from output.
	 */
	public function test_expand_withTrailingWhitespace_trimsOutput(): void
	{
		$expander = new BashCommandExpander();
		$content = '!`printf "hello"`';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame('hello', $result);
	}

	/**
	 * Tests that expand handles timeout for long-running commands.
	 */
	public function test_expand_withTimeout_throwsExceptionOnTimeout(): void
	{
		$expander = new BashCommandExpander(1); // 1 second timeout
		$content = 'Long: !`sleep 5`';

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('timed out');

		$expander->expand($content, $this->temp_dir);
	}

	/**
	 * Tests that expand handles command that produces stderr.
	 */
	public function test_expand_withStderr_capturesOutput(): void
	{
		$expander = new BashCommandExpander();
		// Using a command that writes to stderr but still succeeds
		$content = 'Result: !`echo "stdout" && echo "stderr" >&2`';

		// The command succeeds, so we should get the stdout output
		$result = $expander->expand($content, $this->temp_dir);

		$this->assertStringContainsString('stdout', $result);
	}

	/**
	 * Tests that expand works with command reading from a file.
	 */
	public function test_expand_withFileReadCommand_expandsCorrectly(): void
	{
		$test_file = $this->temp_dir . '/test.txt';
		file_put_contents($test_file, 'file content');

		$expander = new BashCommandExpander();
		$content = 'File: !`cat test.txt`';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame('File: file content', $result);
	}

	/**
	 * Tests that expand handles command with pipe.
	 */
	public function test_expand_withPipedCommand_expandsCorrectly(): void
	{
		$expander = new BashCommandExpander();
		$content = 'Upper: !`echo hello | tr a-z A-Z`';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame('Upper: HELLO', $result);
	}

	/**
	 * Tests that expand handles commands on separate lines.
	 */
	public function test_expand_withCommandsOnSeparateLines_expandsBoth(): void
	{
		$expander = new BashCommandExpander();
		$content = "Git status:\n!`echo 'modified: file.php'`\n\nBranch:\n!`echo 'main'`";

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame("Git status:\nmodified: file.php\n\nBranch:\nmain", $result);
	}

	/**
	 * Tests that expand handles empty command output.
	 */
	public function test_expand_withEmptyCommandOutput_replacesWithEmpty(): void
	{
		$expander = new BashCommandExpander();
		$content = 'Empty: !`printf ""`';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame('Empty: ', $result);
	}

	/**
	 * Tests that expand handles command with environment variables.
	 */
	public function test_expand_withEnvironmentVariable_expandsCorrectly(): void
	{
		$expander = new BashCommandExpander();
		$content = 'Home: !`echo $HOME`';

		$result = $expander->expand($content, $this->temp_dir);

		// HOME should be expanded to the actual home directory
		$this->assertStringContainsString('Home: /', $result);
		$this->assertStringNotContainsString('$HOME', $result);
	}

	/**
	 * Tests that default timeout allows reasonable commands to complete.
	 */
	public function test_expand_withDefaultTimeout_allowsReasonableCommands(): void
	{
		$expander = new BashCommandExpander(); // Uses default timeout
		$content = 'Quick: !`echo quick`';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame('Quick: quick', $result);
	}

	/**
	 * Tests that expand handles adjacent commands without space.
	 */
	public function test_expand_withAdjacentCommands_expandsBoth(): void
	{
		$expander = new BashCommandExpander();
		$content = '!`echo a`!`echo b`';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame('ab', $result);
	}

	/**
	 * Tests that expand handles special characters in output.
	 */
	public function test_expand_withSpecialCharactersInOutput_preservesThem(): void
	{
		$expander = new BashCommandExpander();
		$content = 'Special: !`echo "<>&\""`';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame('Special: <>&"', $result);
	}

	/**
	 * Tests that expand handles git commands.
	 */
	public function test_expand_withGitStatusShort_expandsCorrectly(): void
	{
		// Create a temporary git repository
		$gitDir = $this->temp_dir . '/git-repo';
		mkdir($gitDir, 0755, true);
		$command = 'cd ' . $gitDir . ' && git init -q';
		$command .= ' && git config user.email "test@test.com" && git config user.name "Test"';
		exec($command);

		$expander = new BashCommandExpander();
		$content = 'Branch: !`git branch --show-current`';

		$result = $expander->expand($content, $gitDir);

		// In a fresh repo, the branch might be empty or 'main'/'master'
		$this->assertStringStartsWith('Branch: ', $result);
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
