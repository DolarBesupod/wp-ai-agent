<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Exceptions;

/**
 * Exception thrown when a requested tool is not found in the registry.
 *
 * @since 0.1.0
 */
class ToolNotFoundException extends AgentException
{
	/**
	 * The name of the tool that was not found.
	 */
	private string $tool_name;

	/**
	 * Creates a new ToolNotFoundException.
	 *
	 * @param string          $tool_name The name of the tool that was not found.
	 * @param \Throwable|null $previous  Optional previous exception.
	 */
	public function __construct(string $tool_name, ?\Throwable $previous = null)
	{
		$this->tool_name = $tool_name;

		parent::__construct(
			sprintf('Tool "%s" not found in the registry.', $tool_name),
			0,
			$previous,
			['tool' => $tool_name]
		);
	}

	/**
	 * Returns the name of the tool that was not found.
	 *
	 * @return string
	 */
	public function getToolName(): string
	{
		return $this->tool_name;
	}
}
