<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\ValueObjects;

/**
 * Value object representing a message in a conversation.
 *
 * @since n.e.x.t
 */
final class Message
{
	public const ROLE_SYSTEM = 'system';
	public const ROLE_USER = 'user';
	public const ROLE_ASSISTANT = 'assistant';
	public const ROLE_TOOL = 'tool';

	private string $role;
	private string $content;

	/**
	 * Optional tool call ID for tool result messages.
	 *
	 * @var string|null
	 */
	private ?string $tool_call_id;

	/**
	 * Optional tool name for tool result messages.
	 *
	 * @var string|null
	 */
	private ?string $tool_name;

	/**
	 * Optional tool calls made by the assistant.
	 *
	 * @var array<int, array{id: string, name: string, arguments: array<string, mixed>}>
	 */
	private array $tool_calls;

	/**
	 * Creates a new Message instance.
	 *
	 * @param string                                                                        $role         The message role.
	 * @param string                                                                        $content      The message content.
	 * @param string|null                                                                   $tool_call_id Optional tool call ID.
	 * @param string|null                                                                   $tool_name    Optional tool name.
	 * @param array<int, array{id: string, name: string, arguments: array<string, mixed>}> $tool_calls   Optional tool calls.
	 */
	public function __construct(
		string $role,
		string $content,
		?string $tool_call_id = null,
		?string $tool_name = null,
		array $tool_calls = []
	) {
		$valid_roles = [self::ROLE_SYSTEM, self::ROLE_USER, self::ROLE_ASSISTANT, self::ROLE_TOOL];
		if (!in_array($role, $valid_roles, true)) {
			throw new \InvalidArgumentException(
				sprintf('Invalid role "%s". Must be one of: %s', $role, implode(', ', $valid_roles))
			);
		}

		$this->role = $role;
		$this->content = $content;
		$this->tool_call_id = $tool_call_id;
		$this->tool_name = $tool_name;
		$this->tool_calls = $tool_calls;
	}

	/**
	 * Creates a user message.
	 *
	 * @param string $content The message content.
	 *
	 * @return self
	 */
	public static function user(string $content): self
	{
		return new self(self::ROLE_USER, $content);
	}

	/**
	 * Creates an assistant message.
	 *
	 * @param string                                                                        $content    The message content.
	 * @param array<int, array{id: string, name: string, arguments: array<string, mixed>}> $tool_calls Optional tool calls.
	 *
	 * @return self
	 */
	public static function assistant(string $content, array $tool_calls = []): self
	{
		return new self(self::ROLE_ASSISTANT, $content, null, null, $tool_calls);
	}

	/**
	 * Creates a system message.
	 *
	 * @param string $content The message content.
	 *
	 * @return self
	 */
	public static function system(string $content): self
	{
		return new self(self::ROLE_SYSTEM, $content);
	}

	/**
	 * Creates a tool result message.
	 *
	 * @param string $tool_call_id The tool call ID this result responds to.
	 * @param string $tool_name    The name of the tool.
	 * @param string $content      The tool result content.
	 *
	 * @return self
	 */
	public static function toolResult(string $tool_call_id, string $tool_name, string $content): self
	{
		return new self(self::ROLE_TOOL, $content, $tool_call_id, $tool_name);
	}

	/**
	 * Returns the message role.
	 *
	 * @return string
	 */
	public function getRole(): string
	{
		return $this->role;
	}

	/**
	 * Returns the message content.
	 *
	 * @return string
	 */
	public function getContent(): string
	{
		return $this->content;
	}

	/**
	 * Returns the tool call ID if present.
	 *
	 * @return string|null
	 */
	public function getToolCallId(): ?string
	{
		return $this->tool_call_id;
	}

	/**
	 * Returns the tool name if present.
	 *
	 * @return string|null
	 */
	public function getToolName(): ?string
	{
		return $this->tool_name;
	}

	/**
	 * Returns the tool calls if present.
	 *
	 * @return array<int, array{id: string, name: string, arguments: array<string, mixed>}>
	 */
	public function getToolCalls(): array
	{
		return $this->tool_calls;
	}

	/**
	 * Checks if this message has tool calls.
	 *
	 * @return bool
	 */
	public function hasToolCalls(): bool
	{
		return count($this->tool_calls) > 0;
	}

	/**
	 * Checks if this is a tool result message.
	 *
	 * @return bool
	 */
	public function isToolResult(): bool
	{
		return $this->role === self::ROLE_TOOL;
	}

	/**
	 * Converts the message to an array representation.
	 *
	 * @return array{
	 *     role: string,
	 *     content: string,
	 *     tool_call_id?: string,
	 *     tool_name?: string,
	 *     tool_calls?: array<int, array{id: string, name: string, arguments: array<string, mixed>}>
	 * }
	 */
	public function toArray(): array
	{
		$data = [
			'role' => $this->role,
			'content' => $this->content,
		];

		if ($this->tool_call_id !== null) {
			$data['tool_call_id'] = $this->tool_call_id;
		}

		if ($this->tool_name !== null) {
			$data['tool_name'] = $this->tool_name;
		}

		if (count($this->tool_calls) > 0) {
			$data['tool_calls'] = $this->tool_calls;
		}

		return $data;
	}

	/**
	 * Creates a Message from an array representation.
	 *
	 * @param array{
	 *     role: string,
	 *     content: string,
	 *     tool_call_id?: string,
	 *     tool_name?: string,
	 *     tool_calls?: array<int, array{id: string, name: string, arguments: array<string, mixed>}>
	 * } $data The array data.
	 *
	 * @return self
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			$data['role'],
			$data['content'],
			$data['tool_call_id'] ?? null,
			$data['tool_name'] ?? null,
			$data['tool_calls'] ?? []
		);
	}
}
