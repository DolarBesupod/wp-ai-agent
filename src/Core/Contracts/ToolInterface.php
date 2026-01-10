<?php

declare(strict_types=1);

namespace PhpCliAgent\Core\Contracts;

use PhpCliAgent\Core\ValueObjects\ToolResult;

/**
 * Interface for tools that can be executed by the agent.
 *
 * Tools are the primary mechanism for the agent to interact with the outside
 * world. Each tool encapsulates a specific capability (file operations, shell
 * commands, web requests, etc.) and provides metadata for the AI model.
 *
 * @since n.e.x.t
 */
interface ToolInterface
{
	/**
	 * Returns the unique name of the tool.
	 *
	 * This name is used by the AI model to identify and invoke the tool.
	 * It should be lowercase with underscores (e.g., "read_file", "execute_bash").
	 *
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Returns a description of what the tool does.
	 *
	 * This description is provided to the AI model to help it decide when
	 * to use the tool. It should clearly explain the tool's purpose and
	 * any important constraints or behaviors.
	 *
	 * @return string
	 */
	public function getDescription(): string;

	/**
	 * Returns the JSON Schema for the tool's parameters.
	 *
	 * The schema follows the JSON Schema specification and describes the
	 * expected structure of the arguments array passed to execute().
	 *
	 * Return null if the tool accepts no parameters.
	 *
	 * @return array<string, mixed>|null The JSON Schema or null for no parameters.
	 */
	public function getParametersSchema(): ?array;

	/**
	 * Executes the tool with the given arguments.
	 *
	 * @param array<string, mixed> $arguments The arguments matching the parameters schema.
	 *
	 * @return ToolResult The result of the execution.
	 *
	 * @throws \PhpCliAgent\Core\Exceptions\ToolExecutionException If execution fails.
	 */
	public function execute(array $arguments): ToolResult;

	/**
	 * Indicates whether this tool requires user confirmation before execution.
	 *
	 * Dangerous operations (file writes, shell commands, network requests)
	 * should return true to ensure the user approves the action.
	 *
	 * @return bool True if confirmation is required, false otherwise.
	 */
	public function requiresConfirmation(): bool;
}
