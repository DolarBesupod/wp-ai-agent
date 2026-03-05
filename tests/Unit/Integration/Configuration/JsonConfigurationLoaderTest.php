<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Integration\Configuration;

use Automattic\Automattic\WpAiAgent\Core\Exceptions\ConfigurationException;
use Automattic\Automattic\WpAiAgent\Integration\Configuration\EnvConfigurationLoader;
use Automattic\Automattic\WpAiAgent\Integration\Configuration\JsonConfigurationLoader;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for JsonConfigurationLoader.
 *
 * @covers \Automattic\WpAiAgent\Integration\Configuration\JsonConfigurationLoader
 */
final class JsonConfigurationLoaderTest extends TestCase
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

		$this->temp_dir = sys_get_temp_dir() . '/json_loader_test_' . uniqid();
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
		$loader = new JsonConfigurationLoader();

		$this->assertInstanceOf(JsonConfigurationLoader::class, $loader);
	}

	/**
	 * Tests that load returns default configuration when file does not exist.
	 */
	public function test_load_withNoFile_returnsDefaultConfiguration(): void
	{
		$loader = new JsonConfigurationLoader();

		$config = $loader->load($this->temp_dir);

		$this->assertIsArray($config);
		$this->assertArrayHasKey('provider', $config);
		$this->assertSame('anthropic', $config['provider']['type']);
		$this->assertSame('claude-sonnet-4-20250514', $config['provider']['model']);
		$this->assertSame(8192, $config['provider']['max_tokens']);
		$this->assertSame(100, $config['max_turns']);
		$this->assertArrayHasKey('permissions', $config);
		$this->assertArrayHasKey('allow', $config['permissions']);
	}

	/**
	 * Tests that load reads configuration from settings.json file.
	 */
	public function test_load_withValidJsonFile_returnsConfiguration(): void
	{
		$this->createSettingsFile([
			'provider' => [
				'model' => 'claude-opus',
			],
		]);

		$loader = new JsonConfigurationLoader();

		$config = $loader->load($this->temp_dir);

		$this->assertSame('claude-opus', $config['provider']['model']);
	}

	/**
	 * Tests that configuration is merged with defaults.
	 */
	public function test_load_withPartialConfig_mergesWithDefaults(): void
	{
		$this->createSettingsFile([
			'max_turns' => 50,
		]);

		$loader = new JsonConfigurationLoader();

		$config = $loader->load($this->temp_dir);

		// Custom value
		$this->assertSame(50, $config['max_turns']);
		// Default values preserved
		$this->assertSame('anthropic', $config['provider']['type']);
		$this->assertSame('claude-sonnet-4-20250514', $config['provider']['model']);
	}

	/**
	 * Tests that environment variables are expanded in values.
	 */
	public function test_load_withEnvVariables_resolvesPlaceholders(): void
	{
		$this->createSettingsFile([
			'provider' => [
				'api_key' => '${MY_API_KEY}',
			],
		]);

		$env_provider = static fn(string $name) => $name === 'MY_API_KEY' ? 'secret123' : false;
		$env_loader = new EnvConfigurationLoader(true, $env_provider);
		$loader = new JsonConfigurationLoader($env_loader);

		$config = $loader->load($this->temp_dir);

		$this->assertSame('secret123', $config['provider']['api_key']);
	}

	/**
	 * Tests that invalid JSON throws ConfigurationException.
	 */
	public function test_load_withInvalidJson_throwsConfigurationException(): void
	{
		$settings_dir = $this->temp_dir . '/.wp-ai-agent';
		mkdir($settings_dir, 0755, true);
		file_put_contents($settings_dir . '/settings.json', '{invalid json}');

		$loader = new JsonConfigurationLoader();

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('parse');

		$loader->load($this->temp_dir);
	}

	/**
	 * Tests that non-object JSON root throws ConfigurationException.
	 */
	public function test_load_withNonObjectRoot_throwsConfigurationException(): void
	{
		$settings_dir = $this->temp_dir . '/.wp-ai-agent';
		mkdir($settings_dir, 0755, true);
		file_put_contents($settings_dir . '/settings.json', '["item1", "item2"]');

		$loader = new JsonConfigurationLoader();

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('object');

		$loader->load($this->temp_dir);
	}

	/**
	 * Tests that missing environment variable throws ConfigurationException in strict mode.
	 */
	public function test_load_withMissingEnvVariable_throwsException(): void
	{
		$this->createSettingsFile([
			'provider' => [
				'api_key' => '${MISSING_API_KEY}',
			],
		]);

		$env_loader = new EnvConfigurationLoader(true, fn() => false);
		$loader = new JsonConfigurationLoader($env_loader);

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('MISSING_API_KEY');

		$loader->load($this->temp_dir);
	}

	/**
	 * Tests that nested configuration is deep merged.
	 */
	public function test_load_withNestedConfig_deepMerges(): void
	{
		$this->createSettingsFile([
			'provider' => [
				'model' => 'claude-opus',
				// Note: type and max_tokens should come from defaults
			],
		]);

		$loader = new JsonConfigurationLoader();

		$config = $loader->load($this->temp_dir);

		$this->assertSame('claude-opus', $config['provider']['model']);
		$this->assertSame('anthropic', $config['provider']['type']);
		$this->assertSame(8192, $config['provider']['max_tokens']);
	}

	/**
	 * Tests that permissions.allow is loaded correctly.
	 */
	public function test_load_withPermissionsAllow_loadsToolsList(): void
	{
		$this->createSettingsFile([
			'permissions' => [
				'allow' => ['think', 'read_file', 'glob'],
			],
		]);

		$loader = new JsonConfigurationLoader();

		$config = $loader->load($this->temp_dir);

		$this->assertContains('think', $config['permissions']['allow']);
		$this->assertContains('read_file', $config['permissions']['allow']);
		$this->assertContains('glob', $config['permissions']['allow']);
	}

	/**
	 * Tests that tilde in paths is expanded.
	 */
	public function test_load_withTildeInPaths_expandsTilde(): void
	{
		$this->createSettingsFile([
			'session_storage_path' => '~/.wp-ai-agent/sessions',
		]);

		$env_provider = static fn(string $name) => $name === 'HOME' ? '/home/testuser' : false;
		$env_loader = new EnvConfigurationLoader(true, $env_provider);
		$loader = new JsonConfigurationLoader($env_loader);

		$config = $loader->load($this->temp_dir);

		$this->assertSame('/home/testuser/.wp-ai-agent/sessions', $config['session_storage_path']);
	}

	/**
	 * Tests that getSettingsPath returns the correct path.
	 */
	public function test_getSettingsPath_returnsCorrectPath(): void
	{
		$loader = new JsonConfigurationLoader();

		$path = $loader->getSettingsPath('/project/dir');

		$this->assertSame('/project/dir/.wp-ai-agent/settings.json', $path);
	}

	/**
	 * Tests that fileExists returns false when file does not exist.
	 */
	public function test_fileExists_withNoFile_returnsFalse(): void
	{
		$loader = new JsonConfigurationLoader();

		$result = $loader->fileExists($this->temp_dir);

		$this->assertFalse($result);
	}

	/**
	 * Tests that fileExists returns true when file exists.
	 */
	public function test_fileExists_withFile_returnsTrue(): void
	{
		$this->createSettingsFile(['provider' => []]);

		$loader = new JsonConfigurationLoader();

		$result = $loader->fileExists($this->temp_dir);

		$this->assertTrue($result);
	}

	/**
	 * Tests that load validates configuration and throws on invalid type.
	 */
	public function test_load_withInvalidMaxTokensType_throwsConfigurationException(): void
	{
		$this->createSettingsFile([
			'provider' => [
				'max_tokens' => 'not a number',
			],
		]);

		$loader = new JsonConfigurationLoader();

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('provider.max_tokens');
		$this->expectExceptionMessage('integer');

		$loader->load($this->temp_dir);
	}

	/**
	 * Tests that load validates configuration and throws on invalid minimum value.
	 */
	public function test_load_withNegativeMaxTurns_throwsConfigurationException(): void
	{
		$this->createSettingsFile([
			'max_turns' => -1,
		]);

		$loader = new JsonConfigurationLoader();

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('max_turns');
		$this->expectExceptionMessage('minimum');

		$loader->load($this->temp_dir);
	}

	/**
	 * Tests that empty settings file returns defaults.
	 */
	public function test_load_withEmptySettingsFile_returnsDefaults(): void
	{
		$this->createSettingsFile([]);

		$loader = new JsonConfigurationLoader();

		$config = $loader->load($this->temp_dir);

		// All defaults should be applied
		$this->assertSame('anthropic', $config['provider']['type']);
		$this->assertSame(100, $config['max_turns']);
		$this->assertFalse($config['debug']);
		$this->assertTrue($config['streaming']);
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
