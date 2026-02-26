<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\Cli;

/**
 * Represents the result of a CLI command execution.
 *
 * Contains information about whether a command was handled, whether the REPL
 * should continue running, any output message to display, and content that
 * should be injected into the conversation with the agent.
 *
 * @since n.e.x.t
 */
final class CommandResult
{
	/**
	 * Whether the input was recognized as a command.
	 *
	 * @var bool
	 */
	private bool $handled;

	/**
	 * Whether the REPL should continue running.
	 *
	 * @var bool
	 */
	private bool $should_continue;

	/**
	 * Optional output message to display.
	 *
	 * @var string|null
	 */
	private ?string $message;

	/**
	 * Content to inject into the conversation with the agent.
	 *
	 * @var string|null
	 */
	private ?string $injected_content;

	/**
	 * Creates a new CommandResult instance.
	 *
	 * @param bool        $handled          Whether the input was recognized as a command.
	 * @param bool        $should_continue  Whether the REPL should continue running.
	 * @param string|null $message          Optional output message.
	 * @param string|null $injected_content Content to inject into conversation.
	 */
	public function __construct(
		bool $handled,
		bool $should_continue,
		?string $message = null,
		?string $injected_content = null
	) {
		$this->handled = $handled;
		$this->should_continue = $should_continue;
		$this->message = $message;
		$this->injected_content = $injected_content;
	}

	/**
	 * Creates a result for a handled command that should continue the REPL.
	 *
	 * @param string|null $message Optional success message.
	 *
	 * @return self
	 */
	public static function handled(?string $message = null): self
	{
		return new self(true, true, $message);
	}

	/**
	 * Creates a result for a handled command that should exit the REPL.
	 *
	 * @param string|null $message Optional exit message.
	 *
	 * @return self
	 */
	public static function exit(?string $message = null): self
	{
		return new self(true, false, $message);
	}

	/**
	 * Creates a result for input that was not recognized as a command.
	 *
	 * @return self
	 */
	public static function notHandled(): self
	{
		return new self(false, true, null);
	}

	/**
	 * Creates a result for an unknown command.
	 *
	 * @param string $command The unknown command name.
	 *
	 * @return self
	 */
	public static function unknownCommand(string $command): self
	{
		return new self(true, true, sprintf('Unknown command: /%s. Type /help for available commands.', $command));
	}

	/**
	 * Returns whether the input was recognized as a command.
	 *
	 * @return bool
	 */
	public function wasHandled(): bool
	{
		return $this->handled;
	}

	/**
	 * Returns whether the REPL should continue running.
	 *
	 * @return bool
	 */
	public function shouldContinue(): bool
	{
		return $this->should_continue;
	}

	/**
	 * Returns the output message, if any.
	 *
	 * @return string|null
	 */
	public function getMessage(): ?string
	{
		return $this->message;
	}

	/**
	 * Checks if the result has a message.
	 *
	 * @return bool
	 */
	public function hasMessage(): bool
	{
		return $this->message !== null;
	}

	/**
	 * Creates a result with content to be injected into the conversation.
	 *
	 * This factory method is used for custom commands that produce content
	 * which should be sent to the agent as part of the conversation.
	 *
	 * @param string $content The content to inject into the conversation.
	 *
	 * @return self
	 *
	 * @since n.e.x.t
	 */
	public static function inject(string $content): self
	{
		return new self(true, true, null, $content);
	}

	/**
	 * Checks if the result has content to inject into the conversation.
	 *
	 * @return bool True if there is content to inject.
	 *
	 * @since n.e.x.t
	 */
	public function shouldInject(): bool
	{
		return $this->injected_content !== null;
	}

	/**
	 * Returns the content to be injected into the conversation.
	 *
	 * @return string|null The injected content, or null if none.
	 *
	 * @since n.e.x.t
	 */
	public function getInjectedContent(): ?string
	{
		return $this->injected_content;
	}
}
