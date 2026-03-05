<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Contracts;

/**
 * Interface for handling tool execution confirmations.
 *
 * The confirmation handler is responsible for prompting the user to approve
 * or deny tool executions. It also manages bypass rules for tools that should
 * execute without confirmation.
 *
 * @since n.e.x.t
 */
interface ConfirmationHandlerInterface
{
	/**
	 * Requests confirmation from the user to execute a tool.
	 *
	 * @param string               $tool_name The name of the tool.
	 * @param array<string, mixed> $arguments The arguments that will be passed to the tool.
	 *
	 * @return bool True if the user confirms, false if denied.
	 */
	public function confirm(string $tool_name, array $arguments): bool;

	/**
	 * Checks if a tool should bypass confirmation.
	 *
	 * Some tools may be configured to always execute without user confirmation,
	 * either by user preference or because they are safe by nature.
	 *
	 * @param string $tool_name The name of the tool.
	 *
	 * @return bool True if confirmation should be bypassed.
	 */
	public function shouldBypass(string $tool_name): bool;

	/**
	 * Adds a tool to the bypass list.
	 *
	 * Tools on this list will execute without confirmation for the current session.
	 *
	 * @param string $tool_name The tool name to bypass.
	 *
	 * @return void
	 */
	public function addBypass(string $tool_name): void;

	/**
	 * Removes a tool from the bypass list.
	 *
	 * @param string $tool_name The tool name.
	 *
	 * @return void
	 */
	public function removeBypass(string $tool_name): void;

	/**
	 * Returns all bypassed tool names.
	 *
	 * @return array<int, string>
	 */
	public function getBypasses(): array;

	/**
	 * Clears all bypass rules.
	 *
	 * @return void
	 */
	public function clearBypasses(): void;

	/**
	 * Sets whether to auto-confirm all tool executions.
	 *
	 * This is useful for non-interactive or automated scenarios.
	 * Use with caution as it bypasses all safety confirmations.
	 *
	 * @param bool $auto_confirm Whether to auto-confirm.
	 *
	 * @return void
	 */
	public function setAutoConfirm(bool $auto_confirm): void;

	/**
	 * Checks if auto-confirm mode is enabled.
	 *
	 * @return bool
	 */
	public function isAutoConfirm(): bool;
}
