<?php

declare(strict_types=1);

namespace WpAiAgent\Core\Exceptions;

/**
 * Exception thrown when a user denies confirmation for an operation.
 *
 * This is used by the agent's confirmation handler when the user
 * rejects a tool execution or other confirmation-required action.
 *
 * @since n.e.x.t
 */
class ConfirmationDeniedException extends AgentException
{
	/**
	 * The action that was denied.
	 */
	private string $action;

	/**
	 * Optional reason provided by the user.
	 */
	private ?string $reason;

	/**
	 * Creates a new ConfirmationDeniedException.
	 *
	 * @param string               $action   The action that was denied.
	 * @param string|null          $reason   Optional reason from the user.
	 * @param array<string, mixed> $context  Additional context about the denied action.
	 * @param \Throwable|null      $previous Optional previous exception.
	 */
	public function __construct(
		string $action,
		?string $reason = null,
		array $context = [],
		?\Throwable $previous = null
	) {
		$this->action = $action;
		$this->reason = $reason;

		$message = sprintf('Confirmation denied for action: %s', $action);
		if ($reason !== null) {
			$message .= sprintf(' Reason: %s', $reason);
		}

		$context = array_merge($context, [
			'action' => $action,
			'denied_reason' => $reason,
		]);

		parent::__construct($message, 0, $previous, $context);
	}

	/**
	 * Returns the action that was denied.
	 *
	 * @return string
	 */
	public function getAction(): string
	{
		return $this->action;
	}

	/**
	 * Returns the denial reason, if provided.
	 *
	 * @return string|null
	 */
	public function getReason(): ?string
	{
		return $this->reason;
	}

	/**
	 * Creates an exception for denied tool execution.
	 *
	 * @param string               $tool_name The tool that was denied.
	 * @param array<string, mixed> $arguments The arguments that would have been passed.
	 * @param string|null          $reason    Optional reason from the user.
	 *
	 * @return self
	 */
	public static function toolExecutionDenied(
		string $tool_name,
		array $arguments = [],
		?string $reason = null
	): self {
		return new self(
			sprintf('execute tool "%s"', $tool_name),
			$reason,
			[
				'tool' => $tool_name,
				'arguments' => $arguments,
			]
		);
	}

	/**
	 * Creates an exception for denied file operation.
	 *
	 * @param string      $operation The operation type (read, write, delete).
	 * @param string      $path      The file path.
	 * @param string|null $reason    Optional reason from the user.
	 *
	 * @return self
	 */
	public static function fileOperationDenied(
		string $operation,
		string $path,
		?string $reason = null
	): self {
		return new self(
			sprintf('%s file "%s"', $operation, $path),
			$reason,
			[
				'operation' => $operation,
				'path' => $path,
			]
		);
	}
}
