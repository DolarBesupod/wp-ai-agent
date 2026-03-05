<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Exceptions;

/**
 * Exception thrown when AI client operations fail.
 *
 * This exception handles errors from the AI client layer,
 * complementing AiAdapterException for adapter-specific issues.
 *
 * @since n.e.x.t
 */
class AiClientException extends AgentException
{
	/**
	 * Creates an exception for client initialization failures.
	 *
	 * @param string          $reason   The reason for the failure.
	 * @param \Throwable|null $previous Optional previous exception.
	 *
	 * @return self
	 */
	public static function initializationFailed(string $reason, ?\Throwable $previous = null): self
	{
		return new self(
			sprintf('Failed to initialize AI client: %s', $reason),
			0,
			$previous,
			['type' => 'initialization_failed', 'reason' => $reason]
		);
	}

	/**
	 * Creates an exception for invalid API key.
	 *
	 * @return self
	 */
	public static function invalidApiKey(): self
	{
		return new self(
			'Invalid or missing API key. Please check your configuration.',
			0,
			null,
			['type' => 'invalid_api_key']
		);
	}

	/**
	 * Creates an exception for model not found.
	 *
	 * @param string $model The requested model identifier.
	 *
	 * @return self
	 */
	public static function modelNotFound(string $model): self
	{
		return new self(
			sprintf('Model "%s" not found or not accessible.', $model),
			0,
			null,
			['type' => 'model_not_found', 'model' => $model]
		);
	}

	/**
	 * Creates an exception for streaming errors.
	 *
	 * @param string          $reason   The reason for the failure.
	 * @param \Throwable|null $previous Optional previous exception.
	 *
	 * @return self
	 */
	public static function streamingFailed(string $reason, ?\Throwable $previous = null): self
	{
		return new self(
			sprintf('AI streaming failed: %s', $reason),
			0,
			$previous,
			['type' => 'streaming_failed', 'reason' => $reason]
		);
	}

	/**
	 * Creates an exception for message formatting errors.
	 *
	 * @param string          $reason   The reason for the failure.
	 * @param \Throwable|null $previous Optional previous exception.
	 *
	 * @return self
	 */
	public static function messageFormattingFailed(string $reason, ?\Throwable $previous = null): self
	{
		return new self(
			sprintf('Failed to format message for AI: %s', $reason),
			0,
			$previous,
			['type' => 'message_formatting_failed', 'reason' => $reason]
		);
	}

	/**
	 * Creates an exception for tool conversion errors.
	 *
	 * @param string          $tool_name The tool that failed conversion.
	 * @param string          $reason    The reason for the failure.
	 * @param \Throwable|null $previous  Optional previous exception.
	 *
	 * @return self
	 */
	public static function toolConversionFailed(
		string $tool_name,
		string $reason,
		?\Throwable $previous = null
	): self {
		return new self(
			sprintf('Failed to convert tool "%s" for AI: %s', $tool_name, $reason),
			0,
			$previous,
			['type' => 'tool_conversion_failed', 'tool' => $tool_name, 'reason' => $reason]
		);
	}

	/**
	 * Creates an exception for unsupported provider.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $provider_id The unsupported provider identifier.
	 *
	 * @return self
	 */
	public static function unsupportedProvider(string $provider_id): self
	{
		return new self(
			sprintf('Unsupported AI provider: "%s".', $provider_id),
			0,
			null,
			['type' => 'unsupported_provider', 'provider_id' => $provider_id]
		);
	}

	/**
	 * Creates an exception for quota exceeded.
	 *
	 * @param string|null $limit_type The type of limit exceeded (tokens, requests, etc.).
	 *
	 * @return self
	 */
	public static function quotaExceeded(?string $limit_type = null): self
	{
		$message = 'AI usage quota exceeded.';
		if ($limit_type !== null) {
			$message = sprintf('AI usage quota exceeded: %s limit reached.', $limit_type);
		}

		return new self(
			$message,
			0,
			null,
			['type' => 'quota_exceeded', 'limit_type' => $limit_type]
		);
	}

	/**
	 * Creates an exception for an empty AI response body.
	 *
	 * @since n.e.x.t
	 *
	 * @return self
	 */
	public static function emptyResponse(): self
	{
		return new self(
			'AI response body is empty.',
			0,
			null,
			['type' => 'empty_response']
		);
	}

	/**
	 * Creates an exception for a missing SSE event type in the response.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $event_type The expected SSE event type that was not found.
	 *
	 * @return self
	 */
	public static function sseEventNotFound(string $event_type): self
	{
		return new self(
			sprintf('Expected SSE event "%s" not found in response.', $event_type),
			0,
			null,
			['type' => 'sse_event_not_found', 'event_type' => $event_type]
		);
	}
}
