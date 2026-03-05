<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Integration\Mcp;

use Automattic\WpAiAgent\Integration\Mcp\McpServerConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for McpServerConfiguration.
 *
 * @covers \Automattic\WpAiAgent\Integration\Mcp\McpServerConfiguration
 */
final class McpServerConfigurationTest extends TestCase
{
	/**
	 * Tests stdio factory creates configuration correctly.
	 */
	public function test_stdio_createsStdioConfiguration(): void
	{
		$config = McpServerConfiguration::stdio(
			'test-server',
			'node',
			['server.js'],
			['API_KEY' => 'secret'],
			60.0
		);

		$this->assertSame('test-server', $config->getName());
		$this->assertSame('node', $config->getCommand());
		$this->assertSame(['server.js'], $config->getArgs());
		$this->assertSame(['API_KEY' => 'secret'], $config->getEnv());
		$this->assertSame(60.0, $config->getTimeout());
		$this->assertTrue($config->isStdioTransport());
		$this->assertFalse($config->isHttpTransport());
	}

	/**
	 * Tests stdio factory uses defaults for optional parameters.
	 */
	public function test_stdio_usesDefaults(): void
	{
		$config = McpServerConfiguration::stdio('test-server', 'python');

		$this->assertSame('test-server', $config->getName());
		$this->assertSame('python', $config->getCommand());
		$this->assertSame([], $config->getArgs());
		$this->assertNull($config->getEnv());
		$this->assertSame(30.0, $config->getTimeout());
	}

	/**
	 * Tests http factory creates configuration correctly.
	 */
	public function test_http_createsHttpConfiguration(): void
	{
		$config = McpServerConfiguration::http(
			'wordpress',
			'https://example.com/mcp',
			['X-Custom' => 'header'],
			null,
			45.0
		);

		$this->assertSame('wordpress', $config->getName());
		$this->assertSame('https://example.com/mcp', $config->getUrl());
		$this->assertSame(['X-Custom' => 'header'], $config->getHeaders());
		$this->assertSame(45.0, $config->getTimeout());
		$this->assertTrue($config->isHttpTransport());
		$this->assertFalse($config->isStdioTransport());
	}

	/**
	 * Tests http factory with bearer token.
	 */
	public function test_http_withBearerToken_addsAuthorizationHeader(): void
	{
		$config = McpServerConfiguration::http(
			'auth-server',
			'https://api.example.com/mcp',
			[],
			'my-secret-token'
		);

		$headers = $config->getHeaders();
		$this->assertArrayHasKey('Authorization', $headers);
		$this->assertSame('Bearer my-secret-token', $headers['Authorization']);
	}

	/**
	 * Tests http factory merges bearer token with existing headers.
	 */
	public function test_http_withBearerTokenAndHeaders_mergesCorrectly(): void
	{
		$config = McpServerConfiguration::http(
			'merged-server',
			'https://api.example.com/mcp',
			['X-Custom' => 'value'],
			'token123'
		);

		$headers = $config->getHeaders();
		$this->assertSame('value', $headers['X-Custom']);
		$this->assertSame('Bearer token123', $headers['Authorization']);
	}

	/**
	 * Tests fromArray creates stdio configuration correctly with all fields.
	 */
	public function test_fromArray_withCommand_createsStdioConfiguration(): void
	{
		$array = [
			'command' => 'npx',
			'args' => ['-y', '@modelcontextprotocol/server-filesystem'],
			'env' => ['HOME' => '/home/user'],
			'timeout' => 45.0,
		];

		$config = McpServerConfiguration::fromArray('filesystem', $array);

		$this->assertSame('filesystem', $config->getName());
		$this->assertSame('npx', $config->getCommand());
		$this->assertSame(['-y', '@modelcontextprotocol/server-filesystem'], $config->getArgs());
		$this->assertSame(['HOME' => '/home/user'], $config->getEnv());
		$this->assertSame(45.0, $config->getTimeout());
		$this->assertTrue($config->isStdioTransport());
	}

	/**
	 * Tests fromArray creates http configuration when URL is provided.
	 */
	public function test_fromArray_withUrl_createsHttpConfiguration(): void
	{
		$array = [
			'url' => 'https://api.example.com/mcp/v1',
			'bearer_token' => 'secret-token',
			'timeout' => 60.0,
		];

		$config = McpServerConfiguration::fromArray('http-server', $array);

		$this->assertSame('http-server', $config->getName());
		$this->assertSame('https://api.example.com/mcp/v1', $config->getUrl());
		$this->assertSame('Bearer secret-token', $config->getHeaders()['Authorization']);
		$this->assertSame(60.0, $config->getTimeout());
		$this->assertTrue($config->isHttpTransport());
	}

	/**
	 * Tests fromArray with custom headers for HTTP.
	 */
	public function test_fromArray_withHttpHeaders_setsHeaders(): void
	{
		$array = [
			'url' => 'https://api.example.com/mcp',
			'headers' => ['X-API-Key' => 'key123'],
		];

		$config = McpServerConfiguration::fromArray('header-server', $array);

		$this->assertSame('key123', $config->getHeaders()['X-API-Key']);
	}

	/**
	 * Tests fromArray handles missing optional fields.
	 */
	public function test_fromArray_withMinimalFields_createsConfiguration(): void
	{
		$array = [
			'command' => 'my-server',
		];

		$config = McpServerConfiguration::fromArray('minimal', $array);

		$this->assertSame('minimal', $config->getName());
		$this->assertSame('my-server', $config->getCommand());
		$this->assertSame([], $config->getArgs());
		$this->assertNull($config->getEnv());
		$this->assertSame(30.0, $config->getTimeout());
	}

	/**
	 * Tests fromArray handles empty array.
	 */
	public function test_fromArray_withEmptyArray_createsConfigurationWithDefaults(): void
	{
		$config = McpServerConfiguration::fromArray('empty', []);

		$this->assertSame('empty', $config->getName());
		$this->assertSame('', $config->getCommand());
		$this->assertSame([], $config->getArgs());
		$this->assertNull($config->getEnv());
		$this->assertSame(30.0, $config->getTimeout());
	}

	/**
	 * Tests isValid returns true for valid stdio configuration.
	 */
	public function test_isValid_withValidStdioConfiguration_returnsTrue(): void
	{
		$config = McpServerConfiguration::stdio('server', 'command');

		$this->assertTrue($config->isValid());
	}

	/**
	 * Tests isValid returns true for valid http configuration.
	 */
	public function test_isValid_withValidHttpConfiguration_returnsTrue(): void
	{
		$config = McpServerConfiguration::http('server', 'https://example.com/mcp');

		$this->assertTrue($config->isValid());
	}

	/**
	 * Tests isValid returns false when name is empty.
	 */
	public function test_isValid_withEmptyName_returnsFalse(): void
	{
		$config = McpServerConfiguration::stdio('', 'command');

		$this->assertFalse($config->isValid());
	}

	/**
	 * Tests isValid returns false when command is empty for stdio.
	 */
	public function test_isValid_withEmptyCommand_returnsFalse(): void
	{
		$config = McpServerConfiguration::stdio('server', '');

		$this->assertFalse($config->isValid());
	}

	/**
	 * Tests isValid returns false when URL is empty for http.
	 */
	public function test_isValid_withEmptyUrl_returnsFalse(): void
	{
		$config = McpServerConfiguration::http('server', '');

		$this->assertFalse($config->isValid());
	}

	/**
	 * Tests toArray returns correct array for stdio transport.
	 */
	public function test_toArray_forStdio_returnsCorrectArray(): void
	{
		$config = McpServerConfiguration::stdio(
			'test-server',
			'node',
			['index.js'],
			['DEBUG' => '1'],
			45.0
		);

		$result = $config->toArray();

		$this->assertSame('test-server', $result['name']);
		$this->assertSame('stdio', $result['transport']);
		$this->assertSame('node', $result['command']);
		$this->assertSame(['index.js'], $result['args']);
		$this->assertSame(['DEBUG' => '1'], $result['env']);
		$this->assertSame(45.0, $result['timeout']);
	}

	/**
	 * Tests toArray returns correct array for http transport.
	 */
	public function test_toArray_forHttp_returnsCorrectArray(): void
	{
		$config = McpServerConfiguration::http(
			'http-server',
			'https://example.com/mcp',
			['Authorization' => 'Bearer token'],
			null,
			60.0
		);

		$result = $config->toArray();

		$this->assertSame('http-server', $result['name']);
		$this->assertSame('http', $result['transport']);
		$this->assertSame('https://example.com/mcp', $result['url']);
		$this->assertSame(['Authorization' => 'Bearer token'], $result['headers']);
		$this->assertSame(60.0, $result['timeout']);
		$this->assertArrayNotHasKey('command', $result);
	}

	/**
	 * Tests toArray excludes env when null for stdio.
	 */
	public function test_toArray_withNullEnv_excludesEnv(): void
	{
		$config = McpServerConfiguration::stdio('server', 'cmd', [], null);

		$array = $config->toArray();

		$this->assertArrayNotHasKey('env', $array);
	}

	/**
	 * Tests toArray excludes headers when empty for http.
	 */
	public function test_toArray_withEmptyHeaders_excludesHeaders(): void
	{
		$config = McpServerConfiguration::http('server', 'https://example.com/mcp');

		$array = $config->toArray();

		$this->assertArrayNotHasKey('headers', $array);
	}

	/**
	 * Tests getTransport returns correct transport type.
	 */
	public function test_getTransport_returnsCorrectType(): void
	{
		$stdio = McpServerConfiguration::stdio('s', 'cmd');
		$http = McpServerConfiguration::http('h', 'https://example.com');

		$this->assertSame('stdio', $stdio->getTransport());
		$this->assertSame('http', $http->getTransport());
	}

	/**
	 * Tests roundtrip through fromArray and toArray for stdio.
	 */
	public function test_fromArrayToArray_roundtrip_stdio(): void
	{
		$original = [
			'command' => 'python',
			'args' => ['-m', 'mcp_server'],
			'env' => ['PYTHONPATH' => '/custom'],
			'timeout' => 120,
		];

		$config = McpServerConfiguration::fromArray('roundtrip', $original);
		$result = $config->toArray();

		$this->assertSame('roundtrip', $result['name']);
		$this->assertSame('python', $result['command']);
		$this->assertSame(['-m', 'mcp_server'], $result['args']);
		$this->assertSame(['PYTHONPATH' => '/custom'], $result['env']);
		$this->assertSame(120.0, $result['timeout']);
	}

	/**
	 * Tests roundtrip through fromArray and toArray for http.
	 */
	public function test_fromArrayToArray_roundtrip_http(): void
	{
		$original = [
			'url' => 'https://api.example.com/mcp',
			'bearer_token' => 'secret',
			'timeout' => 90,
		];

		$config = McpServerConfiguration::fromArray('http-roundtrip', $original);
		$result = $config->toArray();

		$this->assertSame('http-roundtrip', $result['name']);
		$this->assertSame('https://api.example.com/mcp', $result['url']);
		$this->assertSame('Bearer secret', $result['headers']['Authorization']);
		$this->assertSame(90.0, $result['timeout']);
	}
}
