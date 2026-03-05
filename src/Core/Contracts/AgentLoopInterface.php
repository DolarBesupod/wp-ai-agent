<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Contracts;

/**
 * Interface for the ReAct (Reasoning and Acting) agent loop.
 *
 * The agent loop implements the core reasoning cycle:
 * 1. Think: Analyze the current context and decide on action
 * 2. Act: Execute chosen tool or generate response
 * 3. Observe: Process tool results and update context
 *
 * The loop continues until the model produces a final response without tool calls.
 *
 * @since n.e.x.t
 */
interface AgentLoopInterface
{
	/**
	 * Runs the agent loop for a given session.
	 *
	 * The loop will:
	 * - Send the session messages to the AI model
	 * - Process any tool calls requested by the model
	 * - Continue until a final response is generated
	 *
	 * @param SessionInterface $session The session containing the conversation context.
	 *
	 * @return void
	 */
	public function run(SessionInterface $session): void;

	/**
	 * Sets the maximum number of iterations before stopping.
	 *
	 * This prevents infinite loops when the model keeps requesting tools.
	 *
	 * @param int $max_iterations The maximum number of loop iterations.
	 *
	 * @return void
	 */
	public function setMaxIterations(int $max_iterations): void;

	/**
	 * Returns the maximum number of iterations.
	 *
	 * @return int
	 */
	public function getMaxIterations(): int;

	/**
	 * Checks if the loop is currently running.
	 *
	 * @return bool
	 */
	public function isRunning(): bool;

	/**
	 * Stops the current loop iteration gracefully.
	 *
	 * This allows the current tool execution to complete but prevents
	 * further iterations.
	 *
	 * @return void
	 */
	public function stop(): void;
}
