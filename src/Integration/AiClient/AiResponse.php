<?php

declare(strict_types=1);

namespace PhpCliAgent\Integration\AiClient;

use PhpCliAgent\Core\Contracts\AiResponseInterface;
use PhpCliAgent\Core\ValueObjects\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPress\AiClient\Tools\DTO\FunctionCall;

/**
 * AI response implementation wrapping GenerativeAiResult from php-ai-client.
 *
 * This class adapts the GenerativeAiResult from the WordPress AI Client library
 * to the AiResponseInterface expected by the agent's core layer.
 *
 * @since n.e.x.t
 */
final class AiResponse implements AiResponseInterface
{
	/**
	 * The wrapped GenerativeAiResult.
	 *
	 * @var GenerativeAiResult
	 */
	private GenerativeAiResult $result;

	/**
	 * Extracted tool calls from the response.
	 *
	 * @var array<int, array{id: string, name: string, arguments: array<string, mixed>}>|null
	 */
	private ?array $tool_calls = null;

	/**
	 * Creates a new AiResponse instance.
	 *
	 * @param GenerativeAiResult $result The underlying AI result.
	 */
	public function __construct(GenerativeAiResult $result)
	{
		$this->result = $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getContent(): string
	{
		$candidates = $this->result->getCandidates();

		if (count($candidates) === 0) {
			return '';
		}

		$message = $candidates[0]->getMessage();
		$text_parts = [];

		foreach ($message->getParts() as $part) {
			$text = $part->getText();
			if ($text !== null && $part->getChannel()->isContent()) {
				$text_parts[] = $text;
			}
		}

		return implode('', $text_parts);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getToolCalls(): array
	{
		if ($this->tool_calls !== null) {
			return $this->tool_calls;
		}

		$this->tool_calls = [];
		$candidates = $this->result->getCandidates();

		if (count($candidates) === 0) {
			return $this->tool_calls;
		}

		$message = $candidates[0]->getMessage();

		foreach ($message->getParts() as $part) {
			$function_call = $part->getFunctionCall();

			if ($function_call !== null) {
				$args = $function_call->getArgs();

				$this->tool_calls[] = [
					'id' => $function_call->getId() ?? uniqid('tool_call_', true),
					'name' => $function_call->getName() ?? '',
					'arguments' => is_array($args) ? $args : [],
				];
			}
		}

		return $this->tool_calls;
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasToolCalls(): bool
	{
		return count($this->getToolCalls()) > 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getStopReason(): string
	{
		$candidates = $this->result->getCandidates();

		if (count($candidates) === 0) {
			return 'unknown';
		}

		$finish_reason = $candidates[0]->getFinishReason();

		return $this->mapFinishReason($finish_reason);
	}

	/**
	 * {@inheritDoc}
	 */
	public function isFinalResponse(): bool
	{
		$stop_reason = $this->getStopReason();

		return $stop_reason === 'end_turn' || $stop_reason === 'stop';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getUsage(): array
	{
		$token_usage = $this->result->getTokenUsage();

		return [
			'input_tokens' => $token_usage->getPromptTokens(),
			'output_tokens' => $token_usage->getCompletionTokens(),
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function getRawResponse(): array
	{
		return $this->result->toArray();
	}

	/**
	 * {@inheritDoc}
	 */
	public function toMessage(): Message
	{
		return Message::assistant(
			$this->getContent(),
			$this->getToolCalls()
		);
	}

	/**
	 * Maps the FinishReasonEnum to the expected string format.
	 *
	 * @param FinishReasonEnum $finish_reason The finish reason from the result.
	 *
	 * @return string The mapped stop reason.
	 */
	private function mapFinishReason(FinishReasonEnum $finish_reason): string
	{
		if ($finish_reason->isStop()) {
			return 'end_turn';
		}

		if ($finish_reason->isToolCalls()) {
			return 'tool_use';
		}

		if ($finish_reason->isLength()) {
			return 'max_tokens';
		}

		if ($finish_reason->isContentFilter()) {
			return 'content_filter';
		}

		if ($finish_reason->isError()) {
			return 'error';
		}

		return 'unknown';
	}
}
