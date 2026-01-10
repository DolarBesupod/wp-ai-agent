<?php

declare(strict_types=1);

namespace PhpCliAgent\Integration\AiClient;

use PhpCliAgent\Core\Contracts\AiResponseInterface;
use PhpCliAgent\Core\Exceptions\AiAdapterException;
use PhpCliAgent\Core\Exceptions\AiClientException;
use PhpCliAgent\Core\ValueObjects\Message;
use WordPress\AiClient\Builders\PromptBuilder;
use WordPress\AiClient\Common\Exception\InvalidArgumentException as AiClientInvalidArgumentException;
use WordPress\AiClient\Common\Exception\RuntimeException as AiClientRuntimeException;
use WordPress\AiClient\Messages\DTO\Message as AiClientMessage;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Exception\ClientException;
use WordPress\AiClient\Providers\Http\Exception\NetworkException;
use WordPress\AiClient\Providers\Http\Exception\ServerException;
use WordPress\AiClient\Providers\Http\HttpTransporterFactory;
use WordPress\AiClient\Providers\ProviderRegistry;
use WordPress\AiClient\ProviderImplementations\Anthropic\AnthropicApiKeyRequestAuthentication;
use WordPress\AiClient\ProviderImplementations\Anthropic\AnthropicProvider;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

/**
 * AI client adapter wrapping the wordpress/php-ai-client library.
 *
 * This adapter implements the AiClientAdapterInterface, providing a bridge
 * between the agent's core layer and the WordPress AI Client library.
 * It configures the Anthropic Claude provider and handles message conversion,
 * tool declarations, and response parsing.
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
	 * @param string|null                    $api_key          Optional API key. If not provided,
	 *                                                         will be read from ANTHROPIC_API_KEY environment variable.
	 * @param string                         $model            The model to use.
	 * @param int                            $max_tokens       Maximum tokens for responses.
	 * @param HttpTransporterInterface|null  $http_transporter Optional HTTP transporter.
	 *
	 * @throws AiClientException If initialization fails.
	 */
	public function __construct(
		?string $api_key = null,
		string $model = self::DEFAULT_MODEL,
		int $max_tokens = self::DEFAULT_MAX_TOKENS,
		?HttpTransporterInterface $http_transporter = null
	) {
		$this->model = $model;
		$this->max_tokens = $max_tokens;

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

			$builder = new PromptBuilder($this->provider_registry, $ai_messages);
			$builder->usingProvider('anthropic');
			$builder->usingModelPreference($this->model);
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
		return 'anthropic';
	}

	/**
	 * Initializes the provider registry with the Anthropic provider.
	 *
	 * @param HttpTransporterInterface|null $http_transporter Optional HTTP transporter.
	 *
	 * @throws AiClientException If initialization fails.
	 */
	private function initializeProvider(?HttpTransporterInterface $http_transporter): void
	{
		try {
			$this->provider_registry = new ProviderRegistry();

			$transporter = $http_transporter ?? HttpTransporterFactory::createTransporter();
			$this->provider_registry->setHttpTransporter($transporter);

			$this->provider_registry->registerProvider(AnthropicProvider::class);

			$authentication = new AnthropicApiKeyRequestAuthentication($this->api_key);
			$this->provider_registry->setProviderRequestAuthentication('anthropic', $authentication);

			$this->initialized = true;
		} catch (\Throwable $exception) {
			throw AiClientException::initializationFailed(
				$exception->getMessage(),
				$exception
			);
		}
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
	 * Gets the API key from environment variables.
	 *
	 * @return string|null The API key, or null if not set.
	 */
	private function getApiKeyFromEnvironment(): ?string
	{
		$api_key = getenv('ANTHROPIC_API_KEY');

		if ($api_key === false) {
			if (defined('ANTHROPIC_API_KEY')) {
				return (string) constant('ANTHROPIC_API_KEY');
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
			$function_call = new FunctionCall(
				$tool_call['id'],
				$tool_call['name'],
				$tool_call['arguments']
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
	 * @param array<int, array{name: string, description: string, parameters?: array<string, mixed>}> $tools The tool declarations.
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
