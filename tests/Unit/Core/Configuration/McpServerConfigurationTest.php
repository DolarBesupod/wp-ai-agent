<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Core\Configuration;

use PhpCliAgent\Core\Configuration\McpServerConfiguration;
use PhpCliAgent\Core\Exceptions\ConfigurationException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for McpServerConfiguration (Core layer).
 *
 * @covers \PhpCliAgent\Core\Configuration\McpServerConfiguration
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
			false
		);

		$this->assertSame('test-server', $config->getName());
		$this->assertSame('node', $config->getCommand());
		$this->assertSame(['server.js'], $config->getArgs());
		$this->assertSame(['API_KEY' => 'secret'], $config->getEnv());
		$this->assertFalse($config->isEnabled());
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
		$this->assertTrue($config->isEnabled());
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
			'enabled' => false,
		];

		$config = McpServerConfiguration::fromArray('filesystem', $array);

		$this->assertSame('filesystem', $config->getName());
		$this->assertSame('npx', $config->getCommand());
		$this->assertSame(['-y', '@modelcontextprotocol/server-filesystem'], $config->getArgs());
		$this->assertSame(['HOME' => '/home/user'], $config->getEnv());
		$this->assertFalse($config->isEnabled());
	}

	/**
	 * Tests fromArray handles minimal fields.
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
		$this->assertTrue($config->isEnabled());
	}

	/**
	 * Tests fromArray throws exception when command is missing.
	 */
	public function test_fromArray_withMissingCommand_throwsException(): void
	{
		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('mcp_servers.test.command');

		McpServerConfiguration::fromArray('test', []);
	}

	/**
	 * Tests fromArray throws exception when command is empty.
	 */
	public function test_fromArray_withEmptyCommand_throwsException(): void
	{
		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('mcp_servers.server.command');

		McpServerConfiguration::fromArray('server', ['command' => '']);
	}

	/**
	 * Tests isEnabled returns true by default.
	 */
	public function test_isEnabled_byDefault_returnsTrue(): void
	{
		$config = new McpServerConfiguration('server', 'command');

		$this->assertTrue($config->isEnabled());
	}

	/**
	 * Tests isEnabled returns false when disabled.
	 */
	public function test_isEnabled_whenDisabled_returnsFalse(): void
	{
		$config = new McpServerConfiguration('server', 'command', [], null, false);

		$this->assertFalse($config->isEnabled());
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
			true
		);

		$expected = [
			'name' => 'test-server',
			'command' => 'node',
			'args' => ['index.js'],
			'enabled' => true,
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
	 * Tests toArray includes enabled field.
	 */
	public function test_toArray_includesEnabledField(): void
	{
		$enabled_config = new McpServerConfiguration('s1', 'c1', [], null, true);
		$disabled_config = new McpServerConfiguration('s2', 'c2', [], null, false);

		$this->assertTrue($enabled_config->toArray()['enabled']);
		$this->assertFalse($disabled_config->toArray()['enabled']);
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
			'enabled' => true,
		];

		$config = McpServerConfiguration::fromArray('roundtrip', $original);
		$result = $config->toArray();

		$this->assertSame('roundtrip', $result['name']);
		$this->assertSame('python', $result['command']);
		$this->assertSame(['-m', 'mcp_server'], $result['args']);
		$this->assertSame(['PYTHONPATH' => '/custom'], $result['env']);
		$this->assertTrue($result['enabled']);
	}
}
