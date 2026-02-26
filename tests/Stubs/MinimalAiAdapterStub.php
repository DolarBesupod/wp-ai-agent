<?php

// phpcs:disable

declare(strict_types=1);

namespace WpAiAgent\Tests\Stubs;

use WpAiAgent\Core\Contracts\AiAdapterInterface;
use WpAiAgent\Core\Contracts\AiResponseInterface;

/**
 * Minimal stub implementation of AiAdapterInterface for subprocess-based tests.
 *
 * Used by the chat() REPL subprocess test helper. All methods are no-ops or
 * return sensible defaults. Not intended for use in standard PHPUnit tests.
 */
final class MinimalAiAdapterStub implements AiAdapterInterface
{
	private string $model = 'claude-sonnet-4-20250514';

	public function chat(array $messages, string $system, array $tools = []): AiResponseInterface
	{
		throw new \RuntimeException('MinimalAiAdapterStub::chat() is not implemented');
	}

	public function chatStream(
		array $messages,
		string $system,
		array $tools,
		callable $on_chunk
	): AiResponseInterface {
		throw new \RuntimeException('MinimalAiAdapterStub::chatStream() is not implemented');
	}

	public function setModel(string $model): void
	{
		$this->model = $model;
	}

	public function getModel(): string
	{
		return $this->model;
	}

	public function setMaxTokens(int $max_tokens): void
	{
	}

	public function setTemperature(float $temperature): void
	{
	}

	public function getLastUsage(): ?array
	{
		return null;
	}
}
