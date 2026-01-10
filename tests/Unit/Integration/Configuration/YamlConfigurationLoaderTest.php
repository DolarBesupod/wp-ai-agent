<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Integration\Configuration;

use PhpCliAgent\Core\Configuration\AgentConfiguration;
use PhpCliAgent\Core\Exceptions\ConfigurationException;
use PhpCliAgent\Integration\Configuration\EnvConfigurationLoader;
use PhpCliAgent\Integration\Configuration\YamlConfigurationLoader;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for YamlConfigurationLoader.
 *
 * @covers \PhpCliAgent\Integration\Configuration\YamlConfigurationLoader
 */
final class YamlConfigurationLoaderTest extends TestCase
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

		$this->temp_dir = sys_get_temp_dir() . '/yaml_loader_test_' . uniqid();
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
		$loader = new YamlConfigurationLoader();

		$this->assertInstanceOf(YamlConfigurationLoader::class, $loader);
	}

	/**
	 * Tests loading a valid YAML configuration file.
	 */
	public function test_load_withValidYamlFile_returnsAgentConfiguration(): void
	{
		$yaml_content = <<<YAML
provider:
  type: anthropic
  api_key: test-key-123
  model: claude-sonnet-4-20250514
  max_tokens: 8192

max_turns: 50
YAML;

		$config_path = $this->createTempFile('agent.yaml', $yaml_content);
		$loader = new YamlConfigurationLoader();

		$config = $loader->load($config_path, $this->temp_dir, true);

		$this->assertInstanceOf(AgentConfiguration::class, $config);
		$this->assertSame('anthropic', $config->getProvider()->getType());
		$this->assertSame('test-key-123', $config->getProvider()->getApiKey());
		$this->assertSame(50, $config->getMaxTurns());
	}

	/**
	 * Tests that environment variables are resolved in configuration.
	 */
	public function test_load_withEnvVariables_resolvesPlaceholders(): void
	{
		$yaml_content = <<<YAML
provider:
  type: anthropic
  api_key: \${TEST_API_KEY}
YAML;

		$config_path = $this->createTempFile('agent.yaml', $yaml_content);

		$env_provider = static fn(string $name) => $name === 'TEST_API_KEY' ? 'resolved-key' : false;
		$env_loader = new EnvConfigurationLoader(true, $env_provider);
		$loader = new YamlConfigurationLoader($env_loader);

		$config = $loader->load($config_path, $this->temp_dir, true);

		$this->assertSame('resolved-key', $config->getProvider()->getApiKey());
	}

	/**
	 * Tests that missing environment variable throws exception.
	 */
	public function test_load_withMissingEnvVariable_throwsException(): void
	{
		$yaml_content = <<<YAML
provider:
  api_key: \${MISSING_API_KEY}
YAML;

		$config_path = $this->createTempFile('agent.yaml', $yaml_content);

		$env_loader = new EnvConfigurationLoader(true, fn() => false);
		$loader = new YamlConfigurationLoader($env_loader);

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('MISSING_API_KEY');

		$loader->load($config_path, $this->temp_dir, true);
	}

	/**
	 * Tests that configuration files are merged correctly.
	 */
	public function test_load_withMultipleConfigs_mergesCorrectly(): void
	{
		// Create user config
		$user_config_dir = $this->temp_dir . '/.php-cli-agent';
		mkdir($user_config_dir, 0755, true);
		$user_yaml = <<<YAML
provider:
  type: anthropic
  api_key: user-key
  model: claude-opus-4-20250514

max_turns: 200
session_storage_path: /user/sessions
YAML;
		file_put_contents($user_config_dir . '/agent.yaml', $user_yaml);

		// Create project config
		$project_yaml = <<<YAML
provider:
  model: claude-sonnet-4-20250514

max_turns: 50
YAML;
		file_put_contents($this->temp_dir . '/agent.yaml', $project_yaml);

		// Create env loader that points HOME to temp dir
		$temp_dir = $this->temp_dir;
		$env_provider = static fn(string $name) => $name === 'HOME' ? $temp_dir : false;
		$env_loader = new EnvConfigurationLoader(true, $env_provider);
		$loader = new YamlConfigurationLoader($env_loader);

		$config = $loader->load(null, $this->temp_dir, false);

		// User config provides the api_key
		$this->assertSame('user-key', $config->getProvider()->getApiKey());
		// Project config overrides model and max_turns
		$this->assertSame('claude-sonnet-4-20250514', $config->getProvider()->getModel());
		$this->assertSame(50, $config->getMaxTurns());
	}

	/**
	 * Tests that explicit config path overrides all others.
	 */
	public function test_load_withExplicitPath_overridesOthers(): void
	{
		// Create project config
		$project_yaml = <<<YAML
provider:
  api_key: project-key

max_turns: 50
YAML;
		file_put_contents($this->temp_dir . '/agent.yaml', $project_yaml);

		// Create explicit override config
		$override_yaml = <<<YAML
provider:
  api_key: override-key

max_turns: 999
YAML;
		$override_path = $this->createTempFile('override.yaml', $override_yaml);

		$env_provider = static fn(string $name) => $name === 'HOME' ? '/nonexistent' : false;
		$env_loader = new EnvConfigurationLoader(true, $env_provider);
		$loader = new YamlConfigurationLoader($env_loader);

		$config = $loader->load($override_path, $this->temp_dir, true);

		$this->assertSame('override-key', $config->getProvider()->getApiKey());
		$this->assertSame(999, $config->getMaxTurns());
	}

	/**
	 * Tests loading with non-existent explicit config throws exception.
	 */
	public function test_load_withNonExistentExplicitPath_throwsException(): void
	{
		$loader = new YamlConfigurationLoader();

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('does not exist');

		$loader->load('/nonexistent/config.yaml', $this->temp_dir, true);
	}

	/**
	 * Tests loadYamlFile with a valid file.
	 */
	public function test_loadYamlFile_withValidFile_returnsArray(): void
	{
		$yaml_content = <<<YAML
key1: value1
key2:
  nested: value2
YAML;

		$file_path = $this->createTempFile('test.yaml', $yaml_content);
		$loader = new YamlConfigurationLoader();

		$result = $loader->loadYamlFile($file_path);

		$this->assertSame('value1', $result['key1']);
		$this->assertSame('value2', $result['key2']['nested']);
	}

	/**
	 * Tests loadYamlFile with non-existent file throws exception.
	 */
	public function test_loadYamlFile_withNonExistentFile_throwsException(): void
	{
		$loader = new YamlConfigurationLoader();

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('does not exist');

		$loader->loadYamlFile('/nonexistent/file.yaml');
	}

	/**
	 * Tests loadYamlFile with invalid YAML throws exception.
	 */
	public function test_loadYamlFile_withInvalidYaml_throwsException(): void
	{
		$yaml_content = 'key: "unclosed string';

		$file_path = $this->createTempFile('invalid.yaml', $yaml_content);
		$loader = new YamlConfigurationLoader();

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('parse');

		$loader->loadYamlFile($file_path);
	}

	/**
	 * Tests loadYamlFile with non-mapping root throws exception.
	 */
	public function test_loadYamlFile_withNonMappingRoot_throwsException(): void
	{
		$yaml_content = "- item1\n- item2\n";

		$file_path = $this->createTempFile('array.yaml', $yaml_content);
		$loader = new YamlConfigurationLoader();

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('mapping at the root level');

		$loader->loadYamlFile($file_path);
	}

	/**
	 * Tests getConfigSearchPaths returns expected paths.
	 */
	public function test_getConfigSearchPaths_returnsExpectedPaths(): void
	{
		$env_provider = static fn(string $name) => $name === 'HOME' ? '/home/testuser' : false;
		$env_loader = new EnvConfigurationLoader(true, $env_provider);
		$loader = new YamlConfigurationLoader($env_loader);

		$paths = $loader->getConfigSearchPaths('/project/dir');

		$this->assertContains('/home/testuser/.php-cli-agent/agent.yaml', $paths);
		$this->assertContains('/project/dir/agent.yaml', $paths);
	}

	/**
	 * Tests that MCP servers are loaded correctly.
	 */
	public function test_load_withMcpServers_parsesMcpConfig(): void
	{
		$yaml_content = <<<YAML
provider:
  api_key: test-key

mcp_servers:
  filesystem:
    command: npx
    args: ["-y", "@modelcontextprotocol/server-filesystem"]
    enabled: true
  sqlite:
    command: uvx
    args: ["mcp-server-sqlite", "test.db"]
    enabled: false
YAML;

		$config_path = $this->createTempFile('agent.yaml', $yaml_content);
		$loader = new YamlConfigurationLoader();

		$config = $loader->load($config_path, $this->temp_dir, true);

		$servers = $config->getMcpServers();
		$this->assertCount(2, $servers);

		$enabled_servers = $config->getEnabledMcpServers();
		$this->assertCount(1, $enabled_servers);
		$this->assertSame('filesystem', $enabled_servers[0]->getName());
	}

	/**
	 * Tests that bypass_confirmation_tools is loaded correctly.
	 */
	public function test_load_withBypassTools_loadsToolsList(): void
	{
		$yaml_content = <<<YAML
provider:
  api_key: test-key

bypass_confirmation_tools:
  - think
  - read_file
  - glob
YAML;

		$config_path = $this->createTempFile('agent.yaml', $yaml_content);
		$loader = new YamlConfigurationLoader();

		$config = $loader->load($config_path, $this->temp_dir, true);

		$tools = $config->getBypassConfirmationTools();
		$this->assertContains('think', $tools);
		$this->assertContains('read_file', $tools);
		$this->assertContains('glob', $tools);
	}

	/**
	 * Tests that tilde in paths is expanded.
	 */
	public function test_load_withTildeInPaths_expandsTilde(): void
	{
		$yaml_content = <<<YAML
provider:
  api_key: test-key

session_storage_path: ~/.php-cli-agent/sessions
log_path: ~/logs
YAML;

		$config_path = $this->createTempFile('agent.yaml', $yaml_content);

		$env_provider = static fn(string $name) => $name === 'HOME' ? '/home/testuser' : false;
		$env_loader = new EnvConfigurationLoader(true, $env_provider);
		$loader = new YamlConfigurationLoader($env_loader);

		$config = $loader->load($config_path, $this->temp_dir, true);

		$this->assertSame('/home/testuser/.php-cli-agent/sessions', $config->getSessionStoragePath());
		$this->assertSame('/home/testuser/logs', $config->getLogPath());
	}

	/**
	 * Tests that defaults are applied when config is minimal.
	 */
	public function test_load_withMinimalConfig_appliesDefaults(): void
	{
		$yaml_content = <<<YAML
provider:
  api_key: minimal-key
YAML;

		$config_path = $this->createTempFile('agent.yaml', $yaml_content);

		$env_provider = static fn() => false;
		$env_loader = new EnvConfigurationLoader(true, $env_provider);
		$loader = new YamlConfigurationLoader($env_loader);

		$config = $loader->load($config_path, $this->temp_dir, true);

		$this->assertSame('anthropic', $config->getProvider()->getType());
		$this->assertSame('claude-sonnet-4-20250514', $config->getProvider()->getModel());
		$this->assertSame(8192, $config->getProvider()->getMaxTokens());
		$this->assertSame(100, $config->getMaxTurns());
	}

	/**
	 * Tests deep merging of associative arrays.
	 */
	public function test_load_withNestedConfigs_deepMerges(): void
	{
		// Create user config with provider settings
		$user_config_dir = $this->temp_dir . '/.php-cli-agent';
		mkdir($user_config_dir, 0755, true);
		$user_yaml = <<<YAML
provider:
  type: anthropic
  api_key: user-key
  model: claude-opus-4-20250514
  max_tokens: 4096
YAML;
		file_put_contents($user_config_dir . '/agent.yaml', $user_yaml);

		// Create project config that only overrides model
		$project_yaml = <<<YAML
provider:
  model: claude-sonnet-4-20250514
YAML;
		file_put_contents($this->temp_dir . '/agent.yaml', $project_yaml);

		$temp_dir = $this->temp_dir;
		$env_provider = static fn(string $name) => $name === 'HOME' ? $temp_dir : false;
		$env_loader = new EnvConfigurationLoader(true, $env_provider);
		$loader = new YamlConfigurationLoader($env_loader);

		$config = $loader->load(null, $this->temp_dir, false);

		// User config values preserved
		$this->assertSame('anthropic', $config->getProvider()->getType());
		$this->assertSame('user-key', $config->getProvider()->getApiKey());
		$this->assertSame(4096, $config->getProvider()->getMaxTokens());
		// Project config override applied
		$this->assertSame('claude-sonnet-4-20250514', $config->getProvider()->getModel());
	}

	/**
	 * Creates a temporary file with the given content.
	 *
	 * @param string $filename The file name.
	 * @param string $content  The file content.
	 *
	 * @return string The full path to the file.
	 */
	private function createTempFile(string $filename, string $content): string
	{
		$path = $this->temp_dir . '/' . $filename;
		file_put_contents($path, $content);

		return $path;
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
