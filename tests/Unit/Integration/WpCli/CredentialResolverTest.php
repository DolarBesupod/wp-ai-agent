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
	 * Tests that resolve() supports subscription credentials from dedicated constant.
	 */
	public function test_resolve_withSubscriptionConstant_returnsSubscriptionMode(): void
	{
		$this->constants['ANTHROPIC_SUBSCRIPTION_KEY'] = 'sub-const-key';

		$resolver = $this->createResolver();
		$result = $resolver->resolve('anthropic');

		$this->assertSame('sub-const-key', $result->getSecret());
		$this->assertSame(AuthMode::SUBSCRIPTION, $result->getAuthMode());
		$this->assertSame('constant', $result->getSource());
	}

	/**
	 * Tests that resolve() supports subscription credentials from dedicated env var.
	 */
	public function test_resolve_withSubscriptionEnv_returnsSubscriptionMode(): void
	{
		$this->env_vars['ANTHROPIC_SUBSCRIPTION_KEY'] = 'sub-env-key';

		$resolver = $this->createResolver();
		$result = $resolver->resolve('anthropic');

		$this->assertSame('sub-env-key', $result->getSecret());
		$this->assertSame(AuthMode::SUBSCRIPTION, $result->getAuthMode());
		$this->assertSame('env', $result->getSource());
	}

	/**
	 * Tests that resolve('claudeCode') supports subscription credentials
	 * from dedicated constants.
	 */
	public function test_resolve_withClaudeCodeSubscriptionConstant_returnsSubscriptionMode(): void
	{
		$this->constants['CLAUDE_CODE_SUBSCRIPTION_KEY'] = 'sk-ant-oat01-const-token-value';

		$resolver = $this->createResolver();
		$result = $resolver->resolve('claudeCode');

		$this->assertSame('sk-ant-oat01-const-token-value', $result->getSecret());
		$this->assertSame(AuthMode::SUBSCRIPTION, $result->getAuthMode());
		$this->assertSame('constant', $result->getSource());
	}

	/**
	 * Tests that resolve('claudeCode') supports subscription credentials
	 * from dedicated environment variables.
	 */
	public function test_resolve_withClaudeCodeSubscriptionEnv_returnsSubscriptionMode(): void
	{
		$this->env_vars['CLAUDE_CODE_SUBSCRIPTION_KEY'] = 'sk-ant-oat01-env-token-value';

		$resolver = $this->createResolver();
		$result = $resolver->resolve('claudeCode');

		$this->assertSame('sk-ant-oat01-env-token-value', $result->getSecret());
		$this->assertSame(AuthMode::SUBSCRIPTION, $result->getAuthMode());
		$this->assertSame('env', $result->getSource());
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
		$this->repository->setCredential('custom-llm', AuthMode::API_KEY, 'sk-custom-test');

		$resolver = $this->createResolver();

		$result = $resolver->resolve('custom-llm');

		$this->assertSame('sk-custom-test', $result->getSecret());
		$this->assertSame(AuthMode::API_KEY, $result->getAuthMode());
		$this->assertSame('db', $result->getSource());
	}

	// -----------------------------------------------------------------------
	// resolve() — OpenAI provider
	// -----------------------------------------------------------------------

	/**
	 * Tests that resolve('openai') returns the constant value when
	 * OPENAI_API_KEY is defined as a PHP constant.
	 */
	public function test_resolve_withOpenaiConstant_returnsApiKeyMode(): void
	{
		$this->constants['OPENAI_API_KEY'] = 'sk-openai-constant';

		$resolver = $this->createResolver();
		$result = $resolver->resolve('openai');

		$this->assertInstanceOf(ResolvedCredential::class, $result);
		$this->assertSame('sk-openai-constant', $result->getSecret());
		$this->assertSame(AuthMode::API_KEY, $result->getAuthMode());
		$this->assertSame('constant', $result->getSource());
	}

	/**
	 * Tests that resolve('openai') throws ConfigurationException when no
	 * credential is found from any source, and the message mentions OPENAI_API_KEY.
	 */
	public function test_resolve_withNoOpenaiCredential_throwsConfigurationException(): void
	{
		$resolver = $this->createResolver();

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('OPENAI_API_KEY');

		$resolver->resolve('openai');
	}

	/**
	 * Tests that resolve('openai') returns subscription mode when
	 * OPENAI_SUBSCRIPTION_KEY is defined as a PHP constant.
	 */
	public function test_resolve_withOpenAiSubscriptionConstant_returnsSubscriptionMode(): void
	{
		$this->constants['OPENAI_SUBSCRIPTION_KEY'] = 'eyJhbGciOi-sub-const';

		$resolver = $this->createResolver();
		$result = $resolver->resolve('openai');

		$this->assertInstanceOf(ResolvedCredential::class, $result);
		$this->assertSame('eyJhbGciOi-sub-const', $result->getSecret());
		$this->assertSame(AuthMode::SUBSCRIPTION, $result->getAuthMode());
		$this->assertSame('constant', $result->getSource());
	}

	/**
	 * Tests that resolve('openai') returns subscription mode when
	 * OPENAI_SUBSCRIPTION_KEY is set as an environment variable.
	 */
	public function test_resolve_withOpenAiSubscriptionEnv_returnsSubscriptionMode(): void
	{
		$this->env_vars['OPENAI_SUBSCRIPTION_KEY'] = 'eyJhbGciOi-sub-env';

		$resolver = $this->createResolver();
		$result = $resolver->resolve('openai');

		$this->assertSame('eyJhbGciOi-sub-env', $result->getSecret());
		$this->assertSame(AuthMode::SUBSCRIPTION, $result->getAuthMode());
		$this->assertSame('env', $result->getSource());
	}

	/**
	 * Tests that resolve('openai') prefers the API key over the subscription
	 * key when both are defined.
	 */
	public function test_resolve_withOpenAiApiKeyAndSubscription_prefersApiKey(): void
	{
		$this->constants['OPENAI_API_KEY'] = 'sk-openai-api';
		$this->constants['OPENAI_SUBSCRIPTION_KEY'] = 'eyJhbGciOi-sub';

		$resolver = $this->createResolver();
		$result = $resolver->resolve('openai');

		$this->assertSame('sk-openai-api', $result->getSecret());
		$this->assertSame(AuthMode::API_KEY, $result->getAuthMode());
		$this->assertSame('constant', $result->getSource());
	}

	// -----------------------------------------------------------------------
	// resolve() — Google provider
	// -----------------------------------------------------------------------

	/**
	 * Tests that resolve('google') returns the env var value when
	 * GOOGLE_API_KEY is set as an environment variable.
	 */
	public function test_resolve_withGoogleEnvVar_returnsApiKeyMode(): void
	{
		$this->env_vars['GOOGLE_API_KEY'] = 'AIza-google-env';

		$resolver = $this->createResolver();
		$result = $resolver->resolve('google');

		$this->assertInstanceOf(ResolvedCredential::class, $result);
		$this->assertSame('AIza-google-env', $result->getSecret());
		$this->assertSame(AuthMode::API_KEY, $result->getAuthMode());
		$this->assertSame('env', $result->getSource());
	}

	// -----------------------------------------------------------------------
	// getStatus()
	// -----------------------------------------------------------------------

	/**
	 * Tests that getStatus() returns entries for all known providers with
	 * correct resolution info.
	 */
	public function test_getStatus_returnsAllProvidersWithResolutionInfo(): void
	{
		$this->constants['ANTHROPIC_SUBSCRIPTION_KEY'] = 'sub-ant-const';
		$this->constants['OPENAI_API_KEY'] = 'sk-openai-const';
		$this->env_vars['GOOGLE_API_KEY'] = 'AIza-google-env';

		$resolver = $this->createResolver();

		$status = $resolver->getStatus();

		$this->assertCount(4, $status);

		// Anthropic resolved from subscription constant.
		$anthropic = $this->findStatusEntry($status, 'anthropic');
		$this->assertNotNull($anthropic);
		$this->assertSame('subscription', $anthropic['auth_mode']);
		$this->assertSame('constant', $anthropic['source']);
		$this->assertTrue($anthropic['available']);

		// OpenAI resolved from constant.
		$openai = $this->findStatusEntry($status, 'openai');
		$this->assertNotNull($openai);
		$this->assertSame('api_key', $openai['auth_mode']);
		$this->assertSame('constant', $openai['source']);
		$this->assertTrue($openai['available']);

		// Google resolved from env var.
		$google = $this->findStatusEntry($status, 'google');
		$this->assertNotNull($google);
		$this->assertSame('api_key', $google['auth_mode']);
		$this->assertSame('env', $google['source']);
		$this->assertTrue($google['available']);

		// claudeCode is known and should be present even if not configured.
		$claude_code = $this->findStatusEntry($status, 'claudeCode');
		$this->assertNotNull($claude_code);
		$this->assertSame('', $claude_code['auth_mode']);
		$this->assertSame('none', $claude_code['source']);
		$this->assertFalse($claude_code['available']);
	}

	/**
	 * Tests that getStatus() includes DB-only providers alongside the three
	 * known providers.
	 */
	public function test_getStatus_includesDbProvidersAlongsideKnownProviders(): void
	{
		$this->repository->setCredential('custom-llm', AuthMode::API_KEY, 'sk-custom');

		$resolver = $this->createResolver();

		$status = $resolver->getStatus();

		// 4 known providers (anthropic, claudeCode, openai, google) + 1 DB-only.
		$this->assertCount(5, $status);

		$custom = $this->findStatusEntry($status, 'custom-llm');
		$this->assertNotNull($custom);
		$this->assertSame('api_key', $custom['auth_mode']);
		$this->assertSame('db', $custom['source']);
		$this->assertTrue($custom['available']);

		$claude_code = $this->findStatusEntry($status, 'claudeCode');
		$this->assertNotNull($claude_code);
		$this->assertFalse($claude_code['available']);

		// Known providers without credentials should show as unavailable.
		$openai = $this->findStatusEntry($status, 'openai');
		$this->assertNotNull($openai);
		$this->assertFalse($openai['available']);

		$google = $this->findStatusEntry($status, 'google');
		$this->assertNotNull($google);
		$this->assertFalse($google['available']);
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
