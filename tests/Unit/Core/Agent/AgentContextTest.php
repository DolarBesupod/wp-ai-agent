<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Core\Agent;

use PHPUnit\Framework\TestCase;
use Automattic\Automattic\WpAiAgent\Core\Agent\AgentContext;
use Automattic\Automattic\WpAiAgent\Core\Agent\AgentState;
use Automattic\Automattic\WpAiAgent\Core\Contracts\AiAdapterInterface;
use Automattic\Automattic\WpAiAgent\Core\Contracts\SessionInterface;
use Automattic\Automattic\WpAiAgent\Core\Session\Session;
use Automattic\Automattic\WpAiAgent\Core\ValueObjects\Message;
use Automattic\Automattic\WpAiAgent\Core\ValueObjects\ToolResult;
use RuntimeException;

/**
 * Tests for AgentContext.
 *
 * @covers \Automattic\WpAiAgent\Core\Agent\AgentContext
 */
final class AgentContextTest extends TestCase
{
	private SessionInterface $session;
	private AiAdapterInterface $ai_adapter;

	protected function setUp(): void
	{
		$this->session = new Session(null, 'Test system prompt');
		$this->ai_adapter = $this->createMock(AiAdapterInterface::class);
	}

	public function test_create_returnsContextWithDefaults(): void
	{
		$context = AgentContext::create($this->session, $this->ai_adapter);

		$this->assertSame($this->session, $context->getSession());
		$this->assertSame($this->ai_adapter, $context->getAiAdapter());
		$this->assertSame(AgentState::PENDING, $context->getState());
		$this->assertSame(0, $context->getCurrentTurn());
		$this->assertSame(100, $context->getMaxTurns());
		$this->assertNull($context->getErrorMessage());
		$this->assertNull($context->getErrorException());
		$this->assertSame([], $context->getPendingToolResults());
		$this->assertFalse($context->isUserCancelled());
	}

	public function test_create_withCustomMaxTurns(): void
	{
		$context = AgentContext::create($this->session, $this->ai_adapter, 50);

		$this->assertSame(50, $context->getMaxTurns());
	}

	public function test_withState_returnsNewContextWithUpdatedState(): void
	{
		$context = AgentContext::create($this->session, $this->ai_adapter);

		$new_context = $context->withState(AgentState::THINKING);

		$this->assertNotSame($context, $new_context);
		$this->assertSame(AgentState::PENDING, $context->getState());
		$this->assertSame(AgentState::THINKING, $new_context->getState());
	}

	public function test_withIncrementedTurn_returnsNewContextWithIncrementedCounter(): void
	{
		$context = AgentContext::create($this->session, $this->ai_adapter);
		$this->assertSame(0, $context->getCurrentTurn());

		$context1 = $context->withIncrementedTurn();
		$this->assertSame(1, $context1->getCurrentTurn());
		$this->assertSame(0, $context->getCurrentTurn());

		$context2 = $context1->withIncrementedTurn();
		$this->assertSame(2, $context2->getCurrentTurn());
	}

	public function test_withError_setsErrorAndState(): void
	{
		$context = AgentContext::create($this->session, $this->ai_adapter);
		$exception = new RuntimeException('Test exception');

		$error_context = $context->withError('Something went wrong', $exception);

		$this->assertSame(AgentState::ERROR, $error_context->getState());
		$this->assertSame('Something went wrong', $error_context->getErrorMessage());
		$this->assertSame($exception, $error_context->getErrorException());
	}

	public function test_withError_worksWithoutException(): void
	{
		$context = AgentContext::create($this->session, $this->ai_adapter);

		$error_context = $context->withError('Error message only');

		$this->assertSame(AgentState::ERROR, $error_context->getState());
		$this->assertSame('Error message only', $error_context->getErrorMessage());
		$this->assertNull($error_context->getErrorException());
	}

	public function test_withPendingToolResults_storesResults(): void
	{
		$context = AgentContext::create($this->session, $this->ai_adapter);
		$results = [
			[
				'tool_call_id' => 'call_123',
				'tool_name' => 'read_file',
				'result' => ToolResult::success('File contents'),
			],
		];

		$new_context = $context->withPendingToolResults($results);

		$this->assertCount(1, $new_context->getPendingToolResults());
		$this->assertSame([], $context->getPendingToolResults());
	}

	public function test_withClearedToolResults_clearsResults(): void
	{
		$results = [
			[
				'tool_call_id' => 'call_123',
				'tool_name' => 'test_tool',
				'result' => ToolResult::success('output'),
			],
		];
		$context = AgentContext::create($this->session, $this->ai_adapter)
			->withPendingToolResults($results);

		$cleared = $context->withClearedToolResults();

		$this->assertCount(1, $context->getPendingToolResults());
		$this->assertSame([], $cleared->getPendingToolResults());
	}

	public function test_withUserCancelled_setsCancelledStateAndFlag(): void
	{
		$context = AgentContext::create($this->session, $this->ai_adapter);

		$cancelled = $context->withUserCancelled();

		$this->assertSame(AgentState::CANCELLED, $cancelled->getState());
		$this->assertTrue($cancelled->isUserCancelled());
		$this->assertFalse($context->isUserCancelled());
	}

	public function test_withMaxTurnsReached_setsState(): void
	{
		$context = AgentContext::create($this->session, $this->ai_adapter);

		$max_turns_context = $context->withMaxTurnsReached();

		$this->assertSame(AgentState::MAX_TURNS_REACHED, $max_turns_context->getState());
		$this->assertSame(AgentState::PENDING, $context->getState());
	}

	public function test_withCompleted_setsCompletedState(): void
	{
		$context = AgentContext::create($this->session, $this->ai_adapter);

		$completed = $context->withCompleted();

		$this->assertSame(AgentState::COMPLETED, $completed->getState());
	}

	public function test_hasExceededMaxTurns_returnsFalseWhenUnderLimit(): void
	{
		$context = AgentContext::create($this->session, $this->ai_adapter, 5);

		$this->assertFalse($context->hasExceededMaxTurns());

		$context = $context->withIncrementedTurn();
		$this->assertFalse($context->hasExceededMaxTurns());

		$context = $context->withIncrementedTurn();
		$this->assertFalse($context->hasExceededMaxTurns());
	}

	public function test_hasExceededMaxTurns_returnsTrueWhenAtLimit(): void
	{
		$context = AgentContext::create($this->session, $this->ai_adapter, 3)
			->withIncrementedTurn()
			->withIncrementedTurn()
			->withIncrementedTurn();

		$this->assertTrue($context->hasExceededMaxTurns());
	}

	public function test_hasExceededMaxTurns_returnsTrueWhenOverLimit(): void
	{
		$context = AgentContext::create($this->session, $this->ai_adapter, 2)
			->withIncrementedTurn()
			->withIncrementedTurn()
			->withIncrementedTurn();

		$this->assertTrue($context->hasExceededMaxTurns());
	}

	public function test_addMessage_addsMessageToSession(): void
	{
		$context = AgentContext::create($this->session, $this->ai_adapter);
		$message = Message::user('Hello');

		$returned = $context->addMessage($message);

		$this->assertSame($context, $returned);
		$this->assertCount(1, $this->session->getMessages());
		$this->assertSame($message, $this->session->getMessages()[0]);
	}

	public function test_getMessagesForApi_delegatesToSession(): void
	{
		$this->session->addMessage(Message::user('Hello'));
		$this->session->addMessage(Message::assistant('Hi there'));
		$context = AgentContext::create($this->session, $this->ai_adapter);

		$messages = $context->getMessagesForApi();

		$this->assertCount(2, $messages);
	}

	public function test_getSystemPrompt_delegatesToSession(): void
	{
		$context = AgentContext::create($this->session, $this->ai_adapter);

		$this->assertSame('Test system prompt', $context->getSystemPrompt());
	}

	public function test_immutability_preservesOriginalContext(): void
	{
		$original = AgentContext::create($this->session, $this->ai_adapter);

		$modified = $original
			->withState(AgentState::THINKING)
			->withIncrementedTurn()
			->withPendingToolResults([
				[
					'tool_call_id' => 'call_1',
					'tool_name' => 'test',
					'result' => ToolResult::success('output'),
				],
			]);

		$this->assertSame(AgentState::PENDING, $original->getState());
		$this->assertSame(0, $original->getCurrentTurn());
		$this->assertSame([], $original->getPendingToolResults());

		$this->assertSame(AgentState::THINKING, $modified->getState());
		$this->assertSame(1, $modified->getCurrentTurn());
		$this->assertCount(1, $modified->getPendingToolResults());
	}

	public function test_constructor_acceptsAllParameters(): void
	{
		$exception = new RuntimeException('Test');
		$results = [
			[
				'tool_call_id' => 'call_1',
				'tool_name' => 'test',
				'result' => ToolResult::success('output'),
			],
		];

		$context = new AgentContext(
			$this->session,
			$this->ai_adapter,
			AgentState::ACTING,
			5,
			10,
			'Error msg',
			$exception,
			$results,
			true
		);

		$this->assertSame($this->session, $context->getSession());
		$this->assertSame($this->ai_adapter, $context->getAiAdapter());
		$this->assertSame(AgentState::ACTING, $context->getState());
		$this->assertSame(5, $context->getCurrentTurn());
		$this->assertSame(10, $context->getMaxTurns());
		$this->assertSame('Error msg', $context->getErrorMessage());
		$this->assertSame($exception, $context->getErrorException());
		$this->assertSame($results, $context->getPendingToolResults());
		$this->assertTrue($context->isUserCancelled());
	}
}
