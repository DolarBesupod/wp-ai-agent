<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Exceptions;

/**
 * Exception thrown when MCP (Model Context Protocol) connection operations fail.
 *
 * @since 0.1.0
 */
class McpConnectionException extends AgentException
{
	/**
	 * Creates an exception for connection failures.
	 *
	 * @param string $server The MCP server identifier or URL.
	 * @param string $reason The reason for the failure.
	 * @param \Throwable|null $previous Optional previous exception.
	 *
	 * @return self
	 */
	public static function connectionFailed(string $server, string $reason, ?\Throwable $previous = null): self
	{
		return new self(
			sprintf('Failed to connect to MCP server "%s": %s', $server, $reason),
			0,
			$previous,
			['type' => 'connection_failed', 'server' => $server, 'reason' => $reason]
		);
	}

	/**
	 * Creates an exception for connection timeout.
	 *
	 * @param string $server          The MCP server identifier or URL.
	 * @param int    $timeout_seconds The timeout duration in seconds.
	 *
	 * @return self
	 */
	public static function timeout(string $server, int $timeout_seconds): self
	{
		return new self(
			sprintf('Connection to MCP server "%s" timed out after %d seconds.', $server, $timeout_seconds),
			0,
			null,
			['type' => 'timeout', 'server' => $server, 'timeout_seconds' => $timeout_seconds]
		);
	}

	/**
	 * Creates an exception for protocol errors.
	 *
	 * @param string $server The MCP server identifier or URL.
	 * @param string $reason The protocol error description.
	 * @param \Throwable|null $previous Optional previous exception.
	 *
	 * @return self
	 */
	public static function protocolError(string $server, string $reason, ?\Throwable $previous = null): self
	{
		return new self(
			sprintf('MCP protocol error with server "%s": %s', $server, $reason),
			0,
			$previous,
			['type' => 'protocol_error', 'server' => $server, 'reason' => $reason]
		);
	}

	/**
	 * Creates an exception for authentication failures.
	 *
	 * @param string $server The MCP server identifier or URL.
	 *
	 * @return self
	 */
	public static function authenticationFailed(string $server): self
	{
		return new self(
			sprintf('Authentication failed with MCP server "%s".', $server),
			0,
			null,
			['type' => 'authentication_failed', 'server' => $server]
		);
	}

	/**
	 * Creates an exception for server unavailability.
	 *
	 * @param string $server The MCP server identifier or URL.
	 *
	 * @return self
	 */
	public static function serverUnavailable(string $server): self
	{
		return new self(
			sprintf('MCP server "%s" is unavailable.', $server),
			0,
			null,
			['type' => 'server_unavailable', 'server' => $server]
		);
	}

	/**
	 * Creates an exception for tool invocation failures.
	 *
	 * @param string $server The MCP server identifier or URL.
	 * @param string          $tool_name The tool that failed.
	 * @param string $reason The reason for the failure.
	 * @param \Throwable|null $previous  Optional previous exception.
	 *
	 * @return self
	 */
	public static function toolInvocationFailed(
		string $server,
		string $tool_name,
		string $reason,
		?\Throwable $previous = null
	): self {
		return new self(
			sprintf('Failed to invoke tool "%s" on MCP server "%s": %s', $tool_name, $server, $reason),
			0,
			$previous,
			['type' => 'tool_invocation_failed', 'server' => $server, 'tool' => $tool_name, 'reason' => $reason]
		);
	}
}
