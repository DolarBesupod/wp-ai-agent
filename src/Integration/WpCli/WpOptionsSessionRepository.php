<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\WpCli;

use Automattic\WpAiAgent\Core\Contracts\SessionInterface;
use Automattic\WpAiAgent\Core\Contracts\SessionMetadataInterface;
use Automattic\WpAiAgent\Core\Contracts\SessionRepositoryInterface;
use Automattic\WpAiAgent\Core\Exceptions\SessionNotFoundException;
use Automattic\WpAiAgent\Core\Exceptions\SessionPersistenceException;
use Automattic\WpAiAgent\Core\ValueObjects\SessionId;
use Automattic\WpAiAgent\Integration\Session\JsonSessionSerializer;

/**
 * WordPress options-based session repository.
 *
 * Persists sessions as individual WordPress options with autoload=false.
 * An index option tracks all stored session IDs.
 *
 * Option naming:
 * - Index:       wp_ai_agent_sessions
 * - Per session: wp_ai_agent_session_{session_id}
 *
 * @since 0.1.0
 */
class WpOptionsSessionRepository implements SessionRepositoryInterface
{
	/**
	 * WordPress option name for the session index.
	 */
	private const INDEX_OPTION = 'wp_ai_agent_sessions';

	/**
	 * Prefix for per-session WordPress options.
	 */
	private const SESSION_OPTION_PREFIX = 'wp_ai_agent_session_';

	/**
	 * The serializer for JSON conversion.
	 *
	 * @var JsonSessionSerializer
	 */
	private JsonSessionSerializer $serializer;

	/**
	 * Creates a new WpOptionsSessionRepository instance.
	 *
	 * @param JsonSessionSerializer|null $serializer Optional custom serializer instance.
	 *
	 * @since 0.1.0
	 */
	public function __construct(?JsonSessionSerializer $serializer = null)
	{
		$this->serializer = $serializer ?? new JsonSessionSerializer();
	}

	/**
	 * Saves a session to the WordPress options table.
	 *
	 * Serializes the session to JSON and stores it as a WordPress option with
	 * autoload disabled. Also adds the session ID to the index if not present.
	 *
	 * @param SessionInterface $session The session to save.
	 *
	 * @return void
	 *
	 * @throws SessionPersistenceException If serialization fails.
	 *
	 * @since 0.1.0
	 */
	public function save(SessionInterface $session): void
	{
		$session_id = $session->getId();
		$id_string = $session_id->toString();
		$option_key = self::SESSION_OPTION_PREFIX . $id_string;

		$json = $this->serializer->serialize($session);

		\update_option($option_key, $json, false);

		$index = json_decode(\get_option(self::INDEX_OPTION, '[]'), true);

		if (!is_array($index)) {
			$index = [];
		}

		if (!in_array($id_string, $index, true)) {
			$index[] = $id_string;
			\update_option(self::INDEX_OPTION, json_encode($index), false);
		}
	}

	/**
	 * Loads a session from the WordPress options table.
	 *
	 * @param SessionId $session_id The session identifier.
	 *
	 * @return SessionInterface The loaded session.
	 *
	 * @throws SessionNotFoundException     If the option does not exist.
	 * @throws SessionPersistenceException  If the stored value is invalid JSON.
	 *
	 * @since 0.1.0
	 */
	public function load(SessionId $session_id): SessionInterface
	{
		$option_key = self::SESSION_OPTION_PREFIX . $session_id->toString();

		$value = \get_option($option_key, false);

		if (false === $value) {
			throw new SessionNotFoundException($session_id);
		}

		if (!is_string($value)) {
			throw SessionPersistenceException::loadFailed(
				sprintf('Expected string from option "%s", got %s', $option_key, gettype($value))
			);
		}

		return $this->serializer->deserialize($value);
	}

	/**
	 * Checks whether a session exists in the WordPress options table.
	 *
	 * @param SessionId $session_id The session identifier.
	 *
	 * @return bool True if the session exists, false otherwise.
	 *
	 * @since 0.1.0
	 */
	public function exists(SessionId $session_id): bool
	{
		$option_key = self::SESSION_OPTION_PREFIX . $session_id->toString();

		return \get_option($option_key, null) !== null;
	}

	/**
	 * Deletes a session from the WordPress options table and removes it from the index.
	 *
	 * @param SessionId $session_id The session identifier.
	 *
	 * @return bool True if the session existed and was deleted, false if it did not exist.
	 *
	 * @since 0.1.0
	 */
	public function delete(SessionId $session_id): bool
	{
		$id_string = $session_id->toString();
		$option_key = self::SESSION_OPTION_PREFIX . $id_string;

		$existed = \get_option($option_key, false) !== false;

		\delete_option($option_key);

		$index = json_decode(\get_option(self::INDEX_OPTION, '[]'), true);

		if (!is_array($index)) {
			$index = [];
		}

		$filtered = array_values(array_filter($index, static function (mixed $entry) use ($id_string): bool {
			return $entry !== $id_string;
		}));

		\update_option(self::INDEX_OPTION, json_encode($filtered), false);

		return $existed;
	}

	/**
	 * Lists all session identifiers stored in the WordPress options table.
	 *
	 * Returns an empty array when the index option does not exist.
	 *
	 * @return array<int, SessionId> The session identifiers.
	 *
	 * @since 0.1.0
	 */
	public function listAll(): array
	{
		$raw = \get_option(self::INDEX_OPTION, false);

		if (false === $raw) {
			return [];
		}

		$index = json_decode(is_string($raw) ? $raw : '[]', true);

		if (!is_array($index)) {
			return [];
		}

		$session_ids = [];

		foreach ($index as $id_string) {
			if (is_string($id_string) && '' !== $id_string) {
				$session_ids[] = SessionId::fromString($id_string);
			}
		}

		return $session_ids;
	}

	/**
	 * Lists sessions with their metadata without loading full message history.
	 *
	 * Loads each session in the index and returns its ID paired with metadata.
	 * Sessions that cannot be read are silently skipped.
	 *
	 * @return array<int, array{id: SessionId, metadata: SessionMetadataInterface}>
	 *
	 * @since 0.1.0
	 */
	public function listWithMetadata(): array
	{
		$session_ids = $this->listAll();
		$result = [];

		foreach ($session_ids as $session_id) {
			try {
				$session = $this->load($session_id);
				$result[] = [
					'id' => $session_id,
					'metadata' => $session->getMetadata(),
				];
			} catch (\Throwable $exception) {
				// Skip sessions that cannot be read.
				continue;
			}
		}

		usort($result, static function (array $a, array $b): int {
			/** @var array{id: SessionId, metadata: SessionMetadataInterface} $a */
			/** @var array{id: SessionId, metadata: SessionMetadataInterface} $b */
			$a_updated = $a['metadata']->getUpdatedAt()->getTimestamp();
			$b_updated = $b['metadata']->getUpdatedAt()->getTimestamp();

			return $b_updated <=> $a_updated;
		});

		return $result;
	}
}
