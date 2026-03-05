<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Integration\Mcp;

use Automattic\PhpMcpClient\Client\ClientCapabilities;
use Automattic\PhpMcpClient\Client\McpClient;
use Automattic\PhpMcpClient\Client\ServerCapabilities;
use Automattic\PhpMcpClient\Client\ServerInfo;
use Automattic\PhpMcpClient\Contracts\TransportInterface;
use Automattic\PhpMcpClient\Exception\ConnectionException;
use Automattic\PhpMcpClient\Exception\TimeoutException;
use Automattic\PhpMcpClient\Exception\TransportException;
use Automattic\PhpMcpClient\Transport\StdioTransport;
use Automattic\WpAiAgent\Core\Exceptions\McpConnectionException;
use Automattic\WpAiAgent\Integration\Mcp\McpClientManager;
use Automattic\WpAiAgent\Integration\Mcp\McpServerConfiguration;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for McpClientManager.
 *
 * @covers \Automattic\WpAiAgent\Integration\Mcp\McpClientManager
 */
final class McpClientManagerTest extends TestCase
{
	/**
	 * Mock logger for tests.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $mock_logger;

	/**
	 * Sets up the test environment.
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->mock_logger = $this->createMock(LoggerInterface::class);
	}

	/**
	 * Creates a testable manager with injectable dependencies.
	 *
	 * @param McpClient|null          $mock_client Mock client to return.
	 * @param StdioTransport|null     $mock_transport Mock transport to use.
	 * @param LoggerInterface|null    $logger Logger instance.
	 * @param ClientCapabilities|null $capabilities Client capabilities.
	 *
	 * @return TestableMcpClientManager
	 */
	private function createTestableManager(
		?McpClient $mock_client = null,
		?StdioTransport $mock_transport = null,
		?LoggerInterface $logger = null,
		?ClientCapabilities $capabilities = null
	): TestableMcpClientManager {
		return new TestableMcpClientManager(
			$mock_client,
			$mock_transport,
			$logger ?? $this->mock_logger,
			$capabilities
		);
	}

	/**
	 * Tests that the manager can be instantiated without dependencies.
	 */
	public function test_constructor_withoutDependencies_succeeds(): void
	{
		$manager = new McpClientManager();

		$this->assertInstanceOf(McpClientManager::class, $manager);
	}

	/**
	 * Tests that the manager can be instantiated with logger.
	 */
	public function test_constructor_withLogger_succeeds(): void
	{
		$manager = new McpClientManager($this->mock_logger);

		$this->assertInstanceOf(McpClientManager::class, $manager);
	}

	/**
	 * Tests that the manager can be instantiated with capabilities.
	 */
	public function test_constructor_withCapabilities_succeeds(): void
	{
		$capabilities = new ClientCapabilities();
		$manager = new McpClientManager(null, $capabilities);

		$this->assertInstanceOf(McpClientManager::class, $manager);
	}

	/**
	 * Tests connect with invalid configuration throws exception.
	 */
	public function test_connect_withInvalidConfiguration_throwsException(): void
	{
		$manager = new McpClientManager($this->mock_logger);
		$config = McpServerConfiguration::stdio('test', '');

		$this->expectException(McpConnectionException::class);
		$this->expectExceptionMessage('Invalid server configuration');

		$manager->connect($config);
	}

	/**
	 * Tests connect successfully establishes connection.
	 */
	public function test_connect_withValidConfiguration_establishesConnection(): void
	{
		$mock_client = $this->createMock(McpClient::class);
		$mock_client->expects($this->once())
			->method('connect')
			->with(30.0);
		$mock_client->method('isConnected')
			->willReturn(true);
		$mock_client->method('getServerInfo')
			->willReturn(new ServerInfo('TestServer', '1.0.0', '2025-11-25', new ServerCapabilities([])));

		$manager = $this->createTestableManager($mock_client);
		$config = McpServerConfiguration::stdio('test-server', 'echo');

		$manager->connect($config);

		$this->assertTrue($manager->isConnected('test-server'));
	}

	/**
	 * Tests connect handles timeout exception.
	 */
	public function test_connect_withTimeout_throwsMcpConnectionException(): void
	{
		$mock_client = $this->createMock(McpClient::class);
		$mock_client->method('connect')
			->willThrowException(new TimeoutException('Connection timed out'));

		$manager = $this->createTestableManager($mock_client);
		$config = McpServerConfiguration::stdio('test-server', 'slow-command', [], null, 5.0);

		$this->expectException(McpConnectionException::class);
		$this->expectExceptionMessage('timed out after 5 seconds');

		$manager->connect($config);
	}

	/**
	 * Tests connect handles connection exception.
	 */
	public function test_connect_withConnectionError_throwsMcpConnectionException(): void
	{
		$mock_client = $this->createMock(McpClient::class);
		$mock_client->method('connect')
			->willThrowException(new ConnectionException('Failed to start process'));

		$manager = $this->createTestableManager($mock_client);
		$config = McpServerConfiguration::stdio('test-server', 'nonexistent');

		$this->expectException(McpConnectionException::class);
		$this->expectExceptionMessage('Failed to start process');

		$manager->connect($config);
	}

	/**
	 * Tests connect handles transport exception.
	 */
	public function test_connect_withTransportError_throwsMcpConnectionException(): void
	{
		$mock_client = $this->createMock(McpClient::class);
		$mock_client->method('connect')
			->willThrowException(new TransportException('Transport failed'));

		$manager = $this->createTestableManager($mock_client);
		$config = McpServerConfiguration::stdio('test-server', 'bad-transport');

		$this->expectException(McpConnectionException::class);
		$this->expectExceptionMessage('Transport error');

		$manager->connect($config);
	}

	/**
	 * Tests connect does not reconnect if already connected.
	 */
	public function test_connect_whenAlreadyConnected_skipsReconnection(): void
	{
		$mock_client = $this->createMock(McpClient::class);
		$mock_client->expects($this->once())
			->method('connect');
		$mock_client->method('isConnected')
			->willReturn(true);
		$mock_client->method('getServerInfo')
			->willReturn(new ServerInfo('TestServer', '1.0.0', '2025-11-25', new ServerCapabilities([])));

		$manager = $this->createTestableManager($mock_client);
		$config = McpServerConfiguration::stdio('test-server', 'echo');

		$manager->connect($config);
		$manager->connect($config); // Second call should be skipped

		$this->assertTrue($manager->isConnected('test-server'));
	}

	/**
	 * Tests connectAll connects to multiple servers.
	 */
	public function test_connectAll_withMultipleServers_connectsToAll(): void
	{
		$mock_client = $this->createMock(McpClient::class);
		$mock_client->method('isConnected')
			->willReturn(true);
		$mock_client->method('getServerInfo')
			->willReturn(new ServerInfo('TestServer', '1.0.0', '2025-11-25', new ServerCapabilities([])));

		$manager = $this->createTestableManager($mock_client);

		$configs = [
			McpServerConfiguration::stdio('server1', 'cmd1'),
			McpServerConfiguration::stdio('server2', 'cmd2'),
			McpServerConfiguration::stdio('server3', 'cmd3'),
		];

		$failures = $manager->connectAll($configs);

		$this->assertEmpty($failures);
		$this->assertCount(3, $manager->getConnectedServers());
	}

	/**
	 * Tests connectAll returns failures without stopping.
	 */
	public function test_connectAll_withSomeFailures_returnsFailures(): void
	{
		$call_count = 0;
		$mock_client = $this->createMock(McpClient::class);
		$mock_client->method('connect')
			->willReturnCallback(function () use (&$call_count) {
				$call_count++;
				if ($call_count === 2) {
					throw new ConnectionException('Server 2 failed');
				}
			});
		$mock_client->method('isConnected')
			->willReturn(true);
		$mock_client->method('getServerInfo')
			->willReturn(new ServerInfo('TestServer', '1.0.0', '2025-11-25', new ServerCapabilities([])));

		$manager = $this->createTestableManager($mock_client);

		$configs = [
			McpServerConfiguration::stdio('server1', 'cmd1'),
			McpServerConfiguration::stdio('server2', 'cmd2'),
			McpServerConfiguration::stdio('server3', 'cmd3'),
		];

		$failures = $manager->connectAll($configs);

		$this->assertCount(1, $failures);
		$this->assertArrayHasKey('server2', $failures);
		$this->assertInstanceOf(McpConnectionException::class, $failures['server2']);
	}

	/**
	 * Tests getClient returns client when connected.
	 */
	public function test_getClient_whenConnected_returnsClient(): void
	{
		$mock_client = $this->createMock(McpClient::class);
		$mock_client->method('isConnected')
			->willReturn(true);
		$mock_client->method('getServerInfo')
			->willReturn(new ServerInfo('TestServer', '1.0.0', '2025-11-25', new ServerCapabilities([])));

		$manager = $this->createTestableManager($mock_client);
		$config = McpServerConfiguration::stdio('test-server', 'echo');

		$manager->connect($config);

		$client = $manager->getClient('test-server');

		$this->assertSame($mock_client, $client);
	}

	/**
	 * Tests getClient returns null when not connected.
	 */
	public function test_getClient_whenNotConnected_returnsNull(): void
	{
		$manager = new McpClientManager($this->mock_logger);

		$client = $manager->getClient('nonexistent');

		$this->assertNull($client);
	}

	/**
	 * Tests isConnected returns true for connected server.
	 */
	public function test_isConnected_withConnectedServer_returnsTrue(): void
	{
		$mock_client = $this->createMock(McpClient::class);
		$mock_client->method('isConnected')
			->willReturn(true);
		$mock_client->method('getServerInfo')
			->willReturn(new ServerInfo('TestServer', '1.0.0', '2025-11-25', new ServerCapabilities([])));

		$manager = $this->createTestableManager($mock_client);
		$config = McpServerConfiguration::stdio('test-server', 'echo');

		$manager->connect($config);

		$this->assertTrue($manager->isConnected('test-server'));
	}

	/**
	 * Tests isConnected returns false for unknown server.
	 */
	public function test_isConnected_withUnknownServer_returnsFalse(): void
	{
		$manager = new McpClientManager($this->mock_logger);

		$this->assertFalse($manager->isConnected('unknown'));
	}

	/**
	 * Tests isConnected returns false when client reports disconnected.
	 */
	public function test_isConnected_whenClientDisconnected_returnsFalse(): void
	{
		$mock_client = $this->createMock(McpClient::class);
		$mock_client->method('isConnected')
			->willReturnOnConsecutiveCalls(true, false);
		$mock_client->method('getServerInfo')
			->willReturn(new ServerInfo('TestServer', '1.0.0', '2025-11-25', new ServerCapabilities([])));

		$manager = $this->createTestableManager($mock_client);
		$config = McpServerConfiguration::stdio('test-server', 'echo');

		$manager->connect($config);
		$this->assertTrue($manager->isConnected('test-server'));

		// Client now reports disconnected
		$this->assertFalse($manager->isConnected('test-server'));
	}

	/**
	 * Tests disconnect removes client and calls disconnect on it.
	 */
	public function test_disconnect_callsClientDisconnect(): void
	{
		$mock_client = $this->createMock(McpClient::class);
		$mock_client->expects($this->once())
			->method('disconnect');
		$mock_client->method('isConnected')
			->willReturn(true);
		$mock_client->method('getServerInfo')
			->willReturn(new ServerInfo('TestServer', '1.0.0', '2025-11-25', new ServerCapabilities([])));

		$manager = $this->createTestableManager($mock_client);
		$config = McpServerConfiguration::stdio('test-server', 'echo');

		$manager->connect($config);
		$manager->disconnect('test-server');

		$this->assertNull($manager->getClient('test-server'));
	}

	/**
	 * Tests disconnect handles exception gracefully.
	 */
	public function test_disconnect_withException_handlesGracefully(): void
	{
		$mock_client = $this->createMock(McpClient::class);
		$mock_client->method('disconnect')
			->willThrowException(new TransportException('Disconnect failed'));
		$mock_client->method('isConnected')
			->willReturn(true);
		$mock_client->method('getServerInfo')
			->willReturn(new ServerInfo('TestServer', '1.0.0', '2025-11-25', new ServerCapabilities([])));

		$manager = $this->createTestableManager($mock_client);
		$config = McpServerConfiguration::stdio('test-server', 'echo');

		$manager->connect($config);
		$manager->disconnect('test-server'); // Should not throw

		$this->assertNull($manager->getClient('test-server'));
	}

	/**
	 * Tests disconnect on unknown server does nothing.
	 */
	public function test_disconnect_withUnknownServer_doesNothing(): void
	{
		$manager = new McpClientManager($this->mock_logger);

		$manager->disconnect('unknown'); // Should not throw

		$this->assertFalse($manager->isConnected('unknown'));
	}

	/**
	 * Tests disconnectAll disconnects all clients.
	 */
	public function test_disconnectAll_disconnectsAllClients(): void
	{
		$mock_client = $this->createMock(McpClient::class);
		$mock_client->method('isConnected')
			->willReturn(true);
		$mock_client->method('getServerInfo')
			->willReturn(new ServerInfo('TestServer', '1.0.0', '2025-11-25', new ServerCapabilities([])));

		$manager = $this->createTestableManager($mock_client);

		$configs = [
			McpServerConfiguration::stdio('server1', 'cmd1'),
			McpServerConfiguration::stdio('server2', 'cmd2'),
		];

		$manager->connectAll($configs);
		$this->assertCount(2, $manager->getConnectedServers());

		$manager->disconnectAll();

		$this->assertEmpty($manager->getConnectedServers());
	}

	/**
	 * Tests getConnectedServers returns only connected servers.
	 */
	public function test_getConnectedServers_returnsOnlyConnectedServers(): void
	{
		$mock_client = $this->createMock(McpClient::class);
		$mock_client->method('isConnected')
			->willReturn(true);
		$mock_client->method('getServerInfo')
			->willReturn(new ServerInfo('TestServer', '1.0.0', '2025-11-25', new ServerCapabilities([])));

		$manager = $this->createTestableManager($mock_client);

		$configs = [
			McpServerConfiguration::stdio('server1', 'cmd1'),
			McpServerConfiguration::stdio('server2', 'cmd2'),
		];

		$manager->connectAll($configs);

		$connected = $manager->getConnectedServers();

		$this->assertCount(2, $connected);
		$this->assertContains('server1', $connected);
		$this->assertContains('server2', $connected);
	}

	/**
	 * Tests getConfigurations returns all registered configurations.
	 */
	public function test_getConfigurations_returnsAllConfigurations(): void
	{
		$mock_client = $this->createMock(McpClient::class);
		$mock_client->method('isConnected')
			->willReturn(true);
		$mock_client->method('getServerInfo')
			->willReturn(new ServerInfo('TestServer', '1.0.0', '2025-11-25', new ServerCapabilities([])));

		$manager = $this->createTestableManager($mock_client);
		$config = McpServerConfiguration::stdio('test-server', 'echo');

		$manager->connect($config);

		$configurations = $manager->getConfigurations();

		$this->assertArrayHasKey('test-server', $configurations);
		$this->assertSame($config, $configurations['test-server']);
	}

	/**
	 * Tests getConfiguration returns specific configuration.
	 */
	public function test_getConfiguration_withExistingServer_returnsConfiguration(): void
	{
		$mock_client = $this->createMock(McpClient::class);
		$mock_client->method('isConnected')
			->willReturn(true);
		$mock_client->method('getServerInfo')
			->willReturn(new ServerInfo('TestServer', '1.0.0', '2025-11-25', new ServerCapabilities([])));

		$manager = $this->createTestableManager($mock_client);
		$config = McpServerConfiguration::stdio('test-server', 'echo');

		$manager->connect($config);

		$retrieved = $manager->getConfiguration('test-server');

		$this->assertSame($config, $retrieved);
	}

	/**
	 * Tests getConfiguration returns null for unknown server.
	 */
	public function test_getConfiguration_withUnknownServer_returnsNull(): void
	{
		$manager = new McpClientManager($this->mock_logger);

		$config = $manager->getConfiguration('unknown');

		$this->assertNull($config);
	}
}

/**
 * Testable version of McpClientManager that allows dependency injection.
 */
class TestableMcpClientManager extends McpClientManager
{
	private ?McpClient $mock_client;
	private ?TransportInterface $mock_transport;

	public function __construct(
		?McpClient $mock_client = null,
		?TransportInterface $mock_transport = null,
		?LoggerInterface $logger = null,
		?ClientCapabilities $capabilities = null
	) {
		parent::__construct($logger, $capabilities);
		$this->mock_client = $mock_client;
		$this->mock_transport = $mock_transport;
	}

	protected function createTransport(McpServerConfiguration $config): TransportInterface
	{
		if ($this->mock_transport !== null) {
			return $this->mock_transport;
		}

		return parent::createTransport($config);
	}

	protected function createClient(TransportInterface $transport): McpClient
	{
		if ($this->mock_client !== null) {
			return $this->mock_client;
		}

		return parent::createClient($transport);
	}
}
