<?php

declare(strict_types=1);

namespace WpAiAgent\Core\Exceptions;

/**
 * Exception thrown when session persistence operations fail.
 *
 * @since n.e.x.t
 */
class SessionPersistenceException extends AgentException
{
	/**
	 * Creates an exception for save failures.
	 *
	 * @param string          $reason   The reason for the failure.
	 * @param \Throwable|null $previous Optional previous exception.
	 *
	 * @return self
	 */
	public static function saveFailed(string $reason, ?\Throwable $previous = null): self
	{
		return new self(
			sprintf('Failed to save session: %s', $reason),
			0,
			$previous
		);
	}

	/**
	 * Creates an exception for load failures.
	 *
	 * @param string          $reason   The reason for the failure.
	 * @param \Throwable|null $previous Optional previous exception.
	 *
	 * @return self
	 */
	public static function loadFailed(string $reason, ?\Throwable $previous = null): self
	{
		return new self(
			sprintf('Failed to load session: %s', $reason),
			0,
			$previous
		);
	}

	/**
	 * Creates an exception for delete failures.
	 *
	 * @param string          $reason   The reason for the failure.
	 * @param \Throwable|null $previous Optional previous exception.
	 *
	 * @return self
	 */
	public static function deleteFailed(string $reason, ?\Throwable $previous = null): self
	{
		return new self(
			sprintf('Failed to delete session: %s', $reason),
			0,
			$previous
		);
	}
}
