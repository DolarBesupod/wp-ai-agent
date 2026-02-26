<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Integration\WpCli;

use PHPUnit\Framework\TestCase;
use Tests\Stubs\WpOptionsStore;
use WpAiAgent\Core\Credential\AuthMode;
use WpAiAgent\Core\Credential\ResolvedCredential;
use WpAiAgent\Core\Exceptions\ConfigurationException;
use WpAiAgent\Integration\WpCli\CredentialResolver;
use WpAiAgent\Integration\WpCli\WpOptionsCredentialRepository;

/**
 * Unit tests for CredentialResolver.
 *
 * Uses callable injection for both environment variables and PHP constants
 * to achieve full test isolation without defining real constants or setting
 * real environment variables.
 *
 * @covers \WpAiAgent\Integration\WpCli\CredentialResolver
 *
 * @since n.e.x.t
 */
final class CredentialResolverTest extends TestCase
{
	private WpOptionsCredentialRepository $repository;

	/**
	 * Simulated constants: constant name => value.
	 *
	 * @var array<string, string>
	 */
	private array $constants = [];

	/**
	 * Simulated environment variables: variable name => value.
	 *
	 * @var array<string, string>
	 */
	private array $env_vars = [];

	/**
	 * Resets the in-memory stores and creates a fresh repository before each test.
	 */
	protected function setUp(): void
	{
		parent::setUp();

		WpOptionsStore::reset();
		$this->constants = [];
		$this->env_vars = [];
		$this->repository = new WpOptionsCredentialRepository();
	}

	// -----------------------------------------------------------------------
	// resolve() — priority chain
	// -----------------------------------------------------------------------

	/**
	 * Tests that resolve() returns the constant value when a PHP constant is defined,
	 * even when an env var and DB credential also exist.
	 */
	public function test_resolve_withConstant_returnsConstantSource(): void
	{
		$this->constants['ANTHROPIC_API_KEY'] = 'sk-ant-constant';
		$this->env_vars['ANTHROPIC_API_KEY'] = 'sk-ant-env';
		$this->repository->setCredential('anthropic', AuthMode::API_KEY, 'sk-ant-db');

		$resolver = $this->createResolver();

		$result = $resolver->resolve('anthropic');

		$this->assertInstanceOf(ResolvedCredential::class, $result);
		$this->assertSame('sk-ant-constant', $result->getSecret());
		$this->assertSame(AuthMode::API_KEY, $result->getAuthMode());
		$this->assertSame('constant', $result->getSource());
	}

	/**
	 * Tests that resolve() returns the env var value when no constant is defined
	 * but an env var and DB credential exist.
	 */
	public function test_resolve_withEnvVar_returnsEnvSource(): void
	{
		// No constant defined.
		$this->env_vars['ANTHROPIC_API_KEY'] = 'sk-ant-env';
		$this->repository->setCredential('anthropic', AuthMode::API_KEY, 'sk-ant-db');

		$resolver = $this->createResolver();

		$result = $resolver->resolve('anthropic');

		$this->assertSame('sk-ant-env', $result->getSecret());
		$this->assertSame(AuthMode::API_KEY, $result->getAuthMode());
		$this->assertSame('env', $result->getSource());
	}

	/**
	 * Tests that resolve() returns the DB credential when no constant or env var
	 * is available.
	 */
	public function test_resolve_withDbCredential_returnsDbSource(): void
	{
		// No constant, no env var.
		$this->repository->setCredential('anthropic', AuthMode::API_KEY, 'sk-ant-db');

		$resolver = $this->createResolver();

		$result = $resolver->resolve('anthropic');

		$this->assertSame('sk-ant-db', $result->getSecret());
		$this->assertSame(AuthMode::API_KEY, $result->getAuthMode());
		$this->assertSame('db', $result->getSource());
	}

	/**
	 * Tests that resolve() throws ConfigurationException when no credential
	 * is found from any source.
	 */
	public function test_resolve_withNoCredential_throwsConfigurationException(): void
	{
		$resolver = $this->createResolver();

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('No API key found for provider "anthropic"');

		$resolver->resolve('anthropic');
	}

	/**
	 * Tests that resolve() skips empty constant values and falls through
	 * to the next priority level.
	 */
	public function test_resolve_withEmptyConstant_fallsThroughToEnv(): void
	{
		$this->constants['ANTHROPIC_API_KEY'] = '';
		$this->env_vars['ANTHROPIC_API_KEY'] = 'sk-ant-env';

		$resolver = $this->createResolver();

		$result = $resolver->resolve('anthropic');

		$this->assertSame('sk-ant-env', $result->getSecret());
		$this->assertSame('env', $result->getSource());
	}

	/**
	 * Tests that resolve() skips empty env var values and falls through
	 * to the DB credential.
	 */
	public function test_resolve_withEmptyEnvVar_fallsThroughToDb(): void
	{
		$this->env_vars['ANTHROPIC_API_KEY'] = '';
		$this->repository->setCredential('anthropic', AuthMode::API_KEY, 'sk-ant-db');

		$resolver = $this->createResolver();

		$result = $resolver->resolve('anthropic');

		$this->assertSame('sk-ant-db', $result->getSecret());
		$this->assertSame('db', $result->getSource());
	}

	/**
	 * Tests that resolve() works for an unknown provider that has a DB
	 * credential but no constant mapping.
	 */
	public function test_resolve_withUnknownProvider_usesDbCredential(): void
	{
		$this->repository->setCredential('openai', AuthMode::API_KEY, 'sk-openai-test');

		$resolver = $this->createResolver();

		$result = $resolver->resolve('openai');

		$this->assertSame('sk-openai-test', $result->getSecret());
		$this->assertSame(AuthMode::API_KEY, $result->getAuthMode());
		$this->assertSame('db', $result->getSource());
	}

	// -----------------------------------------------------------------------
	// getStatus()
	// -----------------------------------------------------------------------

	/**
	 * Tests that getStatus() returns entries for all known providers and DB
	 * providers with correct resolution info.
	 */
	public function test_getStatus_returnsAllProvidersWithResolutionInfo(): void
	{
		$this->constants['ANTHROPIC_API_KEY'] = 'sk-ant-const';
		$this->repository->setCredential('openai', AuthMode::API_KEY, 'sk-openai-test');

		$resolver = $this->createResolver();

		$status = $resolver->getStatus();

		$this->assertCount(2, $status);

		// Anthropic resolved from constant.
		$anthropic = $this->findStatusEntry($status, 'anthropic');
		$this->assertNotNull($anthropic);
		$this->assertSame('api_key', $anthropic['auth_mode']);
		$this->assertSame('constant', $anthropic['source']);
		$this->assertTrue($anthropic['available']);

		// OpenAI resolved from DB.
		$openai = $this->findStatusEntry($status, 'openai');
		$this->assertNotNull($openai);
		$this->assertSame('api_key', $openai['auth_mode']);
		$this->assertSame('db', $openai['source']);
		$this->assertTrue($openai['available']);
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Creates a CredentialResolver with injected test doubles for env and constants.
	 */
	private function createResolver(): CredentialResolver
	{
		$env_getter = function (string $name): string|false {
			return $this->env_vars[$name] ?? false;
		};

		$constant_checker = function (string $name): string|false {
			return $this->constants[$name] ?? false;
		};

		return new CredentialResolver($this->repository, $env_getter, $constant_checker);
	}

	/**
	 * Finds a status entry by provider name.
	 *
	 * @param array<int, array{provider: string, auth_mode: string, source: string, available: bool}> $status
	 * @param string $provider
	 *
	 * @return array{provider: string, auth_mode: string, source: string, available: bool}|null
	 */
	private function findStatusEntry(array $status, string $provider): ?array
	{
		foreach ($status as $entry) {
			if ($entry['provider'] === $provider) {
				return $entry;
			}
		}

		return null;
	}
}
