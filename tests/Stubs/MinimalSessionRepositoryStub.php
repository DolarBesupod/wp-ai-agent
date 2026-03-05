<?php

// phpcs:disable

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Stubs;

use Automattic\Automattic\WpAiAgent\Core\Contracts\SessionInterface;
use Automattic\Automattic\WpAiAgent\Core\Contracts\SessionRepositoryInterface;
use Automattic\Automattic\WpAiAgent\Core\Exceptions\SessionNotFoundException;
use Automattic\Automattic\WpAiAgent\Core\ValueObjects\SessionId;

/**
 * Minimal stub implementation of SessionRepositoryInterface for subprocess-based tests.
 *
 * Used by the chat() REPL subprocess test helper. All mutating methods are no-ops;
 * load() always throws SessionNotFoundException. Not intended for use in standard
 * PHPUnit tests.
 */
final class MinimalSessionRepositoryStub implements SessionRepositoryInterface
{
	public function save(SessionInterface $session): void
	{
	}

	public function load(SessionId $session_id): SessionInterface
	{
		throw new SessionNotFoundException('No sessions in stub');
	}

	public function exists(SessionId $session_id): bool
	{
		return false;
	}

	public function delete(SessionId $session_id): bool
	{
		return false;
	}

	public function listAll(): array
	{
		return [];
	}

	public function listWithMetadata(): array
	{
		return [];
	}
}
