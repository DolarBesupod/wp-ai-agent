<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Integration\Mcp;

use PhpCliAgent\Integration\Mcp\McpServerConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for McpServerConfiguration.
 *
 * @covers \PhpCliAgent\Integration\Mcp\McpServerConfiguration
 */
final class McpServerConfigurationTest extends TestCase
{
	/**
	 * Tests constructor sets all properties correctly.
	 */
	public function test_constructor_setsAllProperties(): void
	{
		$config = new McpServerConfiguration(
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
	}

	/**
	 * Tests constructor uses defaults for optional parameters.
	 */
	public function test_constructor_usesDefaults(): void
	{
		$config = new McpServerConfiguration('test-server', 'python');

		$this->assertSame('test-server', $config->getName());
		$this->assertSame('python', $config->getCommand());
		$this->assertSame([], $config->getArgs());
		$this->assertNull($config->getEnv());
		$this->assertSame(30.0, $config->getTimeout());
	}

	/**
	 * Tests fromArray creates configuration correctly with all fields.
	 */
	public function test_fromArray_withAllFields_createsConfiguration(): void
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
	 * Tests isValid returns true for valid configuration.
	 */
	public function test_isValid_withValidConfiguration_returnsTrue(): void
	{
		$config = new McpServerConfiguration('server', 'command');

		$this->assertTrue($config->isValid());
	}

	/**
	 * Tests isValid returns false when name is empty.
	 */
	public function test_isValid_withEmptyName_returnsFalse(): void
	{
		$config = new McpServerConfiguration('', 'command');

		$this->assertFalse($config->isValid());
	}

	/**
	 * Tests isValid returns false when command is empty.
	 */
	public function test_isValid_withEmptyCommand_returnsFalse(): void
	{
		$config = new McpServerConfiguration('server', '');

		$this->assertFalse($config->isValid());
	}

	/**
	 * Tests isValid returns false when both name and command are empty.
	 */
	public function test_isValid_withBothEmpty_returnsFalse(): void
	{
		$config = new McpServerConfiguration('', '');

		$this->assertFalse($config->isValid());
	}

	/**
	 * Tests toArray returns correct array representation.
	 */
	public function test_toArray_returnsCorrectArray(): void
	{
		$config = new McpServerConfiguration(
			'test-server',
			'node',
			['index.js'],
			['DEBUG' => '1'],
			45.0
		);

		$expected = [
			'name' => 'test-server',
			'command' => 'node',
			'args' => ['index.js'],
			'timeout' => 45.0,
			'env' => ['DEBUG' => '1'],
		];

		$this->assertSame($expected, $config->toArray());
	}

	/**
	 * Tests toArray excludes env when null.
	 */
	public function test_toArray_withNullEnv_excludesEnv(): void
	{
		$config = new McpServerConfiguration('server', 'cmd', [], null);

		$array = $config->toArray();

		$this->assertArrayNotHasKey('env', $array);
	}

	/**
	 * Tests roundtrip through fromArray and toArray.
	 */
	public function test_fromArrayToArray_roundtrip(): void
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
}
