<?php

declare(strict_types=1);

namespace WpAiAgent\Core\Contracts;

use WpAiAgent\Core\ValueObjects\SessionId;

/**
 * Interface for session persistence.
 *
 * The session repository handles saving and loading sessions to/from storage.
 * Implementations may use files, databases, or other storage mechanisms.
 *
 * @since n.e.x.t
 */
interface SessionRepositoryInterface
{
	/**
	 * Saves a session to storage.
	 *
	 * @param SessionInterface $session The session to save.
	 *
	 * @return void
	 *
	 * @throws \WpAiAgent\Core\Exceptions\SessionPersistenceException If saving fails.
	 */
	public function save(SessionInterface $session): void;

	/**
	 * Loads a session from storage.
	 *
	 * @param SessionId $session_id The session identifier.
	 *
	 * @return SessionInterface The loaded session.
	 *
	 * @throws \WpAiAgent\Core\Exceptions\SessionNotFoundException If the session doesn't exist.
	 * @throws \WpAiAgent\Core\Exceptions\SessionPersistenceException If loading fails.
	 */
	public function load(SessionId $session_id): SessionInterface;

	/**
	 * Checks if a session exists in storage.
	 *
	 * @param SessionId $session_id The session identifier.
	 *
	 * @return bool True if the session exists, false otherwise.
	 */
	public function exists(SessionId $session_id): bool;

	/**
	 * Deletes a session from storage.
	 *
	 * @param SessionId $session_id The session identifier.
	 *
	 * @return bool True if deleted, false if it didn't exist.
	 *
	 * @throws \WpAiAgent\Core\Exceptions\SessionPersistenceException If deletion fails.
	 */
	public function delete(SessionId $session_id): bool;

	/**
	 * Lists all session identifiers in storage.
	 *
	 * @return array<int, SessionId> The session identifiers.
	 */
	public function listAll(): array;

	/**
	 * Lists sessions with their metadata without loading full message history.
	 *
	 * This is useful for displaying a session list to the user.
	 *
	 * @return array<int, array{id: SessionId, metadata: SessionMetadataInterface}>
	 */
	public function listWithMetadata(): array;
}
