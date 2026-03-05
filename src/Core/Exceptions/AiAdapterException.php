<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Core\Exceptions;

/**
 * Exception thrown when AI adapter operations fail.
 *
 * @since n.e.x.t
 */
class AiAdapterException extends AgentException
{
	/**
	 * Creates an exception for API request failures.
	 *
	 * @param string          $reason   The reason for the failure.
	 * @param \Throwable|null $previous Optional previous exception.
	 *
	 * @return self
	 */
	public static function requestFailed(string $reason, ?\Throwable $previous = null): self
	{
		return new self(
			sprintf('AI adapter request failed: %s', $reason),
			0,
			$previous
		);
	}

	/**
	 * Creates an exception for response parsing failures.
	 *
	 * @param string          $reason   The reason for the failure.
	 * @param \Throwable|null $previous Optional previous exception.
	 *
	 * @return self
	 */
	public static function responseParseFailed(string $reason, ?\Throwable $previous = null): self
	{
		return new self(
			sprintf('Failed to parse AI response: %s', $reason),
			0,
			$previous
		);
	}

	/**
	 * Creates an exception for rate limiting.
	 *
	 * @param int $retry_after Seconds to wait before retrying.
	 *
	 * @return self
	 */
	public static function rateLimited(int $retry_after): self
	{
		return new self(
			sprintf('Rate limited by AI provider. Retry after %d seconds.', $retry_after)
		);
	}

	/**
	 * Creates an exception for authentication failures.
	 *
	 * @return self
	 */
	public static function authenticationFailed(): self
	{
		return new self('AI adapter authentication failed. Check your API key.');
	}

	/**
	 * Creates an exception for context length exceeded.
	 *
	 * @param int $max_tokens The maximum allowed tokens.
	 *
	 * @return self
	 */
	public static function contextLengthExceeded(int $max_tokens): self
	{
		return new self(
			sprintf('Context length exceeded. Maximum tokens: %d', $max_tokens)
		);
	}
}
