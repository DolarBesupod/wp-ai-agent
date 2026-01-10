<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Integration\Cli;

use PHPUnit\Framework\TestCase;
use PhpCliAgent\Integration\Cli\CommandResult;

/**
 * Tests for CommandResult value object.
 *
 * @covers \PhpCliAgent\Integration\Cli\CommandResult
 */
final class CommandResultTest extends TestCase
{
	public function test_constructor_setsAllProperties(): void
	{
		$result = new CommandResult(true, false, 'Test message');

		$this->assertTrue($result->wasHandled());
		$this->assertFalse($result->shouldContinue());
		$this->assertSame('Test message', $result->getMessage());
	}

	public function test_constructor_withNullMessage(): void
	{
		$result = new CommandResult(true, true, null);

		$this->assertNull($result->getMessage());
		$this->assertFalse($result->hasMessage());
	}

	public function test_handled_createsHandledContinuingResult(): void
	{
		$result = CommandResult::handled();

		$this->assertTrue($result->wasHandled());
		$this->assertTrue($result->shouldContinue());
		$this->assertNull($result->getMessage());
	}

	public function test_handled_withMessage(): void
	{
		$result = CommandResult::handled('Success!');

		$this->assertTrue($result->wasHandled());
		$this->assertTrue($result->shouldContinue());
		$this->assertSame('Success!', $result->getMessage());
		$this->assertTrue($result->hasMessage());
	}

	public function test_exit_createsHandledExitResult(): void
	{
		$result = CommandResult::exit();

		$this->assertTrue($result->wasHandled());
		$this->assertFalse($result->shouldContinue());
		$this->assertNull($result->getMessage());
	}

	public function test_exit_withMessage(): void
	{
		$result = CommandResult::exit('Goodbye!');

		$this->assertTrue($result->wasHandled());
		$this->assertFalse($result->shouldContinue());
		$this->assertSame('Goodbye!', $result->getMessage());
	}

	public function test_notHandled_createsNotHandledResult(): void
	{
		$result = CommandResult::notHandled();

		$this->assertFalse($result->wasHandled());
		$this->assertTrue($result->shouldContinue());
		$this->assertNull($result->getMessage());
	}

	public function test_unknownCommand_createsErrorResult(): void
	{
		$result = CommandResult::unknownCommand('foo');

		$this->assertTrue($result->wasHandled());
		$this->assertTrue($result->shouldContinue());
		$this->assertSame(
			'Unknown command: /foo. Type /help for available commands.',
			$result->getMessage()
		);
	}

	public function test_hasMessage_returnsTrueWhenMessageSet(): void
	{
		$result = CommandResult::handled('Test');
		$this->assertTrue($result->hasMessage());
	}

	public function test_hasMessage_returnsFalseWhenNoMessage(): void
	{
		$result = CommandResult::handled();
		$this->assertFalse($result->hasMessage());
	}
}
