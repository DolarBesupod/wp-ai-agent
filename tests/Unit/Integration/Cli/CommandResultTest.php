<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Integration\Cli;

use PHPUnit\Framework\TestCase;
use WpAiAgent\Integration\Cli\CommandResult;

/**
 * Tests for CommandResult value object.
 *
 * @covers \WpAiAgent\Integration\Cli\CommandResult
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

	public function test_inject_createsResultWithInjectedContent(): void
	{
		$content = 'Review this code: file.php';
		$result = CommandResult::inject($content);

		$this->assertTrue($result->wasHandled());
		$this->assertTrue($result->shouldContinue());
		$this->assertTrue($result->shouldInject());
		$this->assertSame($content, $result->getInjectedContent());
	}

	public function test_inject_withEmptyContent(): void
	{
		$result = CommandResult::inject('');

		$this->assertTrue($result->shouldInject());
		$this->assertSame('', $result->getInjectedContent());
	}

	public function test_inject_withMultilineContent(): void
	{
		$content = "Line 1\nLine 2\nLine 3";
		$result = CommandResult::inject($content);

		$this->assertTrue($result->shouldInject());
		$this->assertSame($content, $result->getInjectedContent());
	}

	public function test_shouldInject_returnsFalseForHandledResult(): void
	{
		$result = CommandResult::handled('Some message');

		$this->assertFalse($result->shouldInject());
		$this->assertNull($result->getInjectedContent());
	}

	public function test_shouldInject_returnsFalseForExitResult(): void
	{
		$result = CommandResult::exit();

		$this->assertFalse($result->shouldInject());
		$this->assertNull($result->getInjectedContent());
	}

	public function test_shouldInject_returnsFalseForNotHandledResult(): void
	{
		$result = CommandResult::notHandled();

		$this->assertFalse($result->shouldInject());
		$this->assertNull($result->getInjectedContent());
	}

	public function test_getInjectedContent_returnsNullForNonInjectResult(): void
	{
		$result = CommandResult::handled();

		$this->assertNull($result->getInjectedContent());
	}
}
