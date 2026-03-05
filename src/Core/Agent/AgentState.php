<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Core\Agent;

/**
 * Enum representing the possible states of the agent loop.
 *
 * The agent loop transitions through these states during execution:
 * - PENDING: Initial state, waiting to start
 * - THINKING: AI is processing and generating a response
 * - ACTING: Executing tool calls requested by the AI
 * - COMPLETED: Loop finished successfully with a final response
 * - CANCELLED: User denied a tool execution confirmation
 * - MAX_TURNS_REACHED: Loop stopped due to iteration limit
 * - ERROR: An error occurred during execution
 *
 * @since n.e.x.t
 */
enum AgentState: string
{
	case PENDING = 'pending';
	case THINKING = 'thinking';
	case ACTING = 'acting';
	case COMPLETED = 'completed';
	case CANCELLED = 'cancelled';
	case MAX_TURNS_REACHED = 'max_turns_reached';
	case ERROR = 'error';

	/**
	 * Checks if this is a terminal state.
	 *
	 * Terminal states indicate the loop has finished and will not continue.
	 *
	 * @return bool True if this is a terminal state.
	 */
	public function isTerminal(): bool
	{
		return match ($this) {
			self::COMPLETED,
			self::CANCELLED,
			self::MAX_TURNS_REACHED,
			self::ERROR => true,
			default => false,
		};
	}

	/**
	 * Checks if this is an active processing state.
	 *
	 * @return bool True if the loop is actively processing.
	 */
	public function isProcessing(): bool
	{
		return match ($this) {
			self::THINKING,
			self::ACTING => true,
			default => false,
		};
	}

	/**
	 * Returns a human-readable description of the state.
	 *
	 * @return string
	 */
	public function getDescription(): string
	{
		return match ($this) {
			self::PENDING => 'Waiting to start',
			self::THINKING => 'Processing request',
			self::ACTING => 'Executing tools',
			self::COMPLETED => 'Completed successfully',
			self::CANCELLED => 'Cancelled by user',
			self::MAX_TURNS_REACHED => 'Maximum iterations reached',
			self::ERROR => 'Error occurred',
		};
	}

	/**
	 * Checks if the state indicates success.
	 *
	 * @return bool True if the loop completed successfully.
	 */
	public function isSuccess(): bool
	{
		return $this === self::COMPLETED;
	}

	/**
	 * Checks if the state indicates a failure.
	 *
	 * @return bool True if the loop failed or was stopped abnormally.
	 */
	public function isFailure(): bool
	{
		return match ($this) {
			self::CANCELLED,
			self::ERROR => true,
			default => false,
		};
	}
}
