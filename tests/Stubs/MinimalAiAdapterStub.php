<?php

// phpcs:disable

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Stubs;

use Automattic\Automattic\WpAiAgent\Core\Contracts\AiResponseInterface;
use Automattic\Automattic\WpAiAgent\Core\Credential\AuthMode;
use Automattic\Automattic\WpAiAgent\Integration\AiClient\AiClientAdapterInterface;
use WordPress\AiClient\Providers\ProviderRegistry;

/**
 * Minimal stub implementation of AiClientAdapterInterface for subprocess-based tests.
 *
 * Used by the chat() REPL subprocess test helper. All methods are no-ops or
 * return sensible defaults. Not intended for use in standard PHPUnit tests.
 */
final class MinimalAiAdapterStub implements AiClientAdapterInterface
{
	private string $model = 'claude-sonnet-4-20250514';

	private string $provider_id = 'anthropic';

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

	public function getProviderRegistry(): ProviderRegistry
	{
		return new ProviderRegistry();
	}

	public function isConfigured(): bool
	{
		return true;
	}

	public function getProviderId(): string
	{
		return $this->provider_id;
	}

	public function switchProvider(string $provider_id, string $api_key, AuthMode $auth_mode = AuthMode::API_KEY): void
	{
		$this->provider_id = $provider_id;
	}
}
