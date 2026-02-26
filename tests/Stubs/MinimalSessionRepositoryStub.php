<?php

// phpcs:disable

declare(strict_types=1);

namespace WpAiAgent\Tests\Stubs;

use WpAiAgent\Core\Contracts\SessionInterface;
use WpAiAgent\Core\Contracts\SessionRepositoryInterface;
use WpAiAgent\Core\Exceptions\SessionNotFoundException;
use WpAiAgent\Core\ValueObjects\SessionId;

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
