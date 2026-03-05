<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Core\Configuration;

use Automattic\Automattic\WpAiAgent\Core\Configuration\McpServerConfiguration;
use Automattic\Automattic\WpAiAgent\Core\Exceptions\ConfigurationException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for McpServerConfiguration (Core layer).
 *
 * @covers \Automattic\WpAiAgent\Core\Configuration\McpServerConfiguration
 */
final class McpServerConfigurationTest extends TestCase
{
	// =========================================================================
	// Constructor Tests - Stdio Transport
	// =========================================================================

	/**
	 * Tests constructor sets all properties for stdio transport.
	 */
	public function test_constructor_stdio_setsAllProperties(): void
	{
		$config = new McpServerConfiguration(
			'test-server',
			McpServerConfiguration::TRANSPORT_STDIO,
			'node',
			['server.js'],
			['API_KEY' => 'secret'],
			'',
			[],
			null,
			60.0,
			false
		);

		$this->assertSame('test-server', $config->getName());
		$this->assertSame(McpServerConfiguration::TRANSPORT_STDIO, $config->getTransport());
		$this->assertTrue($config->isStdioTransport());
		$this->assertFalse($config->isHttpTransport());
		$this->assertSame('node', $config->getCommand());
		$this->assertSame(['server.js'], $config->getArgs());
		$this->assertSame(['API_KEY' => 'secret'], $config->getEnv());
		$this->assertSame(60.0, $config->getTimeout());
		$this->assertFalse($config->isEnabled());
	}

	/**
	 * Tests constructor uses defaults for optional parameters.
	 */
	public function test_constructor_usesDefaults(): void
	{
		$config = new McpServerConfiguration('test-server');

		$this->assertSame('test-server', $config->getName());
		$this->assertSame(McpServerConfiguration::TRANSPORT_STDIO, $config->getTransport());
		$this->assertSame('', $config->getCommand());
		$this->assertSame([], $config->getArgs());
		$this->assertNull($config->getEnv());
		$this->assertSame('', $config->getUrl());
		$this->assertSame([], $config->getHeaders());
		$this->assertNull($config->getBearerToken());
		$this->assertSame(30.0, $config->getTimeout());
		$this->assertTrue($config->isEnabled());
	}

	// =========================================================================
	// Constructor Tests - HTTP Transport
	// =========================================================================

	/**
	 * Tests constructor sets all properties for HTTP transport.
	 */
	public function test_constructor_http_setsAllProperties(): void
	{
		$config = new McpServerConfiguration(
			'wordpress',
			McpServerConfiguration::TRANSPORT_HTTP,
			'',
			[],
			null,
			'https://api.example.com/mcp',
			['X-Custom' => 'value'],
			'my-token',
			90.0,
			true
		);

		$this->assertSame('wordpress', $config->getName());
		$this->assertSame(McpServerConfiguration::TRANSPORT_HTTP, $config->getTransport());
		$this->assertTrue($config->isHttpTransport());
		$this->assertFalse($config->isStdioTransport());
		$this->assertSame('https://api.example.com/mcp', $config->getUrl());
		$this->assertSame(['X-Custom' => 'value'], $config->getHeaders());
		$this->assertSame('my-token', $config->getBearerToken());
		$this->assertSame(90.0, $config->getTimeout());
		$this->assertTrue($config->isEnabled());
	}

	// =========================================================================
	// fromArray Tests - Stdio Transport
	// =========================================================================

	/**
	 * Tests fromArray creates stdio configuration correctly with all fields.
	 */
	public function test_fromArray_stdio_withAllFields_createsConfiguration(): void
	{
		$array = [
			'command' => 'npx',
			'args' => ['-y', '@modelcontextprotocol/server-filesystem'],
			'env' => ['HOME' => '/home/user'],
			'timeout' => 45.0,
			'enabled' => false,
		];

		$config = McpServerConfiguration::fromArray('filesystem', $array);

		$this->assertSame('filesystem', $config->getName());
		$this->assertTrue($config->isStdioTransport());
		$this->assertSame('npx', $config->getCommand());
		$this->assertSame(['-y', '@modelcontextprotocol/server-filesystem'], $config->getArgs());
		$this->assertSame(['HOME' => '/home/user'], $config->getEnv());
		$this->assertSame(45.0, $config->getTimeout());
		$this->assertFalse($config->isEnabled());
	}

	/**
	 * Tests fromArray handles minimal fields for stdio.
	 */
	public function test_fromArray_stdio_withMinimalFields_createsConfiguration(): void
	{
		$array = [
			'command' => 'my-server',
		];

		$config = McpServerConfiguration::fromArray('minimal', $array);

		$this->assertSame('minimal', $config->getName());
		$this->assertTrue($config->isStdioTransport());
		$this->assertSame('my-server', $config->getCommand());
		$this->assertSame([], $config->getArgs());
		$this->assertNull($config->getEnv());
		$this->assertSame(30.0, $config->getTimeout());
		$this->assertTrue($config->isEnabled());
	}

	/**
	 * Tests fromArray throws exception when neither command nor url is provided.
	 */
	public function test_fromArray_withMissingCommandAndUrl_throwsException(): void
	{
		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('mcp_servers.test.command or mcp_servers.test.url');

		McpServerConfiguration::fromArray('test', []);
	}

	/**
	 * Tests fromArray throws exception when command is empty and no url.
	 */
	public function test_fromArray_withEmptyCommandAndNoUrl_throwsException(): void
	{
		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('mcp_servers.server.command or mcp_servers.server.url');

		McpServerConfiguration::fromArray('server', ['command' => '']);
	}

	// =========================================================================
	// fromArray Tests - HTTP Transport
	// =========================================================================

	/**
	 * Tests fromArray creates HTTP configuration with all fields.
	 */
	public function test_fromArray_http_withAllFields_createsConfiguration(): void
	{
		$array = [
			'url' => 'https://api.example.com/mcp/v1',
			'headers' => ['X-API-Key' => 'secret123'],
			'bearer_token' => 'my-bearer-token',
			'timeout' => 120.0,
			'enabled' => true,
		];

		$config = McpServerConfiguration::fromArray('api-server', $array);

		$this->assertSame('api-server', $config->getName());
		$this->assertTrue($config->isHttpTransport());
		$this->assertSame('https://api.example.com/mcp/v1', $config->getUrl());
		$this->assertSame(['X-API-Key' => 'secret123'], $config->getHeaders());
		$this->assertSame('my-bearer-token', $config->getBearerToken());
		$this->assertSame(120.0, $config->getTimeout());
		$this->assertTrue($config->isEnabled());
	}

	/**
	 * Tests fromArray creates HTTP configuration with minimal fields.
	 */
	public function test_fromArray_http_withMinimalFields_createsConfiguration(): void
	{
		$array = [
			'url' => 'https://api.example.com/mcp',
		];

		$config = McpServerConfiguration::fromArray('minimal-http', $array);

		$this->assertSame('minimal-http', $config->getName());
		$this->assertTrue($config->isHttpTransport());
		$this->assertSame('https://api.example.com/mcp', $config->getUrl());
		$this->assertSame([], $config->getHeaders());
		$this->assertNull($config->getBearerToken());
		$this->assertSame(30.0, $config->getTimeout());
		$this->assertTrue($config->isEnabled());
	}

	/**
	 * Tests fromArray prefers URL over command (HTTP wins).
	 */
	public function test_fromArray_withBothUrlAndCommand_prefersUrl(): void
	{
		$array = [
			'url' => 'https://api.example.com/mcp',
			'command' => 'some-command',
		];

		$config = McpServerConfiguration::fromArray('mixed', $array);

		$this->assertTrue($config->isHttpTransport());
		$this->assertSame('https://api.example.com/mcp', $config->getUrl());
	}

	// =========================================================================
	// isEnabled Tests
	// =========================================================================

	/**
	 * Tests isEnabled returns true by default.
	 */
	public function test_isEnabled_byDefault_returnsTrue(): void
	{
		$config = new McpServerConfiguration('server', McpServerConfiguration::TRANSPORT_STDIO, 'command');

		$this->assertTrue($config->isEnabled());
	}

	/**
	 * Tests isEnabled returns false when disabled.
	 */
	public function test_isEnabled_whenDisabled_returnsFalse(): void
	{
		$config = new McpServerConfiguration(
			'server',
			McpServerConfiguration::TRANSPORT_STDIO,
			'command',
			[],
			null,
			'',
			[],
			null,
			30.0,
			false
		);

		$this->assertFalse($config->isEnabled());
	}

	// =========================================================================
	// isValid Tests
	// =========================================================================

	/**
	 * Tests isValid returns true for valid stdio configuration.
	 */
	public function test_isValid_stdio_withValidConfiguration_returnsTrue(): void
	{
		$config = new McpServerConfiguration('server', McpServerConfiguration::TRANSPORT_STDIO, 'command');

		$this->assertTrue($config->isValid());
	}

	/**
	 * Tests isValid returns true for valid HTTP configuration.
	 */
	public function test_isValid_http_withValidConfiguration_returnsTrue(): void
	{
		$config = new McpServerConfiguration(
			'server',
			McpServerConfiguration::TRANSPORT_HTTP,
			'',
			[],
			null,
			'https://api.example.com/mcp'
		);

		$this->assertTrue($config->isValid());
	}

	/**
	 * Tests isValid returns false when name is empty.
	 */
	public function test_isValid_withEmptyName_returnsFalse(): void
	{
		$config = new McpServerConfiguration('', McpServerConfiguration::TRANSPORT_STDIO, 'command');

		$this->assertFalse($config->isValid());
	}

	/**
	 * Tests isValid returns false when stdio command is empty.
	 */
	public function test_isValid_stdio_withEmptyCommand_returnsFalse(): void
	{
		$config = new McpServerConfiguration('server', McpServerConfiguration::TRANSPORT_STDIO, '');

		$this->assertFalse($config->isValid());
	}

	/**
	 * Tests isValid returns false when HTTP url is empty.
	 */
	public function test_isValid_http_withEmptyUrl_returnsFalse(): void
	{
		$config = new McpServerConfiguration('server', McpServerConfiguration::TRANSPORT_HTTP);

		$this->assertFalse($config->isValid());
	}

	// =========================================================================
	// toArray Tests - Stdio
	// =========================================================================

	/**
	 * Tests toArray returns correct array for stdio transport.
	 */
	public function test_toArray_stdio_returnsCorrectArray(): void
	{
		$config = new McpServerConfiguration(
			'test-server',
			McpServerConfiguration::TRANSPORT_STDIO,
			'node',
			['index.js'],
			['DEBUG' => '1'],
			'',
			[],
			null,
			45.0,
			true
		);

		$expected = [
			'name' => 'test-server',
			'transport' => 'stdio',
			'timeout' => 45.0,
			'enabled' => true,
			'command' => 'node',
			'args' => ['index.js'],
			'env' => ['DEBUG' => '1'],
		];

		$this->assertSame($expected, $config->toArray());
	}

	/**
	 * Tests toArray excludes env when null for stdio.
	 */
	public function test_toArray_stdio_withNullEnv_excludesEnv(): void
	{
		$config = new McpServerConfiguration(
			'server',
			McpServerConfiguration::TRANSPORT_STDIO,
			'cmd'
		);

		$array = $config->toArray();

		$this->assertArrayNotHasKey('env', $array);
	}

	// =========================================================================
	// toArray Tests - HTTP
	// =========================================================================

	/**
	 * Tests toArray returns correct array for HTTP transport.
	 */
	public function test_toArray_http_returnsCorrectArray(): void
	{
		$config = new McpServerConfiguration(
			'wordpress',
			McpServerConfiguration::TRANSPORT_HTTP,
			'',
			[],
			null,
			'https://api.example.com/mcp',
			['X-Custom' => 'value'],
			'my-token',
			90.0,
			true
		);

		$expected = [
			'name' => 'wordpress',
			'transport' => 'http',
			'timeout' => 90.0,
			'enabled' => true,
			'url' => 'https://api.example.com/mcp',
			'headers' => ['X-Custom' => 'value'],
			'bearer_token' => 'my-token',
		];

		$this->assertSame($expected, $config->toArray());
	}

	/**
	 * Tests toArray excludes headers when empty for HTTP.
	 */
	public function test_toArray_http_withEmptyHeaders_excludesHeaders(): void
	{
		$config = new McpServerConfiguration(
			'server',
			McpServerConfiguration::TRANSPORT_HTTP,
			'',
			[],
			null,
			'https://api.example.com/mcp'
		);

		$array = $config->toArray();

		$this->assertArrayNotHasKey('headers', $array);
		$this->assertArrayNotHasKey('bearer_token', $array);
	}

	/**
	 * Tests toArray includes enabled field.
	 */
	public function test_toArray_includesEnabledField(): void
	{
		$enabled_config = new McpServerConfiguration(
			's1',
			McpServerConfiguration::TRANSPORT_STDIO,
			'c1'
		);
		$disabled_config = new McpServerConfiguration(
			's2',
			McpServerConfiguration::TRANSPORT_STDIO,
			'c2',
			[],
			null,
			'',
			[],
			null,
			30.0,
			false
		);

		$this->assertTrue($enabled_config->toArray()['enabled']);
		$this->assertFalse($disabled_config->toArray()['enabled']);
	}

	// =========================================================================
	// Roundtrip Tests
	// =========================================================================

	/**
	 * Tests roundtrip through fromArray and toArray for stdio.
	 */
	public function test_fromArrayToArray_stdio_roundtrip(): void
	{
		$original = [
			'command' => 'python',
			'args' => ['-m', 'mcp_server'],
			'env' => ['PYTHONPATH' => '/custom'],
			'timeout' => 60.0,
			'enabled' => true,
		];

		$config = McpServerConfiguration::fromArray('roundtrip', $original);
		$result = $config->toArray();

		$this->assertSame('roundtrip', $result['name']);
		$this->assertSame('stdio', $result['transport']);
		$this->assertSame('python', $result['command']);
		$this->assertSame(['-m', 'mcp_server'], $result['args']);
		$this->assertSame(['PYTHONPATH' => '/custom'], $result['env']);
		$this->assertSame(60.0, $result['timeout']);
		$this->assertTrue($result['enabled']);
	}

	/**
	 * Tests roundtrip through fromArray and toArray for HTTP.
	 */
	public function test_fromArrayToArray_http_roundtrip(): void
	{
		$original = [
			'url' => 'https://api.example.com/mcp',
			'headers' => ['Authorization' => 'Bearer xyz'],
			'bearer_token' => 'my-token',
			'timeout' => 120.0,
			'enabled' => false,
		];

		$config = McpServerConfiguration::fromArray('http-roundtrip', $original);
		$result = $config->toArray();

		$this->assertSame('http-roundtrip', $result['name']);
		$this->assertSame('http', $result['transport']);
		$this->assertSame('https://api.example.com/mcp', $result['url']);
		$this->assertSame(['Authorization' => 'Bearer xyz'], $result['headers']);
		$this->assertSame('my-token', $result['bearer_token']);
		$this->assertSame(120.0, $result['timeout']);
		$this->assertFalse($result['enabled']);
	}
}
