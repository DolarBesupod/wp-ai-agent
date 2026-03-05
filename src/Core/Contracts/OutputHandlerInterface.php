<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Contracts;

use Automattic\WpAiAgent\Core\ValueObjects\ToolResult;

/**
 * Interface for handling output to the user.
 *
 * The output handler abstracts the presentation layer, allowing the agent
 * to work with different output targets (CLI, web, tests) without changes
 * to core logic.
 *
 * @since n.e.x.t
 */
interface OutputHandlerInterface
{
	/**
	 * Writes text to the output without a newline.
	 *
	 * @param string $text The text to write.
	 *
	 * @return void
	 */
	public function write(string $text): void;

	/**
	 * Writes text followed by a newline.
	 *
	 * @param string $text The text to write.
	 *
	 * @return void
	 */
	public function writeLine(string $text): void;

	/**
	 * Writes an error message.
	 *
	 * The output may be styled differently to indicate an error.
	 *
	 * @param string $text The error message.
	 *
	 * @return void
	 */
	public function writeError(string $text): void;

	/**
	 * Writes a success message.
	 *
	 * The output may be styled differently to indicate success.
	 *
	 * @param string $text The success message.
	 *
	 * @return void
	 */
	public function writeSuccess(string $text): void;

	/**
	 * Writes a warning message.
	 *
	 * The output may be styled differently to indicate a warning.
	 *
	 * @param string $text The warning message.
	 *
	 * @return void
	 */
	public function writeWarning(string $text): void;

	/**
	 * Writes a tool execution result.
	 *
	 * @param string     $tool_name The name of the executed tool.
	 * @param ToolResult $result    The execution result.
	 *
	 * @return void
	 */
	public function writeToolResult(string $tool_name, ToolResult $result): void;

	/**
	 * Writes the assistant's response text.
	 *
	 * @param string $text The assistant's response.
	 *
	 * @return void
	 */
	public function writeAssistantResponse(string $text): void;

	/**
	 * Writes a streaming chunk of the assistant's response.
	 *
	 * This is used for real-time streaming output.
	 *
	 * @param string $chunk The text chunk.
	 *
	 * @return void
	 */
	public function writeStreamChunk(string $chunk): void;

	/**
	 * Writes a status message (e.g., "Thinking...", "Executing tool...").
	 *
	 * @param string $status The status message.
	 *
	 * @return void
	 */
	public function writeStatus(string $status): void;

	/**
	 * Writes a debug message.
	 *
	 * Debug messages may be hidden unless debug mode is enabled.
	 *
	 * @param string $message The debug message.
	 *
	 * @return void
	 */
	public function writeDebug(string $message): void;

	/**
	 * Clears the current line (for updating status messages).
	 *
	 * @return void
	 */
	public function clearLine(): void;

	/**
	 * Sets whether debug output is enabled.
	 *
	 * @param bool $enabled Whether to show debug output.
	 *
	 * @return void
	 */
	public function setDebugEnabled(bool $enabled): void;

	/**
	 * Checks if debug output is enabled.
	 *
	 * @return bool
	 */
	public function isDebugEnabled(): bool;
}
