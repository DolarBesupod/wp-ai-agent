<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Contracts;

use RuntimeException;

/**
 * Interface for expanding bash command references in content.
 *
 * Commands and skills can execute shell commands using the !`command` syntax:
 * - !`echo hello` - Execute and replace with output
 * - !`git status --short` - Execute git command and inline output
 * - !`date +%Y-%m-%d` - Execute date command with format arguments
 *
 * When expanded, the !`command` references are replaced with the command output.
 *
 * Security Note: This is a deliberate feature for power users creating their
 * own commands/skills. The commands come from files the user has explicitly
 * created or installed.
 *
 * @since n.e.x.t
 */
interface BashCommandExpanderInterface
{
	/**
	 * Expands bash command references in content.
	 *
	 * Replaces !`command` references with the output of executing the command.
	 * Commands are executed in a shell process with the specified working directory.
	 *
	 * Regular backticks without the ! prefix are NOT executed and are left unchanged.
	 *
	 * @param string $content           The content containing !`command` references.
	 * @param string $working_directory The directory to run commands in.
	 *
	 * @return string The content with commands replaced by their output.
	 *
	 * @throws RuntimeException If a command fails (non-zero exit code) or times out.
	 */
	public function expand(string $content, string $working_directory): string;
}
