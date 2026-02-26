<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\WpCli;

use WpAiAgent\Core\Contracts\ConfirmationHandlerInterface;

/**
 * WP-CLI confirmation handler for tool execution approvals.
 *
 * Implements ConfirmationHandlerInterface using WP-CLI's native confirmation
 * prompt. Supports bypass lists for tools that should execute without prompting,
 * and an auto-confirm mode for non-interactive or automated scenarios.
 *
 * @since n.e.x.t
 */
class WpCliConfirmationHandler implements ConfirmationHandlerInterface
{
	/**
	 * Tool names that bypass confirmation.
	 *
	 * @var array<int, string>
	 */
	private array $bypassed_tools;

	/**
	 * Whether to auto-confirm all tool executions.
	 *
	 * @var bool
	 */
	private bool $auto_confirm;

	/**
	 * Creates a new WpCliConfirmationHandler.
	 *
	 * @param array<int, string> $initial_bypasses Tool names that bypass confirmation.
	 * @param bool               $auto_confirm     Whether to auto-confirm all executions.
	 *
	 * @since n.e.x.t
	 */
	public function __construct(array $initial_bypasses = [], bool $auto_confirm = false)
	{
		$this->bypassed_tools = $initial_bypasses;
		$this->auto_confirm = $auto_confirm;
	}

	/**
	 * Requests confirmation from the user to execute a tool.
	 *
	 * When auto-confirm is enabled or the tool is on the bypass list, returns
	 * true immediately without prompting. Otherwise, calls WP_CLI::confirm()
	 * which exits on a negative response — this is caught and converted to false.
	 *
	 * @param string               $tool_name The name of the tool.
	 * @param array<string, mixed> $arguments The arguments that will be passed to the tool.
	 *
	 * @return bool True if the execution is approved, false if denied.
	 *
	 * @since n.e.x.t
	 */
	public function confirm(string $tool_name, array $arguments): bool
	{
		if ($this->auto_confirm) {
			return true;
		}

		if ($this->shouldBypass($tool_name)) {
			return true;
		}

		$message = sprintf('Execute %s?', $tool_name);

		try {
			\WP_CLI::confirm($message);
			return true;
		} catch (\WP_CLI\ExitException $e) {
			return false;
		}
	}

	/**
	 * Checks if a tool should bypass confirmation.
	 *
	 * @param string $tool_name The name of the tool.
	 *
	 * @return bool True if confirmation should be bypassed.
	 *
	 * @since n.e.x.t
	 */
	public function shouldBypass(string $tool_name): bool
	{
		return in_array($tool_name, $this->bypassed_tools, true);
	}

	/**
	 * Adds a tool to the bypass list.
	 *
	 * Tools on this list will execute without confirmation. Only adds the tool
	 * if it is not already present.
	 *
	 * @param string $tool_name The tool name to bypass.
	 *
	 * @return void
	 *
	 * @since n.e.x.t
	 */
	public function addBypass(string $tool_name): void
	{
		if (!in_array($tool_name, $this->bypassed_tools, true)) {
			$this->bypassed_tools[] = $tool_name;
		}
	}

	/**
	 * Removes a tool from the bypass list.
	 *
	 * @param string $tool_name The tool name.
	 *
	 * @return void
	 *
	 * @since n.e.x.t
	 */
	public function removeBypass(string $tool_name): void
	{
		$this->bypassed_tools = array_values(
			array_filter($this->bypassed_tools, static fn (string $t): bool => $t !== $tool_name)
		);
	}

	/**
	 * Returns all bypassed tool names.
	 *
	 * @return array<int, string>
	 *
	 * @since n.e.x.t
	 */
	public function getBypasses(): array
	{
		return $this->bypassed_tools;
	}

	/**
	 * Clears all bypass rules.
	 *
	 * @return void
	 *
	 * @since n.e.x.t
	 */
	public function clearBypasses(): void
	{
		$this->bypassed_tools = [];
	}

	/**
	 * Sets whether to auto-confirm all tool executions.
	 *
	 * When enabled, confirm() returns true without calling WP_CLI::confirm().
	 * Use with caution as this bypasses all safety confirmations.
	 *
	 * @param bool $auto_confirm Whether to auto-confirm.
	 *
	 * @return void
	 *
	 * @since n.e.x.t
	 */
	public function setAutoConfirm(bool $auto_confirm): void
	{
		$this->auto_confirm = $auto_confirm;
	}

	/**
	 * Checks if auto-confirm mode is enabled.
	 *
	 * @return bool
	 *
	 * @since n.e.x.t
	 */
	public function isAutoConfirm(): bool
	{
		return $this->auto_confirm;
	}
}
