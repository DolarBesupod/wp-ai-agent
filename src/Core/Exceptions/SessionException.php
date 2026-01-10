<?php

declare(strict_types=1);

namespace PhpCliAgent\Core\Exceptions;

/**
 * Exception thrown for general session-related errors.
 *
 * This serves as a base for more specific session exceptions like
 * SessionNotFoundException and SessionPersistenceException.
 *
 * @since n.e.x.t
 */
class SessionException extends AgentException
{
	/**
	 * Creates an exception for session state errors.
	 *
	 * @param string          $reason   The reason for the error.
	 * @param \Throwable|null $previous Optional previous exception.
	 *
	 * @return self
	 */
	public static function invalidState(string $reason, ?\Throwable $previous = null): self
	{
		return new self(
			sprintf('Invalid session state: %s', $reason),
			0,
			$previous,
			['type' => 'invalid_state', 'reason' => $reason]
		);
	}

	/**
	 * Creates an exception for session expiration.
	 *
	 * @param string $session_id The expired session identifier.
	 *
	 * @return self
	 */
	public static function expired(string $session_id): self
	{
		return new self(
			sprintf('Session "%s" has expired.', $session_id),
			0,
			null,
			['type' => 'expired', 'session_id' => $session_id]
		);
	}

	/**
	 * Creates an exception for session initialization failures.
	 *
	 * @param string          $reason   The reason for the failure.
	 * @param \Throwable|null $previous Optional previous exception.
	 *
	 * @return self
	 */
	public static function initializationFailed(string $reason, ?\Throwable $previous = null): self
	{
		return new self(
			sprintf('Failed to initialize session: %s', $reason),
			0,
			$previous,
			['type' => 'initialization_failed', 'reason' => $reason]
		);
	}

	/**
	 * Creates an exception for session corruption.
	 *
	 * @param string          $session_id The corrupted session identifier.
	 * @param string          $reason     The reason for corruption.
	 * @param \Throwable|null $previous   Optional previous exception.
	 *
	 * @return self
	 */
	public static function corrupted(string $session_id, string $reason, ?\Throwable $previous = null): self
	{
		return new self(
			sprintf('Session "%s" is corrupted: %s', $session_id, $reason),
			0,
			$previous,
			['type' => 'corrupted', 'session_id' => $session_id, 'reason' => $reason]
		);
	}
}
