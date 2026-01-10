<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Core\Configuration;

use PhpCliAgent\Core\Configuration\AgentConfiguration;
use PhpCliAgent\Core\Configuration\McpServerConfiguration;
use PhpCliAgent\Core\Configuration\ProviderConfiguration;
use PhpCliAgent\Core\Exceptions\ConfigurationException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AgentConfiguration.
 *
 * @covers \PhpCliAgent\Core\Configuration\AgentConfiguration
 */
final class AgentConfigurationTest extends TestCase
{
	/**
	 * Tests constructor sets all properties correctly.
	 */
	public function test_constructor_setsAllProperties(): void
	{
		$provider = new ProviderConfiguration('anthropic', 'test-key');
		$mcp_servers = [
			new McpServerConfiguration('server1', 'node'),
		];

		$config = new AgentConfiguration(
			$provider,
			$mcp_servers,
			'/custom/sessions',
			'/custom/logs',
			50,
			'You are a helpful assistant.',
			['think', 'read_file']
		);

		$this->assertSame($provider, $config->getProvider());
		$this->assertSame($mcp_servers, $config->getMcpServers());
		$this->assertSame('/custom/sessions', $config->getSessionStoragePath());
		$this->assertSame('/custom/logs', $config->getLogPath());
		$this->assertSame(50, $config->getMaxTurns());
		$this->assertSame('You are a helpful assistant.', $config->getDefaultSystemPrompt());
		$this->assertSame(['think', 'read_file'], $config->getBypassConfirmationTools());
	}

	/**
	 * Tests constructor uses defaults for optional parameters.
	 */
	public function test_constructor_usesDefaults(): void
	{
		$provider = new ProviderConfiguration('anthropic', 'test-key');

		$config = new AgentConfiguration($provider);

		$this->assertSame($provider, $config->getProvider());
		$this->assertSame([], $config->getMcpServers());
		$this->assertSame('~/.php-cli-agent/sessions', $config->getSessionStoragePath());
		$this->assertSame('~/.php-cli-agent/logs', $config->getLogPath());
		$this->assertSame(100, $config->getMaxTurns());
		$this->assertSame('', $config->getDefaultSystemPrompt());
		$this->assertSame([], $config->getBypassConfirmationTools());
	}

	/**
	 * Tests fromArray creates configuration correctly with all fields.
	 */
	public function test_fromArray_withAllFields_createsConfiguration(): void
	{
		$array = [
			'provider' => [
				'type' => 'anthropic',
				'api_key' => 'sk-test',
				'model' => 'claude-opus-4-20250514',
				'max_tokens' => 16384,
			],
			'mcp_servers' => [
				'filesystem' => [
					'command' => 'npx',
					'args' => ['-y', '@modelcontextprotocol/server-filesystem'],
				],
				'sqlite' => [
					'command' => 'uvx',
					'args' => ['mcp-server-sqlite', 'test.db'],
					'enabled' => false,
				],
			],
			'session_storage_path' => '/var/sessions',
			'log_path' => '/var/logs',
			'max_turns' => 200,
			'default_system_prompt' => 'Custom prompt',
			'bypass_confirmation_tools' => ['think'],
		];

		$config = AgentConfiguration::fromArray($array);

		$this->assertSame('anthropic', $config->getProvider()->getType());
		$this->assertSame('sk-test', $config->getProvider()->getApiKey());
		$this->assertCount(2, $config->getMcpServers());
		$this->assertSame('/var/sessions', $config->getSessionStoragePath());
		$this->assertSame('/var/logs', $config->getLogPath());
		$this->assertSame(200, $config->getMaxTurns());
		$this->assertSame('Custom prompt', $config->getDefaultSystemPrompt());
		$this->assertSame(['think'], $config->getBypassConfirmationTools());
	}

	/**
	 * Tests fromArray with minimal fields uses defaults.
	 */
	public function test_fromArray_withMinimalFields_createsConfiguration(): void
	{
		$array = [
			'provider' => [
				'api_key' => 'minimal-key',
			],
		];

		$config = AgentConfiguration::fromArray($array);

		$this->assertSame('anthropic', $config->getProvider()->getType());
		$this->assertSame('minimal-key', $config->getProvider()->getApiKey());
		$this->assertSame([], $config->getMcpServers());
		$this->assertSame('~/.php-cli-agent/sessions', $config->getSessionStoragePath());
		$this->assertSame('~/.php-cli-agent/logs', $config->getLogPath());
		$this->assertSame(100, $config->getMaxTurns());
	}

	/**
	 * Tests fromArray throws exception when provider is missing.
	 */
	public function test_fromArray_withMissingProvider_throwsException(): void
	{
		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('provider');

		AgentConfiguration::fromArray([]);
	}

	/**
	 * Tests fromArray throws exception when provider is not an array.
	 */
	public function test_fromArray_withInvalidProvider_throwsException(): void
	{
		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('provider');

		AgentConfiguration::fromArray(['provider' => 'invalid']);
	}

	/**
	 * Tests fromArray throws exception when api_key is missing.
	 */
	public function test_fromArray_withMissingApiKey_throwsException(): void
	{
		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('api_key');

		AgentConfiguration::fromArray([
			'provider' => [
				'type' => 'anthropic',
			],
		]);
	}

	/**
	 * Tests getEnabledMcpServers returns only enabled servers.
	 */
	public function test_getEnabledMcpServers_returnsOnlyEnabled(): void
	{
		$provider = new ProviderConfiguration('anthropic', 'key');
		$mcp_servers = [
			new McpServerConfiguration('enabled1', 'cmd1', [], null, true),
			new McpServerConfiguration('disabled1', 'cmd2', [], null, false),
			new McpServerConfiguration('enabled2', 'cmd3', [], null, true),
			new McpServerConfiguration('disabled2', 'cmd4', [], null, false),
		];

		$config = new AgentConfiguration($provider, $mcp_servers);

		$enabled = $config->getEnabledMcpServers();

		$this->assertCount(2, $enabled);
		$this->assertSame('enabled1', $enabled[0]->getName());
		$this->assertSame('enabled2', $enabled[1]->getName());
	}

	/**
	 * Tests getExpandedSessionStoragePath expands tilde.
	 */
	public function test_getExpandedSessionStoragePath_expandsTilde(): void
	{
		$provider = new ProviderConfiguration('anthropic', 'key');
		$config = new AgentConfiguration($provider, [], '~/.php-cli-agent/sessions');

		$home = getenv('HOME') ?: getenv('USERPROFILE');
		if ($home !== false) {
			$expected = $home . '/.php-cli-agent/sessions';
			$this->assertSame($expected, $config->getExpandedSessionStoragePath());
		} else {
			$this->assertSame('~/.php-cli-agent/sessions', $config->getExpandedSessionStoragePath());
		}
	}

	/**
	 * Tests getExpandedLogPath expands tilde.
	 */
	public function test_getExpandedLogPath_expandsTilde(): void
	{
		$provider = new ProviderConfiguration('anthropic', 'key');
		$config = new AgentConfiguration($provider, [], '~/.php-cli-agent/sessions', '~/logs');

		$home = getenv('HOME') ?: getenv('USERPROFILE');
		if ($home !== false) {
			$expected = $home . '/logs';
			$this->assertSame($expected, $config->getExpandedLogPath());
		} else {
			$this->assertSame('~/logs', $config->getExpandedLogPath());
		}
	}

	/**
	 * Tests getExpandedSessionStoragePath returns unchanged path without tilde.
	 */
	public function test_getExpandedSessionStoragePath_withoutTilde_returnsUnchanged(): void
	{
		$provider = new ProviderConfiguration('anthropic', 'key');
		$config = new AgentConfiguration($provider, [], '/absolute/path');

		$this->assertSame('/absolute/path', $config->getExpandedSessionStoragePath());
	}

	/**
	 * Tests shouldBypassConfirmation returns true for bypassed tools.
	 */
	public function test_shouldBypassConfirmation_withBypassedTool_returnsTrue(): void
	{
		$provider = new ProviderConfiguration('anthropic', 'key');
		$config = new AgentConfiguration(
			$provider,
			[],
			'~/.php-cli-agent/sessions',
			'~/.php-cli-agent/logs',
			100,
			'',
			['think', 'read_file']
		);

		$this->assertTrue($config->shouldBypassConfirmation('think'));
		$this->assertTrue($config->shouldBypassConfirmation('read_file'));
	}

	/**
	 * Tests shouldBypassConfirmation returns false for non-bypassed tools.
	 */
	public function test_shouldBypassConfirmation_withNonBypassedTool_returnsFalse(): void
	{
		$provider = new ProviderConfiguration('anthropic', 'key');
		$config = new AgentConfiguration(
			$provider,
			[],
			'~/.php-cli-agent/sessions',
			'~/.php-cli-agent/logs',
			100,
			'',
			['think']
		);

		$this->assertFalse($config->shouldBypassConfirmation('bash'));
		$this->assertFalse($config->shouldBypassConfirmation('write_file'));
	}

	/**
	 * Tests isValid returns true for valid configuration.
	 */
	public function test_isValid_withValidConfiguration_returnsTrue(): void
	{
		$provider = new ProviderConfiguration('anthropic', 'key');
		$mcp_servers = [
			new McpServerConfiguration('server', 'cmd'),
		];
		$config = new AgentConfiguration($provider, $mcp_servers);

		$this->assertTrue($config->isValid());
	}

	/**
	 * Tests isValid returns false when provider is invalid.
	 */
	public function test_isValid_withInvalidProvider_returnsFalse(): void
	{
		$provider = new ProviderConfiguration('invalid', '');
		$config = new AgentConfiguration($provider);

		$this->assertFalse($config->isValid());
	}

	/**
	 * Tests isValid returns false when MCP server is invalid.
	 */
	public function test_isValid_withInvalidMcpServer_returnsFalse(): void
	{
		$provider = new ProviderConfiguration('anthropic', 'key');
		$mcp_servers = [
			new McpServerConfiguration('', 'cmd'),
		];
		$config = new AgentConfiguration($provider, $mcp_servers);

		$this->assertFalse($config->isValid());
	}

	/**
	 * Tests isValid returns false when max_turns is zero or negative.
	 */
	public function test_isValid_withZeroMaxTurns_returnsFalse(): void
	{
		$provider = new ProviderConfiguration('anthropic', 'key');
		$config = new AgentConfiguration($provider, [], '~/', '~/', 0);

		$this->assertFalse($config->isValid());
	}

	/**
	 * Tests toArray returns correct array representation.
	 */
	public function test_toArray_returnsCorrectArray(): void
	{
		$array = [
			'provider' => [
				'type' => 'anthropic',
				'api_key' => 'test-key',
				'model' => 'claude-sonnet-4-20250514',
				'max_tokens' => 8192,
			],
			'mcp_servers' => [
				'server1' => [
					'command' => 'node',
					'args' => ['index.js'],
					'enabled' => true,
				],
			],
			'session_storage_path' => '/sessions',
			'log_path' => '/logs',
			'max_turns' => 50,
			'default_system_prompt' => 'Hello',
			'bypass_confirmation_tools' => ['think'],
		];

		$config = AgentConfiguration::fromArray($array);
		$result = $config->toArray();

		$this->assertSame($array['provider'], $result['provider']);
		$this->assertSame($array['session_storage_path'], $result['session_storage_path']);
		$this->assertSame($array['log_path'], $result['log_path']);
		$this->assertSame($array['max_turns'], $result['max_turns']);
		$this->assertSame($array['default_system_prompt'], $result['default_system_prompt']);
		$this->assertSame($array['bypass_confirmation_tools'], $result['bypass_confirmation_tools']);
	}

	/**
	 * Tests toArray properly formats MCP servers.
	 */
	public function test_toArray_formatsMcpServersCorrectly(): void
	{
		$provider = new ProviderConfiguration('anthropic', 'key');
		$mcp_servers = [
			new McpServerConfiguration('fs', 'npx', ['-y', 'mcp-fs'], null, true),
			new McpServerConfiguration('db', 'uvx', ['mcp-sqlite'], ['PATH' => '/usr'], false),
		];
		$config = new AgentConfiguration($provider, $mcp_servers);

		$result = $config->toArray();

		$this->assertArrayHasKey('fs', $result['mcp_servers']);
		$this->assertArrayHasKey('db', $result['mcp_servers']);
		$this->assertArrayNotHasKey('name', $result['mcp_servers']['fs']);
		$this->assertArrayNotHasKey('name', $result['mcp_servers']['db']);
		$this->assertSame('npx', $result['mcp_servers']['fs']['command']);
		$this->assertSame('uvx', $result['mcp_servers']['db']['command']);
	}

	/**
	 * Tests roundtrip through fromArray and toArray.
	 */
	public function test_fromArrayToArray_roundtrip(): void
	{
		$original = [
			'provider' => [
				'type' => 'openai',
				'api_key' => 'sk-roundtrip',
				'model' => 'gpt-4',
				'max_tokens' => 4096,
			],
			'mcp_servers' => [
				'test' => [
					'command' => 'test-cmd',
					'args' => ['--flag'],
					'env' => ['KEY' => 'value'],
					'enabled' => true,
				],
			],
			'session_storage_path' => '/sessions',
			'log_path' => '/logs',
			'max_turns' => 75,
			'default_system_prompt' => 'Roundtrip test',
			'bypass_confirmation_tools' => ['tool1', 'tool2'],
		];

		$config = AgentConfiguration::fromArray($original);
		$result = $config->toArray();

		$this->assertSame($original['provider'], $result['provider']);
		$this->assertSame($original['session_storage_path'], $result['session_storage_path']);
		$this->assertSame($original['log_path'], $result['log_path']);
		$this->assertSame($original['max_turns'], $result['max_turns']);
		$this->assertSame($original['default_system_prompt'], $result['default_system_prompt']);
		$this->assertSame($original['bypass_confirmation_tools'], $result['bypass_confirmation_tools']);
		$this->assertSame($original['mcp_servers']['test']['command'], $result['mcp_servers']['test']['command']);
	}
}
