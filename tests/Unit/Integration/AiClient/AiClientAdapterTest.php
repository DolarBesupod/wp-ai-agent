<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Integration\AiClient;

use PhpCliAgent\Core\Exceptions\AiClientException;
use PhpCliAgent\Integration\AiClient\AiClientAdapter;
use PhpCliAgent\Integration\AiClient\AiClientAdapterInterface;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\ProviderRegistry;

/**
 * Unit tests for AiClientAdapter.
 *
 * @covers \PhpCliAgent\Integration\AiClient\AiClientAdapter
 */
final class AiClientAdapterTest extends TestCase
{
	/**
	 * Stores original environment value.
	 *
	 * @var string|false
	 */
	private $original_api_key;

	/**
	 * Mock HTTP transporter used across tests.
	 *
	 * @var HttpTransporterInterface
	 */
	private HttpTransporterInterface $mock_transporter;

	/**
	 * Sets up the test environment.
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->original_api_key = getenv('ANTHROPIC_API_KEY');
		$this->mock_transporter = $this->createMock(HttpTransporterInterface::class);
	}

	/**
	 * Tears down the test environment.
	 */
	protected function tearDown(): void
	{
		if ($this->original_api_key !== false) {
			putenv('ANTHROPIC_API_KEY=' . $this->original_api_key);
		} else {
			putenv('ANTHROPIC_API_KEY');
		}
		parent::tearDown();
	}

	/**
	 * Creates an adapter with the mock transporter for testing.
	 *
	 * @param string $api_key    The API key.
	 * @param string $model      The model to use.
	 * @param int    $max_tokens Maximum tokens.
	 *
	 * @return AiClientAdapter
	 */
	private function createAdapter(
		string $api_key = 'test_key',
		string $model = 'claude-sonnet-4-20250514',
		int $max_tokens = 4096
	): AiClientAdapter {
		return new AiClientAdapter(
			$api_key,
			$model,
			$max_tokens,
			$this->mock_transporter
		);
	}

	/**
	 * Tests that the adapter implements AiClientAdapterInterface.
	 */
	public function test_implementsInterface(): void
	{
		$adapter = $this->createAdapter();

		$this->assertInstanceOf(AiClientAdapterInterface::class, $adapter);
	}

	/**
	 * Tests that constructor throws exception when API key is missing.
	 */
	public function test_constructor_withoutApiKey_throwsException(): void
	{
		putenv('ANTHROPIC_API_KEY');

		$this->expectException(AiClientException::class);
		$this->expectExceptionMessage('Invalid or missing API key');

		new AiClientAdapter(null, 'claude-sonnet-4-20250514', 4096, $this->mock_transporter);
	}

	/**
	 * Tests that constructor accepts API key parameter.
	 */
	public function test_constructor_withApiKeyParameter_succeeds(): void
	{
		$adapter = $this->createAdapter('test_api_key_123');

		$this->assertTrue($adapter->isConfigured());
	}

	/**
	 * Tests that constructor reads API key from environment.
	 */
	public function test_constructor_withEnvironmentApiKey_succeeds(): void
	{
		putenv('ANTHROPIC_API_KEY=env_api_key_456');

		$adapter = new AiClientAdapter(null, 'claude-sonnet-4-20250514', 4096, $this->mock_transporter);

		$this->assertTrue($adapter->isConfigured());
	}

	/**
	 * Tests that getModel returns the configured model.
	 */
	public function test_getModel_returnsConfiguredModel(): void
	{
		$adapter = $this->createAdapter('test_key', 'claude-opus-4-20250514');

		$this->assertSame('claude-opus-4-20250514', $adapter->getModel());
	}

	/**
	 * Tests that getModel returns default model when not specified.
	 */
	public function test_getModel_returnsDefaultModel(): void
	{
		$adapter = $this->createAdapter();

		$this->assertSame('claude-sonnet-4-20250514', $adapter->getModel());
	}

	/**
	 * Tests that setModel updates the model.
	 */
	public function test_setModel_updatesModel(): void
	{
		$adapter = $this->createAdapter();

		$adapter->setModel('claude-haiku-3-20240307');

		$this->assertSame('claude-haiku-3-20240307', $adapter->getModel());
	}

	/**
	 * Tests that setMaxTokens is callable.
	 */
	public function test_setMaxTokens_isCallable(): void
	{
		$adapter = $this->createAdapter();

		$adapter->setMaxTokens(8192);

		// No exception means success; we can't easily verify internal state
		$this->assertTrue(true);
	}

	/**
	 * Tests that setTemperature is callable.
	 */
	public function test_setTemperature_isCallable(): void
	{
		$adapter = $this->createAdapter();

		$adapter->setTemperature(0.5);

		// No exception means success
		$this->assertTrue(true);
	}

	/**
	 * Tests that getLastUsage returns null before any request.
	 */
	public function test_getLastUsage_beforeRequest_returnsNull(): void
	{
		$adapter = $this->createAdapter();

		$this->assertNull($adapter->getLastUsage());
	}

	/**
	 * Tests that getProviderRegistry returns a ProviderRegistry instance.
	 */
	public function test_getProviderRegistry_returnsProviderRegistry(): void
	{
		$adapter = $this->createAdapter();

		$registry = $adapter->getProviderRegistry();

		$this->assertInstanceOf(ProviderRegistry::class, $registry);
	}

	/**
	 * Tests that isConfigured returns true when properly set up.
	 */
	public function test_isConfigured_whenInitialized_returnsTrue(): void
	{
		$adapter = $this->createAdapter();

		$this->assertTrue($adapter->isConfigured());
	}

	/**
	 * Tests that getProviderId returns anthropic.
	 */
	public function test_getProviderId_returnsAnthropic(): void
	{
		$adapter = $this->createAdapter();

		$this->assertSame('anthropic', $adapter->getProviderId());
	}

	/**
	 * Tests that constructor accepts custom HTTP transporter.
	 */
	public function test_constructor_withCustomHttpTransporter_succeeds(): void
	{
		$custom_transporter = $this->createMock(HttpTransporterInterface::class);

		$adapter = new AiClientAdapter(
			'test_key',
			'claude-sonnet-4-20250514',
			4096,
			$custom_transporter
		);

		$this->assertTrue($adapter->isConfigured());
	}

	/**
	 * Tests that constructor with empty string API key throws exception.
	 */
	public function test_constructor_withEmptyApiKey_throwsException(): void
	{
		putenv('ANTHROPIC_API_KEY');

		$this->expectException(AiClientException::class);
		$this->expectExceptionMessage('Invalid or missing API key');

		new AiClientAdapter('', 'claude-sonnet-4-20250514', 4096, $this->mock_transporter);
	}

	/**
	 * Tests that the adapter can be configured with custom max tokens.
	 */
	public function test_constructor_withCustomMaxTokens_succeeds(): void
	{
		$adapter = $this->createAdapter('test_key', 'claude-sonnet-4-20250514', 8192);

		// Verify the adapter is properly configured
		$this->assertTrue($adapter->isConfigured());
	}
}
