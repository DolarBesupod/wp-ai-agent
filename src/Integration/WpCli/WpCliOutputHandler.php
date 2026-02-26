<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\WpCli;

use WpAiAgent\Core\Contracts\OutputHandlerInterface;
use WpAiAgent\Core\ValueObjects\ToolResult;

/**
 * WP-CLI-specific output handler using native WP-CLI output functions.
 *
 * Implements OutputHandlerInterface for WP-CLI environments, delegating
 * all output to WP_CLI methods. No ANSI escape codes are used; WP-CLI
 * manages terminal formatting internally.
 *
 * @since n.e.x.t
 */
final class WpCliOutputHandler implements OutputHandlerInterface
{
	/**
	 * Whether debug output is enabled.
	 *
	 * @var bool
	 */
	private bool $debug_enabled = false;

	/**
	 * Writes text to the output without a newline.
	 *
	 * Used for progressive/streaming output where WP-CLI formatting is
	 * not desired (e.g., streaming AI response chunks).
	 *
	 * @param string $text The text to write.
	 *
	 * @return void
	 *
	 * @since n.e.x.t
	 */
	public function write(string $text): void
	{
		echo $text;
	}

	/**
	 * Writes text followed by a newline via WP_CLI::line().
	 *
	 * @param string $text The text to write.
	 *
	 * @return void
	 *
	 * @since n.e.x.t
	 */
	public function writeLine(string $text): void
	{
		\WP_CLI::line($text);
	}

	/**
	 * Writes an error message via WP_CLI::error() in non-fatal mode.
	 *
	 * The second argument `false` prevents WP-CLI from calling exit(),
	 * allowing the agent to continue execution after reporting an error.
	 *
	 * @param string $text The error message.
	 *
	 * @return void
	 *
	 * @since n.e.x.t
	 */
	public function writeError(string $text): void
	{
		\WP_CLI::error($text, false);
	}

	/**
	 * Writes a success message via WP_CLI::success().
	 *
	 * @param string $text The success message.
	 *
	 * @return void
	 *
	 * @since n.e.x.t
	 */
	public function writeSuccess(string $text): void
	{
		\WP_CLI::success($text);
	}

	/**
	 * Writes a warning message via WP_CLI::warning().
	 *
	 * @param string $text The warning message.
	 *
	 * @return void
	 *
	 * @since n.e.x.t
	 */
	public function writeWarning(string $text): void
	{
		\WP_CLI::warning($text);
	}

	/**
	 * Writes a tool execution result as a formatted WP-CLI line.
	 *
	 * Shows [OK] or [FAIL] prefix, the tool name, and up to 200 characters
	 * of output. For failed results, shows the error message instead of output.
	 *
	 * @param string     $tool_name The name of the executed tool.
	 * @param ToolResult $result    The execution result.
	 *
	 * @return void
	 *
	 * @since n.e.x.t
	 */
	public function writeToolResult(string $tool_name, ToolResult $result): void
	{
		$status = $result->isSuccess() ? 'OK' : 'FAIL';

		if (!$result->isSuccess() && $result->getError() !== null) {
			$output = $result->getError();
		} else {
			$output = $result->getOutput();
		}

		\WP_CLI::line(sprintf('[%s] %s: %s', $status, $tool_name, substr($output, 0, 200)));
	}

	/**
	 * Writes the assistant's response text via WP_CLI::line().
	 *
	 * @param string $text The assistant's response.
	 *
	 * @return void
	 *
	 * @since n.e.x.t
	 */
	public function writeAssistantResponse(string $text): void
	{
		\WP_CLI::line($text);
	}

	/**
	 * Writes a streaming chunk of the assistant's response.
	 *
	 * Echoes the chunk directly without a newline, bypassing WP-CLI
	 * formatting to preserve streaming behaviour.
	 *
	 * @param string $chunk The text chunk.
	 *
	 * @return void
	 *
	 * @since n.e.x.t
	 */
	public function writeStreamChunk(string $chunk): void
	{
		echo $chunk;
	}

	/**
	 * Writes a status message via WP_CLI::log().
	 *
	 * @param string $status The status message.
	 *
	 * @return void
	 *
	 * @since n.e.x.t
	 */
	public function writeStatus(string $status): void
	{
		\WP_CLI::log($status);
	}

	/**
	 * Writes a debug message via WP_CLI::debug() when debug mode is enabled.
	 *
	 * Uses the 'wp-ai-agent' group so debug output can be filtered with
	 * `--debug=wp-ai-agent`. Has no effect when debug mode is disabled.
	 *
	 * @param string $message The debug message.
	 *
	 * @return void
	 *
	 * @since n.e.x.t
	 */
	public function writeDebug(string $message): void
	{
		if (!$this->debug_enabled) {
			return;
		}

		\WP_CLI::debug($message, 'wp-ai-agent');
	}

	/**
	 * No-op: WP-CLI manages terminal state and line clearing internally.
	 *
	 * @return void
	 *
	 * @since n.e.x.t
	 */
	public function clearLine(): void
	{
		// No-op: WP-CLI owns the terminal; line clearing is not applicable.
	}

	/**
	 * Sets whether debug output is enabled.
	 *
	 * @param bool $enabled Whether to show debug output.
	 *
	 * @return void
	 *
	 * @since n.e.x.t
	 */
	public function setDebugEnabled(bool $enabled): void
	{
		$this->debug_enabled = $enabled;
	}

	/**
	 * Checks if debug output is enabled.
	 *
	 * @return bool
	 *
	 * @since n.e.x.t
	 */
	public function isDebugEnabled(): bool
	{
		return $this->debug_enabled;
	}
}
