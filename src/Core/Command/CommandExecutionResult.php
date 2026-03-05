<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Command;

/**
 * Immutable value object representing the result of a command execution.
 *
 * This captures the expanded content (after argument substitution and
 * template processing), whether it should be injected into the conversation,
 * any direct output to display, and error information.
 *
 * @since 0.1.0
 */
final class CommandExecutionResult
{
	/**
	 * Whether the execution was successful.
	 *
	 * @var bool
	 */
	private bool $success;

	/**
	 * The fully expanded command content.
	 *
	 * @var string
	 */
	private string $expanded_content;

	/**
	 * Whether the expanded content should be injected into the conversation.
	 *
	 * @var bool
	 */
	private bool $inject_into_conversation;

	/**
	 * Direct output to display to the user (for built-in commands).
	 *
	 * @var string|null
	 */
	private ?string $direct_output;

	/**
	 * Error message if the execution failed.
	 *
	 * @var string|null
	 */
	private ?string $error;

	/**
	 * Creates a new CommandExecutionResult instance.
	 *
	 * @param bool $success Whether execution was successful.
	 * @param string $expanded_content The expanded content.
	 * @param bool        $inject_into_conversation Whether to inject into conversation.
	 * @param string|null $direct_output            Direct output to display.
	 * @param string|null $error                    Error message if failed.
	 */
	private function __construct(
		bool $success,
		string $expanded_content,
		bool $inject_into_conversation,
		?string $direct_output,
		?string $error
	) {
		$this->success = $success;
		$this->expanded_content = $expanded_content;
		$this->inject_into_conversation = $inject_into_conversation;
		$this->direct_output = $direct_output;
		$this->error = $error;
	}

	/**
	 * Creates a successful execution result.
	 *
	 * @param string $expanded_content The fully expanded command content.
	 * @param bool        $inject_into_conversation Whether to inject into conversation (default: true).
	 * @param string|null $direct_output            Optional direct output to display.
	 *
	 * @return self
	 *
	 * @since 0.1.0
	 */
	public static function success(
		string $expanded_content,
		bool $inject_into_conversation = true,
		?string $direct_output = null
	): self {
		return new self(
			success: true,
			expanded_content: $expanded_content,
			inject_into_conversation: $inject_into_conversation,
			direct_output: $direct_output,
			error: null
		);
	}

	/**
	 * Creates a failed execution result.
	 *
	 * @param string $error The error message.
	 *
	 * @return self
	 *
	 * @since 0.1.0
	 */
	public static function failure(string $error): self
	{
		return new self(
			success: false,
			expanded_content: '',
			inject_into_conversation: false,
			direct_output: null,
			error: $error
		);
	}

	/**
	 * Creates a result with direct output only (no conversation injection).
	 *
	 * This is useful for built-in commands that display information
	 * directly without sending it to the AI.
	 *
	 * @param string $output The output to display.
	 *
	 * @return self
	 *
	 * @since 0.1.0
	 */
	public static function directOutput(string $output): self
	{
		return new self(
			success: true,
			expanded_content: '',
			inject_into_conversation: false,
			direct_output: $output,
			error: null
		);
	}

	/**
	 * Returns the fully expanded command content.
	 *
	 * @return string
	 *
	 * @since 0.1.0
	 */
	public function getExpandedContent(): string
	{
		return $this->expanded_content;
	}

	/**
	 * Checks if the content should be injected into the conversation.
	 *
	 * @return bool
	 *
	 * @since 0.1.0
	 */
	public function shouldInjectIntoConversation(): bool
	{
		return $this->inject_into_conversation;
	}

	/**
	 * Returns direct output to display (for built-in commands).
	 *
	 * @return string|null The direct output, or null if none.
	 *
	 * @since 0.1.0
	 */
	public function getDirectOutput(): ?string
	{
		return $this->direct_output;
	}

	/**
	 * Checks if the execution was successful.
	 *
	 * @return bool
	 *
	 * @since 0.1.0
	 */
	public function isSuccess(): bool
	{
		return $this->success;
	}

	/**
	 * Returns the error message if the execution failed.
	 *
	 * @return string|null The error message, or null if successful.
	 *
	 * @since 0.1.0
	 */
	public function getError(): ?string
	{
		return $this->error;
	}

	/**
	 * Checks if there is direct output to display.
	 *
	 * @return bool True if direct output is set.
	 *
	 * @since 0.1.0
	 */
	public function hasDirectOutput(): bool
	{
		return $this->direct_output !== null;
	}
}
