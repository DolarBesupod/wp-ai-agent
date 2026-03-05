<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Exceptions;

/**
 * Base exception for all agent-related exceptions.
 *
 * Provides structured error data through a context array, allowing exceptions
 * to carry additional information about the error circumstances.
 *
 * @since 0.1.0
 */
class AgentException extends \RuntimeException
{
	/**
	 * Structured error context data.
	 *
	 * @var array<string, mixed>
	 */
	private array $context;

	/**
	 * Creates a new AgentException.
	 *
	 * @param string $message The exception message.
	 * @param int $code The exception code.
	 * @param \Throwable|null      $previous Optional previous exception.
	 * @param array<string, mixed> $context  Optional structured context data.
	 */
	public function __construct(
		string $message = '',
		int $code = 0,
		?\Throwable $previous = null,
		array $context = []
	) {
		parent::__construct($message, $code, $previous);
		$this->context = $context;
	}

	/**
	 * Returns the structured error context.
	 *
	 * @return array<string, mixed>
	 */
	public function getContext(): array
	{
		return $this->context;
	}

	/**
	 * Returns a specific context value by key.
	 *
	 * @param string $key     The context key.
	 * @param mixed  $default Default value if key doesn't exist.
	 *
	 * @return mixed
	 */
	public function getContextValue(string $key, mixed $default = null): mixed
	{
		return $this->context[$key] ?? $default;
	}

	/**
	 * Creates a new exception with additional context merged.
	 *
	 * @param array<string, mixed> $additional_context Context to merge.
	 *
	 * @return self
	 */
	public function withContext(array $additional_context): self
	{
		return new self(
			$this->getMessage(),
			$this->getCode(),
			$this->getPrevious(),
			array_merge($this->context, $additional_context)
		);
	}
}
