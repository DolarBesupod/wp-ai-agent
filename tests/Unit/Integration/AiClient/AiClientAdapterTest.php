<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Integration\AiClient;

use Automattic\Automattic\WpAiAgent\Core\Credential\AuthMode;
use Automattic\Automattic\WpAiAgent\Core\Exceptions\AiClientException;
use Automattic\Automattic\WpAiAgent\Core\ValueObjects\Message;
use Automattic\Automattic\WpAiAgent\Integration\AiClient\AiClientAdapter;
use Automattic\Automattic\WpAiAgent\Integration\AiClient\AiClientAdapterInterface;
use Automattic\Automattic\WpAiAgent\Integration\AiClient\AnthropicSubscriptionRequestAuthentication;
use Automattic\Automattic\WpAiAgent\Integration\AiClient\ClaudeCodeSubscriptionRequestAuthentication;
use Automattic\Automattic\WpAiAgent\Integration\AiClient\OpenAiSubscriptionRequestAuthentication;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\ProviderRegistry;
use WordPress\AnthropicAiProvider\Authentication\AnthropicApiKeyRequestAuthentication;
use WordPress\GoogleAiProvider\Authentication\GoogleApiKeyRequestAuthentication;

/**
 * Unit tests for AiClientAdapter.
 *
 * @covers \Automattic\WpAiAgent\Integration\AiClient\AiClientAdapter
 */
final class AiClientAdapterTest extends TestCase
{
	/**
	 * Stores original environment values.
	 *
	 * @var array<string, string|false>
	 */
	private array $original_env_keys;

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
		$this->original_env_keys = [
			'ANTHROPIC_API_KEY' => getenv('ANTHROPIC_API_KEY'),
			'CLAUDE_CODE_SUBSCRIPTION_KEY' => getenv('CLAUDE_CODE_SUBSCRIPTION_KEY'),
			'OPENAI_API_KEY' => getenv('OPENAI_API_KEY'),
			'GOOGLE_API_KEY' => getenv('GOOGLE_API_KEY'),
		];
		$this->mock_transporter = $this->createMock(HttpTransporterInterface::class);
	}

	/**
	 * Tears down the test environment.
	 */
	protected function tearDown(): void
	{
		foreach ($this->original_env_keys as $key => $value) {
			if ($value !== false) {
				putenv($key . '=' . $value);
			} else {
				putenv($key);
			}
		}
		parent::tearDown();
	}

	/**
	 * Creates an adapter with the mock transporter for testing.
	 *
	 * @param string   $api_key     The API key.
	 * @param AuthMode $auth_mode   Authentication mode.
	 * @param string   $model       The model to use.
	 * @param int      $max_tokens  Maximum tokens.
	 * @param string   $provider_id The provider identifier.
	 *
	 * @return AiClientAdapter
	 */
	private function createAdapter(
		string $api_key = 'test_key',
		AuthMode $auth_mode = AuthMode::API_KEY,
		string $model = 'claude-sonnet-4-20250514',
		int $max_tokens = 4096,
		string $provider_id = 'anthropic'
	): AiClientAdapter {
		return new AiClientAdapter(
			$api_key,
			$auth_mode,
			$model,
			$max_tokens,
			$this->mock_transporter,
			$provider_id
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

			new AiClientAdapter(null, AuthMode::API_KEY, 'claude-sonnet-4-20250514', 4096, $this->mock_transporter);
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

		$adapter = new AiClientAdapter(
			null,
			AuthMode::API_KEY,
			'claude-sonnet-4-20250514',
			4096,
			$this->mock_transporter
		);

		$this->assertTrue($adapter->isConfigured());
	}

	/**
	 * Tests that getModel returns the configured model.
	 */
	public function test_getModel_returnsConfiguredModel(): void
	{
		$adapter = $this->createAdapter('test_key', AuthMode::API_KEY, 'claude-opus-4-20250514');

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
	 * Tests that getProviderId returns anthropic by default.
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
			AuthMode::API_KEY,
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

		new AiClientAdapter('', AuthMode::API_KEY, 'claude-sonnet-4-20250514', 4096, $this->mock_transporter);
	}

	/**
	 * Tests that the adapter can be configured with custom max tokens.
	 */
	public function test_constructor_withCustomMaxTokens_succeeds(): void
	{
		$adapter = $this->createAdapter('test_key', AuthMode::API_KEY, 'claude-sonnet-4-20250514', 8192);

		// Verify the adapter is properly configured
		$this->assertTrue($adapter->isConfigured());
	}

	/**
	 * Tests that subscription mode wires subscription request authentication.
	 */
	public function test_constructor_withSubscriptionMode_usesSubscriptionAuthentication(): void
	{
		$adapter = $this->createAdapter('subscription-secret', AuthMode::SUBSCRIPTION);

		$authentication = $adapter->getProviderRegistry()->getProviderRequestAuthentication('anthropic');

		$this->assertInstanceOf(AnthropicSubscriptionRequestAuthentication::class, $authentication);
	}

	// ── Multi-provider constructor tests ──

	/**
	 * Tests that constructor with openai provider_id registers OpenAI provider.
	 */
	public function test_constructor_withOpenaiProvider_succeeds(): void
	{
		$adapter = $this->createAdapter('sk-test-key', AuthMode::API_KEY, 'gpt-4o', 4096, 'openai');

		$this->assertSame('openai', $adapter->getProviderId());
		$this->assertTrue($adapter->isConfigured());
	}

	/**
	 * Tests that constructor with google provider_id registers Google provider.
	 */
	public function test_constructor_withGoogleProvider_succeeds(): void
	{
		$adapter = $this->createAdapter('AIza-test-key', AuthMode::API_KEY, 'gemini-2.0-flash', 4096, 'google');

		$this->assertSame('google', $adapter->getProviderId());
		$this->assertTrue($adapter->isConfigured());
	}

	/**
	 * Tests that constructor with unknown provider throws exception.
	 */
	public function test_constructor_withUnknownProvider_throwsException(): void
	{
		$this->expectException(AiClientException::class);
		$this->expectExceptionMessage('Unsupported AI provider: "llama"');

		$this->createAdapter('test_key', AuthMode::API_KEY, 'llama-3', 4096, 'llama');
	}

	/**
	 * Tests that OpenAI provider uses standard Bearer token authentication.
	 */
	public function test_constructor_withOpenaiProvider_usesApiKeyAuthentication(): void
	{
		$adapter = $this->createAdapter('sk-test-key', AuthMode::API_KEY, 'gpt-4o', 4096, 'openai');

		$authentication = $adapter->getProviderRegistry()->getProviderRequestAuthentication('openai');

		$this->assertInstanceOf(ApiKeyRequestAuthentication::class, $authentication);
		// Ensure it is NOT the Anthropic or Google subclass
		$this->assertNotInstanceOf(AnthropicApiKeyRequestAuthentication::class, $authentication);
		$this->assertNotInstanceOf(GoogleApiKeyRequestAuthentication::class, $authentication);
	}

	/**
	 * Tests that Google provider uses Google-specific authentication.
	 */
	public function test_constructor_withGoogleProvider_usesGoogleAuthentication(): void
	{
		$adapter = $this->createAdapter('AIza-test-key', AuthMode::API_KEY, 'gemini-2.0-flash', 4096, 'google');

		$authentication = $adapter->getProviderRegistry()->getProviderRequestAuthentication('google');

		$this->assertInstanceOf(GoogleApiKeyRequestAuthentication::class, $authentication);
	}

	/**
	 * Tests that Anthropic provider uses Anthropic-specific authentication.
	 */
	public function test_constructor_withAnthropicProvider_usesAnthropicAuthentication(): void
	{
		$adapter = $this->createAdapter();

		$authentication = $adapter->getProviderRegistry()->getProviderRequestAuthentication('anthropic');

		$this->assertInstanceOf(AnthropicApiKeyRequestAuthentication::class, $authentication);
	}

	// ── getApiKeyFromEnvironment per-provider tests ──

	/**
	 * Tests that OpenAI adapter reads OPENAI_API_KEY from environment.
	 */
	public function test_constructor_withOpenaiEnvKey_readsCorrectVariable(): void
	{
		putenv('OPENAI_API_KEY=sk-env-test');

		$adapter = new AiClientAdapter(
			null,
			AuthMode::API_KEY,
			'gpt-4o',
			4096,
			$this->mock_transporter,
			'openai'
		);

		$this->assertTrue($adapter->isConfigured());
		$this->assertSame('openai', $adapter->getProviderId());
	}

	/**
	 * Tests that Google adapter reads GOOGLE_API_KEY from environment.
	 */
	public function test_constructor_withGoogleEnvKey_readsCorrectVariable(): void
	{
		putenv('GOOGLE_API_KEY=AIza-env-test');

		$adapter = new AiClientAdapter(
			null,
			AuthMode::API_KEY,
			'gemini-2.0-flash',
			4096,
			$this->mock_transporter,
			'google'
		);

		$this->assertTrue($adapter->isConfigured());
		$this->assertSame('google', $adapter->getProviderId());
	}

	/**
	 * Tests that OpenAI adapter without key throws exception.
	 */
	public function test_constructor_withOpenaiNoKey_throwsException(): void
	{
		putenv('OPENAI_API_KEY');

		$this->expectException(AiClientException::class);
		$this->expectExceptionMessage('Invalid or missing API key');

		new AiClientAdapter(
			null,
			AuthMode::API_KEY,
			'gpt-4o',
			4096,
			$this->mock_transporter,
			'openai'
		);
	}

	// ── switchProvider tests ──

	/**
	 * Tests that switchProvider changes the active provider.
	 */
	public function test_switchProvider_changesToNewProvider(): void
	{
		$adapter = $this->createAdapter();

		$adapter->switchProvider('openai', 'sk-new-key');

		$this->assertSame('openai', $adapter->getProviderId());
	}

	/**
	 * Tests that switchProvider to Google works correctly.
	 */
	public function test_switchProvider_toGoogle_succeeds(): void
	{
		$adapter = $this->createAdapter();

		$adapter->switchProvider('google', 'AIza-new-key');

		$this->assertSame('google', $adapter->getProviderId());
	}

	/**
	 * Tests that switchProvider back to Anthropic works.
	 */
	public function test_switchProvider_backToAnthropic_succeeds(): void
	{
		$adapter = $this->createAdapter();

		$adapter->switchProvider('openai', 'sk-key');
		$adapter->switchProvider('anthropic', 'ant-key');

		$this->assertSame('anthropic', $adapter->getProviderId());
	}

	/**
	 * Tests that switchProvider with unknown provider throws exception.
	 */
	public function test_switchProvider_withUnknownProvider_throwsException(): void
	{
		$adapter = $this->createAdapter();

		$this->expectException(AiClientException::class);
		$this->expectExceptionMessage('Unsupported AI provider: "cohere"');

		$adapter->switchProvider('cohere', 'some-key');
	}

	/**
	 * Tests that switchProvider preserves the provider registry instance.
	 */
	public function test_switchProvider_preservesRegistry(): void
	{
		$adapter = $this->createAdapter();
		$registry_before = $adapter->getProviderRegistry();

		$adapter->switchProvider('openai', 'sk-key');

		$this->assertSame($registry_before, $adapter->getProviderRegistry());
	}

	/**
	 * Tests that switchProvider sets correct authentication for OpenAI.
	 */
	public function test_switchProvider_toOpenai_setsCorrectAuth(): void
	{
		$adapter = $this->createAdapter();

		$adapter->switchProvider('openai', 'sk-key');

		$authentication = $adapter->getProviderRegistry()->getProviderRequestAuthentication('openai');
		$this->assertInstanceOf(ApiKeyRequestAuthentication::class, $authentication);
		$this->assertNotInstanceOf(AnthropicApiKeyRequestAuthentication::class, $authentication);
	}

	/**
	 * Tests that switchProvider sets correct authentication for Google.
	 */
	public function test_switchProvider_toGoogle_setsCorrectAuth(): void
	{
		$adapter = $this->createAdapter();

		$adapter->switchProvider('google', 'AIza-key');

		$authentication = $adapter->getProviderRegistry()->getProviderRequestAuthentication('google');
		$this->assertInstanceOf(GoogleApiKeyRequestAuthentication::class, $authentication);
	}

	/**
	 * Tests that switchProvider with Anthropic subscription mode works.
	 */
	public function test_switchProvider_toAnthropicSubscription_setsCorrectAuth(): void
	{
		$adapter = $this->createAdapter('sk-key', AuthMode::API_KEY, 'gpt-4o', 4096, 'openai');

		$adapter->switchProvider('anthropic', 'sub-key', AuthMode::SUBSCRIPTION);

		$authentication = $adapter->getProviderRegistry()->getProviderRequestAuthentication('anthropic');
		$this->assertInstanceOf(AnthropicSubscriptionRequestAuthentication::class, $authentication);
	}

	/**
	 * Tests that OpenAI with subscription mode returns OpenAiSubscriptionRequestAuthentication.
	 */
	public function test_constructor_withOpenaiSubscription_returnsOpenAiSubscriptionAuth(): void
	{
		$adapter = $this->createAdapter('codex-token', AuthMode::SUBSCRIPTION, 'gpt-4o', 4096, 'openai');

		$authentication = $adapter->getProviderRegistry()->getProviderRequestAuthentication('openai');

		$this->assertInstanceOf(OpenAiSubscriptionRequestAuthentication::class, $authentication);
	}

	/**
	 * Tests that claudeCode subscription mode returns ClaudeCode auth.
	 */
	public function test_constructor_withClaudeCodeSubscription_returnsClaudeCodeSubscriptionAuth(): void
	{
		$adapter = $this->createAdapter(
			'sk-ant-oat01-' . str_repeat('a', 90),
			AuthMode::SUBSCRIPTION,
			'claude-opus-4-1',
			4096,
			'claudeCode'
		);

		$authentication = $adapter->getProviderRegistry()->getProviderRequestAuthentication('claudeCode');

		$this->assertInstanceOf(ClaudeCodeSubscriptionRequestAuthentication::class, $authentication);
	}

	/**
	 * Tests that subscription mode with unsupported provider throws exception.
	 */
	public function test_constructor_withUnsupportedProviderSubscription_throwsException(): void
	{
		$this->expectException(AiClientException::class);
		$this->expectExceptionMessage('subscription mode not supported');

		$this->createAdapter('google-token', AuthMode::SUBSCRIPTION, 'gemini-2.0-flash', 4096, 'google');
	}

	/**
	 * Tests that switchProvider to OpenAI with subscription mode works.
	 */
	public function test_switchProvider_toOpenaiSubscription_setsCorrectAuth(): void
	{
		$adapter = $this->createAdapter();

		$adapter->switchProvider('openai', 'codex-token', AuthMode::SUBSCRIPTION);

		$authentication = $adapter->getProviderRegistry()->getProviderRequestAuthentication('openai');
		$this->assertInstanceOf(OpenAiSubscriptionRequestAuthentication::class, $authentication);
	}

	/**
	 * Tests that switchProvider to claudeCode with subscription mode works.
	 */
	public function test_switchProvider_toClaudeCodeSubscription_setsCorrectAuth(): void
	{
		$adapter = $this->createAdapter();

		$adapter->switchProvider(
			'claudeCode',
			'sk-ant-oat01-' . str_repeat('c', 90),
			AuthMode::SUBSCRIPTION
		);

		$authentication = $adapter->getProviderRegistry()->getProviderRequestAuthentication('claudeCode');
		$this->assertInstanceOf(ClaudeCodeSubscriptionRequestAuthentication::class, $authentication);
	}

	/**
	 * Tests that switchProvider does not change provider on failure.
	 */
	public function test_switchProvider_onFailure_preservesOriginalProvider(): void
	{
		$adapter = $this->createAdapter();

		try {
			$adapter->switchProvider('cohere', 'some-key');
		} catch (AiClientException) {
			// Expected.
		}

		$this->assertSame('anthropic', $adapter->getProviderId());
	}

	// ── Model routing tests (createModelInstance) ──

	/**
	 * Tests that OpenAI with subscription mode uses ChatGptCodexTextGenerationModel.
	 *
	 * ChatGptCodexTextGenerationModel adds `stream: true` to every request,
	 * so the presence of that parameter in the captured request data proves
	 * the correct model class was wired.
	 */
	public function test_chat_withOpenAiSubscription_usesChatGptCodexModel(): void
	{
		$captured_request = null;
		$this->mock_transporter = $this->createMock(HttpTransporterInterface::class);
		$this->mock_transporter->method('send')
			->willReturnCallback(function (Request $request) use (&$captured_request) {
				$captured_request = $request;
				$sse_body = "event: response.completed\ndata: " . json_encode([
					'id' => 'resp_test',
					'status' => 'completed',
					'output' => [
						[
							'type' => 'message',
							'role' => 'assistant',
							'content' => [
								['type' => 'output_text', 'text' => 'Hello'],
							],
						],
					],
					'usage' => ['input_tokens' => 10, 'output_tokens' => 5, 'total_tokens' => 15],
				]) . "\n\n";
				return new Response(200, ['Content-Type' => 'text/event-stream'], $sse_body);
			});

		$adapter = $this->createAdapter('codex-token', AuthMode::SUBSCRIPTION, 'o4-mini', 4096, 'openai');
		$messages = [new Message(Message::ROLE_USER, 'Hello')];
		$adapter->chat($messages, 'You are helpful.');

		$this->assertNotNull($captured_request, 'Request should have been captured by the mock transporter.');
		$data = $captured_request->getData();
		$this->assertIsArray($data);
		$this->assertArrayHasKey('stream', $data);
		$this->assertTrue($data['stream'], 'ChatGptCodexTextGenerationModel must set stream to true.');
	}

	/**
	 * Tests that OpenAI with API key mode uses the vendor OpenAiTextGenerationModel.
	 *
	 * The vendor model does NOT add `stream: true`, so verifying its absence
	 * proves the standard model class was wired instead of ChatGptCodexTextGenerationModel.
	 */
	public function test_chat_withOpenAiApiKey_usesVendorModel(): void
	{
		$captured_request = null;
		$this->mock_transporter = $this->createMock(HttpTransporterInterface::class);
		$this->mock_transporter->method('send')
			->willReturnCallback(function (Request $request) use (&$captured_request) {
				$captured_request = $request;
				$json_body = json_encode([
					'id' => 'resp_test',
					'status' => 'completed',
					'output' => [
						[
							'type' => 'message',
							'role' => 'assistant',
							'content' => [
								['type' => 'output_text', 'text' => 'Hello'],
							],
						],
					],
					'usage' => ['input_tokens' => 10, 'output_tokens' => 5, 'total_tokens' => 15],
				]);
				return new Response(200, ['Content-Type' => 'application/json'], $json_body);
			});

		$adapter = $this->createAdapter('sk-test-key', AuthMode::API_KEY, 'gpt-4o', 4096, 'openai');
		$messages = [new Message(Message::ROLE_USER, 'Hello')];
		$adapter->chat($messages, 'You are helpful.');

		$this->assertNotNull($captured_request, 'Request should have been captured by the mock transporter.');
		$data = $captured_request->getData();
		$this->assertIsArray($data);
		$this->assertArrayNotHasKey('stream', $data, 'Vendor OpenAiTextGenerationModel must not set stream.');
	}

	/**
	 * Tests that claudeCode subscription requests use Anthropic messages contract
	 * with Claude Code-specific auth headers.
	 */
	public function test_chat_withClaudeCodeSubscription_usesClaudeCodeHeadersAndMessagesBody(): void
	{
		$captured_request = null;
		$this->mock_transporter = $this->createMock(HttpTransporterInterface::class);
		$this->mock_transporter->method('send')
			->willReturnCallback(function (Request $request) use (&$captured_request) {
				$captured_request = $request;

				$body = json_encode([
					'id' => 'msg_test',
					'role' => 'assistant',
					'content' => [
						['type' => 'text', 'text' => 'Hello from Claude Code'],
					],
					'stop_reason' => 'end_turn',
					'usage' => ['input_tokens' => 11, 'output_tokens' => 7],
				]);

				return new Response(200, ['Content-Type' => 'application/json'], $body);
			});

		$adapter = $this->createAdapter(
			'sk-ant-oat01-' . str_repeat('d', 90),
			AuthMode::SUBSCRIPTION,
			'claude-opus-4-1',
			4096,
			'claudeCode'
		);
		$messages = [new Message(Message::ROLE_USER, 'Hello')];
		$adapter->chat($messages, 'You are helpful.');

		$this->assertNotNull($captured_request, 'Request should have been captured by the mock transporter.');
		$this->assertSame('https://api.anthropic.com/v1/messages', $captured_request->getUri());

		$this->assertSame(
			['Bearer ' . 'sk-ant-oat01-' . str_repeat('d', 90)],
			$captured_request->getHeader('Authorization')
		);
		$this->assertSame(['2023-06-01'], $captured_request->getHeader('anthropic-version'));
		$this->assertSame(['application/json'], $captured_request->getHeader('accept'));
		$this->assertSame(['true'], $captured_request->getHeader('anthropic-dangerous-direct-browser-access'));
		$this->assertStringContainsString(
			'claude-cli/2.1.2',
			(string) $captured_request->getHeaderAsString('user-agent')
		);
		$this->assertSame(['cli'], $captured_request->getHeader('x-app'));
		$this->assertStringContainsString(
			'claude-code-20250219',
			(string) $captured_request->getHeaderAsString('anthropic-beta')
		);
		$this->assertFalse($captured_request->hasHeader('x-api-key'));

		$data = $captured_request->getData();
		$this->assertIsArray($data);
		$this->assertSame('claude-opus-4-1', $data['model']);
		$this->assertSame(4096, $data['max_tokens']);
		$this->assertIsArray($data['messages']);
		$this->assertSame('user', $data['messages'][0]['role']);
	}
}
