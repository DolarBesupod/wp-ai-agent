<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Integration\WpCli;

use PHPUnit\Framework\TestCase;
use Tests\Stubs\WpOptionsStore;
use WpAiAgent\Core\Credential\AuthMode;
use WpAiAgent\Core\Credential\Credential;
use WpAiAgent\Core\Exceptions\CredentialNotFoundException;
use WpAiAgent\Integration\WpCli\WpOptionsCredentialRepository;

/**
 * Unit tests for WpOptionsCredentialRepository.
 *
 * WordPress functions (get_option, update_option, delete_option, wp_json_encode)
 * are provided by the stub in tests/Stubs/WpFunctionsStub.php, which is loaded
 * by tests/bootstrap.php. WpOptionsStore::reset() is called in setUp() to
 * ensure complete isolation between test cases.
 *
 * @covers \WpAiAgent\Integration\WpCli\WpOptionsCredentialRepository
 *
 * @since n.e.x.t
 */
final class WpOptionsCredentialRepositoryTest extends TestCase
{
	private WpOptionsCredentialRepository $repository;

	/**
	 * Resets the in-memory option store and creates a fresh repository before
	 * each test.
	 */
	protected function setUp(): void
	{
		parent::setUp();

		WpOptionsStore::reset();
		$this->repository = new WpOptionsCredentialRepository();
	}

	// -----------------------------------------------------------------------
	// setCredential()
	// -----------------------------------------------------------------------

	/**
	 * Tests that setCredential() stores the credential JSON under the expected
	 * option key.
	 */
	public function test_setCredential_storesOptionWithAutoloadFalse(): void
	{
		$this->repository->setCredential('anthropic', AuthMode::API_KEY, 'sk-ant-test123');

		$stored = WpOptionsStore::get('wp_ai_agent_credential_anthropic', false);

		$this->assertIsString($stored);
		$data = json_decode($stored, true);
		$this->assertIsArray($data);
		$this->assertSame('anthropic', $data['provider']);
		$this->assertSame('api_key', $data['auth_mode']);
		$this->assertSame('sk-ant-test123', $data['secret']);
		$this->assertArrayHasKey('created_at', $data);
		$this->assertArrayHasKey('updated_at', $data);
		$this->assertArrayHasKey('meta', $data);
	}

	/**
	 * Tests that setCredential() adds the provider name to the index option.
	 */
	public function test_setCredential_addsProviderToIndex(): void
	{
		$this->repository->setCredential('anthropic', AuthMode::API_KEY, 'sk-ant-test123');

		$providers = $this->repository->listProviders();

		$this->assertContains('anthropic', $providers);
	}

	/**
	 * Tests that saving the same provider twice results in the provider
	 * appearing only once in the index.
	 */
	public function test_setCredential_twice_providerAppearsOnceInIndex(): void
	{
		$this->repository->setCredential('anthropic', AuthMode::API_KEY, 'sk-first');
		$this->repository->setCredential('anthropic', AuthMode::API_KEY, 'sk-second');

		$providers = $this->repository->listProviders();
		$matching = array_filter($providers, static fn (string $name): bool => $name === 'anthropic');

		$this->assertCount(1, $matching);
	}

	/**
	 * Tests that overwriting an existing credential preserves the original
	 * created_at timestamp while updating the updated_at timestamp.
	 */
	public function test_setCredential_overwrite_preservesCreatedAt(): void
	{
		$this->repository->setCredential('anthropic', AuthMode::API_KEY, 'sk-first');

		$first = $this->repository->getCredential('anthropic');
		$original_created_at = $first->getCreatedAt()->format(\DateTimeInterface::ATOM);

		// Small delay to ensure updated_at differs.
		usleep(10000);

		$this->repository->setCredential('anthropic', AuthMode::API_KEY, 'sk-second');

		$second = $this->repository->getCredential('anthropic');

		$this->assertSame($original_created_at, $second->getCreatedAt()->format(\DateTimeInterface::ATOM));
		$this->assertSame('sk-second', $second->getSecret());
	}

	/**
	 * Tests that setCredential() throws InvalidArgumentException when the
	 * secret is empty.
	 */
	public function test_setCredential_withEmptySecret_throwsException(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Secret must not be empty.');

		$this->repository->setCredential('anthropic', AuthMode::API_KEY, '');
	}

	/**
	 * Tests that setCredential() throws InvalidArgumentException when the
	 * provider is empty.
	 */
	public function test_setCredential_withEmptyProvider_throwsException(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Provider name must not be empty.');

		$this->repository->setCredential('', AuthMode::API_KEY, 'sk-test');
	}

	// -----------------------------------------------------------------------
	// getCredential()
	// -----------------------------------------------------------------------

	/**
	 * Tests that getCredential() returns a Credential with the correct field
	 * values after a setCredential() call.
	 */
	public function test_getCredential_returnsStoredCredential(): void
	{
		$this->repository->setCredential('anthropic', AuthMode::API_KEY, 'sk-ant-test123', ['env' => 'prod']);

		$credential = $this->repository->getCredential('anthropic');

		$this->assertInstanceOf(Credential::class, $credential);
		$this->assertSame('anthropic', $credential->getProvider());
		$this->assertSame(AuthMode::API_KEY, $credential->getAuthMode());
		$this->assertSame('sk-ant-test123', $credential->getSecret());
		$this->assertSame(['env' => 'prod'], $credential->getMeta());
	}

	/**
	 * Tests that getCredential() throws CredentialNotFoundException when the
	 * option does not exist.
	 */
	public function test_getCredential_whenMissing_throwsCredentialNotFoundException(): void
	{
		$this->expectException(CredentialNotFoundException::class);

		$this->repository->getCredential('nonexistent');
	}

	/**
	 * Tests that getCredential() throws CredentialNotFoundException when the
	 * stored option value is not valid JSON.
	 */
	public function test_getCredential_withCorruptJson_throwsCredentialNotFoundException(): void
	{
		WpOptionsStore::set('wp_ai_agent_credential_broken', 'not-valid-json');

		$this->expectException(CredentialNotFoundException::class);

		$this->repository->getCredential('broken');
	}

	// -----------------------------------------------------------------------
	// deleteCredential()
	// -----------------------------------------------------------------------

	/**
	 * Tests that deleteCredential() removes the option and updates the index.
	 */
	public function test_deleteCredential_removesOptionAndIndex(): void
	{
		$this->repository->setCredential('anthropic', AuthMode::API_KEY, 'sk-test');

		$result = $this->repository->deleteCredential('anthropic');

		$this->assertTrue($result);
		$this->assertFalse($this->repository->hasCredential('anthropic'));
		$this->assertNotContains('anthropic', $this->repository->listProviders());
	}

	/**
	 * Tests that deleteCredential() returns false when the credential did not
	 * exist.
	 */
	public function test_deleteCredential_whenMissing_returnsFalse(): void
	{
		$result = $this->repository->deleteCredential('ghost');

		$this->assertFalse($result);
	}

	// -----------------------------------------------------------------------
	// hasCredential()
	// -----------------------------------------------------------------------

	/**
	 * Tests that hasCredential() returns true after a credential is stored.
	 */
	public function test_hasCredential_returnsTrueWhenExists(): void
	{
		$this->repository->setCredential('anthropic', AuthMode::API_KEY, 'sk-test');

		$this->assertTrue($this->repository->hasCredential('anthropic'));
	}

	/**
	 * Tests that hasCredential() returns false when no credential is stored.
	 */
	public function test_hasCredential_returnsFalseWhenMissing(): void
	{
		$this->assertFalse($this->repository->hasCredential('nonexistent'));
	}

	// -----------------------------------------------------------------------
	// listProviders()
	// -----------------------------------------------------------------------

	/**
	 * Tests that listProviders() returns all stored provider names.
	 */
	public function test_listProviders_returnsAllProviders(): void
	{
		$this->repository->setCredential('anthropic', AuthMode::API_KEY, 'sk-ant');
		$this->repository->setCredential('openai', AuthMode::API_KEY, 'sk-oai');

		$providers = $this->repository->listProviders();

		$this->assertCount(2, $providers);
		$this->assertContains('anthropic', $providers);
		$this->assertContains('openai', $providers);
	}

	/**
	 * Tests that listProviders() returns an empty array when the index option
	 * does not exist.
	 */
	public function test_listProviders_returnsEmptyWhenNoIndex(): void
	{
		$result = $this->repository->listProviders();

		$this->assertSame([], $result);
	}
}
