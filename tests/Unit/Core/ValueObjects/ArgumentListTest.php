<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Core\ValueObjects;

use Automattic\WpAiAgent\Core\ValueObjects\ArgumentList;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ArgumentList value object.
 *
 * @covers \Automattic\WpAiAgent\Core\ValueObjects\ArgumentList
 */
final class ArgumentListTest extends TestCase
{
	/**
	 * Tests that fromString creates an instance from simple space-separated arguments.
	 */
	public function test_fromString_withSimpleArguments_parsesCorrectly(): void
	{
		$arguments = ArgumentList::fromString('arg1 arg2 arg3');

		$this->assertSame('arg1', $arguments->get(1));
		$this->assertSame('arg2', $arguments->get(2));
		$this->assertSame('arg3', $arguments->get(3));
		$this->assertSame(3, $arguments->count());
	}

	/**
	 * Tests that fromString handles empty string.
	 */
	public function test_fromString_withEmptyString_createsEmptyList(): void
	{
		$arguments = ArgumentList::fromString('');

		$this->assertSame(0, $arguments->count());
		$this->assertNull($arguments->get(1));
		$this->assertSame([], $arguments->getAll());
	}

	/**
	 * Tests that fromString handles whitespace-only string.
	 */
	public function test_fromString_withWhitespaceOnly_createsEmptyList(): void
	{
		$arguments = ArgumentList::fromString('   ');

		$this->assertSame(0, $arguments->count());
		$this->assertNull($arguments->get(1));
	}

	/**
	 * Tests that fromString handles double-quoted strings as single argument.
	 */
	public function test_fromString_withDoubleQuotedString_treatsAsOneArgument(): void
	{
		$arguments = ArgumentList::fromString('before "hello world" after');

		$this->assertSame('before', $arguments->get(1));
		$this->assertSame('hello world', $arguments->get(2));
		$this->assertSame('after', $arguments->get(3));
		$this->assertSame(3, $arguments->count());
	}

	/**
	 * Tests that fromString handles single-quoted strings as single argument.
	 */
	public function test_fromString_withSingleQuotedString_treatsAsOneArgument(): void
	{
		$arguments = ArgumentList::fromString("first 'quoted value' last");

		$this->assertSame('first', $arguments->get(1));
		$this->assertSame('quoted value', $arguments->get(2));
		$this->assertSame('last', $arguments->get(3));
		$this->assertSame(3, $arguments->count());
	}

	/**
	 * Tests that fromString handles mixed quote types.
	 */
	public function test_fromString_withMixedQuotes_parsesBothCorrectly(): void
	{
		$arguments = ArgumentList::fromString('"double quoted" \'single quoted\' unquoted');

		$this->assertSame('double quoted', $arguments->get(1));
		$this->assertSame('single quoted', $arguments->get(2));
		$this->assertSame('unquoted', $arguments->get(3));
	}

	/**
	 * Tests that fromString handles quoted strings with special characters.
	 */
	public function test_fromString_withSpecialCharsInQuotes_preservesSpecialChars(): void
	{
		$arguments = ArgumentList::fromString('"arg with $special chars" normal');

		$this->assertSame('arg with $special chars', $arguments->get(1));
		$this->assertSame('normal', $arguments->get(2));
	}

	/**
	 * Tests that fromString handles multiple spaces between arguments.
	 */
	public function test_fromString_withMultipleSpaces_ignoresExtraSpaces(): void
	{
		$arguments = ArgumentList::fromString('arg1    arg2     arg3');

		$this->assertSame('arg1', $arguments->get(1));
		$this->assertSame('arg2', $arguments->get(2));
		$this->assertSame('arg3', $arguments->get(3));
		$this->assertSame(3, $arguments->count());
	}

	/**
	 * Tests that fromString handles leading and trailing spaces.
	 */
	public function test_fromString_withLeadingTrailingSpaces_trimsInput(): void
	{
		$arguments = ArgumentList::fromString('  arg1 arg2  ');

		$this->assertSame('arg1', $arguments->get(1));
		$this->assertSame('arg2', $arguments->get(2));
		$this->assertSame(2, $arguments->count());
	}

	/**
	 * Tests that fromString handles a single argument.
	 */
	public function test_fromString_withSingleArgument_parsesCorrectly(): void
	{
		$arguments = ArgumentList::fromString('only');

		$this->assertSame('only', $arguments->get(1));
		$this->assertSame(1, $arguments->count());
	}

	/**
	 * Tests that fromString handles empty quotes.
	 */
	public function test_fromString_withEmptyQuotes_createsEmptyStringArgument(): void
	{
		$arguments = ArgumentList::fromString('before "" after');

		$this->assertSame('before', $arguments->get(1));
		$this->assertSame('', $arguments->get(2));
		$this->assertSame('after', $arguments->get(3));
		$this->assertSame(3, $arguments->count());
	}

	/**
	 * Tests that get returns null for non-existent positions.
	 */
	public function test_get_withNonExistentPosition_returnsNull(): void
	{
		$arguments = ArgumentList::fromString('one two');

		$this->assertNull($arguments->get(0));
		$this->assertNull($arguments->get(3));
		$this->assertNull($arguments->get(100));
	}

	/**
	 * Tests that get uses 1-based indexing.
	 */
	public function test_get_usesOneBasedIndexing(): void
	{
		$arguments = ArgumentList::fromString('first second third');

		$this->assertNull($arguments->get(0));
		$this->assertSame('first', $arguments->get(1));
		$this->assertSame('second', $arguments->get(2));
		$this->assertSame('third', $arguments->get(3));
	}

	/**
	 * Tests that getAll returns all arguments as an array.
	 */
	public function test_getAll_returnsAllArguments(): void
	{
		$arguments = ArgumentList::fromString('one two three');

		$this->assertSame(['one', 'two', 'three'], $arguments->getAll());
	}

	/**
	 * Tests that getAll returns empty array for empty input.
	 */
	public function test_getAll_withEmptyInput_returnsEmptyArray(): void
	{
		$arguments = ArgumentList::fromString('');

		$this->assertSame([], $arguments->getAll());
	}

	/**
	 * Tests that getRaw returns the original input string.
	 */
	public function test_getRaw_returnsOriginalInput(): void
	{
		$raw = 'arg1 "quoted arg" arg3';
		$arguments = ArgumentList::fromString($raw);

		$this->assertSame($raw, $arguments->getRaw());
	}

	/**
	 * Tests that getRaw returns original input with extra spaces.
	 */
	public function test_getRaw_preservesOriginalSpacing(): void
	{
		$raw = '  arg1   arg2  ';
		$arguments = ArgumentList::fromString($raw);

		$this->assertSame($raw, $arguments->getRaw());
	}

	/**
	 * Tests that count returns correct number of arguments.
	 */
	public function test_count_returnsCorrectCount(): void
	{
		$this->assertSame(0, ArgumentList::fromString('')->count());
		$this->assertSame(1, ArgumentList::fromString('one')->count());
		$this->assertSame(2, ArgumentList::fromString('one two')->count());
		$this->assertSame(3, ArgumentList::fromString('"a b" c "d e"')->count());
	}

	/**
	 * Tests that fromString handles escaped quotes inside quoted strings.
	 */
	public function test_fromString_withEscapedQuotes_handlesEscapes(): void
	{
		$arguments = ArgumentList::fromString('"hello \"world\"" normal');

		$this->assertSame('hello "world"', $arguments->get(1));
		$this->assertSame('normal', $arguments->get(2));
	}

	/**
	 * Tests that fromString handles unclosed quotes gracefully.
	 */
	public function test_fromString_withUnclosedQuote_treatsRestAsArgument(): void
	{
		$arguments = ArgumentList::fromString('normal "unclosed quote');

		$this->assertSame('normal', $arguments->get(1));
		$this->assertSame('unclosed quote', $arguments->get(2));
	}

	/**
	 * Tests that fromString handles tabs as separators.
	 */
	public function test_fromString_withTabs_treatAsWhitespace(): void
	{
		$arguments = ArgumentList::fromString("arg1\targ2\t\targ3");

		$this->assertSame('arg1', $arguments->get(1));
		$this->assertSame('arg2', $arguments->get(2));
		$this->assertSame('arg3', $arguments->get(3));
		$this->assertSame(3, $arguments->count());
	}

	/**
	 * Tests that fromString handles asterisks and glob patterns.
	 */
	public function test_fromString_withGlobPattern_preservesPattern(): void
	{
		$arguments = ArgumentList::fromString('*.php src/');

		$this->assertSame('*.php', $arguments->get(1));
		$this->assertSame('src/', $arguments->get(2));
	}

	/**
	 * Tests that fromString handles file paths with spaces in quotes.
	 */
	public function test_fromString_withPathContainingSpaces_preservesPath(): void
	{
		$arguments = ArgumentList::fromString('"/path/to/my file.txt" output');

		$this->assertSame('/path/to/my file.txt', $arguments->get(1));
		$this->assertSame('output', $arguments->get(2));
	}

	/**
	 * Tests that fromString handles equals signs.
	 */
	public function test_fromString_withEqualsSign_preservesEquals(): void
	{
		$arguments = ArgumentList::fromString('--option=value other');

		$this->assertSame('--option=value', $arguments->get(1));
		$this->assertSame('other', $arguments->get(2));
	}

	/**
	 * Tests isEmpty returns true for empty list.
	 */
	public function test_isEmpty_withEmptyList_returnsTrue(): void
	{
		$arguments = ArgumentList::fromString('');

		$this->assertTrue($arguments->isEmpty());
	}

	/**
	 * Tests isEmpty returns false for non-empty list.
	 */
	public function test_isEmpty_withArguments_returnsFalse(): void
	{
		$arguments = ArgumentList::fromString('something');

		$this->assertFalse($arguments->isEmpty());
	}
}
