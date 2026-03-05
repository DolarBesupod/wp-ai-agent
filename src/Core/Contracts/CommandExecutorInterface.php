<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Core\Contracts;

use Automattic\Automattic\WpAiAgent\Core\Command\Command;
use Automattic\Automattic\WpAiAgent\Core\Command\CommandExecutionResult;
use Automattic\Automattic\WpAiAgent\Core\ValueObjects\ArgumentList;

/**
 * Interface for executing commands.
 *
 * The command executor is responsible for processing a command with its
 * arguments, performing any necessary expansions (argument substitution,
 * file references, bash commands), and returning the result.
 *
 * @since n.e.x.t
 */
interface CommandExecutorInterface
{
	/**
	 * Executes a command with the given arguments.
	 *
	 * The executor processes the command body, substituting argument
	 * placeholders, expanding file references, and executing inline
	 * bash commands as needed.
	 *
	 * @param Command      $command   The command to execute.
	 * @param ArgumentList $arguments The arguments passed to the command.
	 *
	 * @return CommandExecutionResult The execution result.
	 */
	public function execute(Command $command, ArgumentList $arguments): CommandExecutionResult;
}
