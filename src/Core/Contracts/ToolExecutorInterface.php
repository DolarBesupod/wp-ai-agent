<?php

declare(strict_types=1);

namespace WpAiAgent\Core\Contracts;

use WpAiAgent\Core\ValueObjects\ToolResult;

/**
 * Interface for executing tools with confirmation handling.
 *
 * The tool executor is responsible for:
 * - Looking up tools in the registry
 * - Requesting user confirmation when required
 * - Executing the tool with provided arguments
 * - Handling execution errors gracefully
 *
 * @since n.e.x.t
 */
interface ToolExecutorInterface
{
	/**
	 * Executes a tool by name with the given arguments.
	 *
	 * This method will:
	 * 1. Look up the tool in the registry
	 * 2. Check if confirmation is required and request it
	 * 3. Execute the tool if confirmed or not requiring confirmation
	 * 4. Return the result or a failure if denied/not found
	 *
	 * @param string               $tool_name The name of the tool to execute.
	 * @param array<string, mixed> $arguments The arguments for the tool.
	 *
	 * @return ToolResult The execution result.
	 */
	public function execute(string $tool_name, array $arguments): ToolResult;

	/**
	 * Executes multiple tool calls in sequence.
	 *
	 * @param array<int, array{name: string, arguments: array<string, mixed>}> $tool_calls The tool calls to execute.
	 *
	 * @return array<int, array{name: string, result: ToolResult}> The results indexed by tool call order.
	 */
	public function executeMultiple(array $tool_calls): array;

	/**
	 * Checks if a tool can be executed without confirmation.
	 *
	 * This is useful for pre-checking before execution or for UI purposes.
	 *
	 * @param string $tool_name The tool name.
	 *
	 * @return bool True if the tool can execute without confirmation.
	 */
	public function canExecuteWithoutConfirmation(string $tool_name): bool;
}
