<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Contracts;

/**
 * Interface for AI model responses.
 *
 * Represents a response from the AI model, including text content, tool calls,
 * and metadata about the response.
 *
 * @since 0.1.0
 */
interface AiResponseInterface
{
	/**
	 * Returns the text content of the response.
	 *
	 * @return string
	 */
	public function getContent(): string;

	/**
	 * Returns the tool calls requested by the model.
	 *
	 * @return array<int, array{id: string, name: string, arguments: array<string, mixed>}>
	 */
	public function getToolCalls(): array;

	/**
	 * Checks if the response contains tool calls.
	 *
	 * @return bool
	 */
	public function hasToolCalls(): bool;

	/**
	 * Returns the stop reason for the response.
	 *
	 * Common values: "end_turn", "tool_use", "max_tokens", "stop_sequence"
	 *
	 * @return string
	 */
	public function getStopReason(): string;

	/**
	 * Checks if this is a final response (no more tool calls needed).
	 *
	 * @return bool
	 */
	public function isFinalResponse(): bool;

	/**
	 * Returns the token usage for this response.
	 *
	 * @return array{input_tokens: int, output_tokens: int}
	 */
	public function getUsage(): array;

	/**
	 * Returns the raw response data from the AI provider.
	 *
	 * @return array<string, mixed>
	 */
	public function getRawResponse(): array;

	/**
	 * Converts the response to a Message value object.
	 *
	 * @return \Automattic\WpAiAgent\Core\ValueObjects\Message
	 */
	public function toMessage(): \Automattic\WpAiAgent\Core\ValueObjects\Message;
}
