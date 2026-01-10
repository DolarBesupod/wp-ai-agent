<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Integration\Configuration;

use PhpCliAgent\Core\Exceptions\ConfigurationException;
use PhpCliAgent\Integration\Configuration\EnvConfigurationLoader;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EnvConfigurationLoader.
 *
 * @covers \PhpCliAgent\Integration\Configuration\EnvConfigurationLoader
 */
final class EnvConfigurationLoaderTest extends TestCase
{
	/**
	 * Tests that constructor creates loader with default settings.
	 */
	public function test_constructor_withDefaults_createsLoader(): void
	{
		$loader = new EnvConfigurationLoader();

		$this->assertInstanceOf(EnvConfigurationLoader::class, $loader);
	}

	/**
	 * Tests that resolve replaces environment variable placeholders.
	 */
	public function test_resolve_withEnvVarPlaceholder_replacesValue(): void
	{
		$env_provider = $this->createEnvProvider(['API_KEY' => 'secret-key-123']);
		$loader = new EnvConfigurationLoader(true, $env_provider);

		$config = [
			'provider' => [
				'api_key' => '${API_KEY}',
			],
		];

		$resolved = $loader->resolve($config);

		$this->assertSame('secret-key-123', $resolved['provider']['api_key']);
	}

	/**
	 * Tests that resolve handles multiple placeholders in same string.
	 */
	public function test_resolve_withMultiplePlaceholders_replacesAll(): void
	{
		$env_provider = $this->createEnvProvider([
			'USER' => 'john',
			'HOST' => 'localhost',
		]);
		$loader = new EnvConfigurationLoader(true, $env_provider);

		$config = [
			'connection' => '${USER}@${HOST}',
		];

		$resolved = $loader->resolve($config);

		$this->assertSame('john@localhost', $resolved['connection']);
	}

	/**
	 * Tests that resolve preserves non-placeholder values.
	 */
	public function test_resolve_withNoPlaceholder_preservesValue(): void
	{
		$loader = new EnvConfigurationLoader(true, fn() => false);

		$config = [
			'static_value' => 'just a string',
			'number' => 42,
			'boolean' => true,
		];

		$resolved = $loader->resolve($config);

		$this->assertSame('just a string', $resolved['static_value']);
		$this->assertSame(42, $resolved['number']);
		$this->assertTrue($resolved['boolean']);
	}

	/**
	 * Tests that resolve recursively processes nested arrays.
	 */
	public function test_resolve_withNestedConfig_resolvesRecursively(): void
	{
		$env_provider = $this->createEnvProvider([
			'DB_HOST' => 'mysql.example.com',
			'DB_USER' => 'admin',
			'DB_PASS' => 'secret',
		]);
		$loader = new EnvConfigurationLoader(true, $env_provider);

		$config = [
			'database' => [
				'connection' => [
					'host' => '${DB_HOST}',
					'credentials' => [
						'username' => '${DB_USER}',
						'password' => '${DB_PASS}',
					],
				],
			],
		];

		$resolved = $loader->resolve($config);

		$this->assertSame('mysql.example.com', $resolved['database']['connection']['host']);
		$this->assertSame('admin', $resolved['database']['connection']['credentials']['username']);
		$this->assertSame('secret', $resolved['database']['connection']['credentials']['password']);
	}

	/**
	 * Tests that resolve in strict mode throws on missing env variable.
	 */
	public function test_resolve_inStrictMode_withMissingVar_throwsException(): void
	{
		$loader = new EnvConfigurationLoader(true, fn() => false);

		$config = [
			'api_key' => '${MISSING_VAR}',
		];

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('MISSING_VAR');

		$loader->resolve($config);
	}

	/**
	 * Tests that resolve in non-strict mode preserves missing placeholders.
	 */
	public function test_resolve_inNonStrictMode_withMissingVar_preservesPlaceholder(): void
	{
		$loader = new EnvConfigurationLoader(false, fn() => false);

		$config = [
			'api_key' => '${MISSING_VAR}',
		];

		$resolved = $loader->resolve($config);

		$this->assertSame('${MISSING_VAR}', $resolved['api_key']);
	}

	/**
	 * Tests resolveString with a simple placeholder.
	 */
	public function test_resolveString_withPlaceholder_resolvesValue(): void
	{
		$env_provider = $this->createEnvProvider(['TOKEN' => 'abc123']);
		$loader = new EnvConfigurationLoader(true, $env_provider);

		$result = $loader->resolveString('Bearer ${TOKEN}');

		$this->assertSame('Bearer abc123', $result);
	}

	/**
	 * Tests resolveString throws in strict mode for missing variable.
	 */
	public function test_resolveString_inStrictMode_withMissingVar_throwsException(): void
	{
		$loader = new EnvConfigurationLoader(true, fn() => false);

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('NOT_SET');

		$loader->resolveString('${NOT_SET}');
	}

	/**
	 * Tests expandTilde expands ~ to home directory.
	 */
	public function test_expandTilde_withTilde_expandsToHome(): void
	{
		$env_provider = $this->createEnvProvider(['HOME' => '/home/testuser']);
		$loader = new EnvConfigurationLoader(true, $env_provider);

		$result = $loader->expandTilde('~/.config/app');

		$this->assertSame('/home/testuser/.config/app', $result);
	}

	/**
	 * Tests expandTilde uses USERPROFILE if HOME not set.
	 */
	public function test_expandTilde_withUserProfile_expandsToHome(): void
	{
		$env_provider = $this->createEnvProvider(['USERPROFILE' => 'C:\\Users\\TestUser']);
		$loader = new EnvConfigurationLoader(true, $env_provider);

		$result = $loader->expandTilde('~/.config/app');

		$this->assertSame('C:\\Users\\TestUser/.config/app', $result);
	}

	/**
	 * Tests expandTilde returns original path if no home found.
	 */
	public function test_expandTilde_withNoHome_returnsOriginal(): void
	{
		$loader = new EnvConfigurationLoader(true, fn() => false);

		$result = $loader->expandTilde('~/.config/app');

		$this->assertSame('~/.config/app', $result);
	}

	/**
	 * Tests expandTilde returns path unchanged if not starting with tilde.
	 */
	public function test_expandTilde_withAbsolutePath_returnsUnchanged(): void
	{
		$loader = new EnvConfigurationLoader(true, fn() => false);

		$result = $loader->expandTilde('/absolute/path');

		$this->assertSame('/absolute/path', $result);
	}

	/**
	 * Tests that only valid environment variable names are matched.
	 */
	public function test_resolve_withInvalidVarName_preservesLiteral(): void
	{
		$loader = new EnvConfigurationLoader(true, fn() => false);

		$config = [
			'value' => '${123_INVALID}', // Invalid: starts with number
		];

		$resolved = $loader->resolve($config);

		$this->assertSame('${123_INVALID}', $resolved['value']);
	}

	/**
	 * Tests placeholder with underscores in variable name.
	 */
	public function test_resolve_withUnderscoreInVarName_resolves(): void
	{
		$env_provider = $this->createEnvProvider(['MY_API_KEY' => 'key-value']);
		$loader = new EnvConfigurationLoader(true, $env_provider);

		$config = [
			'key' => '${MY_API_KEY}',
		];

		$resolved = $loader->resolve($config);

		$this->assertSame('key-value', $resolved['key']);
	}

	/**
	 * Tests resolve handles empty string environment variable value.
	 */
	public function test_resolve_withEmptyEnvValue_resolvesToEmpty(): void
	{
		$env_provider = $this->createEnvProvider(['EMPTY_VAR' => '']);
		$loader = new EnvConfigurationLoader(true, $env_provider);

		$config = [
			'value' => '${EMPTY_VAR}',
		];

		$resolved = $loader->resolve($config);

		$this->assertSame('', $resolved['value']);
	}

	/**
	 * Creates an environment variable provider for testing.
	 *
	 * @param array<string, string> $env_vars Environment variables to provide.
	 *
	 * @return callable
	 */
	private function createEnvProvider(array $env_vars): callable
	{
		return static function (string $name) use ($env_vars) {
			return $env_vars[$name] ?? false;
		};
	}
}
