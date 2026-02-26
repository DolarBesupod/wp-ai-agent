<?php

// phpcs:disable

declare(strict_types=1);

namespace WpAiAgent\Tests\Stubs;

use WpAiAgent\Core\Contracts\AgentInterface;
use WpAiAgent\Core\Contracts\SessionInterface;
use WpAiAgent\Core\ValueObjects\SessionId;

/**
 * Minimal stub implementation of AgentInterface for subprocess-based tests.
 *
 * Used by the chat() REPL subprocess test helper. All methods are no-ops or
 * return sensible defaults. Not intended for use in standard PHPUnit tests.
 */
final class MinimalAgentStub implements AgentInterface
{
	public function startSession(): SessionId
	{
		return SessionId::fromString('test-session');
	}

	public function resumeSession(SessionId $session_id): void
	{
	}

	public function sendMessage(string $message): void
	{
	}

	public function getCurrentSession(): ?SessionInterface
	{
		return null;
	}

	public function endSession(): void
	{
	}
}
