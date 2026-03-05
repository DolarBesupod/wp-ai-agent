<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Integration\AiClient;

use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Tools\DTO\FunctionCall;

/**
 * Extracts FunctionCall objects from AI responses.
 *
 * This class is responsible for iterating through message parts
 * in a GenerativeAiResult or Message and extracting all FunctionCall
 * objects. It provides a clean interface for obtaining tool calls
 * from AI responses without depending on the full response wrapper.
 *
 * @since n.e.x.t
 */
final class ToolCallExtractor
{
	/**
	 * Extracts FunctionCall objects from a GenerativeAiResult.
	 *
	 * @param GenerativeAiResult $result The AI result to extract from.
	 *
	 * @return array<int, FunctionCall> Array of FunctionCall objects.
	 */
	public function fromResult(GenerativeAiResult $result): array
	{
		$candidates = $result->getCandidates();

		if (count($candidates) === 0) {
			return [];
		}

		return $this->fromMessage($candidates[0]->getMessage());
	}

	/**
	 * Extracts FunctionCall objects from a Message.
	 *
	 * @param Message $message The message to extract from.
	 *
	 * @return array<int, FunctionCall> Array of FunctionCall objects.
	 */
	public function fromMessage(Message $message): array
	{
		$function_calls = [];

		foreach ($message->getParts() as $part) {
			if ($part->getType()->isFunctionCall()) {
				$function_call = $part->getFunctionCall();

				if ($function_call !== null) {
					$function_calls[] = $function_call;
				}
			}
		}

		return $function_calls;
	}

	/**
	 * Checks if a GenerativeAiResult contains any function calls.
	 *
	 * @param GenerativeAiResult $result The AI result to check.
	 *
	 * @return bool True if the result contains function calls, false otherwise.
	 */
	public function hasToolCalls(GenerativeAiResult $result): bool
	{
		return count($this->fromResult($result)) > 0;
	}

	/**
	 * Extracts FunctionCall objects and converts them to the array format.
	 *
	 * This method provides compatibility with the format used by AiResponse.
	 *
	 * @param GenerativeAiResult $result The AI result to extract from.
	 *
	 * @return array<int, array{id: string, name: string, arguments: array<string, mixed>}>
	 */
	public function toArrayFormat(GenerativeAiResult $result): array
	{
		$function_calls = $this->fromResult($result);
		$array_format = [];

		foreach ($function_calls as $function_call) {
			$args = $function_call->getArgs();

			$array_format[] = [
				'id' => $function_call->getId() ?? uniqid('tool_call_', true),
				'name' => $function_call->getName() ?? '',
				'arguments' => is_array($args) ? $args : [],
			];
		}

		return $array_format;
	}
}
