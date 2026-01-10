<?php

declare(strict_types=1);

namespace PhpCliAgent\Integration\Mcp;

/**
 * Configuration for an MCP server connection.
 *
 * Holds all configuration needed to connect to an MCP server via stdio transport.
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
	 * Connection timeout in seconds.
	 *
	 * @var float
	 */
	private float $timeout;

	/**
	 * Creates a new MCP server configuration.
	 *
	 * @param string                      $name    The unique server name/identifier.
	 * @param string                      $command The command to execute to start the MCP server.
	 * @param array<string>               $args    Command arguments.
	 * @param array<string, string>|null  $env     Environment variables for the server process.
	 * @param float                       $timeout Connection timeout in seconds.
	 */
	public function __construct(
		string $name,
		string $command,
		array $args = [],
		?array $env = null,
		float $timeout = 30.0
	) {
		$this->name = $name;
		$this->command = $command;
		$this->args = $args;
		$this->env = $env;
		$this->timeout = $timeout;
	}

	/**
	 * Creates a configuration from an array.
	 *
	 * @param string               $name   The server name.
	 * @param array<string, mixed> $config The configuration array.
	 *
	 * @return self
	 */
	public static function fromArray(string $name, array $config): self
	{
		return new self(
			$name,
			$config['command'] ?? '',
			$config['args'] ?? [],
			$config['env'] ?? null,
			(float) ($config['timeout'] ?? 30.0)
		);
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
	 * Gets the connection timeout.
	 *
	 * @return float
	 */
	public function getTimeout(): float
	{
		return $this->timeout;
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
			'timeout' => $this->timeout,
		];

		if ($this->env !== null) {
			$result['env'] = $this->env;
		}

		return $result;
	}
}
