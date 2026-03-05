<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Exceptions;

use Automattic\WpAiAgent\Core\ValueObjects\SessionId;

/**
 * Exception thrown when a session is not found.
 *
 * @since n.e.x.t
 */
class SessionNotFoundException extends AgentException
{
	private SessionId $session_id;

	/**
	 * Creates a new SessionNotFoundException.
	 *
	 * @param SessionId       $session_id The session ID that was not found.
	 * @param string          $message    Optional custom message.
	 * @param \Throwable|null $previous   Optional previous exception.
	 */
	public function __construct(
		SessionId $session_id,
		string $message = '',
		?\Throwable $previous = null
	) {
		$this->session_id = $session_id;

		if ($message === '') {
			$message = sprintf('Session with ID "%s" not found.', $session_id->toString());
		}

		parent::__construct($message, 0, $previous);
	}

	/**
	 * Returns the session ID that was not found.
	 *
	 * @return SessionId
	 */
	public function getSessionId(): SessionId
	{
		return $this->session_id;
	}
}
