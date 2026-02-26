<?php

// phpcs:disable

declare(strict_types=1);

namespace WpAiAgent\Tests\Stubs;

use WpAiAgent\Core\Contracts\AgentInterface;
use WpAiAgent\Core\Contracts\SessionInterface;
use WpAiAgent\Core\Session\Session;
use WpAiAgent\Core\ValueObjects\SessionId;

/**
 * Minimal stub implementation of AgentInterface for subprocess-based tests.
 *
 * Used by the chat() REPL subprocess test helper. All methods are no-ops or
 * return sensible defaults. Not intended for use in standard PHPUnit tests.
 */
final class MinimalAgentStub implements AgentInterface
{
	private ?SessionInterface $session = null;

	public function startSession(): SessionId
	{
		$this->session = new Session(null, '');
		return $this->session->getId();
	}

	public function resumeSession(SessionId $session_id): void
	{
	}

	public function sendMessage(string $message): void
	{
	}

	public function getCurrentSession(): ?SessionInterface
	{
		return $this->session;
	}

	public function endSession(): void
	{
	}
}
