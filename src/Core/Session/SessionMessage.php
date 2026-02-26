<?php

declare(strict_types=1);

namespace WpAiAgent\Core\Session;

use DateTimeImmutable;
use WpAiAgent\Core\ValueObjects\Message;

/**
 * Wrapper for messages within a session context.
 *
 * Adds session-specific metadata to messages such as when the message
 * was added to the session and any session-specific attributes.
 *
 * @since n.e.x.t
 */
final class SessionMessage
{
	private Message $message;
	private DateTimeImmutable $added_at;

	/**
	 * Session-specific attributes.
	 *
	 * @var array<string, mixed>
	 */
	private array $attributes;

	/**
	 * Creates a new SessionMessage instance.
	 *
	 * @param Message                 $message    The wrapped message.
	 * @param DateTimeImmutable|null  $added_at   When the message was added.
	 * @param array<string, mixed>    $attributes Session-specific attributes.
	 */
	public function __construct(
		Message $message,
		?DateTimeImmutable $added_at = null,
		array $attributes = []
	) {
		$this->message = $message;
		$this->added_at = $added_at ?? new DateTimeImmutable();
		$this->attributes = $attributes;
	}

	/**
	 * Creates a SessionMessage from a Message.
	 *
	 * @param Message $message The message to wrap.
	 *
	 * @return self
	 */
	public static function fromMessage(Message $message): self
	{
		return new self($message);
	}

	/**
	 * Returns the wrapped message.
	 *
	 * @return Message
	 */
	public function getMessage(): Message
	{
		return $this->message;
	}

	/**
	 * Returns when the message was added to the session.
	 *
	 * @return DateTimeImmutable
	 */
	public function getAddedAt(): DateTimeImmutable
	{
		return $this->added_at;
	}

	/**
	 * Returns the message role.
	 *
	 * @return string
	 */
	public function getRole(): string
	{
		return $this->message->getRole();
	}

	/**
	 * Returns the message content.
	 *
	 * @return string
	 */
	public function getContent(): string
	{
		return $this->message->getContent();
	}

	/**
	 * Returns an attribute value.
	 *
	 * @param string $key     The attribute key.
	 * @param mixed  $default Default value if key doesn't exist.
	 *
	 * @return mixed
	 */
	public function getAttribute(string $key, mixed $default = null): mixed
	{
		return $this->attributes[$key] ?? $default;
	}

	/**
	 * Sets an attribute value.
	 *
	 * @param string $key   The attribute key.
	 * @param mixed  $value The value to set.
	 *
	 * @return self A new instance with the attribute set.
	 */
	public function withAttribute(string $key, mixed $value): self
	{
		$new_attributes = $this->attributes;
		$new_attributes[$key] = $value;

		return new self($this->message, $this->added_at, $new_attributes);
	}

	/**
	 * Checks if an attribute exists.
	 *
	 * @param string $key The attribute key.
	 *
	 * @return bool
	 */
	public function hasAttribute(string $key): bool
	{
		return array_key_exists($key, $this->attributes);
	}

	/**
	 * Returns all attributes.
	 *
	 * @return array<string, mixed>
	 */
	public function getAttributes(): array
	{
		return $this->attributes;
	}

	/**
	 * Converts to array for serialization.
	 *
	 * @return array{
	 *     message: array<string, mixed>,
	 *     added_at: string,
	 *     attributes: array<string, mixed>
	 * }
	 */
	public function toArray(): array
	{
		return [
			'message' => $this->message->toArray(),
			'added_at' => $this->added_at->format(DateTimeImmutable::ATOM),
			'attributes' => $this->attributes,
		];
	}

	/**
	 * Creates a SessionMessage from an array.
	 *
	 * @param array{
	 *     message: array<string, mixed>,
	 *     added_at?: string,
	 *     attributes?: array<string, mixed>
	 * } $data The serialized data.
	 *
	 * @return self
	 *
	 * @throws \Exception If date parsing fails.
	 */
	public static function fromArray(array $data): self
	{
		$added_at = isset($data['added_at'])
			? new DateTimeImmutable($data['added_at'])
			: null;

		/** @var array{role: string, content: string, tool_call_id?: string, tool_name?: string, tool_calls?: array<int, array{id: string, name: string, arguments: array<string, mixed>}>} $message_data */
		$message_data = $data['message'];

		return new self(
			Message::fromArray($message_data),
			$added_at,
			$data['attributes'] ?? []
		);
	}
}
