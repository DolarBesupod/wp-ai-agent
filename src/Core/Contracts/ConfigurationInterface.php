<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Contracts;

/**
 * Interface for agent configuration.
 *
 * The configuration interface provides access to all agent settings including
 * API credentials, model parameters, tool settings, and session storage paths.
 *
 * @since 0.1.0
 */
interface ConfigurationInterface
{
	/**
	 * Returns a configuration value.
	 *
	 * @param string $key     The configuration key (supports dot notation).
	 * @param mixed  $default The default value if key doesn't exist.
	 *
	 * @return mixed
	 */
	public function get(string $key, mixed $default = null): mixed;

	/**
	 * Sets a configuration value.
	 *
	 * @param string $key   The configuration key.
	 * @param mixed  $value The value to set.
	 *
	 * @return void
	 */
	public function set(string $key, mixed $value): void;

	/**
	 * Checks if a configuration key exists.
	 *
	 * @param string $key The configuration key.
	 *
	 * @return bool
	 */
	public function has(string $key): bool;

	/**
	 * Returns the AI model to use.
	 *
	 * @return string The model identifier (e.g., "claude-sonnet-4-20250514").
	 */
	public function getModel(): string;

	/**
	 * Returns the API key for the AI provider.
	 *
	 * @return string
	 *
	 * @throws \Automattic\WpAiAgent\Core\Exceptions\ConfigurationException If API key is not set.
	 */
	public function getApiKey(): string;

	/**
	 * Returns the maximum tokens for AI responses.
	 *
	 * @return int
	 */
	public function getMaxTokens(): int;

	/**
	 * Returns the temperature for AI responses.
	 *
	 * @return float A value between 0.0 and 1.0.
	 */
	public function getTemperature(): float;

	/**
	 * Returns the session storage directory path.
	 *
	 * @return string
	 */
	public function getSessionStoragePath(): string;

	/**
	 * Returns the system prompt template.
	 *
	 * @return string
	 */
	public function getSystemPrompt(): string;

	/**
	 * Returns the maximum number of agent loop iterations.
	 *
	 * @return int
	 */
	public function getMaxIterations(): int;

	/**
	 * Checks if debug mode is enabled.
	 *
	 * @return bool
	 */
	public function isDebugEnabled(): bool;

	/**
	 * Checks if streaming is enabled.
	 *
	 * @return bool
	 */
	public function isStreamingEnabled(): bool;

	/**
	 * Returns tools that should bypass confirmation.
	 *
	 * @return array<int, string> List of tool names.
	 */
	public function getBypassedTools(): array;

	/**
	 * Returns whether auto-confirm mode is enabled.
	 *
	 * When true, all tool executions are confirmed automatically without
	 * prompting the user, equivalent to the --yolo flag at runtime.
	 *
	 * @return bool
	 *
	 * @since 0.1.0
	 */
	public function getAutoConfirm(): bool;

	/**
	 * Returns all configuration values as an array.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array;

	/**
	 * Loads configuration from a file.
	 *
	 * @param string $path The path to the configuration file.
	 *
	 * @return void
	 *
	 * @throws \Automattic\WpAiAgent\Core\Exceptions\ConfigurationException If the file cannot be loaded.
	 */
	public function loadFromFile(string $path): void;

	/**
	 * Merges additional configuration values.
	 *
	 * @param array<string, mixed> $config The configuration to merge.
	 *
	 * @return void
	 */
	public function merge(array $config): void;
}
