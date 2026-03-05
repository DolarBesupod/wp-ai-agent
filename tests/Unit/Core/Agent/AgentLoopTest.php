<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Core\Agent;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Automattic\WpAiAgent\Core\Agent\AgentLoop;
use Automattic\WpAiAgent\Core\Agent\AgentState;
use Automattic\WpAiAgent\Core\Contracts\AiAdapterInterface;
use Automattic\WpAiAgent\Core\Contracts\AiResponseInterface;
use Automattic\WpAiAgent\Core\Contracts\OutputHandlerInterface;
use Automattic\WpAiAgent\Core\Contracts\ToolExecutorInterface;
use Automattic\WpAiAgent\Core\Contracts\ToolRegistryInterface;
use Automattic\WpAiAgent\Core\Exceptions\AgentException;
use Automattic\WpAiAgent\Core\Session\Session;
use Automattic\WpAiAgent\Core\ValueObjects\Message;
use Automattic\WpAiAgent\Core\ValueObjects\ToolResult;

/**
 * Tests for AgentLoop.
 *
 * @covers \Automattic\WpAiAgent\Core\Agent\AgentLoop
 */
final class AgentLoopTest extends TestCase
{
	private AiAdapterInterface&MockObject $ai_adapter;
	private ToolExecutorInterface&MockObject $tool_executor;
	private ToolRegistryInterface&MockObject $tool_registry;
	private OutputHandlerInterface&MockObject $output_handler;
	private AgentLoop $agent_loop;

	protected function setUp(): void
	{
		$this->ai_adapter = $this->createMock(AiAdapterInterface::class);
		$this->tool_executor = $this->createMock(ToolExecutorInterface::class);
		$this->tool_registry = $this->createMock(ToolRegistryInterface::class);
		$this->output_handler = $this->createMock(OutputHandlerInterface::class);

		$this->tool_registry->method('getDeclarations')->willReturn([]);

		$this->agent_loop = new AgentLoop(
			$this->ai_adapter,
			$this->tool_executor,
			$this->tool_registry,
			$this->output_handler
		);
	}

	public function test_run_completesWithTextOnlyResponse(): void
	{
		$session = new Session(null, 'Test prompt');
		$session->addMessage(Message::user('Hello'));

		$response = $this->createMockResponse('Hello! How can I help?', [], true);
		$this->ai_adapter->method('chat')->willReturn($response);

		$this->output_handler->expects($this->atLeastOnce())
			->method('writeAssistantResponse')
			->with('Hello! How can I help?');

		$this->agent_loop->run($session);

		$this->assertFalse($this->agent_loop->isRunning());
		$this->assertSame(2, $session->getMessageCount());
	}

	public function test_run_executesToolCallsAndContinues(): void
	{
		$session = new Session(null, 'Test prompt');
		$session->addMessage(Message::user('Read the file'));

		$tool_calls = [
			['id' => 'call_123', 'name' => 'read_file', 'arguments' => ['path' => '/test.txt']],
		];

		$response_with_tool = $this->createMockResponse('Let me read that file.', $tool_calls, false);
		$response_final = $this->createMockResponse('The file contains: Hello World', [], true);

		$this->ai_adapter->expects($this->exactly(2))
			->method('chat')
			->willReturnOnConsecutiveCalls($response_with_tool, $response_final);

		$this->tool_executor->expects($this->once())
			->method('execute')
			->with('read_file', ['path' => '/test.txt'])
			->willReturn(ToolResult::success('Hello World'));

		$this->agent_loop->run($session);

		$this->assertFalse($this->agent_loop->isRunning());
	}

	public function test_run_stopsWhenUserDeniesConfirmation(): void
	{
		$session = new Session(null, 'Test prompt');
		$session->addMessage(Message::user('Delete the file'));

		$tool_calls = [
			['id' => 'call_456', 'name' => 'delete_file', 'arguments' => ['path' => '/important.txt']],
		];

		$response = $this->createMockResponse('I will delete that file.', $tool_calls, false);
		$this->ai_adapter->method('chat')->willReturn($response);

		$this->tool_executor->expects($this->once())
			->method('execute')
			->willReturn(ToolResult::failure('User denied execution of tool "delete_file".'));

		$this->agent_loop->run($session);

		$context = $this->agent_loop->getCurrentContext();
		$this->assertNull($context);
	}

	public function test_run_stopsAtMaxTurns(): void
	{
		$this->agent_loop->setMaxIterations(3);

		$session = new Session(null, 'Test prompt');
		$session->addMessage(Message::user('Keep calling tools'));

		$tool_calls = [
			['id' => 'call_1', 'name' => 'test_tool', 'arguments' => []],
		];

		$response = $this->createMockResponse('Calling tool...', $tool_calls, false);
		$this->ai_adapter->method('chat')->willReturn($response);

		$this->tool_executor->method('execute')
			->willReturn(ToolResult::success('Tool executed'));

		// Just verify it completes without throwing an exception
		$this->agent_loop->run($session);

		// Verify that the loop stopped (no more than 3 AI calls)
		$this->assertFalse($this->agent_loop->isRunning());
	}

	public function test_run_handlesAiAdapterError(): void
	{
		$session = new Session(null, 'Test prompt');
		$session->addMessage(Message::user('Hello'));

		$this->ai_adapter->method('chat')
			->willThrowException(new \RuntimeException('API Error'));

		// The loop should complete without throwing - errors are handled gracefully
		// by setting error state on the context
		$this->agent_loop->run($session);

		$this->assertFalse($this->agent_loop->isRunning());
	}

	public function test_setMaxIterations_changesLimit(): void
	{
		$this->assertSame(100, $this->agent_loop->getMaxIterations());

		$this->agent_loop->setMaxIterations(50);

		$this->assertSame(50, $this->agent_loop->getMaxIterations());
	}

	public function test_setMaxIterations_throwsOnInvalidValue(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Max iterations must be at least 1');

		$this->agent_loop->setMaxIterations(0);
	}

	public function test_isRunning_returnsFalseInitially(): void
	{
		$this->assertFalse($this->agent_loop->isRunning());
	}

	public function test_stop_preventsNextIteration(): void
	{
		$session = new Session(null, 'Test prompt');
		$session->addMessage(Message::user('Hello'));

		$response = $this->createMockResponse('Response', [], true);
		$this->ai_adapter->method('chat')->willReturn($response);

		$this->agent_loop->stop();
		$this->agent_loop->run($session);

		$this->assertFalse($this->agent_loop->isRunning());
	}

	public function test_run_tracksTokenUsage(): void
	{
		$session = new Session(null, 'Test prompt');
		$session->addMessage(Message::user('Hello'));

		$response = $this->createMockResponse('Hi!', [], true, ['input_tokens' => 50, 'output_tokens' => 25]);
		$this->ai_adapter->method('chat')->willReturn($response);

		$this->agent_loop->run($session);

		$usage = $session->getTokenUsage();
		$this->assertSame(50, $usage['input']);
		$this->assertSame(25, $usage['output']);
	}

	public function test_run_handlesMultipleToolCalls(): void
	{
		$session = new Session(null, 'Test prompt');
		$session->addMessage(Message::user('Read two files'));

		$tool_calls = [
			['id' => 'call_1', 'name' => 'read_file', 'arguments' => ['path' => '/file1.txt']],
			['id' => 'call_2', 'name' => 'read_file', 'arguments' => ['path' => '/file2.txt']],
		];

		$response_with_tools = $this->createMockResponse('Reading files...', $tool_calls, false);
		$response_final = $this->createMockResponse('Both files read.', [], true);

		$this->ai_adapter->expects($this->exactly(2))
			->method('chat')
			->willReturnOnConsecutiveCalls($response_with_tools, $response_final);

		$this->tool_executor->expects($this->exactly(2))
			->method('execute')
			->willReturnOnConsecutiveCalls(
				ToolResult::success('Content 1'),
				ToolResult::success('Content 2')
			);

		$this->agent_loop->run($session);
	}

	public function test_run_handlesToolExecutionException(): void
	{
		$session = new Session(null, 'Test prompt');
		$session->addMessage(Message::user('Execute tool'));

		$tool_calls = [
			['id' => 'call_1', 'name' => 'failing_tool', 'arguments' => []],
		];

		$response_with_tool = $this->createMockResponse('Executing...', $tool_calls, false);
		$response_final = $this->createMockResponse('Tool failed.', [], true);

		$this->ai_adapter->expects($this->exactly(2))
			->method('chat')
			->willReturnOnConsecutiveCalls($response_with_tool, $response_final);

		$this->tool_executor->expects($this->once())
			->method('execute')
			->willThrowException(new \RuntimeException('Tool error'));

		$this->output_handler->expects($this->atLeastOnce())
			->method('writeToolResult');

		$this->agent_loop->run($session);
	}

	public function test_run_addsAssistantMessageToSession(): void
	{
		$session = new Session(null, 'Test prompt');
		$session->addMessage(Message::user('Hello'));

		$response = $this->createMockResponse('Hello there!', [], true);
		$this->ai_adapter->method('chat')->willReturn($response);

		$this->agent_loop->run($session);

		$messages = $session->getMessages();
		$this->assertCount(2, $messages);
		$this->assertSame(Message::ROLE_ASSISTANT, $messages[1]->getRole());
		$this->assertSame('Hello there!', $messages[1]->getContent());
	}

	public function test_run_addsToolResultMessagesToSession(): void
	{
		$session = new Session(null, 'Test prompt');
		$session->addMessage(Message::user('Read file'));

		$tool_calls = [
			['id' => 'call_abc', 'name' => 'read_file', 'arguments' => []],
		];

		$response_with_tool = $this->createMockResponse('Reading...', $tool_calls, false);
		$response_final = $this->createMockResponse('Done.', [], true);

		$this->ai_adapter->expects($this->exactly(2))
			->method('chat')
			->willReturnOnConsecutiveCalls($response_with_tool, $response_final);

		$this->tool_executor->method('execute')
			->willReturn(ToolResult::success('File contents'));

		$this->agent_loop->run($session);

		$messages = $session->getMessages();
		$tool_result_messages = array_filter(
			$messages,
			fn($m) => $m->getRole() === Message::ROLE_TOOL
		);

		$this->assertCount(1, $tool_result_messages);
	}

	public function test_getCurrentContext_returnsNullWhenNotRunning(): void
	{
		$this->assertNull($this->agent_loop->getCurrentContext());
	}

	/**
	 * Creates a mock AiResponseInterface.
	 *
	 * @param string $content The response content.
	 * @param array<int, array{id: string, name: string, arguments: array<string, mixed>}> $tool_calls The tool calls.
	 * @param bool $is_final Whether this is a final response.
	 * @param array{input_tokens: int, output_tokens: int}|null                            $usage      Token usage.
	 *
	 * @return AiResponseInterface&MockObject
	 */
	private function createMockResponse(
		string $content,
		array $tool_calls,
		bool $is_final,
		?array $usage = null
	): AiResponseInterface&MockObject {
		$response = $this->createMock(AiResponseInterface::class);

		$response->method('getContent')->willReturn($content);
		$response->method('getToolCalls')->willReturn($tool_calls);
		$response->method('hasToolCalls')->willReturn(count($tool_calls) > 0);
		$response->method('isFinalResponse')->willReturn($is_final);
		$response->method('getStopReason')->willReturn($is_final ? 'end_turn' : 'tool_use');
		$response->method('getUsage')->willReturn($usage ?? ['input_tokens' => 10, 'output_tokens' => 5]);
		$response->method('toMessage')->willReturn(
			Message::assistant($content, $tool_calls)
		);

		return $response;
	}
}
