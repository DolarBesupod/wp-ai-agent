<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\WpCli;

use WpAiAgent\Core\Contracts\SessionInterface;
use WpAiAgent\Core\Contracts\SessionRepositoryInterface;
use WpAiAgent\Core\Exceptions\SessionNotFoundException;
use WpAiAgent\Core\ValueObjects\SessionId;

/**
 * WordPress options-based session repository stub.
 *
 * Placeholder class to satisfy type requirements while the full implementation
 * is completed in a subsequent task (T1.3). All operations are no-ops or throw
 * SessionNotFoundException.
 *
 * @since n.e.x.t
 */
class WpOptionsSessionRepository implements SessionRepositoryInterface
{
	/**
	 * {@inheritDoc}
	 */
	public function save(SessionInterface $session): void
	{
		// Stub: full implementation in subsequent task.
	}

	/**
	 * {@inheritDoc}
	 */
	public function load(SessionId $session_id): SessionInterface
	{
		throw new SessionNotFoundException($session_id);
	}

	/**
	 * {@inheritDoc}
	 */
	public function exists(SessionId $session_id): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete(SessionId $session_id): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function listAll(): array
	{
		return [];
	}

	/**
	 * {@inheritDoc}
	 */
	public function listWithMetadata(): array
	{
		return [];
	}
}
