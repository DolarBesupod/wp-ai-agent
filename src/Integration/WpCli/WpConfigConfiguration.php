<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Integration\WpCli;

use Automattic\Automattic\WpAiAgent\Core\Contracts\ConfigurationInterface;
use Automattic\Automattic\WpAiAgent\Core\Exceptions\ConfigurationException;

/**
 * Reads agent configuration from PHP constants defined in wp-config.php.
 *
 * This class is read-only. All configuration values are sourced from PHP
 * constants or environment variables. No WordPress functions are called.
 * Use `wp agent config set` (T1.3) to write constants to wp-config.php.
 *
 * @since n.e.x.t
 */
final class WpConfigConfiguration implements ConfigurationInterface
{
	/**
	 * The set of known configuration keys.
	 *
	 * @var array<int, string>
	 */
	private const KNOWN_KEYS = [
		'api_key',
		'model',
		'max_tokens',
		'temperature',
		'system_prompt',
		'debug',
		'streaming',
		'max_iterations',
		'bypassed_tools',
		'auto_confirm',
		'session_storage_path',
	];

	/**
	 * The read-only error message used for mutating operations.
	 */
	private const READ_ONLY_MESSAGE =
		'WpConfigConfiguration is read-only — use wp agent config set to change constants';

	/**
	 * Returns the API key for the AI provider.
	 *
	 * Checks the ANTHROPIC_API_KEY PHP constant first, then falls back to
	 * the ANTHROPIC_API_KEY environment variable. Returns an empty string
	 * when neither is set; the API client is responsible for validating
	 * the key before making a request.
	 *
	 * @return string
	 *
	 * @since n.e.x.t
	 */
	public function getApiKey(): string
	{
		if (defined('ANTHROPIC_API_KEY') && constant('ANTHROPIC_API_KEY') !== '') {
			return (string) constant('ANTHROPIC_API_KEY');
		}

		$env = getenv('ANTHROPIC_API_KEY');

		return $env !== false ? $env : '';
	}

	/**
	 * Returns the AI model to use.
	 *
	 * @return string The model identifier (e.g., "claude-sonnet-4-6").
	 *
	 * @since n.e.x.t
	 */
	public function getModel(): string
	{
		return defined('WP_AI_AGENT_MODEL') ? (string) constant('WP_AI_AGENT_MODEL') : 'claude-sonnet-4-6';
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
		return defined('WP_AI_AGENT_MAX_TOKENS') ? (int) constant('WP_AI_AGENT_MAX_TOKENS') : 8192;
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
		return defined('WP_AI_AGENT_TEMPERATURE') ? (float) constant('WP_AI_AGENT_TEMPERATURE') : 1.0;
	}

	/**
	 * Default system prompt used when WP_AI_AGENT_SYSTEM_PROMPT is not defined.
	 *
	 * @var string
	 */
	private const DEFAULT_SYSTEM_PROMPT = <<<'PROMPT'
You are an expert WordPress assistant with full WP-CLI access to this site.
You can inspect and manage plugins, themes, users, options, posts, and the database.

## Guidelines
- Always prefer non-destructive reads before making any changes.
- Use `wp help` or `wp <command> --help` to discover available commands and their options.
- When running WP-CLI commands, use the site's current working directory.
- Before making a change (activating a plugin, updating an option, etc.), briefly explain what you are about to do and why.
- Be concise. Format output as markdown tables when listing items.

## Site info
You do not have site details pre-loaded. Use the `wp_site_overview` skill or run `wp` commands to discover the current installation when needed.
PROMPT;

	/**
	 * Returns the system prompt.
	 *
	 * Uses the WP_AI_AGENT_SYSTEM_PROMPT constant when defined, otherwise
	 * falls back to the built-in default prompt.
	 *
	 * @return string
	 *
	 * @since n.e.x.t
	 */
	public function getSystemPrompt(): string
	{
		return defined('WP_AI_AGENT_SYSTEM_PROMPT')
			? (string) constant('WP_AI_AGENT_SYSTEM_PROMPT')
			: self::DEFAULT_SYSTEM_PROMPT;
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
		return defined('WP_AI_AGENT_DEBUG') ? (bool) constant('WP_AI_AGENT_DEBUG') : false;
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
		return defined('WP_AI_AGENT_STREAMING') ? (bool) constant('WP_AI_AGENT_STREAMING') : true;
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
		return defined('WP_AI_AGENT_MAX_ITERATIONS') ? (int) constant('WP_AI_AGENT_MAX_ITERATIONS') : 50;
	}

	/**
	 * Returns tools that should bypass confirmation.
	 *
	 * Reads WP_AI_AGENT_BYPASSED_TOOLS as a comma-separated string.
	 * Each element is trimmed of surrounding whitespace.
	 *
	 * @return array<int, string> List of tool names.
	 *
	 * @since n.e.x.t
	 */
	public function getBypassedTools(): array
	{
		$raw = defined('WP_AI_AGENT_BYPASSED_TOOLS') ? (string) constant('WP_AI_AGENT_BYPASSED_TOOLS') : '';

		if ($raw === '') {
			return [];
		}

		return array_map('trim', explode(',', $raw));
	}

	/**
	 * Returns whether auto-confirm mode is enabled.
	 *
	 * Reads the WP_AI_AGENT_AUTO_CONFIRM PHP constant. When defined and truthy,
	 * all tool executions are confirmed automatically without prompting.
	 *
	 * @return bool
	 *
	 * @since n.e.x.t
	 */
	public function getAutoConfirm(): bool
	{
		return defined('WP_AI_AGENT_AUTO_CONFIRM')
			&& (bool) constant('WP_AI_AGENT_AUTO_CONFIRM');
	}

	/**
	 * Returns the session storage directory path.
	 *
	 * Not applicable to the WordPress path — sessions are stored as options.
	 * Returns an empty string so the contract is satisfied without error.
	 *
	 * @return string Always returns an empty string.
	 *
	 * @since n.e.x.t
	 */
	public function getSessionStoragePath(): string
	{
		return '';
	}

	/**
	 * Returns a configuration value by dot-notation key.
	 *
	 * Maps known keys to their corresponding typed getter. Unknown keys
	 * return the provided default value.
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
		return match ($key) {
			'api_key'              => $this->getApiKey(),
			'model'                => $this->getModel(),
			'max_tokens'           => $this->getMaxTokens(),
			'temperature'          => $this->getTemperature(),
			'system_prompt'        => $this->getSystemPrompt(),
			'debug'                => $this->isDebugEnabled(),
			'streaming'            => $this->isStreamingEnabled(),
			'max_iterations'       => $this->getMaxIterations(),
			'bypassed_tools'       => $this->getBypassedTools(),
			'auto_confirm'         => $this->getAutoConfirm(),
			'session_storage_path' => $this->getSessionStoragePath(),
			default                => $default,
		};
	}

	/**
	 * Checks if a configuration key exists.
	 *
	 * Returns true for all known configuration keys; false for unknown ones.
	 *
	 * @param string $key The configuration key.
	 *
	 * @return bool
	 *
	 * @since n.e.x.t
	 */
	public function has(string $key): bool
	{
		return in_array($key, self::KNOWN_KEYS, true);
	}

	/**
	 * Returns all configuration values as an associative array.
	 *
	 * @return array<string, mixed>
	 *
	 * @since n.e.x.t
	 */
	public function toArray(): array
	{
		return [
			'api_key'              => $this->getApiKey(),
			'model'                => $this->getModel(),
			'max_tokens'           => $this->getMaxTokens(),
			'temperature'          => $this->getTemperature(),
			'system_prompt'        => $this->getSystemPrompt(),
			'debug'                => $this->isDebugEnabled(),
			'streaming'            => $this->isStreamingEnabled(),
			'max_iterations'       => $this->getMaxIterations(),
			'bypassed_tools'       => $this->getBypassedTools(),
			'auto_confirm'         => $this->getAutoConfirm(),
			'session_storage_path' => $this->getSessionStoragePath(),
		];
	}

	/**
	 * Not supported — WpConfigConfiguration is read-only.
	 *
	 * @param string $key   The configuration key.
	 * @param mixed  $value The value to set.
	 *
	 * @return void
	 *
	 * @throws ConfigurationException Always thrown; this class is read-only.
	 *
	 * @since n.e.x.t
	 */
	public function set(string $key, mixed $value): void
	{
		throw new ConfigurationException(self::READ_ONLY_MESSAGE);
	}

	/**
	 * Not supported — WpConfigConfiguration is read-only.
	 *
	 * @param array<string, mixed> $config The configuration to merge.
	 *
	 * @return void
	 *
	 * @throws ConfigurationException Always thrown; this class is read-only.
	 *
	 * @since n.e.x.t
	 */
	public function merge(array $config): void
	{
		throw new ConfigurationException(self::READ_ONLY_MESSAGE);
	}

	/**
	 * Not supported — WpConfigConfiguration is read-only.
	 *
	 * @param string $path The path to the configuration file.
	 *
	 * @return void
	 *
	 * @throws ConfigurationException Always thrown; this class is read-only.
	 *
	 * @since n.e.x.t
	 */
	public function loadFromFile(string $path): void
	{
		throw new ConfigurationException(self::READ_ONLY_MESSAGE);
	}
}
