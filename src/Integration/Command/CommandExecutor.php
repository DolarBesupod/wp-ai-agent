<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\Command;

use Automattic\WpAiAgent\Core\Command\Command;
use Automattic\WpAiAgent\Core\Command\CommandExecutionResult;
use Automattic\WpAiAgent\Core\Contracts\ArgumentSubstitutorInterface;
use Automattic\WpAiAgent\Core\Contracts\BashCommandExpanderInterface;
use Automattic\WpAiAgent\Core\Contracts\CommandExecutorInterface;
use Automattic\WpAiAgent\Core\Contracts\FileReferenceExpanderInterface;
use Automattic\WpAiAgent\Core\ValueObjects\ArgumentList;
use RuntimeException;

/**
 * Executes commands by applying all placeholder expansions.
 *
 * The executor processes command content through three expansion phases:
 * 1. Argument substitution ($1, $2, $ARGUMENTS)
 * 2. File reference expansion (@file)
 * 3. Bash command expansion (!`cmd`)
 *
 * Errors from expansions are handled gracefully by including inline error
 * messages rather than failing the entire execution.
 *
 * @since n.e.x.t
 */
final class CommandExecutor implements CommandExecutorInterface
{
	/**
	 * The argument substitutor for replacing argument placeholders.
	 *
	 * @var ArgumentSubstitutorInterface
	 */
	private ArgumentSubstitutorInterface $argument_substitutor;

	/**
	 * The file reference expander for including file contents.
	 *
	 * @var FileReferenceExpanderInterface
	 */
	private FileReferenceExpanderInterface $file_reference_expander;

	/**
	 * The bash command expander for executing inline commands.
	 *
	 * @var BashCommandExpanderInterface
	 */
	private BashCommandExpanderInterface $bash_command_expander;

	/**
	 * Creates a new CommandExecutor instance.
	 *
	 * @param ArgumentSubstitutorInterface   $argument_substitutor    The argument substitutor.
	 * @param FileReferenceExpanderInterface $file_reference_expander The file reference expander.
	 * @param BashCommandExpanderInterface   $bash_command_expander   The bash command expander.
	 *
	 * @since n.e.x.t
	 */
	public function __construct(
		ArgumentSubstitutorInterface $argument_substitutor,
		FileReferenceExpanderInterface $file_reference_expander,
		BashCommandExpanderInterface $bash_command_expander
	) {
		$this->argument_substitutor = $argument_substitutor;
		$this->file_reference_expander = $file_reference_expander;
		$this->bash_command_expander = $bash_command_expander;
	}

	/**
	 * Executes a command with the given arguments.
	 *
	 * Applies expansions in order:
	 * 1. Argument substitution ($1, $2, $ARGUMENTS)
	 * 2. File reference expansion (@file)
	 * 3. Bash command expansion (!`cmd`)
	 *
	 * Errors from file reference or bash command expansion are captured and
	 * included inline as error messages, allowing the execution to continue.
	 *
	 * @param Command      $command   The command to execute.
	 * @param ArgumentList $arguments The arguments passed to the command.
	 *
	 * @return CommandExecutionResult The execution result with expanded content.
	 *
	 * @since n.e.x.t
	 */
	public function execute(Command $command, ArgumentList $arguments): CommandExecutionResult
	{
		$content = $command->getBody();
		$base_path = $this->resolveBasePath($command);
		$errors = [];

		// Step 1: Argument substitution (always succeeds)
		$content = $this->argument_substitutor->substitute($content, $arguments);

		// Step 2: File reference expansion (may fail)
		$file_expansion_result = $this->expandFileReferences($content, $base_path);
		$content = $file_expansion_result['content'];
		if ($file_expansion_result['error'] !== null) {
			$errors[] = $file_expansion_result['error'];
		}

		// Step 3: Bash command expansion (may fail)
		$bash_expansion_result = $this->expandBashCommands($content, $base_path);
		$content = $bash_expansion_result['content'];
		if ($bash_expansion_result['error'] !== null) {
			$errors[] = $bash_expansion_result['error'];
		}

		// If there were errors, prepend them inline to the content
		if (count($errors) > 0) {
			$error_messages = array_map(
				fn(string $error): string => '[' . $error . ']',
				$errors
			);
			$content = implode(' ', $error_messages);
		}

		return CommandExecutionResult::success(
			expanded_content: $content,
			inject_into_conversation: true
		);
	}

	/**
	 * Resolves the base path for file and command expansions.
	 *
	 * For commands loaded from files, uses the directory containing the command file.
	 * For built-in commands (no filepath), uses the current working directory.
	 *
	 * @param Command $command The command to resolve the base path for.
	 *
	 * @return string The base path for expansions.
	 */
	private function resolveBasePath(Command $command): string
	{
		$filepath = $command->getFilePath();

		if ($filepath === null) {
			// Built-in command - use current working directory
			return (string) getcwd();
		}

		// Use the directory containing the command file
		return dirname($filepath);
	}

	/**
	 * Expands file references, capturing errors.
	 *
	 * On success, returns the expanded content and null error.
	 * On failure, returns the original content and the error message.
	 *
	 * @param string $content   The content to expand.
	 * @param string $base_path The base path for relative references.
	 *
	 * @return array{content: string, error: string|null} The expansion result.
	 */
	private function expandFileReferences(string $content, string $base_path): array
	{
		try {
			return [
				'content' => $this->file_reference_expander->expand($content, $base_path),
				'error' => null,
			];
		} catch (RuntimeException $exception) {
			// Keep original content and capture the error
			return [
				'content' => $content,
				'error' => $exception->getMessage(),
			];
		}
	}

	/**
	 * Expands bash commands, capturing errors.
	 *
	 * On success, returns the expanded content and null error.
	 * On failure, returns the original content and the error message.
	 *
	 * @param string $content     The content to expand.
	 * @param string $working_dir The working directory for commands.
	 *
	 * @return array{content: string, error: string|null} The expansion result.
	 */
	private function expandBashCommands(string $content, string $working_dir): array
	{
		try {
			return [
				'content' => $this->bash_command_expander->expand($content, $working_dir),
				'error' => null,
			];
		} catch (RuntimeException $exception) {
			// Keep original content and capture the error
			return [
				'content' => $content,
				'error' => $exception->getMessage(),
			];
		}
	}
}
