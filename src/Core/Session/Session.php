<?php

declare(strict_types=1);

namespace PhpCliAgent\Core\Session;

use PhpCliAgent\Core\Contracts\SessionInterface;
use PhpCliAgent\Core\Contracts\SessionMetadataInterface;
use PhpCliAgent\Core\ValueObjects\Message;
use PhpCliAgent\Core\ValueObjects\SessionId;

/**
 * Session implementation for conversation persistence.
 *
 * Manages the conversation history, system prompt, metadata, and token usage
 * for a single conversation session with the AI agent.
 *
 * @since n.e.x.t
 */
final class Session implements SessionInterface
{
	private SessionId $id;
	private string $system_prompt;
	private SessionMetadataInterface $metadata;

	/**
	 * Messages in this session.
	 *
	 * @var array<int, Message>
	 */
	private array $messages = [];

	/**
	 * Token usage tracking.
	 *
	 * @var array{input: int, output: int}
	 */
	private array $token_usage = [
		'input' => 0,
		'output' => 0,
	];

	/**
	 * Creates a new Session instance.
	 *
	 * @param SessionId|null              $id            The session identifier.
	 * @param string                      $system_prompt The system prompt.
	 * @param SessionMetadataInterface|null $metadata      The session metadata.
	 */
	public function __construct(
		?SessionId $id = null,
		string $system_prompt = '',
		?SessionMetadataInterface $metadata = null
	) {
		$this->id = $id ?? SessionId::generate();
		$this->system_prompt = $system_prompt;
		$this->metadata = $metadata ?? new SessionMetadata();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getId(): SessionId
	{
		return $this->id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getMessages(): array
	{
		return $this->messages;
	}

	/**
	 * {@inheritDoc}
	 */
	public function addMessage(Message $message): void
	{
		$this->messages[] = $message;
		$this->metadata->setUpdatedAt(new \DateTimeImmutable());

		// Auto-derive title from first user message if not set.
		if ($this->metadata->getTitle() === null && $message->getRole() === Message::ROLE_USER) {
			$this->deriveTitle($message->getContent());
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSystemPrompt(): string
	{
		return $this->system_prompt;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setSystemPrompt(string $prompt): void
	{
		$this->system_prompt = $prompt;
		$this->metadata->setUpdatedAt(new \DateTimeImmutable());
	}

	/**
	 * {@inheritDoc}
	 */
	public function getMetadata(): SessionMetadataInterface
	{
		return $this->metadata;
	}

	/**
	 * {@inheritDoc}
	 */
	public function clearMessages(): void
	{
		$this->messages = [];
		$this->metadata->setUpdatedAt(new \DateTimeImmutable());
	}

	/**
	 * {@inheritDoc}
	 */
	public function getMessageCount(): int
	{
		return count($this->messages);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getLastMessage(): ?Message
	{
		if (0 === count($this->messages)) {
			return null;
		}

		return $this->messages[count($this->messages) - 1];
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		$messages_array = array_map(
			static fn(Message $message): array => $message->toArray(),
			$this->messages
		);

		return [
			'id' => $this->id->toString(),
			'system_prompt' => $this->system_prompt,
			'messages' => $messages_array,
			'metadata' => $this->metadata->toArray(),
			'token_usage' => $this->token_usage,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function getMessagesForApi(): array
	{
		$api_messages = [];

		foreach ($this->messages as $message) {
			// Skip system messages - they're handled separately by the AI adapter.
			if ($message->getRole() === Message::ROLE_SYSTEM) {
				continue;
			}

			$api_messages[] = $message->toArray();
		}

		return $api_messages;
	}

	/**
	 * Adds token usage from an API response.
	 *
	 * @param int $input_tokens  The number of input tokens used.
	 * @param int $output_tokens The number of output tokens used.
	 *
	 * @return void
	 */
	public function addTokenUsage(int $input_tokens, int $output_tokens): void
	{
		$this->token_usage['input'] += $input_tokens;
		$this->token_usage['output'] += $output_tokens;
	}

	/**
	 * Returns the token usage statistics.
	 *
	 * @return array{input: int, output: int, total: int}
	 */
	public function getTokenUsage(): array
	{
		return [
			'input' => $this->token_usage['input'],
			'output' => $this->token_usage['output'],
			'total' => $this->token_usage['input'] + $this->token_usage['output'],
		];
	}

	/**
	 * Resets the token usage counters.
	 *
	 * @return void
	 */
	public function resetTokenUsage(): void
	{
		$this->token_usage = [
			'input' => 0,
			'output' => 0,
		];
	}

	/**
	 * Creates a Session from a serialized array.
	 *
	 * @param array{
	 *     id: string,
	 *     system_prompt: string,
	 *     messages: array<int, array<string, mixed>>,
	 *     metadata: array<string, mixed>,
	 *     token_usage?: array{input: int, output: int}
	 * } $data The serialized session data.
	 *
	 * @return self
	 */
	public static function fromArray(array $data): self
	{
		/** @var array{created_at?: string, updated_at?: string, working_directory?: string, custom?: array<string, mixed>} $metadata_data */
		$metadata_data = $data['metadata'];

		$session = new self(
			SessionId::fromString($data['id']),
			$data['system_prompt'],
			SessionMetadata::fromArray($metadata_data)
		);

		foreach ($data['messages'] as $message_data) {
			/** @var array{role: string, content: string, tool_call_id?: string, tool_name?: string, tool_calls?: array<int, array{id: string, name: string, arguments: array<string, mixed>}>} $message_data */
			$session->messages[] = Message::fromArray($message_data);
		}

		if (isset($data['token_usage'])) {
			$session->token_usage = [
				'input' => $data['token_usage']['input'],
				'output' => $data['token_usage']['output'],
			];
		}

		return $session;
	}

	/**
	 * Derives a session title from message content.
	 *
	 * Truncates to a reasonable length for display.
	 *
	 * @param string $content The message content to derive title from.
	 *
	 * @return void
	 */
	private function deriveTitle(string $content): void
	{
		$max_length = 50;
		$title = trim($content);

		// Remove newlines and extra whitespace.
		$title = preg_replace('/\s+/', ' ', $title) ?? $title;

		if (mb_strlen($title) > $max_length) {
			$title = mb_substr($title, 0, $max_length - 3) . '...';
		}

		if ('' !== $title) {
			$this->metadata->setTitle($title);
		}
	}
}
