<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Core\ValueObjects;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PhpCliAgent\Core\ValueObjects\ToolName;

/**
 * Tests for ToolName value object.
 *
 * @covers \PhpCliAgent\Core\ValueObjects\ToolName
 */
final class ToolNameTest extends TestCase
{
	public function test_constructor_acceptsValidName(): void
	{
		$name = new ToolName('read_file');

		$this->assertSame('read_file', $name->toString());
	}

	public function test_constructor_acceptsSimpleName(): void
	{
		$name = new ToolName('bash');

		$this->assertSame('bash', $name->toString());
	}

	public function test_constructor_acceptsNameWithNumbers(): void
	{
		$name = new ToolName('tool2');

		$this->assertSame('tool2', $name->toString());
	}

	public function test_constructor_throwsOnEmptyString(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Tool name cannot be empty');

		new ToolName('');
	}

	public function test_constructor_throwsOnWhitespaceOnly(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Tool name cannot be empty');

		new ToolName('   ');
	}

	public function test_constructor_throwsOnUppercase(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('is invalid');

		new ToolName('ReadFile');
	}

	public function test_constructor_throwsOnStartingWithNumber(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('is invalid');

		new ToolName('2tool');
	}

	public function test_constructor_throwsOnStartingWithUnderscore(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('is invalid');

		new ToolName('_tool');
	}

	public function test_constructor_throwsOnHyphens(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('is invalid');

		new ToolName('read-file');
	}

	public function test_constructor_throwsOnSpaces(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('is invalid');

		new ToolName('read file');
	}

	public function test_fromString_createsInstance(): void
	{
		$name = ToolName::fromString('execute_bash');

		$this->assertSame('execute_bash', $name->toString());
	}

	public function test_toString_returnsValue(): void
	{
		$name = new ToolName('test_tool');

		$this->assertSame('test_tool', $name->toString());
	}

	public function test_magicToString_returnsValue(): void
	{
		$name = new ToolName('magic_tool');

		$this->assertSame('magic_tool', (string) $name);
	}

	public function test_equals_returnsTrueForSameValue(): void
	{
		$name1 = new ToolName('same_tool');
		$name2 = new ToolName('same_tool');

		$this->assertTrue($name1->equals($name2));
		$this->assertTrue($name2->equals($name1));
	}

	public function test_equals_returnsFalseForDifferentValue(): void
	{
		$name1 = new ToolName('first_tool');
		$name2 = new ToolName('second_tool');

		$this->assertFalse($name1->equals($name2));
		$this->assertFalse($name2->equals($name1));
	}

	public function test_equals_returnsTrueForSameInstance(): void
	{
		$name = new ToolName('self_compare');

		$this->assertTrue($name->equals($name));
	}
}
