<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\Mcp;

/**
 * Configuration for an MCP server connection.
 *
 * Holds all configuration needed to connect to an MCP server via stdio or HTTP transport.
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
	 * The HTTP endpoint URL (HTTP transport).
	 *
	 * @var string
	 */
	private string $url;

	/**
	 * Custom headers for HTTP requests (HTTP transport).
	 *
	 * @var array<string, string>
	 */
	private array $headers;

	/**
	 * Connection timeout in seconds.
	 *
	 * @var float
	 */
	private float $timeout;

	/**
	 * Creates a new MCP server configuration.
	 *
	 * @param string                     $name      The unique server name/identifier.
	 * @param string                     $transport The transport type (stdio or http).
	 * @param string                     $command   The command to execute (stdio transport).
	 * @param array<string>              $args      Command arguments (stdio transport).
	 * @param array<string, string>|null $env       Environment variables (stdio transport).
	 * @param string                     $url       The HTTP endpoint URL (HTTP transport).
	 * @param array<string, string>      $headers   Custom headers (HTTP transport).
	 * @param float                      $timeout   Connection timeout in seconds.
	 */
	public function __construct(
		string $name,
		string $transport = self::TRANSPORT_STDIO,
		string $command = '',
		array $args = [],
		?array $env = null,
		string $url = '',
		array $headers = [],
		float $timeout = 30.0
	) {
		$this->name = $name;
		$this->transport = $transport;
		$this->command = $command;
		$this->args = $args;
		$this->env = $env;
		$this->url = $url;
		$this->headers = $headers;
		$this->timeout = $timeout;
	}

	/**
	 * Creates a stdio transport configuration.
	 *
	 * @param string                     $name    The server name.
	 * @param string                     $command The command to execute.
	 * @param array<string>              $args    Command arguments.
	 * @param array<string, string>|null $env     Environment variables.
	 * @param float                      $timeout Connection timeout.
	 *
	 * @return self
	 */
	public static function stdio(
		string $name,
		string $command,
		array $args = [],
		?array $env = null,
		float $timeout = 30.0
	): self {
		return new self(
			$name,
			self::TRANSPORT_STDIO,
			$command,
			$args,
			$env,
			'',
			[],
			$timeout
		);
	}

	/**
	 * Creates an HTTP transport configuration.
	 *
	 * @param string                $name        The server name.
	 * @param string                $url         The HTTP endpoint URL.
	 * @param array<string, string> $headers     Custom headers.
	 * @param string|null           $bearer_token Bearer token for Authorization header.
	 * @param float                 $timeout     Connection timeout.
	 *
	 * @return self
	 */
	public static function http(
		string $name,
		string $url,
		array $headers = [],
		?string $bearer_token = null,
		float $timeout = 30.0
	): self {
		if ($bearer_token !== null) {
			$headers['Authorization'] = 'Bearer ' . $bearer_token;
		}

		return new self(
			$name,
			self::TRANSPORT_HTTP,
			'',
			[],
			null,
			$url,
			$headers,
			$timeout
		);
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
		// Determine transport type from configuration.
		$url = $config['url'] ?? '';
		$command = $config['command'] ?? '';

		if ($url !== '') {
			// HTTP transport.
			$headers = $config['headers'] ?? [];
			$bearer_token = $config['bearer_token'] ?? null;

			return self::http(
				$name,
				$url,
				\is_array($headers) ? $headers : [],
				\is_string($bearer_token) ? $bearer_token : null,
				(float) ($config['timeout'] ?? 30.0)
			);
		}

		// Stdio transport.
		return self::stdio(
			$name,
			$command,
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
	 * Gets the HTTP endpoint URL (HTTP transport).
	 *
	 * @return string
	 */
	public function getUrl(): string
	{
		return $this->url;
	}

	/**
	 * Gets the custom headers (HTTP transport).
	 *
	 * @return array<string, string>
	 */
	public function getHeaders(): array
	{
		return $this->headers;
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
	 * A configuration is valid if it has a non-empty name and either:
	 * - A non-empty command (stdio transport)
	 * - A non-empty URL (HTTP transport)
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
		];

		if ($this->transport === self::TRANSPORT_HTTP) {
			$result['url'] = $this->url;
			if (\count($this->headers) > 0) {
				$result['headers'] = $this->headers;
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
