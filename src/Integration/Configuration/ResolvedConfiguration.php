<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\Configuration;

use WpAiAgent\Core\Contracts\ConfigurationInterface;
use WpAiAgent\Core\Exceptions\ConfigurationException;

/**
 * Configuration implementation for resolved configuration data.
 *
 * This class implements the ConfigurationInterface and provides access to
 * configuration values resolved from multiple sources.
 *
 * @since n.e.x.t
 */
final class ResolvedConfiguration implements ConfigurationInterface
{
	/**
	 * The configuration data.
	 *
	 * @var array<string, mixed>
	 */
	private array $config;

	/**
	 * Creates a new resolved configuration.
	 *
	 * @param array<string, mixed> $config The configuration data.
	 *
	 * @since n.e.x.t
	 */
	public function __construct(array $config)
	{
		$this->config = $config;
	}

	/**
	 * Returns a configuration value.
	 *
	 * @param string $key     The configuration key (supports dot notation).
	 * @param mixed  $default The default value if key doesn't exist.
	 *
	 * @return mixed
	 *
	 * @since n.e.x.t
	 */
	public function get(string $key, mixed $default = null): mixed
	{
		return $this->getNestedValue($key) ?? $default;
	}

	/**
	 * Sets a configuration value.
	 *
	 * @param string $key   The configuration key.
	 * @param mixed  $value The value to set.
	 *
	 * @return void
	 *
	 * @since n.e.x.t
	 */
	public function set(string $key, mixed $value): void
	{
		$this->setNestedValue($key, $value);
	}

	/**
	 * Checks if a configuration key exists.
	 *
	 * @param string $key The configuration key.
	 *
	 * @return bool
	 *
	 * @since n.e.x.t
	 */
	public function has(string $key): bool
	{
		return $this->getNestedValue($key) !== null;
	}

	/**
	 * Returns the AI model to use.
	 *
	 * @return string The model identifier (e.g., "claude-sonnet-4-20250514").
	 *
	 * @since n.e.x.t
	 */
	public function getModel(): string
	{
		$model = $this->config['provider']['model'] ?? '';
		return is_string($model) ? $model : '';
	}

	/**
	 * Returns the API key for the AI provider.
	 *
	 * @return string
	 *
	 * @throws ConfigurationException If API key is not set.
	 *
	 * @since n.e.x.t
	 */
	public function getApiKey(): string
	{
		$api_key = $this->config['provider']['api_key'] ?? null;
		if ($api_key === null || $api_key === '') {
			throw new ConfigurationException(
				'API key is not set. Please set ANTHROPIC_API_KEY environment variable or configure it in settings.json'
			);
		}
		return is_string($api_key) ? $api_key : '';
	}

	/**
	 * Returns the maximum tokens for AI responses.
	 *
	 * @return int
	 *
	 * @since n.e.x.t
	 */
	public function getMaxTokens(): int
	{
		$value = $this->config['provider']['max_tokens'] ?? 8192;
		return is_numeric($value) ? (int) $value : 8192;
	}

	/**
	 * Returns the temperature for AI responses.
	 *
	 * @return float A value between 0.0 and 1.0.
	 *
	 * @since n.e.x.t
	 */
	public function getTemperature(): float
	{
		$value = $this->config['temperature'] ?? 0.7;
		return is_numeric($value) ? (float) $value : 0.7;
	}

	/**
	 * Returns the session storage directory path.
	 *
	 * @return string
	 *
	 * @since n.e.x.t
	 */
	public function getSessionStoragePath(): string
	{
		$path = $this->config['session_storage_path'] ?? sys_get_temp_dir() . '/php-cli-agent-sessions';
		return is_string($path) ? $path : sys_get_temp_dir() . '/php-cli-agent-sessions';
	}

	/**
	 * Returns the system prompt template.
	 *
	 * @return string
	 *
	 * @since n.e.x.t
	 */
	public function getSystemPrompt(): string
	{
		$prompt = $this->config['default_system_prompt'] ?? '';
		if (empty($prompt)) {
			return $this->getDefaultSystemPrompt();
		}
		return is_string($prompt) ? $prompt : '';
	}

	/**
	 * Returns the maximum number of agent loop iterations.
	 *
	 * @return int
	 *
	 * @since n.e.x.t
	 */
	public function getMaxIterations(): int
	{
		$value = $this->config['max_turns'] ?? 100;
		return is_numeric($value) ? (int) $value : 100;
	}

	/**
	 * Checks if debug mode is enabled.
	 *
	 * @return bool
	 *
	 * @since n.e.x.t
	 */
	public function isDebugEnabled(): bool
	{
		return (bool) ($this->config['debug'] ?? false);
	}

	/**
	 * Checks if streaming is enabled.
	 *
	 * @return bool
	 *
	 * @since n.e.x.t
	 */
	public function isStreamingEnabled(): bool
	{
		return (bool) ($this->config['streaming'] ?? true);
	}

	/**
	 * Returns tools that should bypass confirmation.
	 *
	 * Reads from permissions.allow array.
	 *
	 * @return array<int, string> List of tool names.
	 *
	 * @since n.e.x.t
	 */
	public function getBypassedTools(): array
	{
		$permissions = $this->config['permissions'] ?? [];
		if (!isset($permissions['allow']) || !is_array($permissions['allow'])) {
			return [];
		}

		return array_values(array_filter($permissions['allow'], 'is_string'));
	}

	/**
	 * Returns whether auto-confirm mode is enabled.
	 *
	 * When true, all tool executions are confirmed automatically without
	 * prompting the user, equivalent to the --yolo flag at runtime.
	 *
	 * @return bool
	 *
	 * @since n.e.x.t
	 */
	public function getAutoConfirm(): bool
	{
		return (bool) ( $this->config['auto_confirm'] ?? false );
	}

	/**
	 * Returns all configuration values as an array.
	 *
	 * @return array<string, mixed>
	 *
	 * @since n.e.x.t
	 */
	public function toArray(): array
	{
		return $this->config;
	}

	/**
	 * Loads configuration from a file.
	 *
	 * @param string $path The path to the configuration file.
	 *
	 * @return void
	 *
	 * @throws ConfigurationException If the file cannot be loaded.
	 *
	 * @since n.e.x.t
	 */
	public function loadFromFile(string $path): void
	{
		throw new ConfigurationException(
			'ResolvedConfiguration does not support loading from files. Use ConfigurationResolver instead.'
		);
	}

	/**
	 * Merges additional configuration values.
	 *
	 * @param array<string, mixed> $config The configuration to merge.
	 *
	 * @return void
	 *
	 * @since n.e.x.t
	 */
	public function merge(array $config): void
	{
		$this->config = array_merge($this->config, $config);
	}

	/**
	 * Returns the default system prompt.
	 *
	 * @return string
	 */
	private function getDefaultSystemPrompt(): string
	{
		return <<<PROMPT
You are a helpful AI assistant with access to tools.
When the user asks you to perform tasks, use the available tools to help them.
Be concise but thorough in your responses.
PROMPT;
	}

	/**
	 * Gets a nested value from the configuration.
	 *
	 * @param string $key The dot-notation key.
	 *
	 * @return mixed The value or null if not found.
	 */
	private function getNestedValue(string $key): mixed
	{
		$keys = explode('.', $key);
		$value = $this->config;

		foreach ($keys as $k) {
			if (! is_array($value) || ! array_key_exists($k, $value)) {
				return null;
			}
			$value = $value[$k];
		}

		return $value;
	}

	/**
	 * Sets a nested value in the configuration.
	 *
	 * @param string $key   The dot-notation key.
	 * @param mixed  $value The value to set.
	 *
	 * @return void
	 */
	private function setNestedValue(string $key, mixed $value): void
	{
		$keys = explode('.', $key);
		$current = &$this->config;

		foreach (array_slice($keys, 0, -1) as $k) {
			if (! isset($current[$k]) || ! is_array($current[$k])) {
				$current[$k] = [];
			}
			$current = &$current[$k];
		}

		$current[end($keys)] = $value;
	}
}
