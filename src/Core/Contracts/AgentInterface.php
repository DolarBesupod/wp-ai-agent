<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Core\Contracts;

use Automattic\Automattic\WpAiAgent\Core\ValueObjects\SessionId;

/**
 * Interface for the main agent that coordinates the conversation loop.
 *
 * The agent is the entry point for interacting with the AI system. It manages
 * the session lifecycle and delegates to the agent loop for processing messages.
 *
 * @since n.e.x.t
 */
interface AgentInterface
{
	/**
	 * Starts a new conversation session.
	 *
	 * @return SessionId The identifier of the newly created session.
	 */
	public function startSession(): SessionId;

	/**
	 * Resumes an existing conversation session.
	 *
	 * @param SessionId $session_id The session to resume.
	 *
	 * @return void
	 *
	 * @throws \Automattic\WpAiAgent\Core\Exceptions\SessionNotFoundException If the session does not exist.
	 */
	public function resumeSession(SessionId $session_id): void;

	/**
	 * Sends a user message and processes the response.
	 *
	 * This method triggers the agent loop which may execute tools and generate
	 * multiple responses before completing the turn.
	 *
	 * @param string $message The user's input message.
	 *
	 * @return void
	 */
	public function sendMessage(string $message): void;

	/**
	 * Returns the current session.
	 *
	 * @return SessionInterface|null The current session or null if none is active.
	 */
	public function getCurrentSession(): ?SessionInterface;

	/**
	 * Ends the current session.
	 *
	 * @return void
	 */
	public function endSession(): void;
}
