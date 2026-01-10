<?php

declare(strict_types=1);

namespace PhpCliAgent\Core\Contracts;

use PhpCliAgent\Core\ValueObjects\Message;

/**
 * Interface for AI model adapters.
 *
 * The AI adapter abstracts the communication with the AI provider (Anthropic Claude,
 * OpenAI, etc.). It handles message formatting, tool declarations, and response parsing.
 *
 * @since n.e.x.t
 */
interface AiAdapterInterface
{
	/**
	 * Sends messages to the AI model and returns the response.
	 *
	 * @param array<int, Message>                                                           $messages The conversation messages.
	 * @param string                                                                        $system   The system prompt.
	 * @param array<int, array{name: string, description: string, parameters?: array<string, mixed>}> $tools    Tool declarations.
	 *
	 * @return AiResponseInterface The model's response.
	 *
	 * @throws \PhpCliAgent\Core\Exceptions\AiAdapterException If the request fails.
	 */
	public function chat(array $messages, string $system, array $tools = []): AiResponseInterface;

	/**
	 * Sends messages and streams the response.
	 *
	 * @param array<int, Message>                                                           $messages The conversation messages.
	 * @param string                                                                        $system   The system prompt.
	 * @param array<int, array{name: string, description: string, parameters?: array<string, mixed>}> $tools    Tool declarations.
	 * @param callable(string): void                                                        $on_chunk Callback for each text chunk.
	 *
	 * @return AiResponseInterface The complete response after streaming.
	 *
	 * @throws \PhpCliAgent\Core\Exceptions\AiAdapterException If the request fails.
	 */
	public function chatStream(
		array $messages,
		string $system,
		array $tools,
		callable $on_chunk
	): AiResponseInterface;

	/**
	 * Sets the model to use.
	 *
	 * @param string $model The model identifier.
	 *
	 * @return void
	 */
	public function setModel(string $model): void;

	/**
	 * Returns the current model.
	 *
	 * @return string
	 */
	public function getModel(): string;

	/**
	 * Sets the maximum tokens for responses.
	 *
	 * @param int $max_tokens The maximum tokens.
	 *
	 * @return void
	 */
	public function setMaxTokens(int $max_tokens): void;

	/**
	 * Sets the temperature for responses.
	 *
	 * @param float $temperature A value between 0.0 and 1.0.
	 *
	 * @return void
	 */
	public function setTemperature(float $temperature): void;

	/**
	 * Returns token usage statistics from the last request.
	 *
	 * @return array{input_tokens: int, output_tokens: int}|null
	 */
	public function getLastUsage(): ?array;
}
