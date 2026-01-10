<?php

declare(strict_types=1);

namespace PhpCliAgent\Core\Exceptions;

/**
 * Exception thrown when registering a tool with a duplicate name.
 *
 * @since n.e.x.t
 */
class DuplicateToolException extends AgentException
{
	private string $tool_name;

	/**
	 * Creates a new DuplicateToolException.
	 *
	 * @param string $tool_name The duplicate tool name.
	 */
	public function __construct(string $tool_name)
	{
		$this->tool_name = $tool_name;

		parent::__construct(
			sprintf('A tool with name "%s" is already registered.', $tool_name)
		);
	}

	/**
	 * Returns the duplicate tool name.
	 *
	 * @return string
	 */
	public function getToolName(): string
	{
		return $this->tool_name;
	}
}
