<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Core\Exceptions;

/**
 * Exception thrown when tool execution fails.
 *
 * @since n.e.x.t
 */
class ToolExecutionException extends AgentException
{
	private string $tool_name;

	/**
	 * @var array<string, mixed>
	 */
	private array $arguments;

	/**
	 * Creates a new ToolExecutionException.
	 *
	 * @param string               $tool_name The tool that failed.
	 * @param string               $message   The error message.
	 * @param array<string, mixed> $arguments The arguments passed to the tool.
	 * @param \Throwable|null      $previous  Optional previous exception.
	 */
	public function __construct(
		string $tool_name,
		string $message,
		array $arguments = [],
		?\Throwable $previous = null
	) {
		$this->tool_name = $tool_name;
		$this->arguments = $arguments;

		$full_message = sprintf('Tool "%s" execution failed: %s', $tool_name, $message);
		parent::__construct($full_message, 0, $previous);
	}

	/**
	 * Returns the tool name.
	 *
	 * @return string
	 */
	public function getToolName(): string
	{
		return $this->tool_name;
	}

	/**
	 * Returns the arguments passed to the tool.
	 *
	 * @return array<string, mixed>
	 */
	public function getArguments(): array
	{
		return $this->arguments;
	}
}
