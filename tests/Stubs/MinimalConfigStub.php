<?php

// phpcs:disable

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Stubs;

use Automattic\Automattic\WpAiAgent\Core\Contracts\ConfigurationInterface;

/**
 * Minimal stub implementation of ConfigurationInterface for subprocess-based tests.
 *
 * Used by the chat() REPL subprocess test helper. Returns hardcoded defaults for
 * all accessors. Not intended for use in standard PHPUnit tests.
 */
final class MinimalConfigStub implements ConfigurationInterface
{
	public function get(string $key, mixed $default = null): mixed
	{
		return $default;
	}

	public function set(string $key, mixed $value): void
	{
	}

	public function has(string $key): bool
	{
		return false;
	}

	public function getModel(): string
	{
		return 'claude-sonnet-4-6';
	}

	public function getApiKey(): string
	{
		return '';
	}

	public function getMaxTokens(): int
	{
		return 8192;
	}

	public function getTemperature(): float
	{
		return 1.0;
	}

	public function getSessionStoragePath(): string
	{
		return '';
	}

	public function getSystemPrompt(): string
	{
		return '';
	}

	public function getMaxIterations(): int
	{
		return 10;
	}

	public function isDebugEnabled(): bool
	{
		return false;
	}

	public function isStreamingEnabled(): bool
	{
		return true;
	}

	public function getBypassedTools(): array
	{
		return [];
	}

	public function getAutoConfirm(): bool
	{
		return false;
	}

	public function toArray(): array
	{
		return [];
	}

	public function loadFromFile(string $path): void
	{
	}

	public function merge(array $config): void
	{
	}
}
