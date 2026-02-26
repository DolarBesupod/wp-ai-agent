<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\Mcp;

use GalatanOvidiu\PhpMcpClient\Core\Client\ClientCapabilities;
use GalatanOvidiu\PhpMcpClient\Core\Client\McpClient;
use GalatanOvidiu\PhpMcpClient\Core\Contracts\TransportInterface;
use GalatanOvidiu\PhpMcpClient\Core\Exception\ConnectionException;
use GalatanOvidiu\PhpMcpClient\Core\Exception\McpException;
use GalatanOvidiu\PhpMcpClient\Core\Exception\TimeoutException;
use GalatanOvidiu\PhpMcpClient\Core\Exception\TransportException;
use GalatanOvidiu\PhpMcpClient\Integration\Transport\Http\HttpTransport;
use GalatanOvidiu\PhpMcpClient\Integration\Transport\StdioTransport;
use WpAiAgent\Core\Exceptions\McpConnectionException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Manages multiple MCP server connections.
 *
 * This class handles connecting to, tracking, and disconnecting from multiple
 * MCP servers. It uses the StdioTransport from php-mcp-client to communicate
 * with MCP servers via subprocess stdin/stdout.
 *
 * @since n.e.x.t
 */
class McpClientManager
{
	/**
	 * The client application name reported to MCP servers.
	 *
	 * @var string
	 */
	private const CLIENT_NAME = 'php-cli-agent';

	/**
	 * The client application version reported to MCP servers.
	 *
	 * @var string
	 */
	private const CLIENT_VERSION = '1.0.0';

	/**
	 * Connected MCP clients indexed by server name.
	 *
	 * @var array<string, McpClient>
	 */
	private array $clients = [];

	/**
	 * Connection configurations indexed by server name.
	 *
	 * @var array<string, McpServerConfiguration>
	 */
	private array $configurations = [];

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Client capabilities to announce to servers.
	 *
	 * @var ClientCapabilities
	 */
	private ClientCapabilities $capabilities;

	/**
	 * Creates a new McpClientManager.
	 *
	 * @param LoggerInterface|null    $logger       Optional logger instance.
	 * @param ClientCapabilities|null $capabilities Optional client capabilities.
	 */
	public function __construct(
		?LoggerInterface $logger = null,
		?ClientCapabilities $capabilities = null
	) {
		$this->logger = $logger ?? new NullLogger();
		$this->capabilities = $capabilities ?? new ClientCapabilities();
	}

	/**
	 * Connects to an MCP server using the provided configuration.
	 *
	 * @param McpServerConfiguration $config The server configuration.
	 *
	 * @throws McpConnectionException When connection fails.
	 */
	public function connect(McpServerConfiguration $config): void
	{
		$server_name = $config->getName();

		if (!$config->isValid()) {
			$error_detail = $config->isHttpTransport()
				? 'URL cannot be empty'
				: 'command cannot be empty';
			throw McpConnectionException::connectionFailed(
				$server_name,
				'Invalid server configuration: ' . $error_detail
			);
		}

		if ($this->isConnected($server_name)) {
			$this->logger->debug('Already connected to MCP server', ['server' => $server_name]);
			return;
		}

		$this->configurations[$server_name] = $config;

		$log_context = ['server' => $server_name, 'transport' => $config->getTransport()];
		if ($config->isHttpTransport()) {
			$log_context['url'] = $config->getUrl();
		} else {
			$log_context['command'] = $config->getCommand();
		}
		$this->logger->info('Connecting to MCP server', $log_context);

		try {
			$transport = $this->createTransport($config);
			$client = $this->createClient($transport);

			$client->connect($config->getTimeout());

			$this->clients[$server_name] = $client;

			$this->logger->info('Connected to MCP server', [
				'server' => $server_name,
				'serverInfo' => $client->getServerInfo()->getName(),
			]);
		} catch (TimeoutException $exception) {
			throw McpConnectionException::timeout(
				$server_name,
				(int) $config->getTimeout()
			);
		} catch (ConnectionException $exception) {
			throw McpConnectionException::connectionFailed(
				$server_name,
				$exception->getMessage(),
				$exception
			);
		} catch (TransportException $exception) {
			throw McpConnectionException::connectionFailed(
				$server_name,
				sprintf('Transport error: %s', $exception->getMessage()),
				$exception
			);
		} catch (McpException $exception) {
			throw McpConnectionException::protocolError(
				$server_name,
				$exception->getMessage(),
				$exception
			);
		} catch (Throwable $exception) {
			throw McpConnectionException::connectionFailed(
				$server_name,
				sprintf('Unexpected error: %s', $exception->getMessage()),
				$exception
			);
		}
	}

	/**
	 * Connects to multiple MCP servers.
	 *
	 * Attempts to connect to all servers, collecting any failures.
	 * Returns after attempting all connections.
	 *
	 * @param array<McpServerConfiguration> $configs The server configurations.
	 *
	 * @return array<string, Throwable> An array of failures indexed by server name.
	 */
	public function connectAll(array $configs): array
	{
		$failures = [];

		foreach ($configs as $config) {
			try {
				$this->connect($config);
			} catch (Throwable $exception) {
				$failures[$config->getName()] = $exception;
				$this->logger->error('Failed to connect to MCP server', [
					'server' => $config->getName(),
					'error' => $exception->getMessage(),
				]);
			}
		}

		return $failures;
	}

	/**
	 * Gets an MCP client by server name.
	 *
	 * @param string $server_name The server name.
	 *
	 * @return McpClient|null The client, or null if not connected.
	 */
	public function getClient(string $server_name): ?McpClient
	{
		if (!$this->isConnected($server_name)) {
			return null;
		}

		return $this->clients[$server_name];
	}

	/**
	 * Checks if a server is connected.
	 *
	 * @param string $server_name The server name.
	 *
	 * @return bool True if connected.
	 */
	public function isConnected(string $server_name): bool
	{
		if (!isset($this->clients[$server_name])) {
			return false;
		}

		return $this->clients[$server_name]->isConnected();
	}

	/**
	 * Disconnects from an MCP server.
	 *
	 * @param string $server_name The server name.
	 */
	public function disconnect(string $server_name): void
	{
		if (!isset($this->clients[$server_name])) {
			return;
		}

		$this->logger->info('Disconnecting from MCP server', ['server' => $server_name]);

		try {
			$this->clients[$server_name]->disconnect();
		} catch (Throwable $exception) {
			$this->logger->warning('Error disconnecting from MCP server', [
				'server' => $server_name,
				'error' => $exception->getMessage(),
			]);
		}

		unset($this->clients[$server_name]);
	}

	/**
	 * Disconnects from all MCP servers.
	 */
	public function disconnectAll(): void
	{
		$server_names = array_keys($this->clients);

		foreach ($server_names as $server_name) {
			$this->disconnect($server_name);
		}
	}

	/**
	 * Gets all connected server names.
	 *
	 * @return array<string> The connected server names.
	 */
	public function getConnectedServers(): array
	{
		$connected = [];

		foreach (array_keys($this->clients) as $server_name) {
			if ($this->isConnected($server_name)) {
				$connected[] = $server_name;
			}
		}

		return $connected;
	}

	/**
	 * Gets all registered server configurations.
	 *
	 * @return array<string, McpServerConfiguration> The configurations indexed by name.
	 */
	public function getConfigurations(): array
	{
		return $this->configurations;
	}

	/**
	 * Gets the configuration for a specific server.
	 *
	 * @param string $server_name The server name.
	 *
	 * @return McpServerConfiguration|null The configuration, or null if not found.
	 */
	public function getConfiguration(string $server_name): ?McpServerConfiguration
	{
		return $this->configurations[$server_name] ?? null;
	}

	/**
	 * Creates a transport for the given configuration.
	 *
	 * @param McpServerConfiguration $config The server configuration.
	 *
	 * @return TransportInterface The created transport.
	 */
	protected function createTransport(McpServerConfiguration $config): TransportInterface
	{
		if ($config->isHttpTransport()) {
			return new HttpTransport(
				$config->getUrl(),
				null,
				$this->logger,
				$config->getHeaders()
			);
		}

		return new StdioTransport(
			$config->getCommand(),
			$config->getArgs(),
			$config->getEnv()
		);
	}

	/**
	 * Creates an MCP client with the given transport.
	 *
	 * @param TransportInterface $transport The transport to use.
	 *
	 * @return McpClient The created client.
	 */
	protected function createClient(TransportInterface $transport): McpClient
	{
		return new McpClient(
			$transport,
			$this->capabilities,
			self::CLIENT_NAME,
			self::CLIENT_VERSION,
			$this->logger
		);
	}

	/**
	 * Destructor ensures all connections are closed.
	 */
	public function __destruct()
	{
		$this->disconnectAll();
	}
}
