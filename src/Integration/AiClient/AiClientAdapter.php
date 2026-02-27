<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\AiClient;

use WpAiAgent\Core\Credential\AuthMode;
use WpAiAgent\Core\Contracts\AiResponseInterface;
use WpAiAgent\Core\Exceptions\AiAdapterException;
use WpAiAgent\Core\Exceptions\AiClientException;
use WpAiAgent\Core\ValueObjects\Message;
use WordPress\AiClient\Builders\PromptBuilder;
use WordPress\AiClient\Common\Exception\InvalidArgumentException as AiClientInvalidArgumentException;
use WordPress\AiClient\Common\Exception\RuntimeException as AiClientRuntimeException;
use WordPress\AiClient\Messages\DTO\Message as AiClientMessage;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\Exception\ClientException;
use WordPress\AiClient\Providers\Http\Exception\NetworkException;
use WordPress\AiClient\Providers\Http\Exception\ServerException;
use WordPress\AiClient\Providers\Http\HttpTransporterFactory;
use WordPress\AiClient\Providers\ProviderRegistry;
use WordPress\AnthropicAiProvider\Authentication\AnthropicApiKeyRequestAuthentication;
use WordPress\AnthropicAiProvider\Provider\AnthropicProvider;
use WordPress\GoogleAiProvider\Authentication\GoogleApiKeyRequestAuthentication;
use WordPress\GoogleAiProvider\Provider\GoogleProvider;
use WordPress\AnthropicAiProvider\Models\AnthropicTextGenerationModel;
use WordPress\GoogleAiProvider\Models\GoogleTextGenerationModel;
use WordPress\OpenAiAiProvider\Models\OpenAiTextGenerationModel;
use WordPress\OpenAiAiProvider\Provider\OpenAiProvider;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

/**
 * AI client adapter wrapping the wordpress/php-ai-client library.
 *
 * This adapter implements the AiClientAdapterInterface, providing a bridge
 * between the agent's core layer and the WordPress AI Client library.
 * It dynamically registers the correct vendor provider (Anthropic, OpenAI,
 * or Google) and handles message conversion, tool declarations, and
 * response parsing.
 *
 * @since n.e.x.t
 */
final class AiClientAdapter implements AiClientAdapterInterface
{
	/**
	 * Default model to use.
	 *
	 * @var string
	 */
	private const DEFAULT_MODEL = 'claude-sonnet-4-20250514';

	/**
	 * Default max tokens for responses.
	 *
	 * @var int
	 */
	private const DEFAULT_MAX_TOKENS = 4096;

	/**
	 * Mapping of provider IDs to their vendor provider classes.
	 *
	 * @var array<string, class-string>
	 */
	private const PROVIDER_CLASSES = [
		'anthropic' => AnthropicProvider::class,
		'openai' => OpenAiProvider::class,
		'google' => GoogleProvider::class,
	];

	/**
	 * Mapping of provider IDs to their environment variable names for API keys.
	 *
	 * @var array<string, string>
	 */
	private const PROVIDER_ENV_KEYS = [
		'anthropic' => 'ANTHROPIC_API_KEY',
		'openai' => 'OPENAI_API_KEY',
		'google' => 'GOOGLE_API_KEY',
	];

	/**
	 * The provider registry instance.
	 *
	 * @var ProviderRegistry
	 */
	private ProviderRegistry $provider_registry;

	/**
	 * The current model identifier.
	 *
	 * @var string
	 */
	private string $model;

	/**
	 * Maximum tokens for responses.
	 *
	 * @var int
	 */
	private int $max_tokens;

	/**
	 * Temperature for responses.
	 *
	 * @var float
	 */
	private float $temperature = 0.7;

	/**
	 * Token usage from the last request.
	 *
	 * @var array{input_tokens: int, output_tokens: int}|null
	 */
	private ?array $last_usage = null;

	/**
	 * The API key for authentication.
	 *
	 * @var string|null
	 */
	private ?string $api_key = null;

	/**
	 * Authentication mode used for provider requests.
	 *
	 * @var AuthMode
	 */
	private AuthMode $auth_mode;

	/**
	 * The current provider identifier.
	 *
	 * @var string
	 */
	private string $provider_id;

	/**
	 * Whether the adapter has been initialized.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * The streaming handler for simulated streaming.
	 *
	 * @var StreamingHandler|null
	 */
	private ?StreamingHandler $streaming_handler = null;

	/**
	 * Creates a new AiClientAdapter instance.
	 *
	 * @since n.e.x.t
	 *
	 * @param string|null                   $api_key          Optional API key. If not provided,
	 *                                                        will be read from the provider's environment variable.
	 * @param AuthMode                      $auth_mode        Authentication mode (api_key or subscription).
	 * @param string                        $model            The model to use.
	 * @param int                           $max_tokens       Maximum tokens for responses.
	 * @param HttpTransporterInterface|null $http_transporter Optional HTTP transporter.
	 * @param string                        $provider_id      The provider ID (e.g., 'anthropic', 'openai', 'google').
	 *
	 * @throws AiClientException If initialization fails.
	 */
	public function __construct(
		?string $api_key = null,
		AuthMode $auth_mode = AuthMode::API_KEY,
		string $model = self::DEFAULT_MODEL,
		int $max_tokens = self::DEFAULT_MAX_TOKENS,
		?HttpTransporterInterface $http_transporter = null,
		string $provider_id = 'anthropic'
	) {
		$this->model = $model;
		$this->max_tokens = $max_tokens;
		$this->auth_mode = $auth_mode;
		$this->provider_id = $provider_id;

		$this->api_key = $api_key ?? $this->getApiKeyFromEnvironment();

		if ($this->api_key === null || $this->api_key === '') {
			throw AiClientException::invalidApiKey();
		}

		$this->initializeProvider($http_transporter);
	}

	/**
	 * {@inheritDoc}
	 */
	public function chat(array $messages, string $system, array $tools = []): AiResponseInterface
	{
		$this->ensureInitialized();

		try {
			$ai_messages = $this->convertMessages($messages);
			$function_declarations = $this->convertToolsToDeclarations($tools);

			$model_instance = $this->createModelInstance();
			$builder = new PromptBuilder($this->provider_registry, $ai_messages);
			$builder->usingModel($model_instance);
			$builder->usingSystemInstruction($system);
			$builder->usingMaxTokens($this->max_tokens);
			$builder->usingTemperature($this->temperature);

			if (count($function_declarations) > 0) {
				$builder->usingFunctionDeclarations(...$function_declarations);
			}

			$result = $builder->generateTextResult();

			$response = new AiResponse($result);
			$this->last_usage = $response->getUsage();

			return $response;
		} catch (ClientException $exception) {
			return $this->handleClientException($exception);
		} catch (ServerException $exception) {
			throw AiAdapterException::requestFailed(
				sprintf('Server error: %s', $exception->getMessage()),
				$exception
			);
		} catch (NetworkException $exception) {
			throw AiAdapterException::requestFailed(
				sprintf('Network error: %s', $exception->getMessage()),
				$exception
			);
		} catch (AiClientInvalidArgumentException $exception) {
			throw AiAdapterException::requestFailed(
				sprintf('Invalid argument: %s', $exception->getMessage()),
				$exception
			);
		} catch (AiClientRuntimeException $exception) {
			throw AiAdapterException::requestFailed(
				sprintf('Runtime error: %s', $exception->getMessage()),
				$exception
			);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function chatStream(
		array $messages,
		string $system,
		array $tools,
		callable $on_chunk
	): AiResponseInterface {
		$response = $this->chat($messages, $system, $tools);

		$content = $response->getContent();

		if ($content !== '') {
			$this->getStreamingHandler()->streamToCallback($content, $on_chunk);
		}

		return $response;
	}

	/**
	 * Gets the streaming handler instance.
	 *
	 * @since n.e.x.t
	 *
	 * @return StreamingHandler
	 */
	public function getStreamingHandler(): StreamingHandler
	{
		if ($this->streaming_handler === null) {
			$this->streaming_handler = new StreamingHandler();
		}

		return $this->streaming_handler;
	}

	/**
	 * Sets a custom streaming handler.
	 *
	 * @since n.e.x.t
	 *
	 * @param StreamingHandler $handler The streaming handler to use.
	 */
	public function setStreamingHandler(StreamingHandler $handler): void
	{
		$this->streaming_handler = $handler;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setModel(string $model): void
	{
		$this->model = $model;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getModel(): string
	{
		return $this->model;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setMaxTokens(int $max_tokens): void
	{
		$this->max_tokens = $max_tokens;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setTemperature(float $temperature): void
	{
		$this->temperature = $temperature;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getLastUsage(): ?array
	{
		return $this->last_usage;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getProviderRegistry(): ProviderRegistry
	{
		return $this->provider_registry;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isConfigured(): bool
	{
		return $this->initialized && $this->api_key !== null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getProviderId(): string
	{
		return $this->provider_id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function switchProvider(string $provider_id, string $api_key, AuthMode $auth_mode = AuthMode::API_KEY): void
	{
		if (!isset(self::PROVIDER_CLASSES[$provider_id])) {
			throw AiClientException::unsupportedProvider($provider_id);
		}

		try {
			$provider_class = self::PROVIDER_CLASSES[$provider_id];
			$this->provider_registry->registerProvider($provider_class);

			$authentication = $this->createAuthentication($provider_id, $auth_mode, $api_key);
			$this->provider_registry->setProviderRequestAuthentication($provider_id, $authentication);
		} catch (AiClientException $exception) {
			throw $exception;
		} catch (\Throwable $exception) {
			throw AiClientException::initializationFailed(
				sprintf('Failed to switch to provider "%s": %s', $provider_id, $exception->getMessage()),
				$exception
			);
		}

		$this->provider_id = $provider_id;
		$this->api_key = $api_key;
		$this->auth_mode = $auth_mode;
	}

	/**
	 * Initializes the provider registry with the configured provider.
	 *
	 * @since n.e.x.t
	 *
	 * @param HttpTransporterInterface|null $http_transporter Optional HTTP transporter.
	 *
	 * @throws AiClientException If initialization fails.
	 */
	private function initializeProvider(?HttpTransporterInterface $http_transporter): void
	{
		if (!isset(self::PROVIDER_CLASSES[$this->provider_id])) {
			throw AiClientException::unsupportedProvider($this->provider_id);
		}

		try {
			$this->provider_registry = new ProviderRegistry();

			$transporter = $http_transporter ?? HttpTransporterFactory::createTransporter();
			$this->provider_registry->setHttpTransporter($transporter);

			$provider_class = self::PROVIDER_CLASSES[$this->provider_id];
			$this->provider_registry->registerProvider($provider_class);

			$authentication = $this->createAuthentication(
				$this->provider_id,
				$this->auth_mode,
				(string) $this->api_key
			);
			$this->provider_registry->setProviderRequestAuthentication($this->provider_id, $authentication);

			$this->initialized = true;
		} catch (AiClientException $exception) {
			throw $exception;
		} catch (\Throwable $exception) {
			throw AiClientException::initializationFailed(
				$exception->getMessage(),
				$exception
			);
		}
	}

	/**
	 * Creates a model instance for the current provider and model ID.
	 *
	 * Constructs the model directly with synthetic metadata instead of
	 * fetching metadata from the provider's API. This avoids API calls
	 * that require scopes (e.g. api.model.read) which subscription
	 * tokens may not have.
	 *
	 * @since n.e.x.t
	 *
	 * @return \WordPress\AiClient\Providers\Models\Contracts\ModelInterface The model instance.
	 *
	 * @throws AiClientException If the provider is not supported.
	 */
	private function createModelInstance(): \WordPress\AiClient\Providers\Models\Contracts\ModelInterface
	{
		if (!isset(self::PROVIDER_CLASSES[$this->provider_id])) {
			throw AiClientException::unsupportedProvider($this->provider_id);
		}

		$provider_class = self::PROVIDER_CLASSES[$this->provider_id];
		$provider_metadata = $provider_class::metadata();

		// Build metadata locally to avoid API calls (e.g. OpenAI's /models
		// endpoint) which may fail with scoped subscription tokens.
		$model_metadata = new ModelMetadata(
			$this->model,
			$this->model,
			[CapabilityEnum::textGeneration()],
			[]
		);

		// Use custom model for OpenAI subscription (ChatGPT backend requires SSE streaming).
		if ($this->provider_id === 'openai' && $this->auth_mode === AuthMode::SUBSCRIPTION) {
			$model_instance = new ChatGptCodexTextGenerationModel($model_metadata, $provider_metadata);
		} else {
			/** @var \WordPress\AiClient\Providers\Models\Contracts\ModelInterface $model_instance */
			$model_instance = match ($this->provider_id) {
				'anthropic' => new AnthropicTextGenerationModel($model_metadata, $provider_metadata),
				'openai'    => new OpenAiTextGenerationModel($model_metadata, $provider_metadata),
				'google'    => new GoogleTextGenerationModel($model_metadata, $provider_metadata),
			};
		}

		$this->provider_registry->bindModelDependencies($model_instance);
		return $model_instance;
	}

	/**
	 * Creates the appropriate authentication object for a given provider.
	 *
	 * @since n.e.x.t
	 *
	 * @param string   $provider_id The provider identifier.
	 * @param AuthMode $auth_mode   The authentication mode.
	 * @param string   $api_key     The API key.
	 *
	 * @return RequestAuthenticationInterface The authentication instance.
	 */
	private function createAuthentication(
		string $provider_id,
		AuthMode $auth_mode,
		string $api_key
	): RequestAuthenticationInterface {
		if ($auth_mode === AuthMode::SUBSCRIPTION) {
			return match ($provider_id) {
				'anthropic' => new AnthropicSubscriptionRequestAuthentication($api_key),
				'openai'    => new OpenAiSubscriptionRequestAuthentication($api_key),
				default     => throw AiClientException::unsupportedProvider(
					$provider_id . ' (subscription mode not supported)'
				),
			};
		}

		return match ($provider_id) {
			'anthropic' => new AnthropicApiKeyRequestAuthentication($api_key),
			'google' => new GoogleApiKeyRequestAuthentication($api_key),
			'openai' => new ApiKeyRequestAuthentication($api_key),
			default => throw AiClientException::unsupportedProvider($provider_id),
		};
	}

	/**
	 * Ensures the adapter is properly initialized.
	 *
	 * @throws AiClientException If the adapter is not initialized.
	 */
	private function ensureInitialized(): void
	{
		if (!$this->initialized) {
			throw AiClientException::initializationFailed(
				'AI client adapter is not properly initialized'
			);
		}
	}

	/**
	 * Gets the API key from environment variables for the current provider.
	 *
	 * @since n.e.x.t
	 *
	 * @return string|null The API key, or null if not set.
	 */
	private function getApiKeyFromEnvironment(): ?string
	{
		$env_key = self::PROVIDER_ENV_KEYS[$this->provider_id] ?? 'ANTHROPIC_API_KEY';

		$api_key = getenv($env_key);

		if ($api_key === false) {
			if (defined($env_key)) {
				return (string) constant($env_key);
			}
			return null;
		}

		return $api_key;
	}

	/**
	 * Converts agent Message value objects to AI client messages.
	 *
	 * @param array<int, Message> $messages The messages to convert.
	 *
	 * @return array<int, AiClientMessage> The converted messages.
	 *
	 * @throws AiClientException If message conversion fails.
	 */
	private function convertMessages(array $messages): array
	{
		$converted = [];

		foreach ($messages as $message) {
			$converted[] = $this->convertMessage($message);
		}

		return $converted;
	}

	/**
	 * Converts a single agent Message to an AI client message.
	 *
	 * @param Message $message The message to convert.
	 *
	 * @return AiClientMessage The converted message.
	 *
	 * @throws AiClientException If the message role is not supported.
	 */
	private function convertMessage(Message $message): AiClientMessage
	{
		$role = $message->getRole();

		if ($role === Message::ROLE_USER) {
			return $this->createUserMessage($message);
		}

		if ($role === Message::ROLE_ASSISTANT) {
			return $this->createAssistantMessage($message);
		}

		if ($role === Message::ROLE_TOOL) {
			return $this->createToolResultMessage($message);
		}

		throw AiClientException::messageFormattingFailed(
			sprintf('Unsupported message role: %s', $role)
		);
	}

	/**
	 * Creates a user message from the agent Message.
	 *
	 * @param Message $message The message to convert.
	 *
	 * @return UserMessage The user message.
	 */
	private function createUserMessage(Message $message): UserMessage
	{
		$parts = [new MessagePart($message->getContent())];

		return new UserMessage($parts);
	}

	/**
	 * Creates an assistant/model message from the agent Message.
	 *
	 * @param Message $message The message to convert.
	 *
	 * @return ModelMessage The model message.
	 */
	private function createAssistantMessage(Message $message): ModelMessage
	{
		$parts = [];

		$content = $message->getContent();
		if ($content !== '') {
			$parts[] = new MessagePart($content);
		}

		foreach ($message->getToolCalls() as $tool_call) {
			$arguments = $tool_call['arguments'];
			$function_call = new FunctionCall(
				$tool_call['id'],
				$tool_call['name'],
				empty($arguments) ? new \stdClass() : $arguments
			);
			$parts[] = new MessagePart($function_call);
		}

		return new ModelMessage($parts);
	}

	/**
	 * Creates a tool result message from the agent Message.
	 *
	 * @param Message $message The message to convert.
	 *
	 * @return UserMessage The user message containing function response.
	 */
	private function createToolResultMessage(Message $message): UserMessage
	{
		$function_response = new FunctionResponse(
			$message->getToolCallId() ?? '',
			$message->getToolName() ?? '',
			$message->getContent()
		);

		$parts = [new MessagePart($function_response)];

		return new UserMessage($parts);
	}

	/**
	 * Converts tool declarations to FunctionDeclaration objects.
	 *
	 * @param array<int, array{
	 *     name: string,
	 *     description: string,
	 *     parameters?: array<string, mixed>
	 * }> $tools The tool declarations.
	 *
	 * @return array<int, FunctionDeclaration> The function declarations.
	 */
	private function convertToolsToDeclarations(array $tools): array
	{
		$declarations = [];

		foreach ($tools as $tool) {
			$declarations[] = new FunctionDeclaration(
				$tool['name'],
				$tool['description'],
				$tool['parameters'] ?? null
			);
		}

		return $declarations;
	}

	/**
	 * Handles client exceptions from the AI provider.
	 *
	 * @param ClientException $exception The client exception.
	 *
	 * @return never
	 *
	 * @throws AiAdapterException Always thrown with appropriate error type.
	 */
	private function handleClientException(ClientException $exception): never
	{
		$status_code = $exception->getCode();
		$message = $exception->getMessage();

		if ($status_code === 401) {
			throw AiAdapterException::authenticationFailed();
		}

		if ($status_code === 429) {
			// Try to extract retry-after from the message or use a default
			$retry_after = 60;
			if (preg_match('/retry.*?(\d+)/i', $message, $matches)) {
				$retry_after = (int) $matches[1];
			}
			throw AiAdapterException::rateLimited($retry_after);
		}

		if ($status_code === 400 && stripos($message, 'context') !== false) {
			// Extract max tokens if possible
			$max_tokens = 0;
			if (preg_match('/(\d+)\s*tokens?/i', $message, $matches)) {
				$max_tokens = (int) $matches[1];
			}

			if ($max_tokens > 0) {
				throw AiAdapterException::contextLengthExceeded($max_tokens);
			}
		}

		throw AiAdapterException::requestFailed(
			sprintf('Client error (%d): %s', $status_code, $message),
			$exception
		);
	}
}
