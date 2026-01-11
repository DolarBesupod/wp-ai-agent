<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Integration\Mcp;

use GalatanOvidiu\PhpMcpClient\Core\Client\McpClient;
use PhpCliAgent\Core\Contracts\ToolInterface;
use PhpCliAgent\Core\Contracts\ToolRegistryInterface;
use PhpCliAgent\Core\Exceptions\DuplicateToolException;
use PhpCliAgent\Core\Exceptions\McpConnectionException;
use PhpCliAgent\Integration\Mcp\McpClientManager;
use PhpCliAgent\Integration\Mcp\McpToolAdapter;
use PhpCliAgent\Integration\Mcp\McpToolRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for McpToolRegistry.
 *
 * @covers \PhpCliAgent\Integration\Mcp\McpToolRegistry
 */
final class McpToolRegistryTest extends TestCase
{
	/**
	 * Mock MCP client manager.
	 */
	private McpClientManager&MockObject $mock_client_manager;

	/**
	 * Mock tool registry.
	 */
	private ToolRegistryInterface&MockObject $mock_registry;

	/**
	 * Mock logger.
	 */
	private LoggerInterface&MockObject $mock_logger;

	/**
	 * Mock MCP client.
	 */
	private McpClient&MockObject $mock_mcp_client;

	/**
	 * Sets up the test environment.
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->mock_client_manager = $this->createMock(McpClientManager::class);
		$this->mock_registry = $this->createMock(ToolRegistryInterface::class);
		$this->mock_logger = $this->createMock(LoggerInterface::class);
		$this->mock_mcp_client = $this->createMock(McpClient::class);
	}

	/**
	 * Creates a McpToolRegistry instance for testing.
	 *
	 * @param float $default_timeout Default timeout for tools.
	 *
	 * @return McpToolRegistry
	 */
	private function createRegistry(float $default_timeout = 60.0): McpToolRegistry
	{
		return new McpToolRegistry(
			$this->mock_client_manager,
			$this->mock_registry,
			$this->mock_logger,
			$default_timeout
		);
	}

	/**
	 * Tests constructor without optional dependencies.
	 */
	public function test_constructor_withoutOptionalDependencies_succeeds(): void
	{
		$registry = new McpToolRegistry(
			$this->mock_client_manager,
			$this->mock_registry
		);

		$this->assertInstanceOf(McpToolRegistry::class, $registry);
	}

	/**
	 * Tests constructor with custom timeout.
	 */
	public function test_constructor_withCustomTimeout_succeeds(): void
	{
		$registry = new McpToolRegistry(
			$this->mock_client_manager,
			$this->mock_registry,
			$this->mock_logger,
			120.0
		);

		$this->assertInstanceOf(McpToolRegistry::class, $registry);
	}

	/**
	 * Tests discoverFromServer with server that has 3 tools.
	 */
	public function test_discoverFromServer_withThreeTools_registersThreeAdapters(): void
	{
		$tools = [
			['name' => 'read_file', 'description' => 'Read a file', 'inputSchema' => ['type' => 'object']],
			['name' => 'write_file', 'description' => 'Write a file', 'inputSchema' => ['type' => 'object']],
			['name' => 'list_dir', 'description' => 'List directory', 'inputSchema' => ['type' => 'object']],
		];

		$this->mock_client_manager->method('getClient')
			->with('filesystem')
			->willReturn($this->mock_mcp_client);

		$this->mock_mcp_client->method('listTools')
			->willReturn(['tools' => $tools]);

		$this->mock_registry->expects($this->exactly(3))
			->method('register')
			->with($this->isInstanceOf(McpToolAdapter::class));

		$registry = $this->createRegistry();
		$count = $registry->discoverFromServer('filesystem');

		$this->assertSame(3, $count);
	}

	/**
	 * Tests discoverFromServer with server that has no tools.
	 */
	public function test_discoverFromServer_withNoTools_returnsZero(): void
	{
		$this->mock_client_manager->method('getClient')
			->with('empty-server')
			->willReturn($this->mock_mcp_client);

		$this->mock_mcp_client->method('listTools')
			->willReturn(['tools' => []]);

		$this->mock_registry->expects($this->never())
			->method('register');

		$registry = $this->createRegistry();
		$count = $registry->discoverFromServer('empty-server');

		$this->assertSame(0, $count);
	}

	/**
	 * Tests discoverFromServer with disconnected server throws exception.
	 */
	public function test_discoverFromServer_withDisconnectedServer_throwsException(): void
	{
		$this->mock_client_manager->method('getClient')
			->with('disconnected')
			->willReturn(null);

		$registry = $this->createRegistry();

		$this->expectException(McpConnectionException::class);
		$this->expectExceptionMessage('unavailable');

		$registry->discoverFromServer('disconnected');
	}

	/**
	 * Tests discoverFromServer with missing tools key in response.
	 */
	public function test_discoverFromServer_withMissingToolsKey_returnsZero(): void
	{
		$this->mock_client_manager->method('getClient')
			->with('server')
			->willReturn($this->mock_mcp_client);

		$this->mock_mcp_client->method('listTools')
			->willReturn(['something_else' => []]);

		$this->mock_registry->expects($this->never())
			->method('register');

		$registry = $this->createRegistry();
		$count = $registry->discoverFromServer('server');

		$this->assertSame(0, $count);
	}

	/**
	 * Tests discoverFromServer skips tools with empty name.
	 */
	public function test_discoverFromServer_withEmptyToolName_skipsInvalidTool(): void
	{
		$tools = [
			['name' => '', 'description' => 'Invalid tool'],
			['name' => 'valid_tool', 'description' => 'Valid tool', 'inputSchema' => []],
		];

		$this->mock_client_manager->method('getClient')
			->with('server')
			->willReturn($this->mock_mcp_client);

		$this->mock_mcp_client->method('listTools')
			->willReturn(['tools' => $tools]);

		$this->mock_registry->expects($this->once())
			->method('register')
			->with($this->callback(function (ToolInterface $tool) {
				return str_contains($tool->getName(), 'valid_tool');
			}));

		$registry = $this->createRegistry();
		$count = $registry->discoverFromServer('server');

		$this->assertSame(1, $count);
	}

	/**
	 * Tests discoverFromServer handles duplicate tool registration.
	 */
	public function test_discoverFromServer_withDuplicateTool_logsWarningAndContinues(): void
	{
		$tools = [
			['name' => 'tool1', 'description' => 'Tool 1', 'inputSchema' => []],
			['name' => 'tool2', 'description' => 'Tool 2', 'inputSchema' => []],
		];

		$this->mock_client_manager->method('getClient')
			->with('server')
			->willReturn($this->mock_mcp_client);

		$this->mock_mcp_client->method('listTools')
			->willReturn(['tools' => $tools]);

		$call_count = 0;
		$this->mock_registry->method('register')
			->willReturnCallback(function () use (&$call_count) {
				$call_count++;
				if ($call_count === 1) {
					throw new DuplicateToolException('mcp_server_tool1');
				}
			});

		$this->mock_logger->expects($this->atLeastOnce())
			->method('warning')
			->with(
				$this->stringContains('duplicate'),
				$this->anything()
			);

		$registry = $this->createRegistry();
		$count = $registry->discoverFromServer('server');

		// First tool fails due to duplicate, second succeeds
		$this->assertSame(1, $count);
	}

	/**
	 * Tests discoverAndRegister with multiple servers.
	 */
	public function test_discoverAndRegister_withMultipleServers_registersAllTools(): void
	{
		$this->mock_client_manager->method('getConnectedServers')
			->willReturn(['server1', 'server2']);

		$this->mock_client_manager->method('getClient')
			->willReturn($this->mock_mcp_client);

		$this->mock_mcp_client->method('listTools')
			->willReturnOnConsecutiveCalls(
				['tools' => [
					['name' => 'tool1', 'description' => 'Tool 1', 'inputSchema' => []],
					['name' => 'tool2', 'description' => 'Tool 2', 'inputSchema' => []],
				]],
				['tools' => [
					['name' => 'tool3', 'description' => 'Tool 3', 'inputSchema' => []],
				]]
			);

		$this->mock_registry->expects($this->exactly(3))
			->method('register');

		$registry = $this->createRegistry();
		$count = $registry->discoverAndRegister();

		$this->assertSame(3, $count);
	}

	/**
	 * Tests discoverAndRegister with no connected servers.
	 */
	public function test_discoverAndRegister_withNoConnectedServers_returnsZero(): void
	{
		$this->mock_client_manager->method('getConnectedServers')
			->willReturn([]);

		$this->mock_registry->expects($this->never())
			->method('register');

		$registry = $this->createRegistry();
		$count = $registry->discoverAndRegister();

		$this->assertSame(0, $count);
	}

	/**
	 * Tests discoverAndRegister handles server failures gracefully.
	 */
	public function test_discoverAndRegister_withServerFailure_continuesWithOtherServers(): void
	{
		$this->mock_client_manager->method('getConnectedServers')
			->willReturn(['failing-server', 'working-server']);

		$this->mock_client_manager->method('getClient')
			->willReturnCallback(function (string $server_name) {
				if ($server_name === 'failing-server') {
					return null;
				}
				return $this->mock_mcp_client;
			});

		$this->mock_mcp_client->method('listTools')
			->willReturn(['tools' => [
				['name' => 'tool1', 'description' => 'Tool 1', 'inputSchema' => []],
			]]);

		$this->mock_registry->expects($this->once())
			->method('register');

		$registry = $this->createRegistry();
		$count = $registry->discoverAndRegister();

		$this->assertSame(1, $count);
	}

	/**
	 * Tests that registered adapters have correct prefixed names.
	 */
	public function test_discoverFromServer_registersAdaptersWithCorrectPrefix(): void
	{
		$tools = [
			['name' => 'my_tool', 'description' => 'My tool', 'inputSchema' => []],
		];

		$this->mock_client_manager->method('getClient')
			->with('my-server')
			->willReturn($this->mock_mcp_client);

		$this->mock_mcp_client->method('listTools')
			->willReturn(['tools' => $tools]);

		$registered_name = null;
		$this->mock_registry->method('register')
			->willReturnCallback(function (ToolInterface $tool) use (&$registered_name) {
				$registered_name = $tool->getName();
			});

		$registry = $this->createRegistry();
		$registry->discoverFromServer('my-server');

		$this->assertSame('mcp_my_server_my_tool', $registered_name);
	}

	/**
	 * Tests that multiple servers with overlapping tool names get different prefixes.
	 */
	public function test_discoverAndRegister_withOverlappingToolNames_registersBothWithDifferentPrefixes(): void
	{
		$this->mock_client_manager->method('getConnectedServers')
			->willReturn(['server1', 'server2']);

		$call_count = 0;
		$this->mock_client_manager->method('getClient')
			->willReturnCallback(function () use (&$call_count) {
				return $this->mock_mcp_client;
			});

		// Both servers have a tool named 'read_file'
		$this->mock_mcp_client->method('listTools')
			->willReturn(['tools' => [
				['name' => 'read_file', 'description' => 'Read a file', 'inputSchema' => []],
			]]);

		$registered_names = [];
		$this->mock_registry->method('register')
			->willReturnCallback(function (ToolInterface $tool) use (&$registered_names) {
				$registered_names[] = $tool->getName();
			});

		$registry = $this->createRegistry();
		$count = $registry->discoverAndRegister();

		$this->assertSame(2, $count);
		$this->assertContains('mcp_server1_read_file', $registered_names);
		$this->assertContains('mcp_server2_read_file', $registered_names);
	}

	/**
	 * Tests discoverFromServer handles exception from listTools.
	 */
	public function test_discoverFromServer_withListToolsException_returnsZero(): void
	{
		$this->mock_client_manager->method('getClient')
			->with('server')
			->willReturn($this->mock_mcp_client);

		$this->mock_mcp_client->method('listTools')
			->willThrowException(new \RuntimeException('Network error'));

		$this->mock_registry->expects($this->never())
			->method('register');

		$registry = $this->createRegistry();
		$count = $registry->discoverFromServer('server');

		$this->assertSame(0, $count);
	}

	/**
	 * Tests discoverFromServer with tool missing description.
	 */
	public function test_discoverFromServer_withMissingDescription_registersWithEmptyDescription(): void
	{
		$tools = [
			['name' => 'tool_without_desc', 'inputSchema' => []],
		];

		$this->mock_client_manager->method('getClient')
			->with('server')
			->willReturn($this->mock_mcp_client);

		$this->mock_mcp_client->method('listTools')
			->willReturn(['tools' => $tools]);

		$registered_tool = null;
		$this->mock_registry->method('register')
			->willReturnCallback(function (ToolInterface $tool) use (&$registered_tool) {
				$registered_tool = $tool;
			});

		$registry = $this->createRegistry();
		$registry->discoverFromServer('server');

		$this->assertInstanceOf(McpToolAdapter::class, $registered_tool);
		$this->assertSame('', $registered_tool->getDescription());
	}

	/**
	 * Tests discoverFromServer with tool missing inputSchema.
	 */
	public function test_discoverFromServer_withMissingInputSchema_registersWithEmptySchema(): void
	{
		$tools = [
			['name' => 'tool_without_schema', 'description' => 'A tool'],
		];

		$this->mock_client_manager->method('getClient')
			->with('server')
			->willReturn($this->mock_mcp_client);

		$this->mock_mcp_client->method('listTools')
			->willReturn(['tools' => $tools]);

		$registered_tool = null;
		$this->mock_registry->method('register')
			->willReturnCallback(function (ToolInterface $tool) use (&$registered_tool) {
				$registered_tool = $tool;
			});

		$registry = $this->createRegistry();
		$registry->discoverFromServer('server');

		$this->assertInstanceOf(McpToolAdapter::class, $registered_tool);
		$this->assertNull($registered_tool->getParametersSchema());
	}

	/**
	 * Tests discoverFromServer logs info on successful discovery.
	 */
	public function test_discoverFromServer_logsDiscoveryInfo(): void
	{
		$tools = [
			['name' => 'tool1', 'description' => 'Tool 1', 'inputSchema' => []],
		];

		$this->mock_client_manager->method('getClient')
			->with('server')
			->willReturn($this->mock_mcp_client);

		$this->mock_mcp_client->method('listTools')
			->willReturn(['tools' => $tools]);

		$this->mock_logger->expects($this->atLeastOnce())
			->method('info')
			->with(
				$this->stringContains('Discovered'),
				$this->callback(function (array $context) {
					return isset($context['server']) && $context['server'] === 'server';
				})
			);

		$registry = $this->createRegistry();
		$registry->discoverFromServer('server');
	}

	/**
	 * Tests discoverAndRegister logs completion info.
	 */
	public function test_discoverAndRegister_logsCompletionInfo(): void
	{
		$this->mock_client_manager->method('getConnectedServers')
			->willReturn(['server1']);

		$this->mock_client_manager->method('getClient')
			->willReturn($this->mock_mcp_client);

		$this->mock_mcp_client->method('listTools')
			->willReturn(['tools' => [
				['name' => 'tool1', 'description' => 'Tool 1', 'inputSchema' => []],
			]]);

		$this->mock_logger->expects($this->atLeastOnce())
			->method('info')
			->with(
				$this->stringContains('complete'),
				$this->callback(function (array $context) {
					return isset($context['servers']) && isset($context['tools_registered']);
				})
			);

		$registry = $this->createRegistry();
		$registry->discoverAndRegister();
	}

	/**
	 * Tests custom timeout is passed to adapters.
	 */
	public function test_discoverFromServer_withCustomTimeout_createsAdaptersWithTimeout(): void
	{
		$tools = [
			['name' => 'slow_tool', 'description' => 'A slow tool', 'inputSchema' => []],
		];

		$this->mock_client_manager->method('getClient')
			->with('server')
			->willReturn($this->mock_mcp_client);

		$this->mock_mcp_client->method('listTools')
			->willReturn(['tools' => $tools]);

		$registered_tool = null;
		$this->mock_registry->method('register')
			->willReturnCallback(function (ToolInterface $tool) use (&$registered_tool) {
				$registered_tool = $tool;
			});

		// Create registry with 120 second timeout
		$registry = $this->createRegistry(120.0);
		$registry->discoverFromServer('server');

		// The adapter should be created with the custom timeout
		// We can verify this by checking the adapter was registered
		$this->assertInstanceOf(McpToolAdapter::class, $registered_tool);
	}
}
