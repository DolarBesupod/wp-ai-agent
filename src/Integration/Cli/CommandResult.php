<?php

declare(strict_types=1);

namespace PhpCliAgent\Integration\Cli;

/**
 * Represents the result of a CLI command execution.
 *
 * Contains information about whether a command was handled, whether the REPL
 * should continue running, and any output message to display.
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
	 * Creates a new CommandResult instance.
	 *
	 * @param bool        $handled         Whether the input was recognized as a command.
	 * @param bool        $should_continue Whether the REPL should continue running.
	 * @param string|null $message         Optional output message.
	 */
	public function __construct(bool $handled, bool $should_continue, ?string $message = null)
	{
		$this->handled = $handled;
		$this->should_continue = $should_continue;
		$this->message = $message;
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
}
