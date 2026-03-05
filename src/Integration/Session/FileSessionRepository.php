<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Integration\Session;

use Automattic\Automattic\WpAiAgent\Core\Contracts\SessionInterface;
use Automattic\Automattic\WpAiAgent\Core\Contracts\SessionMetadataInterface;
use Automattic\Automattic\WpAiAgent\Core\Contracts\SessionRepositoryInterface;
use Automattic\Automattic\WpAiAgent\Core\Exceptions\SessionNotFoundException;
use Automattic\Automattic\WpAiAgent\Core\Exceptions\SessionPersistenceException;
use Automattic\Automattic\WpAiAgent\Core\Session\Session;
use Automattic\Automattic\WpAiAgent\Core\Session\SessionMetadata;
use Automattic\Automattic\WpAiAgent\Core\ValueObjects\SessionId;

/**
 * File-based session repository implementation.
 *
 * Persists sessions as individual JSON files in a configurable storage directory.
 * Supports file locking for concurrent access safety.
 *
 * @since n.e.x.t
 */
final class FileSessionRepository implements SessionRepositoryInterface
{
	/**
	 * Default sessions storage directory within the user's home.
	 */
	private const DEFAULT_STORAGE_SUBPATH = '.wp-ai-agent/sessions';

	/**
	 * File extension for session files.
	 */
	private const FILE_EXTENSION = '.json';

	/**
	 * The base storage directory path.
	 *
	 * @var string
	 */
	private string $storage_path;

	/**
	 * The serializer for JSON conversion.
	 *
	 * @var JsonSessionSerializer
	 */
	private JsonSessionSerializer $serializer;

	/**
	 * Creates a new FileSessionRepository instance.
	 *
	 * @param string|null                $storage_path Optional custom storage path.
	 *                                                  Defaults to ~/.wp-ai-agent/sessions/
	 * @param JsonSessionSerializer|null $serializer   Optional custom serializer instance.
	 */
	public function __construct(
		?string $storage_path = null,
		?JsonSessionSerializer $serializer = null
	) {
		$this->storage_path = $storage_path ?? $this->getDefaultStoragePath();
		$this->serializer = $serializer ?? new JsonSessionSerializer();
	}

	/**
	 * {@inheritDoc}
	 */
	public function save(SessionInterface $session): void
	{
		$this->ensureStorageDirectoryExists();

		$file_path = $this->getSessionFilePath($session->getId());
		$json = $this->serializer->serialize($session);

		$this->writeFileWithLock($file_path, $json);
	}

	/**
	 * {@inheritDoc}
	 */
	public function load(SessionId $session_id): SessionInterface
	{
		$file_path = $this->getSessionFilePath($session_id);

		if (!file_exists($file_path)) {
			throw new SessionNotFoundException($session_id);
		}

		$json = $this->readFileWithLock($file_path);

		return $this->serializer->deserialize($json);
	}

	/**
	 * {@inheritDoc}
	 */
	public function exists(SessionId $session_id): bool
	{
		$file_path = $this->getSessionFilePath($session_id);

		return file_exists($file_path) && is_readable($file_path);
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete(SessionId $session_id): bool
	{
		$file_path = $this->getSessionFilePath($session_id);

		if (!file_exists($file_path)) {
			return false;
		}

		$deleted = @unlink($file_path);

		if (!$deleted) {
			throw SessionPersistenceException::deleteFailed(
				sprintf('Unable to delete file: %s', $file_path)
			);
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function listAll(): array
	{
		if (!is_dir($this->storage_path)) {
			return [];
		}

		$session_ids = [];
		$pattern = $this->storage_path . '/*' . self::FILE_EXTENSION;
		$files = glob($pattern);

		if (false === $files) {
			return [];
		}

		foreach ($files as $file) {
			$basename = basename($file, self::FILE_EXTENSION);
			if ('' !== $basename) {
				$session_ids[] = SessionId::fromString($basename);
			}
		}

		return $session_ids;
	}

	/**
	 * {@inheritDoc}
	 */
	public function listWithMetadata(): array
	{
		$session_ids = $this->listAll();
		$result = [];

		foreach ($session_ids as $session_id) {
			try {
				$metadata = $this->loadMetadataOnly($session_id);
				$result[] = [
					'id' => $session_id,
					'metadata' => $metadata,
				];
			} catch (\Throwable $exception) {
				// Skip sessions that can't be read properly.
				continue;
			}
		}

		// Sort by updated_at descending (most recent first).
		usort($result, static function (array $a, array $b): int {
			$a_updated = $a['metadata']->getUpdatedAt()->getTimestamp();
			$b_updated = $b['metadata']->getUpdatedAt()->getTimestamp();

			return $b_updated <=> $a_updated;
		});

		return $result;
	}

	/**
	 * Finds the most recent sessions, sorted by update time.
	 *
	 * @param int $limit Maximum number of sessions to return.
	 *
	 * @return array<int, array{id: SessionId, metadata: SessionMetadataInterface}>
	 */
	public function findRecent(int $limit = 10): array
	{
		$all_sessions = $this->listWithMetadata();

		return array_slice($all_sessions, 0, $limit);
	}

	/**
	 * Returns the storage path used by this repository.
	 *
	 * @return string
	 */
	public function getStoragePath(): string
	{
		return $this->storage_path;
	}

	/**
	 * Loads only the metadata from a session file without parsing all messages.
	 *
	 * This is more efficient for listing sessions.
	 *
	 * @param SessionId $session_id The session identifier.
	 *
	 * @return SessionMetadataInterface
	 *
	 * @throws SessionNotFoundException If the session file doesn't exist.
	 * @throws SessionPersistenceException If reading fails.
	 */
	private function loadMetadataOnly(SessionId $session_id): SessionMetadataInterface
	{
		$file_path = $this->getSessionFilePath($session_id);

		if (!file_exists($file_path)) {
			throw new SessionNotFoundException($session_id);
		}

		$json = $this->readFileWithLock($file_path);
		$data = json_decode($json, true);

		if (!is_array($data) || !isset($data['metadata']) || !is_array($data['metadata'])) {
			throw SessionPersistenceException::loadFailed('Invalid session format: missing metadata');
		}

		/** @var array{created_at?: string, updated_at?: string, working_directory?: string, custom?: array<string, mixed>} $metadata_data */
		$metadata_data = $data['metadata'];

		return SessionMetadata::fromArray($metadata_data);
	}

	/**
	 * Determines the default storage path based on the user's home directory.
	 *
	 * @return string
	 */
	private function getDefaultStoragePath(): string
	{
		$home = getenv('HOME');

		if (false === $home || '' === $home) {
			// Fallback for Windows or unusual environments.
			$home = getenv('USERPROFILE');

			if (false === $home || '' === $home) {
				$home = sys_get_temp_dir();
			}
		}

		return rtrim($home, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::DEFAULT_STORAGE_SUBPATH;
	}

	/**
	 * Constructs the full file path for a given session ID.
	 *
	 * @param SessionId $session_id The session identifier.
	 *
	 * @return string
	 */
	private function getSessionFilePath(SessionId $session_id): string
	{
		return $this->storage_path . DIRECTORY_SEPARATOR . $session_id->toString() . self::FILE_EXTENSION;
	}

	/**
	 * Ensures the storage directory exists, creating it if necessary.
	 *
	 * @return void
	 *
	 * @throws SessionPersistenceException If directory creation fails.
	 */
	private function ensureStorageDirectoryExists(): void
	{
		if (is_dir($this->storage_path)) {
			return;
		}

		$created = @mkdir($this->storage_path, 0755, true);

		if (!$created && !is_dir($this->storage_path)) {
			throw SessionPersistenceException::saveFailed(
				sprintf('Unable to create storage directory: %s', $this->storage_path)
			);
		}
	}

	/**
	 * Writes content to a file with exclusive locking.
	 *
	 * @param string $file_path The file path to write to.
	 * @param string $content   The content to write.
	 *
	 * @return void
	 *
	 * @throws SessionPersistenceException If writing fails.
	 */
	private function writeFileWithLock(string $file_path, string $content): void
	{
		// Write to a temporary file first, then move atomically.
		$temp_file = $file_path . '.tmp.' . uniqid('', true);

		$handle = @fopen($temp_file, 'w');

		if (false === $handle) {
			throw SessionPersistenceException::saveFailed(
				sprintf('Unable to open file for writing: %s', $temp_file)
			);
		}

		try {
			// Acquire exclusive lock.
			if (!flock($handle, LOCK_EX)) {
				throw SessionPersistenceException::saveFailed(
					sprintf('Unable to acquire lock on file: %s', $temp_file)
				);
			}

			$written = fwrite($handle, $content);

			if (false === $written) {
				throw SessionPersistenceException::saveFailed(
					sprintf('Unable to write to file: %s', $temp_file)
				);
			}

			// Flush and sync to disk before releasing lock.
			fflush($handle);
			flock($handle, LOCK_UN);
		} finally {
			fclose($handle);
		}

		// Atomically move temp file to final destination.
		$moved = @rename($temp_file, $file_path);

		if (!$moved) {
			@unlink($temp_file);
			throw SessionPersistenceException::saveFailed(
				sprintf('Unable to move temporary file to: %s', $file_path)
			);
		}
	}

	/**
	 * Reads content from a file with shared locking.
	 *
	 * @param string $file_path The file path to read from.
	 *
	 * @return string The file content.
	 *
	 * @throws SessionPersistenceException If reading fails.
	 */
	private function readFileWithLock(string $file_path): string
	{
		$handle = @fopen($file_path, 'r');

		if (false === $handle) {
			throw SessionPersistenceException::loadFailed(
				sprintf('Unable to open file for reading: %s', $file_path)
			);
		}

		try {
			// Acquire shared lock for reading.
			if (!flock($handle, LOCK_SH)) {
				throw SessionPersistenceException::loadFailed(
					sprintf('Unable to acquire lock on file: %s', $file_path)
				);
			}

			$content = stream_get_contents($handle);

			if (false === $content) {
				throw SessionPersistenceException::loadFailed(
					sprintf('Unable to read file content: %s', $file_path)
				);
			}

			flock($handle, LOCK_UN);

			return $content;
		} finally {
			fclose($handle);
		}
	}
}
