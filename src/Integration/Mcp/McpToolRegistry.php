<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\Mcp;

use GalatanOvidiu\PhpMcpClient\Core\Client\McpClient;
use WpAiAgent\Core\Contracts\ToolRegistryInterface;
use WpAiAgent\Core\Exceptions\DuplicateToolException;
use WpAiAgent\Core\Exceptions\McpConnectionException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Discovers and registers MCP tools from connected servers.
 *
 * This registry queries connected MCP servers for their available tools
 * and registers them as McpToolAdapter instances in the main ToolRegistry.
 * Tool names are prefixed with mcp_{serverName}_ to avoid collisions.
 *
 * @since n.e.x.t
 */
class McpToolRegistry
{
	/**
	 * The MCP client manager.
	 *
	 * @var McpClientManager
	 */
	private McpClientManager $client_manager;

	/**
	 * The main tool registry.
	 *
	 * @var ToolRegistryInterface
	 */
	private ToolRegistryInterface $registry;

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Default timeout for tool execution in seconds.
	 *
	 * @var float
	 */
	private float $default_timeout;

	/**
	 * Creates a new McpToolRegistry.
	 *
	 * @param McpClientManager      $client_manager  The MCP client manager.
	 * @param ToolRegistryInterface $registry        The main tool registry.
	 * @param LoggerInterface|null  $logger          Optional logger instance.
	 * @param float                 $default_timeout Default tool execution timeout in seconds.
	 */
	public function __construct(
		McpClientManager $client_manager,
		ToolRegistryInterface $registry,
		?LoggerInterface $logger = null,
		float $default_timeout = 60.0
	) {
		$this->client_manager = $client_manager;
		$this->registry = $registry;
		$this->logger = $logger ?? new NullLogger();
		$this->default_timeout = $default_timeout;
	}

	/**
	 * Discovers and registers tools from all connected MCP servers.
	 *
	 * Queries each connected server for available tools and creates
	 * McpToolAdapter instances for each. Returns the total number of
	 * tools registered.
	 *
	 * @return int The total number of tools registered.
	 */
	public function discoverAndRegister(): int
	{
		$connected_servers = $this->client_manager->getConnectedServers();
		$total_count = 0;

		foreach ($connected_servers as $server_name) {
			try {
				$count = $this->discoverFromServer($server_name);
				$total_count += $count;
			} catch (Throwable $exception) {
				$this->logger->warning('Failed to discover tools from server', [
					'server' => $server_name,
					'error' => $exception->getMessage(),
				]);
			}
		}

		$this->logger->info('MCP tool discovery complete', [
			'servers' => count($connected_servers),
			'tools_registered' => $total_count,
		]);

		return $total_count;
	}

	/**
	 * Discovers and registers tools from a specific MCP server.
	 *
	 * @param string $server_name The name of the server to discover tools from.
	 *
	 * @return int The number of tools registered from this server.
	 *
	 * @throws McpConnectionException When the server is not connected.
	 */
	public function discoverFromServer(string $server_name): int
	{
		$client = $this->client_manager->getClient($server_name);

		if ($client === null) {
			throw McpConnectionException::serverUnavailable($server_name);
		}

		$this->logger->debug('Discovering tools from MCP server', [
			'server' => $server_name,
		]);

		$tools = $this->listToolsFromClient($client, $server_name);

		if (count($tools) === 0) {
			$this->logger->debug('No tools found on MCP server', [
				'server' => $server_name,
			]);
			return 0;
		}

		$registered_count = 0;

		foreach ($tools as $tool) {
			if ($this->registerToolFromDefinition($client, $tool, $server_name)) {
				$registered_count++;
			}
		}

		$this->logger->info('Discovered tools from MCP server', [
			'server' => $server_name,
			'tools_found' => count($tools),
			'tools_registered' => $registered_count,
		]);

		return $registered_count;
	}

	/**
	 * Lists tools from an MCP client.
	 *
	 * @param McpClient $client      The MCP client.
	 * @param string    $server_name The server name for logging.
	 *
	 * @return array<int, array<string, mixed>> The list of tool definitions.
	 */
	private function listToolsFromClient(McpClient $client, string $server_name): array
	{
		try {
			$result = $client->listTools();

			if (!isset($result['tools']) || !is_array($result['tools'])) {
				return [];
			}

			return $result['tools'];
		} catch (Throwable $exception) {
			$this->logger->error('Failed to list tools from MCP server', [
				'server' => $server_name,
				'error' => $exception->getMessage(),
			]);
			return [];
		}
	}

	/**
	 * Registers a tool from its MCP definition.
	 *
	 * @param McpClient             $client      The MCP client.
	 * @param array<string, mixed>  $definition  The tool definition.
	 * @param string                $server_name The server name.
	 *
	 * @return bool True if the tool was registered, false otherwise.
	 */
	private function registerToolFromDefinition(
		McpClient $client,
		array $definition,
		string $server_name
	): bool {
		$name = $this->extractString($definition, 'name');

		if ($name === '') {
			$this->logger->warning('Skipping tool with empty name', [
				'server' => $server_name,
			]);
			return false;
		}

		$description = $this->extractString($definition, 'description');
		$input_schema = $this->extractArray($definition, 'inputSchema');

		$adapter = new McpToolAdapter(
			$client,
			$name,
			$description,
			$input_schema,
			$server_name,
			$this->default_timeout
		);

		try {
			$this->registry->register($adapter);

			$this->logger->debug('Registered MCP tool', [
				'server' => $server_name,
				'original_name' => $name,
				'registered_name' => $adapter->getName(),
			]);

			return true;
		} catch (DuplicateToolException $exception) {
			$this->logger->warning('Skipping duplicate tool', [
				'server' => $server_name,
				'tool' => $name,
				'registered_name' => $adapter->getName(),
			]);
			return false;
		}
	}

	/**
	 * Extracts a string value from an array.
	 *
	 * @param array<string, mixed> $data The data array.
	 * @param string               $key  The key to extract.
	 *
	 * @return string The extracted string or empty string.
	 */
	private function extractString(array $data, string $key): string
	{
		if (!isset($data[$key]) || !is_string($data[$key])) {
			return '';
		}

		return $data[$key];
	}

	/**
	 * Extracts an array value from an array.
	 *
	 * @param array<string, mixed> $data The data array.
	 * @param string               $key  The key to extract.
	 *
	 * @return array<string, mixed> The extracted array or empty array.
	 */
	private function extractArray(array $data, string $key): array
	{
		if (!isset($data[$key]) || !is_array($data[$key])) {
			return [];
		}

		return $data[$key];
	}
}
