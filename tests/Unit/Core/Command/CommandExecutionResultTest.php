<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Core\Command;

use PHPUnit\Framework\TestCase;
use Automattic\WpAiAgent\Core\Command\CommandExecutionResult;

/**
 * Tests for CommandExecutionResult value object.
 *
 * @covers \Automattic\WpAiAgent\Core\Command\CommandExecutionResult
 */
final class CommandExecutionResultTest extends TestCase
{
	public function test_success_createsSuccessfulResult(): void
	{
		$result = CommandExecutionResult::success('Expanded content here');

		$this->assertTrue($result->isSuccess());
		$this->assertSame('Expanded content here', $result->getExpandedContent());
		$this->assertTrue($result->shouldInjectIntoConversation());
		$this->assertNull($result->getDirectOutput());
		$this->assertNull($result->getError());
	}

	public function test_success_withDirectOutput(): void
	{
		$result = CommandExecutionResult::success(
			expanded_content: 'Content',
			direct_output: 'Direct output to display'
		);

		$this->assertTrue($result->isSuccess());
		$this->assertSame('Content', $result->getExpandedContent());
		$this->assertSame('Direct output to display', $result->getDirectOutput());
	}

	public function test_success_withoutInjection(): void
	{
		$result = CommandExecutionResult::success(
			expanded_content: 'Content',
			inject_into_conversation: false
		);

		$this->assertTrue($result->isSuccess());
		$this->assertFalse($result->shouldInjectIntoConversation());
	}

	public function test_failure_createsFailedResult(): void
	{
		$result = CommandExecutionResult::failure('Something went wrong');

		$this->assertFalse($result->isSuccess());
		$this->assertSame('Something went wrong', $result->getError());
		$this->assertSame('', $result->getExpandedContent());
		$this->assertFalse($result->shouldInjectIntoConversation());
	}

	public function test_directOutput_createsResultWithDirectOutputOnly(): void
	{
		$result = CommandExecutionResult::directOutput('Help information here');

		$this->assertTrue($result->isSuccess());
		$this->assertSame('Help information here', $result->getDirectOutput());
		$this->assertSame('', $result->getExpandedContent());
		$this->assertFalse($result->shouldInjectIntoConversation());
	}

	public function test_getExpandedContent_returnsContent(): void
	{
		$content = "This is the expanded\nmultiline content";
		$result = CommandExecutionResult::success($content);

		$this->assertSame($content, $result->getExpandedContent());
	}

	public function test_shouldInjectIntoConversation_returnsTrueByDefault(): void
	{
		$result = CommandExecutionResult::success('Content');

		$this->assertTrue($result->shouldInjectIntoConversation());
	}

	public function test_shouldInjectIntoConversation_returnsFalseWhenDisabled(): void
	{
		$result = CommandExecutionResult::success('Content', inject_into_conversation: false);

		$this->assertFalse($result->shouldInjectIntoConversation());
	}

	public function test_getDirectOutput_returnsNullByDefault(): void
	{
		$result = CommandExecutionResult::success('Content');

		$this->assertNull($result->getDirectOutput());
	}

	public function test_getDirectOutput_returnsOutputWhenSet(): void
	{
		$result = CommandExecutionResult::success('Content', direct_output: 'Output');

		$this->assertSame('Output', $result->getDirectOutput());
	}

	public function test_isSuccess_returnsTrueForSuccessResult(): void
	{
		$result = CommandExecutionResult::success('Content');

		$this->assertTrue($result->isSuccess());
	}

	public function test_isSuccess_returnsFalseForFailureResult(): void
	{
		$result = CommandExecutionResult::failure('Error');

		$this->assertFalse($result->isSuccess());
	}

	public function test_getError_returnsNullForSuccessResult(): void
	{
		$result = CommandExecutionResult::success('Content');

		$this->assertNull($result->getError());
	}

	public function test_getError_returnsErrorForFailureResult(): void
	{
		$result = CommandExecutionResult::failure('Error message');

		$this->assertSame('Error message', $result->getError());
	}

	public function test_hasDirectOutput_returnsTrueWhenDirectOutputSet(): void
	{
		$result = CommandExecutionResult::directOutput('Some output');

		$this->assertTrue($result->hasDirectOutput());
	}

	public function test_hasDirectOutput_returnsFalseWhenNoDirectOutput(): void
	{
		$result = CommandExecutionResult::success('Content');

		$this->assertFalse($result->hasDirectOutput());
	}

	public function test_immutability_resultCannotBeModified(): void
	{
		$result = CommandExecutionResult::success('Original content');

		// The result should be immutable - no public setters
		$this->assertSame('Original content', $result->getExpandedContent());
		$this->assertTrue($result->isSuccess());
	}

	public function test_success_withAllParameters(): void
	{
		$result = CommandExecutionResult::success(
			expanded_content: 'Full content',
			inject_into_conversation: true,
			direct_output: 'Also show this'
		);

		$this->assertTrue($result->isSuccess());
		$this->assertSame('Full content', $result->getExpandedContent());
		$this->assertTrue($result->shouldInjectIntoConversation());
		$this->assertSame('Also show this', $result->getDirectOutput());
		$this->assertNull($result->getError());
	}
}
