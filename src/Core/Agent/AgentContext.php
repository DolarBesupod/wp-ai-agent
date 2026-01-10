<?php

declare(strict_types=1);

namespace PhpCliAgent\Core\Agent;

use PhpCliAgent\Core\Contracts\AiAdapterInterface;
use PhpCliAgent\Core\Contracts\SessionInterface;
use PhpCliAgent\Core\ValueObjects\Message;
use PhpCliAgent\Core\ValueObjects\ToolResult;

/**
 * Immutable context for the agent loop execution.
 *
 * AgentContext holds all the state needed for a single execution of the agent loop,
 * including the session, AI adapter reference, current state, iteration count,
 * and any error information.
 *
 * This class is immutable - all modifications return a new instance.
 *
 * @since n.e.x.t
 */
final class AgentContext
{
	private SessionInterface $session;
	private AiAdapterInterface $ai_adapter;
	private AgentState $state;
	private int $current_turn;
	private int $max_turns;
	private ?string $error_message;
	private ?\Throwable $error_exception;

	/**
	 * Tool results from the current iteration.
	 *
	 * @var array<int, array{tool_call_id: string, tool_name: string, result: ToolResult}>
	 */
	private array $pending_tool_results;

	/**
	 * Whether the current iteration was cancelled due to user denial.
	 *
	 * @var bool
	 */
	private bool $user_cancelled;

	/**
	 * Creates a new AgentContext instance.
	 *
	 * @param SessionInterface                                                              $session              The conversation session.
	 * @param AiAdapterInterface                                                            $ai_adapter           The AI adapter for API calls.
	 * @param AgentState                                                                    $state                The current agent state.
	 * @param int                                                                           $current_turn         The current turn number.
	 * @param int                                                                           $max_turns            Maximum allowed turns.
	 * @param string|null                                                                   $error_message        Optional error message.
	 * @param \Throwable|null                                                               $error_exception      Optional exception.
	 * @param array<int, array{tool_call_id: string, tool_name: string, result: ToolResult}> $pending_tool_results Pending tool results.
	 * @param bool                                                                          $user_cancelled       Whether user cancelled.
	 */
	public function __construct(
		SessionInterface $session,
		AiAdapterInterface $ai_adapter,
		AgentState $state = AgentState::PENDING,
		int $current_turn = 0,
		int $max_turns = 100,
		?string $error_message = null,
		?\Throwable $error_exception = null,
		array $pending_tool_results = [],
		bool $user_cancelled = false
	) {
		$this->session = $session;
		$this->ai_adapter = $ai_adapter;
		$this->state = $state;
		$this->current_turn = $current_turn;
		$this->max_turns = $max_turns;
		$this->error_message = $error_message;
		$this->error_exception = $error_exception;
		$this->pending_tool_results = $pending_tool_results;
		$this->user_cancelled = $user_cancelled;
	}

	/**
	 * Creates a new context for starting a loop execution.
	 *
	 * @param SessionInterface   $session    The conversation session.
	 * @param AiAdapterInterface $ai_adapter The AI adapter.
	 * @param int                $max_turns  Maximum allowed turns.
	 *
	 * @return self
	 */
	public static function create(
		SessionInterface $session,
		AiAdapterInterface $ai_adapter,
		int $max_turns = 100
	): self {
		return new self(
			$session,
			$ai_adapter,
			AgentState::PENDING,
			0,
			$max_turns
		);
	}

	/**
	 * Returns the session.
	 *
	 * @return SessionInterface
	 */
	public function getSession(): SessionInterface
	{
		return $this->session;
	}

	/**
	 * Returns the AI adapter.
	 *
	 * @return AiAdapterInterface
	 */
	public function getAiAdapter(): AiAdapterInterface
	{
		return $this->ai_adapter;
	}

	/**
	 * Returns the current state.
	 *
	 * @return AgentState
	 */
	public function getState(): AgentState
	{
		return $this->state;
	}

	/**
	 * Returns the current turn number.
	 *
	 * @return int
	 */
	public function getCurrentTurn(): int
	{
		return $this->current_turn;
	}

	/**
	 * Returns the maximum allowed turns.
	 *
	 * @return int
	 */
	public function getMaxTurns(): int
	{
		return $this->max_turns;
	}

	/**
	 * Returns the error message if any.
	 *
	 * @return string|null
	 */
	public function getErrorMessage(): ?string
	{
		return $this->error_message;
	}

	/**
	 * Returns the error exception if any.
	 *
	 * @return \Throwable|null
	 */
	public function getErrorException(): ?\Throwable
	{
		return $this->error_exception;
	}

	/**
	 * Returns pending tool results.
	 *
	 * @return array<int, array{tool_call_id: string, tool_name: string, result: ToolResult}>
	 */
	public function getPendingToolResults(): array
	{
		return $this->pending_tool_results;
	}

	/**
	 * Checks if the user cancelled the operation.
	 *
	 * @return bool
	 */
	public function isUserCancelled(): bool
	{
		return $this->user_cancelled;
	}

	/**
	 * Checks if the loop has exceeded max turns.
	 *
	 * @return bool
	 */
	public function hasExceededMaxTurns(): bool
	{
		return $this->current_turn >= $this->max_turns;
	}

	/**
	 * Creates a new context with updated state.
	 *
	 * @param AgentState $state The new state.
	 *
	 * @return self
	 */
	public function withState(AgentState $state): self
	{
		return new self(
			$this->session,
			$this->ai_adapter,
			$state,
			$this->current_turn,
			$this->max_turns,
			$this->error_message,
			$this->error_exception,
			$this->pending_tool_results,
			$this->user_cancelled
		);
	}

	/**
	 * Creates a new context with incremented turn counter.
	 *
	 * @return self
	 */
	public function withIncrementedTurn(): self
	{
		return new self(
			$this->session,
			$this->ai_adapter,
			$this->state,
			$this->current_turn + 1,
			$this->max_turns,
			$this->error_message,
			$this->error_exception,
			$this->pending_tool_results,
			$this->user_cancelled
		);
	}

	/**
	 * Creates a new context with error information.
	 *
	 * @param string          $message   The error message.
	 * @param \Throwable|null $exception Optional exception.
	 *
	 * @return self
	 */
	public function withError(string $message, ?\Throwable $exception = null): self
	{
		return new self(
			$this->session,
			$this->ai_adapter,
			AgentState::ERROR,
			$this->current_turn,
			$this->max_turns,
			$message,
			$exception,
			$this->pending_tool_results,
			$this->user_cancelled
		);
	}

	/**
	 * Creates a new context with pending tool results.
	 *
	 * @param array<int, array{tool_call_id: string, tool_name: string, result: ToolResult}> $results The tool results.
	 *
	 * @return self
	 */
	public function withPendingToolResults(array $results): self
	{
		return new self(
			$this->session,
			$this->ai_adapter,
			$this->state,
			$this->current_turn,
			$this->max_turns,
			$this->error_message,
			$this->error_exception,
			$results,
			$this->user_cancelled
		);
	}

	/**
	 * Creates a new context cleared of pending tool results.
	 *
	 * @return self
	 */
	public function withClearedToolResults(): self
	{
		return new self(
			$this->session,
			$this->ai_adapter,
			$this->state,
			$this->current_turn,
			$this->max_turns,
			$this->error_message,
			$this->error_exception,
			[],
			$this->user_cancelled
		);
	}

	/**
	 * Creates a new context marked as cancelled by user.
	 *
	 * @return self
	 */
	public function withUserCancelled(): self
	{
		return new self(
			$this->session,
			$this->ai_adapter,
			AgentState::CANCELLED,
			$this->current_turn,
			$this->max_turns,
			$this->error_message,
			$this->error_exception,
			$this->pending_tool_results,
			true
		);
	}

	/**
	 * Creates a new context with max turns reached state.
	 *
	 * @return self
	 */
	public function withMaxTurnsReached(): self
	{
		return new self(
			$this->session,
			$this->ai_adapter,
			AgentState::MAX_TURNS_REACHED,
			$this->current_turn,
			$this->max_turns,
			$this->error_message,
			$this->error_exception,
			$this->pending_tool_results,
			$this->user_cancelled
		);
	}

	/**
	 * Creates a new context marked as completed.
	 *
	 * @return self
	 */
	public function withCompleted(): self
	{
		return new self(
			$this->session,
			$this->ai_adapter,
			AgentState::COMPLETED,
			$this->current_turn,
			$this->max_turns,
			$this->error_message,
			$this->error_exception,
			$this->pending_tool_results,
			$this->user_cancelled
		);
	}

	/**
	 * Adds a message to the session.
	 *
	 * Note: This mutates the session but returns the same context.
	 * The session is mutable by design for message accumulation.
	 *
	 * @param Message $message The message to add.
	 *
	 * @return self The same context instance.
	 */
	public function addMessage(Message $message): self
	{
		$this->session->addMessage($message);
		return $this;
	}

	/**
	 * Returns messages formatted for the AI API.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getMessagesForApi(): array
	{
		return $this->session->getMessagesForApi();
	}

	/**
	 * Returns the system prompt from the session.
	 *
	 * @return string
	 */
	public function getSystemPrompt(): string
	{
		return $this->session->getSystemPrompt();
	}
}
