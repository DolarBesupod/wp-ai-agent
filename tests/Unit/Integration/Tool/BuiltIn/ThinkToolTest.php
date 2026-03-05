<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Integration\Tool\BuiltIn;

use PHPUnit\Framework\TestCase;
use Automattic\Automattic\WpAiAgent\Integration\Tool\BuiltIn\ThinkTool;

/**
 * Tests for ThinkTool.
 *
 * @covers \Automattic\WpAiAgent\Integration\Tool\BuiltIn\ThinkTool
 */
final class ThinkToolTest extends TestCase
{
	private ThinkTool $tool;

	protected function setUp(): void
	{
		$this->tool = new ThinkTool();
	}

	public function test_getName_returnsThink(): void
	{
		$this->assertSame('think', $this->tool->getName());
	}

	public function test_getDescription_returnsNonEmptyString(): void
	{
		$description = $this->tool->getDescription();

		$this->assertNotEmpty($description);
		$this->assertIsString($description);
		$this->assertStringContainsString('reasoning', $description);
	}

	public function test_getParametersSchema_returnsValidSchema(): void
	{
		$schema = $this->tool->getParametersSchema();

		$this->assertIsArray($schema);
		$this->assertSame('object', $schema['type']);
		$this->assertArrayHasKey('properties', $schema);
		$this->assertArrayHasKey('thought', $schema['properties']);
		$this->assertSame('string', $schema['properties']['thought']['type']);
		$this->assertSame(['thought'], $schema['required']);
	}

	public function test_requiresConfirmation_returnsFalse(): void
	{
		$this->assertFalse($this->tool->requiresConfirmation());
	}

	public function test_execute_withValidThought_returnsThoughtAsOutput(): void
	{
		$thought = 'Let me analyze the code structure first.';

		$result = $this->tool->execute(['thought' => $thought]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame($thought, $result->getOutput());
	}

	public function test_execute_withComplexThought_returnsEntireThought(): void
	{
		$thought = "First, I need to understand the problem.\n"
			. "The user wants to implement a feature that:\n"
			. "1. Reads data from the database\n"
			. "2. Transforms it according to business rules\n"
			. "3. Outputs the result in JSON format\n\n"
			. "Let me consider the best approach...";

		$result = $this->tool->execute(['thought' => $thought]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame($thought, $result->getOutput());
	}

	public function test_execute_withMissingThought_returnsFailure(): void
	{
		$result = $this->tool->execute([]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('Missing required argument', $result->getError());
		$this->assertStringContainsString('thought', $result->getError());
	}

	public function test_execute_withEmptyThought_returnsFailure(): void
	{
		$result = $this->tool->execute(['thought' => '']);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('cannot be empty', $result->getError());
	}

	public function test_execute_withUnicodeThought_returnsThoughtUnmodified(): void
	{
		$thought = '考えてみましょう: これは日本語のテストです。🤔';

		$result = $this->tool->execute(['thought' => $thought]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame($thought, $result->getOutput());
	}

	public function test_execute_withLongThought_returnsEntireThought(): void
	{
		$thought = str_repeat('This is a very long thought. ', 1000);

		$result = $this->tool->execute(['thought' => $thought]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame($thought, $result->getOutput());
	}

	public function test_execute_withSpecialCharacters_returnsThoughtUnmodified(): void
	{
		$thought = 'Analyzing: $variable, \\escape, `backticks`, \'quotes\', "double"';

		$result = $this->tool->execute(['thought' => $thought]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame($thought, $result->getOutput());
	}

	public function test_execute_hasNoSideEffects(): void
	{
		$thought = 'This thought should have no side effects.';

		$result1 = $this->tool->execute(['thought' => $thought]);
		$result2 = $this->tool->execute(['thought' => $thought]);

		$this->assertSame($result1->getOutput(), $result2->getOutput());
		$this->assertTrue($result1->isSuccess());
		$this->assertTrue($result2->isSuccess());
	}

	public function test_execute_withWhitespaceOnlyThought_returnsThought(): void
	{
		$thought = '   ';

		$result = $this->tool->execute(['thought' => $thought]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame($thought, $result->getOutput());
	}

	public function test_execute_withNonStringThought_returnsFailure(): void
	{
		$result = $this->tool->execute(['thought' => 12345]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('cannot be empty', $result->getError());
	}
}
