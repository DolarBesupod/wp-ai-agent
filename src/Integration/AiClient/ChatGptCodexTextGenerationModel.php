<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\AiClient;

use WpAiAgent\Core\Exceptions\AiClientException;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\Contracts\WithHttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithRequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ClientException;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\WebSearch;
use WordPress\OpenAiAiProvider\Provider\OpenAiProvider;

/**
 * Text generation model for the ChatGPT Codex backend API with streaming SSE.
 *
 * This model sends requests to the OpenAI Responses API with `stream: true`
 * and `store: false`, then parses the accumulated SSE body to extract the
 * `response.completed` event. It reuses the same request-building and
 * response-parsing logic as the vendor OpenAiTextGenerationModel but adds
 * streaming parameters and SSE parsing via SseResponseParser.
 *
 * @since n.e.x.t
 *
 * @phpstan-type OutputContentData array{
 *     type: string,
 *     text?: string,
 *     call_id?: string,
 *     name?: string,
 *     arguments?: string
 * }
 * @phpstan-type OutputItemData array{
 *     type: string,
 *     id?: string,
 *     role?: string,
 *     status?: string,
 *     content?: list<OutputContentData>
 * }
 * @phpstan-type UsageData array{
 *     input_tokens?: int,
 *     output_tokens?: int,
 *     total_tokens?: int
 * }
 * @phpstan-type ResponseData array{
 *     id?: string,
 *     status?: string,
 *     output?: list<OutputItemData>,
 *     output_text?: string,
 *     usage?: UsageData
 * }
 */
final class ChatGptCodexTextGenerationModel extends AbstractApiBasedModel implements
	TextGenerationModelInterface,
	WithHttpTransporterInterface,
	WithRequestAuthenticationInterface
{
	/**
	 * {@inheritDoc}
	 *
	 * Sends a streaming request to the OpenAI Responses API and parses the
	 * SSE body for the `response.completed` event.
	 *
	 * @since n.e.x.t
	 */
	public function generateTextResult(array $prompt): GenerativeAiResult
	{
		$http_transporter = $this->getHttpTransporter();

		$params = $this->prepareGenerateTextParams($prompt);

		// Force streaming and disable server-side storage for Codex backend.
		$params['stream'] = true;
		$params['store'] = false;

		$request = new Request(
			HttpMethodEnum::POST(),
			OpenAiProvider::url('responses'),
			['Content-Type' => 'application/json'],
			$params,
			$this->getRequestOptions()
		);

		// Add authentication credentials to the request.
		$request = $this->getRequestAuthentication()->authenticateRequest($request);

		// Send and process the request.
		$response = $http_transporter->send($request);

		try {
			ResponseUtil::throwIfNotSuccessful($response);
		} catch (ClientException $e) {
			// Include the raw response body in the error for better debugging,
			// as the ChatGPT backend may return error details not extracted by
			// the default error message extractor.
			$response_body = $response->getBody();
			if ($response_body !== null && $response_body !== '') {
				throw AiClientException::streamingFailed(
					sprintf(
						'ChatGPT backend returned HTTP %d: %s',
						$response->getStatusCode(),
						$response_body
					)
				);
			}
			throw $e;
		}

		$body = $response->getBody();
		if ($body === null || trim($body) === '') {
			throw AiClientException::emptyResponse();
		}

		$event_data = SseResponseParser::extractEventData($body, 'response.completed');

		// The response.completed SSE event wraps the actual response object
		// under a "response" key: {"type":"response.completed","response":{...}}.
		if (isset($event_data['response']) && is_array($event_data['response'])) {
			/** @var ResponseData $response_data */
			$response_data = $event_data['response'];
		} else {
			/** @var ResponseData $response_data */
			$response_data = $event_data;
		}

		return $this->parseResponseDataToGenerativeAiResult($response_data);
	}

	/**
	 * Prepares the given prompt and the model configuration into parameters for the API request.
	 *
	 * The `instructions` parameter is always included (defaulting to an empty string)
	 * because the ChatGPT backend requires it.
	 *
	 * @since n.e.x.t
	 *
	 * @param array<int, Message> $prompt The prompt messages.
	 *
	 * @return array<string, mixed> The parameters for the API request.
	 */
	private function prepareGenerateTextParams(array $prompt): array
	{
		$config = $this->getConfig();

		$params = [
			'model' => $this->metadata()->getId(),
			'input' => $this->prepareInputParam($prompt),
		];

		// The ChatGPT backend requires the instructions parameter.
		$system_instruction = $config->getSystemInstruction();
		$params['instructions'] = $system_instruction !== null ? $system_instruction : '';

		// The ChatGPT Codex backend only accepts: model, input, instructions,
		// stream, store, and tools. Parameters like max_output_tokens, temperature,
		// top_p, and text (JSON schema) are not supported and return HTTP 400.

		$function_declarations = $config->getFunctionDeclarations();
		$web_search = $config->getWebSearch();

		if (is_array($function_declarations) || $web_search) {
			$params['tools'] = $this->prepareToolsParam(
				$function_declarations,
				$web_search
			);
		}

		$custom_options = $config->getCustomOptions();
		foreach ($custom_options as $key => $value) {
			if (isset($params[$key])) {
				throw new InvalidArgumentException(
					sprintf(
						'The custom option "%s" conflicts with an existing parameter.',
						$key
					)
				);
			}
			$params[$key] = $value;
		}

		return $params;
	}

	/**
	 * Prepares the input parameter for the API request.
	 *
	 * @since n.e.x.t
	 *
	 * @param array<int, Message> $messages The messages to prepare.
	 *
	 * @return list<array<string, mixed>> The prepared input parameter.
	 */
	private function prepareInputParam(array $messages): array
	{
		$this->validateMessages($messages);

		$input = [];
		foreach ($messages as $message) {
			$input_item = $this->getMessageInputItem($message);
			if ($input_item !== null) {
				$input[] = $input_item;
			}
		}
		return $input;
	}

	/**
	 * Validates that the messages are appropriate for the OpenAI Responses API.
	 *
	 * The OpenAI Responses API requires function calls and function responses to be
	 * sent as top-level input items rather than nested in message content. As such,
	 * they must be the only part in a message.
	 *
	 * @since n.e.x.t
	 *
	 * @param array<int, Message> $messages The messages to validate.
	 *
	 * @throws InvalidArgumentException If validation fails.
	 */
	private function validateMessages(array $messages): void
	{
		foreach ($messages as $message) {
			$parts = $message->getParts();

			if (count($parts) <= 1) {
				continue;
			}

			foreach ($parts as $part) {
				$type = $part->getType();

				if ($type->isFunctionCall()) {
					throw new InvalidArgumentException(
						'Function call parts must be the only part in a message for the OpenAI Responses API.'
					);
				}

				if ($type->isFunctionResponse()) {
					throw new InvalidArgumentException(
						'Function response parts must be the only part in a message for the OpenAI Responses API.'
					);
				}
			}
		}
	}

	/**
	 * Converts a Message object to a Responses API input item.
	 *
	 * @since n.e.x.t
	 *
	 * @param Message $message The message to convert.
	 *
	 * @return array<string, mixed>|null The input item, or null if the message is empty.
	 */
	private function getMessageInputItem(Message $message): ?array
	{
		$parts = $message->getParts();

		if (empty($parts)) {
			return null;
		}

		$role = $message->getRole();
		$content = [];
		foreach ($parts as $part) {
			$part_data = $this->getMessagePartData($part, $role);

			// Function calls and responses are top-level items, not wrapped in a message.
			// validateMessages() ensures these are the only part in a message.
			$part_type = $part_data['type'] ?? '';
			if ($part_type === 'function_call' || $part_type === 'function_call_output') {
				return $part_data;
			}

			$content[] = $part_data;
		}

		return [
			'type' => 'message',
			'role' => $this->getMessageRoleString($role),
			'content' => $content,
		];
	}

	/**
	 * Returns the OpenAI API specific role string for the given message role.
	 *
	 * @since n.e.x.t
	 *
	 * @param MessageRoleEnum $role The message role.
	 *
	 * @return string The role for the API request.
	 */
	private function getMessageRoleString(MessageRoleEnum $role): string
	{
		if ($role === MessageRoleEnum::model()) {
			return 'assistant';
		}
		return 'user';
	}

	/**
	 * Returns the OpenAI API specific data for a message part.
	 *
	 * @since n.e.x.t
	 *
	 * @param MessagePart     $part The message part to get the data for.
	 * @param MessageRoleEnum $role The role of the message containing the part.
	 *
	 * @return array<string, mixed> The data for the message part.
	 *
	 * @throws InvalidArgumentException If the message part type or data is unsupported.
	 */
	private function getMessagePartData(MessagePart $part, MessageRoleEnum $role): array
	{
		$type = $part->getType();
		if ($type->isText()) {
			return [
				'type' => $role->isModel() ? 'output_text' : 'input_text',
				'text' => $part->getText(),
			];
		}
		if ($type->isFile()) {
			$file = $part->getFile();
			if (!$file) {
				throw new RuntimeException(
					'The file typed message part must contain a file.'
				);
			}
			if ($file->isRemote()) {
				$file_url = $file->getUrl();
				if (!$file_url) {
					throw new RuntimeException(
						'The remote file must contain a URL.'
					);
				}
				if ($file->isImage()) {
					return [
						'type' => 'input_image',
						'image_url' => $file_url,
					];
				}
				return [
					'type' => 'input_file',
					'file_url' => $file_url,
				];
			}
			$data_uri = $file->getDataUri();
			if (!$data_uri) {
				throw new RuntimeException(
					'The inline file must contain base64 data.'
				);
			}
			if ($file->isImage()) {
				return [
					'type' => 'input_image',
					'image_url' => $data_uri,
				];
			}
			return [
				'type' => 'input_file',
				'filename' => 'file',
				'file_data' => $data_uri,
			];
		}
		if ($type->isFunctionCall()) {
			$function_call = $part->getFunctionCall();
			if (!$function_call) {
				throw new RuntimeException(
					'The function_call typed message part must contain a function call.'
				);
			}
			return [
				'type' => 'function_call',
				'call_id' => $function_call->getId(),
				'name' => $function_call->getName(),
				'arguments' => json_encode($function_call->getArgs()),
			];
		}
		if ($type->isFunctionResponse()) {
			$function_response = $part->getFunctionResponse();
			if (!$function_response) {
				throw new RuntimeException(
					'The function_response typed message part must contain a function response.'
				);
			}
			return [
				'type' => 'function_call_output',
				'call_id' => $function_response->getId(),
				'output' => json_encode($function_response->getResponse()),
			];
		}
		throw new InvalidArgumentException(
			sprintf(
				'Unsupported message part type "%s".',
				$type
			)
		);
	}

	/**
	 * Prepares the tools parameter for the API request.
	 *
	 * @since n.e.x.t
	 *
	 * @param list<FunctionDeclaration>|null $function_declarations The function declarations, or null if none.
	 * @param WebSearch|null                 $web_search            The web search config, or null if none.
	 *
	 * @return list<array<string, mixed>> The prepared tools parameter.
	 */
	private function prepareToolsParam(
		?array $function_declarations,
		?WebSearch $web_search
	): array {
		$tools = [];

		if (is_array($function_declarations)) {
			foreach ($function_declarations as $function_declaration) {
				$tools[] = [
					'type' => 'function',
					'name' => $function_declaration->getName(),
					'description' => $function_declaration->getDescription(),
					'parameters' => $function_declaration->getParameters(),
				];
			}
		}

		if ($web_search) {
			$tools[] = ['type' => 'web_search'];
		}

		return $tools;
	}

	/**
	 * Parses the decoded response data into a GenerativeAiResult.
	 *
	 * @since n.e.x.t
	 *
	 * @param array<string, mixed> $response_data The decoded response data from the SSE event.
	 *
	 * @return GenerativeAiResult The parsed generative AI result.
	 */
	private function parseResponseDataToGenerativeAiResult(array $response_data): GenerativeAiResult
	{
		if (!isset($response_data['output']) || !$response_data['output']) {
			throw ResponseException::fromMissingData($this->providerMetadata()->getName(), 'output');
		}
		if (!is_array($response_data['output']) || !array_is_list($response_data['output'])) {
			throw ResponseException::fromInvalidData(
				$this->providerMetadata()->getName(),
				'output',
				'The value must be an indexed array.'
			);
		}

		$candidates = [];
		foreach ($response_data['output'] as $index => $output_item) {
			if (!is_array($output_item) || array_is_list($output_item)) {
				throw ResponseException::fromInvalidData(
					$this->providerMetadata()->getName(),
					"output[{$index}]",
					'The value must be an associative array.'
				);
			}

			$candidate = $this->parseOutputItemToCandidate(
				$output_item,
				$index,
				$response_data['status'] ?? 'completed'
			);
			if ($candidate !== null) {
				$candidates[] = $candidate;
			}
		}

		$id = isset($response_data['id']) && is_string($response_data['id']) ? $response_data['id'] : '';

		if (isset($response_data['usage']) && is_array($response_data['usage'])) {
			$usage = $response_data['usage'];
			$token_usage = new TokenUsage(
				$usage['input_tokens'] ?? 0,
				$usage['output_tokens'] ?? 0,
				$usage['total_tokens'] ?? (($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0))
			);
		} else {
			$token_usage = new TokenUsage(0, 0, 0);
		}

		$additional_data = $response_data;
		unset($additional_data['id'], $additional_data['output'], $additional_data['usage']);

		return new GenerativeAiResult(
			$id,
			$candidates,
			$token_usage,
			$this->providerMetadata(),
			$this->metadata(),
			$additional_data
		);
	}

	/**
	 * Parses a single output item from the API response into a Candidate object.
	 *
	 * @since n.e.x.t
	 *
	 * @param array<string, mixed> $output_item     The output item data from the API response.
	 * @param int                  $index           The index of the output item in the output array.
	 * @param string               $response_status The overall response status.
	 *
	 * @return Candidate|null The parsed candidate, or null if the output item should be skipped.
	 */
	private function parseOutputItemToCandidate(array $output_item, int $index, string $response_status): ?Candidate
	{
		$type = $output_item['type'] ?? '';

		if ($type === 'message') {
			return $this->parseMessageOutputToCandidate($output_item, $index, $response_status);
		}

		if ($type === 'function_call') {
			return $this->parseFunctionCallOutputToCandidate($output_item, $index);
		}

		return null;
	}

	/**
	 * Parses a message output item into a Candidate object.
	 *
	 * @since n.e.x.t
	 *
	 * @param array<string, mixed> $output_item     The output item data.
	 * @param int                  $index           The index of the output item.
	 * @param string               $response_status The overall response status.
	 *
	 * @return Candidate The parsed candidate.
	 */
	private function parseMessageOutputToCandidate(
		array $output_item,
		int $index,
		string $response_status
	): Candidate {
		$role = isset($output_item['role']) && $output_item['role'] === 'user'
			? MessageRoleEnum::user()
			: MessageRoleEnum::model();

		$parts = [];
		$has_function_calls = false;

		if (isset($output_item['content']) && is_array($output_item['content'])) {
			foreach ($output_item['content'] as $content_index => $content_item) {
				try {
					$part = $this->parseOutputContentToPart($content_item);
					if ($part !== null) {
						$parts[] = $part;
						if ($part->getType()->isFunctionCall()) {
							$has_function_calls = true;
						}
					}
				} catch (InvalidArgumentException $e) {
					throw ResponseException::fromInvalidData(
						$this->providerMetadata()->getName(),
						'output[' . $index . '].content[' . $content_index . ']',
						$e->getMessage()
					);
				}
			}
		}

		$message = new Message($role, $parts);
		$finish_reason = $this->parseStatusToFinishReason($response_status, $has_function_calls);

		return new Candidate($message, $finish_reason);
	}

	/**
	 * Parses a function_call output item into a Candidate object.
	 *
	 * @since n.e.x.t
	 *
	 * @param array<string, mixed> $output_item The output item data.
	 * @param int                  $index       The index of the output item.
	 *
	 * @return Candidate The parsed candidate.
	 */
	private function parseFunctionCallOutputToCandidate(array $output_item, int $index): Candidate
	{
		if (!isset($output_item['call_id']) || !is_string($output_item['call_id'])) {
			throw ResponseException::fromMissingData(
				$this->providerMetadata()->getName(),
				"output[{$index}].call_id"
			);
		}
		if (!isset($output_item['name']) || !is_string($output_item['name'])) {
			throw ResponseException::fromMissingData(
				$this->providerMetadata()->getName(),
				"output[{$index}].name"
			);
		}

		$args = null;
		if (isset($output_item['arguments']) && is_string($output_item['arguments'])) {
			$decoded = json_decode($output_item['arguments'], true);
			if (is_array($decoded) && count($decoded) > 0) {
				$args = $decoded;
			}
		}

		$function_call = new FunctionCall(
			$output_item['call_id'],
			$output_item['name'],
			$args
		);

		$part = new MessagePart($function_call);
		$message = new Message(MessageRoleEnum::model(), [$part]);

		return new Candidate($message, FinishReasonEnum::toolCalls());
	}

	/**
	 * Parses an output content item into a MessagePart.
	 *
	 * @since n.e.x.t
	 *
	 * @param array<string, mixed> $content_item The content item data.
	 *
	 * @return MessagePart|null The parsed message part, or null to skip.
	 */
	private function parseOutputContentToPart(array $content_item): ?MessagePart
	{
		$type = $content_item['type'] ?? '';

		if ($type === 'output_text') {
			if (!isset($content_item['text']) || !is_string($content_item['text'])) {
				throw new InvalidArgumentException('Content has an invalid output_text shape.');
			}
			return new MessagePart($content_item['text']);
		}

		if ($type === 'function_call') {
			if (
				!isset($content_item['call_id']) ||
				!is_string($content_item['call_id']) ||
				!isset($content_item['name']) ||
				!is_string($content_item['name'])
			) {
				throw new InvalidArgumentException('Content has an invalid function_call shape.');
			}

			$args = null;
			if (isset($content_item['arguments']) && is_string($content_item['arguments'])) {
				$decoded = json_decode($content_item['arguments'], true);
				if (is_array($decoded) && count($decoded) > 0) {
					$args = $decoded;
				}
			}

			return new MessagePart(
				new FunctionCall(
					$content_item['call_id'],
					$content_item['name'],
					$args
				)
			);
		}

		return null;
	}

	/**
	 * Parses the response status to a finish reason.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $status             The response status.
	 * @param bool   $has_function_calls Whether the response contains function calls.
	 *
	 * @return FinishReasonEnum The finish reason.
	 */
	private function parseStatusToFinishReason(string $status, bool $has_function_calls): FinishReasonEnum
	{
		return match ($status) {
			'completed' => $has_function_calls ? FinishReasonEnum::toolCalls() : FinishReasonEnum::stop(),
			'incomplete' => FinishReasonEnum::length(),
			'failed', 'cancelled' => FinishReasonEnum::error(),
			default => FinishReasonEnum::stop(),
		};
	}
}
