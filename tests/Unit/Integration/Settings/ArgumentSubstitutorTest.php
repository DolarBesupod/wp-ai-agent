<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Integration\Settings;

use WpAiAgent\Core\Contracts\ArgumentSubstitutorInterface;
use WpAiAgent\Core\ValueObjects\ArgumentList;
use WpAiAgent\Integration\Settings\ArgumentSubstitutor;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ArgumentSubstitutor.
 *
 * @covers \WpAiAgent\Integration\Settings\ArgumentSubstitutor
 */
final class ArgumentSubstitutorTest extends TestCase
{
	/**
	 * The argument substitutor instance.
	 *
	 * @var ArgumentSubstitutor
	 */
	private ArgumentSubstitutor $substitutor;

	/**
	 * Sets up the test fixture.
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->substitutor = new ArgumentSubstitutor();
	}

	/**
	 * Tests that constructor creates instance implementing the interface.
	 */
	public function test_constructor_implementsInterface(): void
	{
		$this->assertInstanceOf(ArgumentSubstitutorInterface::class, $this->substitutor);
	}

	/**
	 * Tests that substitute replaces $1 placeholder with first argument.
	 */
	public function test_substitute_withDollarOne_replacesWithFirstArgument(): void
	{
		$content = 'Search for files matching: $1';
		$arguments = ArgumentList::fromString('*.php');

		$result = $this->substitutor->substitute($content, $arguments);

		$this->assertSame('Search for files matching: *.php', $result);
	}

	/**
	 * Tests that substitute replaces multiple numbered placeholders.
	 */
	public function test_substitute_withMultipleNumberedPlaceholders_replacesAll(): void
	{
		$content = 'Source: $1, Destination: $2, Mode: $3';
		$arguments = ArgumentList::fromString('input.txt output.txt append');

		$result = $this->substitutor->substitute($content, $arguments);

		$this->assertSame('Source: input.txt, Destination: output.txt, Mode: append', $result);
	}

	/**
	 * Tests that substitute replaces $ARGUMENTS with full argument string.
	 */
	public function test_substitute_withDollarArguments_replacesWithFullString(): void
	{
		$content = 'Full command arguments: $ARGUMENTS';
		$arguments = ArgumentList::fromString('arg1 arg2 arg3');

		$result = $this->substitutor->substitute($content, $arguments);

		$this->assertSame('Full command arguments: arg1 arg2 arg3', $result);
	}

	/**
	 * Tests that substitute handles both numbered and $ARGUMENTS placeholders.
	 */
	public function test_substitute_withMixedPlaceholders_replacesAll(): void
	{
		$content = 'First arg: $1, All args: $ARGUMENTS';
		$arguments = ArgumentList::fromString('alpha beta gamma');

		$result = $this->substitutor->substitute($content, $arguments);

		$this->assertSame('First arg: alpha, All args: alpha beta gamma', $result);
	}

	/**
	 * Tests that substitute replaces missing positional arguments with empty string.
	 */
	public function test_substitute_withMissingPositionalArgument_replacesWithEmpty(): void
	{
		$content = 'First: $1, Second: $2, Third: $3';
		$arguments = ArgumentList::fromString('only-one');

		$result = $this->substitutor->substitute($content, $arguments);

		$this->assertSame('First: only-one, Second: , Third: ', $result);
	}

	/**
	 * Tests that substitute handles empty argument list.
	 */
	public function test_substitute_withEmptyArguments_replacesPlaceholdersWithEmpty(): void
	{
		$content = 'Arg: $1, All: $ARGUMENTS';
		$arguments = ArgumentList::fromString('');

		$result = $this->substitutor->substitute($content, $arguments);

		$this->assertSame('Arg: , All: ', $result);
	}

	/**
	 * Tests that substitute returns content unchanged when no placeholders.
	 */
	public function test_substitute_withNoPlaceholders_returnsContentUnchanged(): void
	{
		$content = 'This is plain content without any placeholders.';
		$arguments = ArgumentList::fromString('arg1 arg2');

		$result = $this->substitutor->substitute($content, $arguments);

		$this->assertSame($content, $result);
	}

	/**
	 * Tests that substitute handles same placeholder multiple times.
	 */
	public function test_substitute_withRepeatedPlaceholder_replacesAllOccurrences(): void
	{
		$content = '$1 is the value, and again: $1';
		$arguments = ArgumentList::fromString('test');

		$result = $this->substitutor->substitute($content, $arguments);

		$this->assertSame('test is the value, and again: test', $result);
	}

	/**
	 * Tests that substitute handles high numbered placeholders.
	 */
	public function test_substitute_withHighNumberedPlaceholder_replacesCorrectly(): void
	{
		$content = 'Tenth arg: $10';
		$arguments = ArgumentList::fromString('1 2 3 4 5 6 7 8 9 tenth');

		$result = $this->substitutor->substitute($content, $arguments);

		$this->assertSame('Tenth arg: tenth', $result);
	}

	/**
	 * Tests that substitute does not replace $0.
	 */
	public function test_substitute_withDollarZero_doesNotReplace(): void
	{
		$content = 'Zero: $0, First: $1';
		$arguments = ArgumentList::fromString('first-arg');

		$result = $this->substitutor->substitute($content, $arguments);

		$this->assertSame('Zero: $0, First: first-arg', $result);
	}

	/**
	 * Tests that substitute handles quoted arguments correctly.
	 */
	public function test_substitute_withQuotedArguments_preservesQuotedContent(): void
	{
		$content = 'Message: $1';
		$arguments = ArgumentList::fromString('"hello world"');

		$result = $this->substitutor->substitute($content, $arguments);

		$this->assertSame('Message: hello world', $result);
	}

	/**
	 * Tests that substitute handles $ARGUMENTS with quoted parts.
	 */
	public function test_substitute_withArgumentsContainingQuoted_preservesRawInput(): void
	{
		$content = 'Raw: $ARGUMENTS';
		$arguments = ArgumentList::fromString('"hello world" test');

		$result = $this->substitutor->substitute($content, $arguments);

		$this->assertSame('Raw: "hello world" test', $result);
	}

	/**
	 * Tests that substitute handles content with dollar signs that are not placeholders.
	 */
	public function test_substitute_withNonPlaceholderDollarSign_leavesUnchanged(): void
	{
		$content = 'Price is $100 and first arg is $1';
		$arguments = ArgumentList::fromString('test');

		$result = $this->substitutor->substitute($content, $arguments);

		$this->assertSame('Price is $100 and first arg is test', $result);
	}

	/**
	 * Tests that substitute handles $ARGUMENTS as case-sensitive.
	 */
	public function test_substitute_withLowercaseArguments_doesNotReplace(): void
	{
		$content = 'Lower: $arguments, Upper: $ARGUMENTS';
		$arguments = ArgumentList::fromString('test');

		$result = $this->substitutor->substitute($content, $arguments);

		$this->assertSame('Lower: $arguments, Upper: test', $result);
	}

	/**
	 * Tests that substitute handles multiline content.
	 */
	public function test_substitute_withMultilineContent_replacesInAllLines(): void
	{
		$content = "Line 1: \$1\nLine 2: \$2\nLine 3: \$ARGUMENTS";
		$arguments = ArgumentList::fromString('first second');

		$result = $this->substitutor->substitute($content, $arguments);

		$this->assertSame("Line 1: first\nLine 2: second\nLine 3: first second", $result);
	}

	/**
	 * Tests that substitute handles placeholders in markdown code blocks.
	 */
	public function test_substitute_inCodeBlock_stillReplacesPlaceholders(): void
	{
		$content = "```bash\ngrep \$1 \$2\n```";
		$arguments = ArgumentList::fromString('pattern file.txt');

		$result = $this->substitutor->substitute($content, $arguments);

		$this->assertSame("```bash\ngrep pattern file.txt\n```", $result);
	}

	/**
	 * Tests that substitute handles adjacent placeholders.
	 */
	public function test_substitute_withAdjacentPlaceholders_replacesCorrectly(): void
	{
		$content = '$1$2$3';
		$arguments = ArgumentList::fromString('a b c');

		$result = $this->substitutor->substitute($content, $arguments);

		$this->assertSame('abc', $result);
	}

	/**
	 * Tests that substitute handles placeholder at start of content.
	 */
	public function test_substitute_withPlaceholderAtStart_replacesCorrectly(): void
	{
		$content = '$1 is the value';
		$arguments = ArgumentList::fromString('test');

		$result = $this->substitutor->substitute($content, $arguments);

		$this->assertSame('test is the value', $result);
	}

	/**
	 * Tests that substitute handles placeholder at end of content.
	 */
	public function test_substitute_withPlaceholderAtEnd_replacesCorrectly(): void
	{
		$content = 'The value is $1';
		$arguments = ArgumentList::fromString('test');

		$result = $this->substitutor->substitute($content, $arguments);

		$this->assertSame('The value is test', $result);
	}

	/**
	 * Tests that substitute handles empty content.
	 */
	public function test_substitute_withEmptyContent_returnsEmpty(): void
	{
		$content = '';
		$arguments = ArgumentList::fromString('test');

		$result = $this->substitutor->substitute($content, $arguments);

		$this->assertSame('', $result);
	}

	/**
	 * Tests that substitute handles content with only placeholder.
	 */
	public function test_substitute_withOnlyPlaceholder_replacesCompletely(): void
	{
		$content = '$1';
		$arguments = ArgumentList::fromString('replacement');

		$result = $this->substitutor->substitute($content, $arguments);

		$this->assertSame('replacement', $result);
	}

	/**
	 * Tests that substitute handles backslash before placeholder.
	 */
	public function test_substitute_withBackslashBeforePlaceholder_stillReplaces(): void
	{
		$content = 'Path: \\$1';
		$arguments = ArgumentList::fromString('test');

		$result = $this->substitutor->substitute($content, $arguments);

		// The backslash is kept, placeholder is still replaced
		$this->assertSame('Path: \\test', $result);
	}
}
