<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\WpCli;

use WpAiAgent\Core\Contracts\ConfirmationHandlerInterface;

/**
 * WP-CLI-specific confirmation handler stub.
 *
 * Placeholder class to satisfy type requirements while the full implementation
 * is completed in a subsequent task (T1.3). Currently auto-confirms all tools
 * that are not in the bypassed list.
 *
 * @since n.e.x.t
 */
class WpCliConfirmationHandler implements ConfirmationHandlerInterface
{
	/**
	 * Tool names that bypass confirmation by default.
	 *
	 * @var array<int, string>
	 */
	private array $bypassed_tools;

	/**
	 * Whether to auto-confirm all tool executions.
	 *
	 * @var bool
	 */
	private bool $auto_confirm = false;

	/**
	 * Creates a new WpCliConfirmationHandler.
	 *
	 * @param array<int, string> $bypassed_tools Tool names that bypass confirmation.
	 */
	public function __construct(array $bypassed_tools = [])
	{
		$this->bypassed_tools = $bypassed_tools;
	}

	/**
	 * {@inheritDoc}
	 */
	public function confirm(string $tool_name, array $arguments): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function shouldBypass(string $tool_name): bool
	{
		return in_array($tool_name, $this->bypassed_tools, true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function addBypass(string $tool_name): void
	{
		if (!in_array($tool_name, $this->bypassed_tools, true)) {
			$this->bypassed_tools[] = $tool_name;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function removeBypass(string $tool_name): void
	{
		$this->bypassed_tools = array_values(
			array_filter($this->bypassed_tools, static fn (string $t): bool => $t !== $tool_name)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getBypasses(): array
	{
		return $this->bypassed_tools;
	}

	/**
	 * {@inheritDoc}
	 */
	public function clearBypasses(): void
	{
		$this->bypassed_tools = [];
	}

	/**
	 * {@inheritDoc}
	 */
	public function setAutoConfirm(bool $auto_confirm): void
	{
		$this->auto_confirm = $auto_confirm;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isAutoConfirm(): bool
	{
		return $this->auto_confirm;
	}
}
