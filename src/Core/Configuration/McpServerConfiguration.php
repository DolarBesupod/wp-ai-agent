<?php

declare(strict_types=1);

namespace PhpCliAgent\Core\Configuration;

use PhpCliAgent\Core\Exceptions\ConfigurationException;

/**
 * Configuration for an MCP server.
 *
 * Holds all configuration needed to define an MCP server connection.
 * This is the Core layer DTO that defines what configuration is needed
 * without knowing how to actually connect to the server.
 *
 * @since n.e.x.t
 */
final class McpServerConfiguration
{
	/**
	 * The unique server name/identifier.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * The command to execute to start the MCP server.
	 *
	 * @var string
	 */
	private string $command;

	/**
	 * Command arguments.
	 *
	 * @var array<string>
	 */
	private array $args;

	/**
	 * Environment variables for the server process.
	 *
	 * @var array<string, string>|null
	 */
	private ?array $env;

	/**
	 * Whether the server is enabled.
	 *
	 * @var bool
	 */
	private bool $enabled;

	/**
	 * Creates a new MCP server configuration.
	 *
	 * @param string                     $name    The unique server name/identifier.
	 * @param string                     $command The command to execute to start the MCP server.
	 * @param array<string>              $args    Command arguments.
	 * @param array<string, string>|null $env     Environment variables for the server process.
	 * @param bool                       $enabled Whether the server is enabled.
	 */
	public function __construct(
		string $name,
		string $command,
		array $args = [],
		?array $env = null,
		bool $enabled = true
	) {
		$this->name = $name;
		$this->command = $command;
		$this->args = $args;
		$this->env = $env;
		$this->enabled = $enabled;
	}

	/**
	 * Creates a configuration from an array.
	 *
	 * @param string               $name   The server name.
	 * @param array<string, mixed> $config The configuration array.
	 *
	 * @return self
	 *
	 * @throws ConfigurationException If required fields are missing.
	 */
	public static function fromArray(string $name, array $config): self
	{
		if (! isset($config['command']) || ! is_string($config['command']) || $config['command'] === '') {
			throw ConfigurationException::missingKey("mcp_servers.{$name}.command");
		}

		/** @var array<string> $args */
		$args = isset($config['args']) && is_array($config['args'])
			? $config['args']
			: [];

		/** @var array<string, string>|null $env */
		$env = isset($config['env']) && is_array($config['env'])
			? $config['env']
			: null;

		$enabled = isset($config['enabled'])
			? (bool) $config['enabled']
			: true;

		return new self($name, $config['command'], $args, $env, $enabled);
	}

	/**
	 * Gets the server name.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Gets the command to execute.
	 *
	 * @return string
	 */
	public function getCommand(): string
	{
		return $this->command;
	}

	/**
	 * Gets the command arguments.
	 *
	 * @return array<string>
	 */
	public function getArgs(): array
	{
		return $this->args;
	}

	/**
	 * Gets the environment variables.
	 *
	 * @return array<string, string>|null
	 */
	public function getEnv(): ?array
	{
		return $this->env;
	}

	/**
	 * Checks if the server is enabled.
	 *
	 * @return bool
	 */
	public function isEnabled(): bool
	{
		return $this->enabled;
	}

	/**
	 * Checks if the configuration is valid.
	 *
	 * A configuration is valid if it has a non-empty name and command.
	 *
	 * @return bool
	 */
	public function isValid(): bool
	{
		return $this->name !== '' && $this->command !== '';
	}

	/**
	 * Converts the configuration to an array.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		$result = [
			'name' => $this->name,
			'command' => $this->command,
			'args' => $this->args,
			'enabled' => $this->enabled,
		];

		if ($this->env !== null) {
			$result['env'] = $this->env;
		}

		return $result;
	}
}
