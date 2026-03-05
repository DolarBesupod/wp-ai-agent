<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Contracts;

use Automattic\WpAiAgent\Core\ValueObjects\Message;
use Automattic\WpAiAgent\Core\ValueObjects\SessionId;

/**
 * Interface for conversation sessions.
 *
 * A session represents a single conversation with the AI agent. It maintains
 * the message history, system prompt, and metadata needed for persistence
 * and context management.
 *
 * @since 0.1.0
 */
interface SessionInterface
{
	/**
	 * Returns the session identifier.
	 *
	 * @return SessionId
	 */
	public function getId(): SessionId;

	/**
	 * Returns all messages in the session.
	 *
	 * @return array<int, Message>
	 */
	public function getMessages(): array;

	/**
	 * Adds a message to the session.
	 *
	 * @param Message $message The message to add.
	 *
	 * @return void
	 */
	public function addMessage(Message $message): void;

	/**
	 * Returns the system prompt for this session.
	 *
	 * @return string
	 */
	public function getSystemPrompt(): string;

	/**
	 * Sets the system prompt for this session.
	 *
	 * @param string $prompt The system prompt.
	 *
	 * @return void
	 */
	public function setSystemPrompt(string $prompt): void;

	/**
	 * Returns the session metadata.
	 *
	 * @return SessionMetadataInterface
	 */
	public function getMetadata(): SessionMetadataInterface;

	/**
	 * Clears all messages from the session.
	 *
	 * This does not affect the system prompt or metadata.
	 *
	 * @return void
	 */
	public function clearMessages(): void;

	/**
	 * Returns the count of messages in the session.
	 *
	 * @return int
	 */
	public function getMessageCount(): int;

	/**
	 * Returns the last message in the session.
	 *
	 * @return Message|null The last message or null if session is empty.
	 */
	public function getLastMessage(): ?Message;

	/**
	 * Converts the session to an array for serialization.
	 *
	 * @return array{
	 *     id: string,
	 *     system_prompt: string,
	 *     messages: array<int, array<string, mixed>>,
	 *     metadata: array<string, mixed>
	 * }
	 */
	public function toArray(): array;

	/**
	 * Returns messages formatted for the AI adapter.
	 *
	 * This may exclude system messages or transform the format as needed
	 * by the specific AI provider.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getMessagesForApi(): array;
}
