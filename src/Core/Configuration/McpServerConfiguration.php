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
 * Supports two transport types:
 * - stdio: Starts an MCP server as a subprocess
 * - http: Connects to an HTTP-based MCP endpoint
 *
 * @since n.e.x.t
 */
final class McpServerConfiguration
{
	/**
	 * Transport type for stdio-based servers.
	 */
	public const TRANSPORT_STDIO = 'stdio';

	/**
	 * Transport type for HTTP-based servers.
	 */
	public const TRANSPORT_HTTP = 'http';

	/**
	 * The unique server name/identifier.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * The transport type (stdio or http).
	 *
	 * @var string
	 */
	private string $transport;

	/**
	 * The command to execute to start the MCP server (stdio transport).
	 *
	 * @var string
	 */
	private string $command;

	/**
	 * Command arguments (stdio transport).
	 *
	 * @var array<string>
	 */
	private array $args;

	/**
	 * Environment variables for the server process (stdio transport).
	 *
	 * @var array<string, string>|null
	 */
	private ?array $env;

	/**
	 * The HTTP endpoint URL (http transport).
	 *
	 * @var string
	 */
	private string $url;

	/**
	 * Custom headers for HTTP requests (http transport).
	 *
	 * @var array<string, string>
	 */
	private array $headers;

	/**
	 * Bearer token for Authorization header (http transport).
	 *
	 * @var string|null
	 */
	private ?string $bearer_token;

	/**
	 * Connection timeout in seconds.
	 *
	 * @var float
	 */
	private float $timeout;

	/**
	 * Whether the server is enabled.
	 *
	 * @var bool
	 */
	private bool $enabled;

	/**
	 * Creates a new MCP server configuration.
	 *
	 * @param string                     $name         The unique server name/identifier.
	 * @param string                     $transport    The transport type (stdio or http).
	 * @param string                     $command      The command to execute (stdio transport).
	 * @param array<string>              $args         Command arguments (stdio transport).
	 * @param array<string, string>|null $env          Environment variables (stdio transport).
	 * @param string                     $url          The HTTP endpoint URL (http transport).
	 * @param array<string, string>      $headers      Custom headers (http transport).
	 * @param string|null                $bearer_token Bearer token for auth (http transport).
	 * @param float                      $timeout      Connection timeout in seconds.
	 * @param bool                       $enabled      Whether the server is enabled.
	 */
	public function __construct(
		string $name,
		string $transport = self::TRANSPORT_STDIO,
		string $command = '',
		array $args = [],
		?array $env = null,
		string $url = '',
		array $headers = [],
		?string $bearer_token = null,
		float $timeout = 30.0,
		bool $enabled = true
	) {
		$this->name = $name;
		$this->transport = $transport;
		$this->command = $command;
		$this->args = $args;
		$this->env = $env;
		$this->url = $url;
		$this->headers = $headers;
		$this->bearer_token = $bearer_token;
		$this->timeout = $timeout;
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
		// Determine transport type from configuration.
		$url = isset($config['url']) && is_string($config['url']) ? $config['url'] : '';
		$command = isset($config['command']) && is_string($config['command']) ? $config['command'] : '';

		// HTTP transport if URL is provided.
		if ($url !== '') {
			/** @var array<string, string> $headers */
			$headers = isset($config['headers']) && is_array($config['headers'])
				? $config['headers']
				: [];

			$bearer_token = isset($config['bearer_token']) && is_string($config['bearer_token'])
				? $config['bearer_token']
				: null;

			$timeout = isset($config['timeout']) && is_numeric($config['timeout'])
				? (float) $config['timeout']
				: 30.0;

			$enabled = isset($config['enabled'])
				? (bool) $config['enabled']
				: true;

			return new self(
				$name,
				self::TRANSPORT_HTTP,
				'',
				[],
				null,
				$url,
				$headers,
				$bearer_token,
				$timeout,
				$enabled
			);
		}

		// Stdio transport requires command.
		if ($command === '') {
			throw ConfigurationException::missingKey("mcp_servers.{$name}.command or mcp_servers.{$name}.url");
		}

		/** @var array<string> $args */
		$args = isset($config['args']) && is_array($config['args'])
			? $config['args']
			: [];

		/** @var array<string, string>|null $env */
		$env = isset($config['env']) && is_array($config['env'])
			? $config['env']
			: null;

		$timeout = isset($config['timeout']) && is_numeric($config['timeout'])
			? (float) $config['timeout']
			: 30.0;

		$enabled = isset($config['enabled'])
			? (bool) $config['enabled']
			: true;

		return new self(
			$name,
			self::TRANSPORT_STDIO,
			$command,
			$args,
			$env,
			'',
			[],
			null,
			$timeout,
			$enabled
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
	 * Gets the transport type.
	 *
	 * @return string
	 */
	public function getTransport(): string
	{
		return $this->transport;
	}

	/**
	 * Checks if this is an HTTP transport.
	 *
	 * @return bool
	 */
	public function isHttpTransport(): bool
	{
		return $this->transport === self::TRANSPORT_HTTP;
	}

	/**
	 * Checks if this is a stdio transport.
	 *
	 * @return bool
	 */
	public function isStdioTransport(): bool
	{
		return $this->transport === self::TRANSPORT_STDIO;
	}

	/**
	 * Gets the command to execute (stdio transport).
	 *
	 * @return string
	 */
	public function getCommand(): string
	{
		return $this->command;
	}

	/**
	 * Gets the command arguments (stdio transport).
	 *
	 * @return array<string>
	 */
	public function getArgs(): array
	{
		return $this->args;
	}

	/**
	 * Gets the environment variables (stdio transport).
	 *
	 * @return array<string, string>|null
	 */
	public function getEnv(): ?array
	{
		return $this->env;
	}

	/**
	 * Gets the HTTP endpoint URL (http transport).
	 *
	 * @return string
	 */
	public function getUrl(): string
	{
		return $this->url;
	}

	/**
	 * Gets the custom headers (http transport).
	 *
	 * @return array<string, string>
	 */
	public function getHeaders(): array
	{
		return $this->headers;
	}

	/**
	 * Gets the bearer token (http transport).
	 *
	 * @return string|null
	 */
	public function getBearerToken(): ?string
	{
		return $this->bearer_token;
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
	 * A configuration is valid if it has a non-empty name and either:
	 * - A non-empty command (stdio transport)
	 * - A non-empty URL (http transport)
	 *
	 * @return bool
	 */
	public function isValid(): bool
	{
		if ($this->name === '') {
			return false;
		}

		if ($this->transport === self::TRANSPORT_HTTP) {
			return $this->url !== '';
		}

		return $this->command !== '';
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
			'transport' => $this->transport,
			'timeout' => $this->timeout,
			'enabled' => $this->enabled,
		];

		if ($this->transport === self::TRANSPORT_HTTP) {
			$result['url'] = $this->url;
			if (count($this->headers) > 0) {
				$result['headers'] = $this->headers;
			}
			if ($this->bearer_token !== null) {
				$result['bearer_token'] = $this->bearer_token;
			}
		} else {
			$result['command'] = $this->command;
			$result['args'] = $this->args;
			if ($this->env !== null) {
				$result['env'] = $this->env;
			}
		}

		return $result;
	}
}
