<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Integration\Configuration;

use Automattic\Automattic\WpAiAgent\Core\Configuration\McpServerConfiguration;
use Automattic\Automattic\WpAiAgent\Core\Exceptions\ConfigurationException;
use Automattic\Automattic\WpAiAgent\Integration\Configuration\EnvConfigurationLoader;
use Automattic\Automattic\WpAiAgent\Integration\Configuration\McpJsonLoader;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for McpJsonLoader.
 *
 * @covers \Automattic\WpAiAgent\Integration\Configuration\McpJsonLoader
 */
final class McpJsonLoaderTest extends TestCase
{
	/**
	 * Temporary directory for test files.
	 *
	 * @var string
	 */
	private string $temp_dir;

	/**
	 * Sets up the test fixture.
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->temp_dir = sys_get_temp_dir() . '/mcp_json_loader_test_' . uniqid();
		mkdir($this->temp_dir, 0755, true);
	}

	/**
	 * Tears down the test fixture.
	 */
	protected function tearDown(): void
	{
		$this->removeDirectory($this->temp_dir);

		parent::tearDown();
	}

	/**
	 * Tests that constructor creates loader with default env loader.
	 */
	public function test_constructor_withDefaults_createsLoader(): void
	{
		$loader = new McpJsonLoader();

		$this->assertInstanceOf(McpJsonLoader::class, $loader);
	}

	/**
	 * Tests that load returns empty array when file does not exist.
	 */
	public function test_load_withNoFile_returnsEmptyArray(): void
	{
		$loader = new McpJsonLoader();

		$servers = $loader->load($this->temp_dir);

		$this->assertIsArray($servers);
		$this->assertCount(0, $servers);
	}

	/**
	 * Tests that load returns HTTP server configuration.
	 */
	public function test_load_withHttpServerDefinition_returnsHttpConfiguration(): void
	{
		$this->createMcpFile([
			'mcpServers' => [
				'api-server' => [
					'url' => 'https://api.example.com/mcp',
					'bearer_token' => 'token123',
				],
			],
		]);

		$loader = new McpJsonLoader();

		$servers = $loader->load($this->temp_dir);

		$this->assertCount(1, $servers);
		$this->assertInstanceOf(McpServerConfiguration::class, $servers[0]);
		$this->assertSame('api-server', $servers[0]->getName());
		$this->assertTrue($servers[0]->isHttpTransport());
		$this->assertSame('https://api.example.com/mcp', $servers[0]->getUrl());
		$this->assertSame('token123', $servers[0]->getBearerToken());
	}

	/**
	 * Tests that load returns stdio server configuration.
	 */
	public function test_load_withStdioServerDefinition_returnsStdioConfiguration(): void
	{
		$this->createMcpFile([
			'mcpServers' => [
				'local-server' => [
					'command' => 'node',
					'args' => ['server.js', '--port', '8080'],
					'env' => [
						'NODE_ENV' => 'development',
					],
				],
			],
		]);

		$loader = new McpJsonLoader();

		$servers = $loader->load($this->temp_dir);

		$this->assertCount(1, $servers);
		$this->assertInstanceOf(McpServerConfiguration::class, $servers[0]);
		$this->assertSame('local-server', $servers[0]->getName());
		$this->assertTrue($servers[0]->isStdioTransport());
		$this->assertSame('node', $servers[0]->getCommand());
		$this->assertSame(['server.js', '--port', '8080'], $servers[0]->getArgs());
		$this->assertSame(['NODE_ENV' => 'development'], $servers[0]->getEnv());
	}

	/**
	 * Tests that environment variables in URL are expanded.
	 */
	public function test_load_withEnvVariableInUrl_expandsVariable(): void
	{
		$this->createMcpFile([
			'mcpServers' => [
				'api-server' => [
					'url' => '${API_URL}',
				],
			],
		]);

		$env_provider = static fn(string $name) => $name === 'API_URL' ? 'https://api.example.com' : false;
		$env_loader = new EnvConfigurationLoader(true, $env_provider);
		$loader = new McpJsonLoader($env_loader);

		$servers = $loader->load($this->temp_dir);

		$this->assertCount(1, $servers);
		$this->assertSame('https://api.example.com', $servers[0]->getUrl());
	}

	/**
	 * Tests that environment variables in bearer token are expanded.
	 */
	public function test_load_withEnvVariableInBearerToken_expandsVariable(): void
	{
		$this->createMcpFile([
			'mcpServers' => [
				'api-server' => [
					'url' => 'https://api.example.com',
					'bearer_token' => '${API_TOKEN}',
				],
			],
		]);

		$env_provider = static fn(string $name) => $name === 'API_TOKEN' ? 'secret-token' : false;
		$env_loader = new EnvConfigurationLoader(true, $env_provider);
		$loader = new McpJsonLoader($env_loader);

		$servers = $loader->load($this->temp_dir);

		$this->assertCount(1, $servers);
		$this->assertSame('secret-token', $servers[0]->getBearerToken());
	}

	/**
	 * Tests that environment variables in command are expanded.
	 */
	public function test_load_withEnvVariableInCommand_expandsVariable(): void
	{
		$this->createMcpFile([
			'mcpServers' => [
				'local-server' => [
					'command' => '${NODE_PATH}',
					'args' => [],
				],
			],
		]);

		$env_provider = static fn(string $name) => $name === 'NODE_PATH' ? '/usr/local/bin/node' : false;
		$env_loader = new EnvConfigurationLoader(true, $env_provider);
		$loader = new McpJsonLoader($env_loader);

		$servers = $loader->load($this->temp_dir);

		$this->assertCount(1, $servers);
		$this->assertSame('/usr/local/bin/node', $servers[0]->getCommand());
	}

	/**
	 * Tests that environment variables in args are expanded.
	 */
	public function test_load_withEnvVariableInArgs_expandsVariable(): void
	{
		$this->createMcpFile([
			'mcpServers' => [
				'local-server' => [
					'command' => 'node',
					'args' => ['${SCRIPT_PATH}'],
				],
			],
		]);

		$env_provider = static fn(string $name) => $name === 'SCRIPT_PATH' ? '/app/server.js' : false;
		$env_loader = new EnvConfigurationLoader(true, $env_provider);
		$loader = new McpJsonLoader($env_loader);

		$servers = $loader->load($this->temp_dir);

		$this->assertCount(1, $servers);
		$this->assertSame(['/app/server.js'], $servers[0]->getArgs());
	}

	/**
	 * Tests that environment variables in env values are expanded.
	 */
	public function test_load_withEnvVariableInEnvValues_expandsVariable(): void
	{
		$this->createMcpFile([
			'mcpServers' => [
				'local-server' => [
					'command' => 'node',
					'args' => [],
					'env' => [
						'API_KEY' => '${SECRET_API_KEY}',
					],
				],
			],
		]);

		$env_provider = static fn(string $name) => $name === 'SECRET_API_KEY' ? 'secret123' : false;
		$env_loader = new EnvConfigurationLoader(true, $env_provider);
		$loader = new McpJsonLoader($env_loader);

		$servers = $loader->load($this->temp_dir);

		$this->assertCount(1, $servers);
		$this->assertSame(['API_KEY' => 'secret123'], $servers[0]->getEnv());
	}

	/**
	 * Tests that multiple servers are loaded.
	 */
	public function test_load_withMultipleServers_returnsAllConfigurations(): void
	{
		$this->createMcpFile([
			'mcpServers' => [
				'server1' => [
					'url' => 'https://server1.example.com',
				],
				'server2' => [
					'command' => 'python',
					'args' => ['server.py'],
				],
				'server3' => [
					'url' => 'https://server3.example.com',
					'bearer_token' => 'token3',
				],
			],
		]);

		$loader = new McpJsonLoader();

		$servers = $loader->load($this->temp_dir);

		$this->assertCount(3, $servers);
		$this->assertSame('server1', $servers[0]->getName());
		$this->assertTrue($servers[0]->isHttpTransport());
		$this->assertSame('server2', $servers[1]->getName());
		$this->assertTrue($servers[1]->isStdioTransport());
		$this->assertSame('server3', $servers[2]->getName());
		$this->assertTrue($servers[2]->isHttpTransport());
	}

	/**
	 * Tests that invalid JSON throws ConfigurationException.
	 */
	public function test_load_withInvalidJson_throwsConfigurationException(): void
	{
		$config_dir = $this->temp_dir . '/.wp-ai-agent';
		mkdir($config_dir, 0755, true);
		file_put_contents($config_dir . '/mcp.json', '{invalid json}');

		$loader = new McpJsonLoader();

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('parse');

		$loader->load($this->temp_dir);
	}

	/**
	 * Tests that non-object root throws ConfigurationException.
	 */
	public function test_load_withNonObjectRoot_throwsConfigurationException(): void
	{
		$config_dir = $this->temp_dir . '/.wp-ai-agent';
		mkdir($config_dir, 0755, true);
		file_put_contents($config_dir . '/mcp.json', '["item1", "item2"]');

		$loader = new McpJsonLoader();

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('object');

		$loader->load($this->temp_dir);
	}

	/**
	 * Tests that missing environment variable throws ConfigurationException in strict mode.
	 */
	public function test_load_withMissingEnvVariable_throwsException(): void
	{
		$this->createMcpFile([
			'mcpServers' => [
				'api-server' => [
					'url' => '${MISSING_URL}',
				],
			],
		]);

		$env_loader = new EnvConfigurationLoader(true, fn() => false);
		$loader = new McpJsonLoader($env_loader);

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('MISSING_URL');

		$loader->load($this->temp_dir);
	}

	/**
	 * Tests that getMcpPath returns the correct path.
	 */
	public function test_getMcpPath_returnsCorrectPath(): void
	{
		$loader = new McpJsonLoader();

		$path = $loader->getMcpPath('/project/dir');

		$this->assertSame('/project/dir/.wp-ai-agent/mcp.json', $path);
	}

	/**
	 * Tests that fileExists returns false when file does not exist.
	 */
	public function test_fileExists_withNoFile_returnsFalse(): void
	{
		$loader = new McpJsonLoader();

		$result = $loader->fileExists($this->temp_dir);

		$this->assertFalse($result);
	}

	/**
	 * Tests that fileExists returns true when file exists.
	 */
	public function test_fileExists_withFile_returnsTrue(): void
	{
		$this->createMcpFile(['mcpServers' => []]);

		$loader = new McpJsonLoader();

		$result = $loader->fileExists($this->temp_dir);

		$this->assertTrue($result);
	}

	/**
	 * Tests that empty mcpServers returns empty array.
	 */
	public function test_load_withEmptyMcpServers_returnsEmptyArray(): void
	{
		$this->createMcpFile(['mcpServers' => []]);

		$loader = new McpJsonLoader();

		$servers = $loader->load($this->temp_dir);

		$this->assertIsArray($servers);
		$this->assertCount(0, $servers);
	}

	/**
	 * Tests that missing mcpServers key returns empty array.
	 */
	public function test_load_withMissingMcpServersKey_returnsEmptyArray(): void
	{
		$this->createMcpFile(['otherKey' => 'value']);

		$loader = new McpJsonLoader();

		$servers = $loader->load($this->temp_dir);

		$this->assertIsArray($servers);
		$this->assertCount(0, $servers);
	}

	/**
	 * Tests that HTTP server with custom headers is loaded correctly.
	 */
	public function test_load_withHttpServerWithHeaders_returnsConfigurationWithHeaders(): void
	{
		$this->createMcpFile([
			'mcpServers' => [
				'api-server' => [
					'url' => 'https://api.example.com/mcp',
					'headers' => [
						'X-Custom-Header' => 'custom-value',
						'Accept' => 'application/json',
					],
				],
			],
		]);

		$loader = new McpJsonLoader();

		$servers = $loader->load($this->temp_dir);

		$this->assertCount(1, $servers);
		$this->assertSame('https://api.example.com/mcp', $servers[0]->getUrl());
		$headers = $servers[0]->getHeaders();
		$this->assertArrayHasKey('X-Custom-Header', $headers);
		$this->assertSame('custom-value', $headers['X-Custom-Header']);
	}

	/**
	 * Tests that HTTP server with timeout is loaded correctly.
	 */
	public function test_load_withHttpServerWithTimeout_returnsConfigurationWithTimeout(): void
	{
		$this->createMcpFile([
			'mcpServers' => [
				'api-server' => [
					'url' => 'https://api.example.com/mcp',
					'timeout' => 60,
				],
			],
		]);

		$loader = new McpJsonLoader();

		$servers = $loader->load($this->temp_dir);

		$this->assertCount(1, $servers);
		$this->assertSame(60.0, $servers[0]->getTimeout());
	}

	/**
	 * Tests that stdio server with timeout is loaded correctly.
	 */
	public function test_load_withStdioServerWithTimeout_returnsConfigurationWithTimeout(): void
	{
		$this->createMcpFile([
			'mcpServers' => [
				'local-server' => [
					'command' => 'node',
					'args' => ['server.js'],
					'timeout' => 120,
				],
			],
		]);

		$loader = new McpJsonLoader();

		$servers = $loader->load($this->temp_dir);

		$this->assertCount(1, $servers);
		$this->assertSame(120.0, $servers[0]->getTimeout());
	}

	/**
	 * Tests that enabled flag defaults to true.
	 */
	public function test_load_withNoEnabledFlag_defaultsToTrue(): void
	{
		$this->createMcpFile([
			'mcpServers' => [
				'api-server' => [
					'url' => 'https://api.example.com/mcp',
				],
			],
		]);

		$loader = new McpJsonLoader();

		$servers = $loader->load($this->temp_dir);

		$this->assertCount(1, $servers);
		$this->assertTrue($servers[0]->isEnabled());
	}

	/**
	 * Tests that enabled flag can be set to false.
	 */
	public function test_load_withEnabledFalse_returnsDisabledConfiguration(): void
	{
		$this->createMcpFile([
			'mcpServers' => [
				'api-server' => [
					'url' => 'https://api.example.com/mcp',
					'enabled' => false,
				],
			],
		]);

		$loader = new McpJsonLoader();

		$servers = $loader->load($this->temp_dir);

		$this->assertCount(1, $servers);
		$this->assertFalse($servers[0]->isEnabled());
	}

	/**
	 * Creates a mcp.json file in the temp directory.
	 *
	 * @param array<string, mixed> $content The JSON content as an array.
	 */
	private function createMcpFile(array $content): void
	{
		$config_dir = $this->temp_dir . '/.wp-ai-agent';
		if (! is_dir($config_dir)) {
			mkdir($config_dir, 0755, true);
		}
		file_put_contents(
			$config_dir . '/mcp.json',
			json_encode($content, JSON_PRETTY_PRINT)
		);
	}

	/**
	 * Recursively removes a directory.
	 *
	 * @param string $dir The directory to remove.
	 */
	private function removeDirectory(string $dir): void
	{
		if (! is_dir($dir)) {
			return;
		}

		$items = scandir($dir);
		if ($items === false) {
			return;
		}

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$path = $dir . '/' . $item;
			if (is_dir($path)) {
				$this->removeDirectory($path);
			} else {
				unlink($path);
			}
		}

		rmdir($dir);
	}
}
