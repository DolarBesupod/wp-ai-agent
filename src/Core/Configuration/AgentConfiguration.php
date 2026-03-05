<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Core\Configuration;

use Automattic\Automattic\WpAiAgent\Core\Exceptions\ConfigurationException;

/**
 * Main configuration for the CLI Agent.
 *
 * Aggregates all configuration settings including provider configuration,
 * MCP servers, and agent-specific settings.
 *
 * @since n.e.x.t
 */
final class AgentConfiguration
{
	/**
	 * Default session storage path.
	 */
	private const DEFAULT_SESSION_STORAGE_PATH = '~/.wp-ai-agent/sessions';

	/**
	 * Default log path.
	 */
	private const DEFAULT_LOG_PATH = '~/.wp-ai-agent/logs';

	/**
	 * Default maximum turns.
	 */
	private const DEFAULT_MAX_TURNS = 100;

	/**
	 * The AI provider configuration.
	 *
	 * @var ProviderConfiguration
	 */
	private ProviderConfiguration $provider;

	/**
	 * The MCP server configurations.
	 *
	 * @var array<McpServerConfiguration>
	 */
	private array $mcp_servers;

	/**
	 * The session storage path.
	 *
	 * @var string
	 */
	private string $session_storage_path;

	/**
	 * The log path.
	 *
	 * @var string
	 */
	private string $log_path;

	/**
	 * The maximum number of agent loop turns.
	 *
	 * @var int
	 */
	private int $max_turns;

	/**
	 * The default system prompt.
	 *
	 * @var string
	 */
	private string $default_system_prompt;

	/**
	 * Tools that bypass user confirmation.
	 *
	 * @var array<string>
	 */
	private array $bypass_confirmation_tools;

	/**
	 * Creates a new agent configuration.
	 *
	 * @param ProviderConfiguration        $provider                  The AI provider configuration.
	 * @param array<McpServerConfiguration> $mcp_servers               The MCP server configurations.
	 * @param string                       $session_storage_path      The session storage path.
	 * @param string                       $log_path                  The log path.
	 * @param int                          $max_turns                 The maximum number of agent loop turns.
	 * @param string                       $default_system_prompt     The default system prompt.
	 * @param array<string>                $bypass_confirmation_tools Tools that bypass confirmation.
	 */
	public function __construct(
		ProviderConfiguration $provider,
		array $mcp_servers = [],
		string $session_storage_path = self::DEFAULT_SESSION_STORAGE_PATH,
		string $log_path = self::DEFAULT_LOG_PATH,
		int $max_turns = self::DEFAULT_MAX_TURNS,
		string $default_system_prompt = '',
		array $bypass_confirmation_tools = []
	) {
		$this->provider = $provider;
		$this->mcp_servers = $mcp_servers;
		$this->session_storage_path = $session_storage_path;
		$this->log_path = $log_path;
		$this->max_turns = $max_turns;
		$this->default_system_prompt = $default_system_prompt;
		$this->bypass_confirmation_tools = $bypass_confirmation_tools;
	}

	/**
	 * Creates a configuration from an array.
	 *
	 * @param array<string, mixed> $config The configuration array.
	 *
	 * @return self
	 *
	 * @throws ConfigurationException If required fields are missing or invalid.
	 */
	public static function fromArray(array $config): self
	{
		if (! isset($config['provider']) || ! is_array($config['provider'])) {
			throw ConfigurationException::missingKey('provider');
		}

		$provider = ProviderConfiguration::fromArray($config['provider']);

		$mcp_servers = [];
		if (isset($config['mcp_servers']) && is_array($config['mcp_servers'])) {
			foreach ($config['mcp_servers'] as $name => $server_config) {
				if (is_array($server_config)) {
					$mcp_servers[] = McpServerConfiguration::fromArray((string) $name, $server_config);
				}
			}
		}

		$session_storage_path = isset($config['session_storage_path']) && is_string($config['session_storage_path'])
			? $config['session_storage_path']
			: self::DEFAULT_SESSION_STORAGE_PATH;

		$log_path = isset($config['log_path']) && is_string($config['log_path'])
			? $config['log_path']
			: self::DEFAULT_LOG_PATH;

		$max_turns = isset($config['max_turns']) && is_numeric($config['max_turns'])
			? (int) $config['max_turns']
			: self::DEFAULT_MAX_TURNS;

		$default_system_prompt = isset($config['default_system_prompt']) && is_string($config['default_system_prompt'])
			? $config['default_system_prompt']
			: '';

		/** @var array<string> $bypass_confirmation_tools */
		$bypass_confirmation_tools = isset($config['bypass_confirmation_tools']) && is_array($config['bypass_confirmation_tools'])
			? $config['bypass_confirmation_tools']
			: [];

		return new self(
			$provider,
			$mcp_servers,
			$session_storage_path,
			$log_path,
			$max_turns,
			$default_system_prompt,
			$bypass_confirmation_tools
		);
	}

	/**
	 * Gets the AI provider configuration.
	 *
	 * @return ProviderConfiguration
	 */
	public function getProvider(): ProviderConfiguration
	{
		return $this->provider;
	}

	/**
	 * Gets the MCP server configurations.
	 *
	 * @return array<McpServerConfiguration>
	 */
	public function getMcpServers(): array
	{
		return $this->mcp_servers;
	}

	/**
	 * Gets only enabled MCP server configurations.
	 *
	 * @return array<McpServerConfiguration>
	 */
	public function getEnabledMcpServers(): array
	{
		return array_values(
			array_filter(
				$this->mcp_servers,
				static fn(McpServerConfiguration $server): bool => $server->isEnabled()
			)
		);
	}

	/**
	 * Gets the session storage path.
	 *
	 * @return string
	 */
	public function getSessionStoragePath(): string
	{
		return $this->session_storage_path;
	}

	/**
	 * Gets the session storage path with tilde expanded.
	 *
	 * @return string
	 */
	public function getExpandedSessionStoragePath(): string
	{
		return $this->expandTilde($this->session_storage_path);
	}

	/**
	 * Gets the log path.
	 *
	 * @return string
	 */
	public function getLogPath(): string
	{
		return $this->log_path;
	}

	/**
	 * Gets the log path with tilde expanded.
	 *
	 * @return string
	 */
	public function getExpandedLogPath(): string
	{
		return $this->expandTilde($this->log_path);
	}

	/**
	 * Gets the maximum turns.
	 *
	 * @return int
	 */
	public function getMaxTurns(): int
	{
		return $this->max_turns;
	}

	/**
	 * Gets the default system prompt.
	 *
	 * @return string
	 */
	public function getDefaultSystemPrompt(): string
	{
		return $this->default_system_prompt;
	}

	/**
	 * Gets the tools that bypass confirmation.
	 *
	 * @return array<string>
	 */
	public function getBypassConfirmationTools(): array
	{
		return $this->bypass_confirmation_tools;
	}

	/**
	 * Checks if a tool should bypass confirmation.
	 *
	 * @param string $tool_name The tool name to check.
	 *
	 * @return bool
	 */
	public function shouldBypassConfirmation(string $tool_name): bool
	{
		return in_array($tool_name, $this->bypass_confirmation_tools, true);
	}

	/**
	 * Checks if the configuration is valid.
	 *
	 * @return bool
	 */
	public function isValid(): bool
	{
		if (! $this->provider->isValid()) {
			return false;
		}

		foreach ($this->mcp_servers as $server) {
			if (! $server->isValid()) {
				return false;
			}
		}

		return $this->max_turns > 0;
	}

	/**
	 * Converts the configuration to an array.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		$mcp_servers = [];
		foreach ($this->mcp_servers as $server) {
			$server_array = $server->toArray();
			$name = $server_array['name'];
			unset($server_array['name']);
			$mcp_servers[$name] = $server_array;
		}

		return [
			'provider' => $this->provider->toArray(),
			'mcp_servers' => $mcp_servers,
			'session_storage_path' => $this->session_storage_path,
			'log_path' => $this->log_path,
			'max_turns' => $this->max_turns,
			'default_system_prompt' => $this->default_system_prompt,
			'bypass_confirmation_tools' => $this->bypass_confirmation_tools,
		];
	}

	/**
	 * Expands tilde (~) to the user's home directory.
	 *
	 * @param string $path The path to expand.
	 *
	 * @return string
	 */
	private function expandTilde(string $path): string
	{
		if (strpos($path, '~') !== 0) {
			return $path;
		}

		$home = getenv('HOME');
		if ($home === false) {
			$home = getenv('USERPROFILE');
		}

		if ($home === false) {
			return $path;
		}

		return $home . substr($path, 1);
	}
}
