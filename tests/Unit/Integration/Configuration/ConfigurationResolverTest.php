<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Integration\Configuration;

use Automattic\WpAiAgent\Core\Contracts\ConfigurationInterface;
use Automattic\WpAiAgent\Integration\Configuration\ConfigurationResolver;
use Automattic\WpAiAgent\Integration\Configuration\EnvConfigurationLoader;
use Automattic\WpAiAgent\Integration\Configuration\JsonConfigurationLoader;
use Automattic\WpAiAgent\Integration\Configuration\McpJsonLoader;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ConfigurationResolver.
 *
 * Tests the configuration priority chain:
 * 1. Environment variables (highest priority)
 * 2. .wp-ai-agent/settings.json + .wp-ai-agent/mcp.json
 * 3. Built-in defaults (lowest priority)
 *
 * @covers \Automattic\WpAiAgent\Integration\Configuration\ConfigurationResolver
 */
final class ConfigurationResolverTest extends TestCase
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

		$this->temp_dir = sys_get_temp_dir() . '/config_resolver_test_' . uniqid();
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
	 * Tests that constructor creates resolver with default loaders.
	 */
	public function test_constructor_withDefaults_createsResolver(): void
	{
		$resolver = new ConfigurationResolver();

		$this->assertInstanceOf(ConfigurationResolver::class, $resolver);
	}

	/**
	 * Tests that resolve returns defaults when no configuration files exist.
	 */
	public function test_resolve_withNoFiles_returnsDefaultConfiguration(): void
	{
		$env_provider = fn(string $name) => $name === 'ANTHROPIC_API_KEY' ? 'test-key' : false;
		$resolver = $this->createResolverWithEnv($env_provider);

		$config = $resolver->resolve($this->temp_dir);

		$this->assertInstanceOf(ConfigurationInterface::class, $config);
		$this->assertSame('claude-sonnet-4-20250514', $config->getModel());
		$this->assertSame(8192, $config->getMaxTokens());
		$this->assertSame(100, $config->getMaxIterations());
	}

	/**
	 * Tests that environment variables override settings.json values.
	 *
	 * Given ANTHROPIC_API_KEY env var is set
	 * And settings.json has different api_key
	 * When configuration is resolved
	 * Then env var value is used
	 */
	public function test_resolve_withEnvVarAndSettingsJson_envVarTakesPrecedence(): void
	{
		$this->createSettingsFile([
			'provider' => [
				'api_key' => 'settings-json-key',
			],
		]);

		$env_provider = fn(string $name) => match ($name) {
			'ANTHROPIC_API_KEY' => 'env-var-key',
			default => false,
		};

		$resolver = $this->createResolverWithEnv($env_provider);

		$config = $resolver->resolve($this->temp_dir);

		$this->assertSame('env-var-key', $config->getApiKey());
	}

	/**
	 * Tests that AGENT_MODEL env var overrides settings.json model.
	 */
	public function test_resolve_withAgentModelEnvVar_envVarOverridesSettingsJson(): void
	{
		$this->createSettingsFile([
			'provider' => [
				'model' => 'claude-opus',
			],
		]);

		$env_provider = fn(string $name) => match ($name) {
			'ANTHROPIC_API_KEY' => 'test-key',
			'AGENT_MODEL' => 'claude-haiku',
			default => false,
		};

		$resolver = $this->createResolverWithEnv($env_provider);

		$config = $resolver->resolve($this->temp_dir);

		$this->assertSame('claude-haiku', $config->getModel());
	}

	/**
	 * Tests that AGENT_MAX_TOKENS env var overrides settings.json.
	 */
	public function test_resolve_withAgentMaxTokensEnvVar_envVarOverridesSettingsJson(): void
	{
		$this->createSettingsFile([
			'provider' => [
				'max_tokens' => 4096,
			],
		]);

		$env_provider = fn(string $name) => match ($name) {
			'ANTHROPIC_API_KEY' => 'test-key',
			'AGENT_MAX_TOKENS' => '16384',
			default => false,
		};

		$resolver = $this->createResolverWithEnv($env_provider);

		$config = $resolver->resolve($this->temp_dir);

		$this->assertSame(16384, $config->getMaxTokens());
	}

	/**
	 * Tests that AGENT_DEBUG env var overrides settings.json.
	 */
	public function test_resolve_withAgentDebugEnvVar_envVarOverridesSettingsJson(): void
	{
		$this->createSettingsFile([
			'debug' => false,
		]);

		$env_provider = fn(string $name) => match ($name) {
			'ANTHROPIC_API_KEY' => 'test-key',
			'AGENT_DEBUG' => '1',
			default => false,
		};

		$resolver = $this->createResolverWithEnv($env_provider);

		$config = $resolver->resolve($this->temp_dir);

		$this->assertTrue($config->isDebugEnabled());
	}

	/**
	 * Tests that only settings.json is used when it exists.
	 */
	public function test_resolve_withOnlySettingsJson_usesSettingsJsonValues(): void
	{
		$this->createSettingsFile([
			'provider' => [
				'model' => 'settings-model',
			],
			'max_turns' => 60,
		]);

		$env_provider = fn(string $name) => match ($name) {
			'ANTHROPIC_API_KEY' => 'test-key',
			default => false,
		};

		$resolver = $this->createResolverWithEnv($env_provider);

		$config = $resolver->resolve($this->temp_dir);

		$this->assertSame('settings-model', $config->getModel());
		$this->assertSame(60, $config->getMaxIterations());
	}

	/**
	 * Tests that MCP servers from mcp.json are used.
	 */
	public function test_resolve_withMcpJson_loadsMcpServers(): void
	{
		$this->createMcpJsonFile([
			'mcpServers' => [
				'filesystem' => [
					'command' => 'npx',
					'args' => ['-y', '@modelcontextprotocol/server-filesystem'],
					'enabled' => true,
				],
			],
		]);

		$env_provider = fn(string $name) => match ($name) {
			'ANTHROPIC_API_KEY' => 'test-key',
			default => false,
		};

		$resolver = $this->createResolverWithEnv($env_provider);

		$mcp_servers = $resolver->getMcpServers($this->temp_dir);

		$this->assertCount(1, $mcp_servers);
		$this->assertSame('filesystem', $mcp_servers[0]->getName());
	}

	/**
	 * Tests that getConfigurationSources returns correct source information.
	 */
	public function test_getConfigurationSources_returnsSourceInformation(): void
	{
		$this->createSettingsFile([
			'provider' => [
				'model' => 'settings-model',
			],
		]);

		$env_provider = fn(string $name) => match ($name) {
			'ANTHROPIC_API_KEY' => 'test-key',
			'AGENT_DEBUG' => '1',
			default => false,
		};

		$resolver = $this->createResolverWithEnv($env_provider);
		$resolver->resolve($this->temp_dir);

		$sources = $resolver->getConfigurationSources();

		$this->assertArrayHasKey('api_key', $sources);
		$this->assertSame('env', $sources['api_key']);
		$this->assertArrayHasKey('model', $sources);
		$this->assertSame('json', $sources['model']);
		$this->assertArrayHasKey('debug', $sources);
		$this->assertSame('env', $sources['debug']);
	}

	/**
	 * Tests that configuration values from defaults are tracked correctly.
	 */
	public function test_getConfigurationSources_tracksDefaultValues(): void
	{
		$env_provider = fn(string $name) => match ($name) {
			'ANTHROPIC_API_KEY' => 'test-key',
			default => false,
		};

		$resolver = $this->createResolverWithEnv($env_provider);
		$resolver->resolve($this->temp_dir);

		$sources = $resolver->getConfigurationSources();

		$this->assertArrayHasKey('model', $sources);
		$this->assertSame('default', $sources['model']);
	}

	/**
	 * Tests that all environment variable overrides work correctly.
	 */
	public function test_resolve_withAllEnvVarOverrides_appliesAllOverrides(): void
	{
		$this->createSettingsFile([
			'provider' => [
				'model' => 'settings-model',
				'max_tokens' => 1000,
			],
			'max_turns' => 10,
			'debug' => false,
			'streaming' => false,
		]);

		$env_provider = fn(string $name) => match ($name) {
			'ANTHROPIC_API_KEY' => 'env-key',
			'AGENT_MODEL' => 'env-model',
			'AGENT_MAX_TOKENS' => '2000',
			'AGENT_MAX_ITERATIONS' => '20',
			'AGENT_DEBUG' => '1',
			'AGENT_STREAMING' => '1',
			'AGENT_TEMPERATURE' => '0.5',
			default => false,
		};

		$resolver = $this->createResolverWithEnv($env_provider);

		$config = $resolver->resolve($this->temp_dir);

		$this->assertSame('env-key', $config->getApiKey());
		$this->assertSame('env-model', $config->getModel());
		$this->assertSame(2000, $config->getMaxTokens());
		$this->assertSame(20, $config->getMaxIterations());
		$this->assertTrue($config->isDebugEnabled());
		$this->assertTrue($config->isStreamingEnabled());
		$this->assertSame(0.5, $config->getTemperature());
	}

	/**
	 * Tests that bypassed tools from settings.json are loaded.
	 */
	public function test_resolve_withBypassedTools_loadsFromSettingsJson(): void
	{
		$this->createSettingsFile([
			'permissions' => [
				'allow' => ['think', 'read_file', 'glob'],
			],
		]);

		$env_provider = fn(string $name) => match ($name) {
			'ANTHROPIC_API_KEY' => 'test-key',
			default => false,
		};

		$resolver = $this->createResolverWithEnv($env_provider);

		$config = $resolver->resolve($this->temp_dir);

		$bypassed = $config->getBypassedTools();
		$this->assertContains('think', $bypassed);
		$this->assertContains('read_file', $bypassed);
		$this->assertContains('glob', $bypassed);
	}

	/**
	 * Tests that session storage path is resolved correctly.
	 */
	public function test_resolve_withSessionStoragePath_expandsTilde(): void
	{
		$this->createSettingsFile([
			'session_storage_path' => '~/.wp-ai-agent/sessions',
		]);

		$env_provider = fn(string $name) => match ($name) {
			'ANTHROPIC_API_KEY' => 'test-key',
			'HOME' => '/home/testuser',
			default => false,
		};

		$resolver = $this->createResolverWithEnv($env_provider);

		$config = $resolver->resolve($this->temp_dir);

		$this->assertSame('/home/testuser/.wp-ai-agent/sessions', $config->getSessionStoragePath());
	}

	/**
	 * Tests that AGENT_SESSION_PATH env var overrides settings.json.
	 */
	public function test_resolve_withAgentSessionPathEnvVar_overridesSettingsJson(): void
	{
		$this->createSettingsFile([
			'session_storage_path' => '/settings/sessions',
		]);

		$env_provider = fn(string $name) => match ($name) {
			'ANTHROPIC_API_KEY' => 'test-key',
			'AGENT_SESSION_PATH' => '/env/sessions',
			default => false,
		};

		$resolver = $this->createResolverWithEnv($env_provider);

		$config = $resolver->resolve($this->temp_dir);

		$this->assertSame('/env/sessions', $config->getSessionStoragePath());
	}

	/**
	 * Creates a resolver with a custom environment provider.
	 *
	 * @param callable $env_provider The environment provider function.
	 *
	 * @return ConfigurationResolver
	 */
	private function createResolverWithEnv(callable $env_provider): ConfigurationResolver
	{
		$env_loader = new EnvConfigurationLoader(false, $env_provider);

		return new ConfigurationResolver(
			new JsonConfigurationLoader($env_loader),
			new McpJsonLoader($env_loader),
			$env_provider
		);
	}

	/**
	 * Creates a settings.json file in the temp directory.
	 *
	 * @param array<string, mixed> $content The JSON content as an array.
	 */
	private function createSettingsFile(array $content): void
	{
		$settings_dir = $this->temp_dir . '/.wp-ai-agent';
		if (! is_dir($settings_dir)) {
			mkdir($settings_dir, 0755, true);
		}
		file_put_contents(
			$settings_dir . '/settings.json',
			json_encode($content, JSON_PRETTY_PRINT)
		);
	}

	/**
	 * Creates an mcp.json file in the temp directory.
	 *
	 * @param array<string, mixed> $content The JSON content as an array.
	 */
	private function createMcpJsonFile(array $content): void
	{
		$settings_dir = $this->temp_dir . '/.wp-ai-agent';
		if (! is_dir($settings_dir)) {
			mkdir($settings_dir, 0755, true);
		}
		file_put_contents(
			$settings_dir . '/mcp.json',
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
