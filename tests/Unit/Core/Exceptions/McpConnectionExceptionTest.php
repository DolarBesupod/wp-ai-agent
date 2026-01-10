<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Core\Exceptions;

use PHPUnit\Framework\TestCase;
use PhpCliAgent\Core\Exceptions\AgentException;
use PhpCliAgent\Core\Exceptions\McpConnectionException;

/**
 * Tests for McpConnectionException.
 *
 * @covers \PhpCliAgent\Core\Exceptions\McpConnectionException
 */
final class McpConnectionExceptionTest extends TestCase
{
	public function test_connectionFailed_createsException(): void
	{
		$exception = McpConnectionException::connectionFailed('localhost:8080', 'Connection refused');

		$this->assertStringContainsString('localhost:8080', $exception->getMessage());
		$this->assertStringContainsString('Connection refused', $exception->getMessage());
		$this->assertSame('connection_failed', $exception->getContextValue('type'));
		$this->assertSame('localhost:8080', $exception->getContextValue('server'));
	}

	public function test_connectionFailed_acceptsPreviousException(): void
	{
		$previous = new \RuntimeException('Socket error');
		$exception = McpConnectionException::connectionFailed('server', 'error', $previous);

		$this->assertSame($previous, $exception->getPrevious());
	}

	public function test_timeout_createsException(): void
	{
		$exception = McpConnectionException::timeout('mcp.example.com', 30);

		$this->assertStringContainsString('mcp.example.com', $exception->getMessage());
		$this->assertStringContainsString('30 seconds', $exception->getMessage());
		$this->assertSame('timeout', $exception->getContextValue('type'));
		$this->assertSame(30, $exception->getContextValue('timeout_seconds'));
	}

	public function test_protocolError_createsException(): void
	{
		$exception = McpConnectionException::protocolError('server', 'Invalid JSON-RPC response');

		$this->assertStringContainsString('protocol error', $exception->getMessage());
		$this->assertSame('protocol_error', $exception->getContextValue('type'));
		$this->assertSame('Invalid JSON-RPC response', $exception->getContextValue('reason'));
	}

	public function test_authenticationFailed_createsException(): void
	{
		$exception = McpConnectionException::authenticationFailed('secure-server');

		$this->assertStringContainsString('Authentication failed', $exception->getMessage());
		$this->assertSame('authentication_failed', $exception->getContextValue('type'));
		$this->assertSame('secure-server', $exception->getContextValue('server'));
	}

	public function test_serverUnavailable_createsException(): void
	{
		$exception = McpConnectionException::serverUnavailable('mcp-server');

		$this->assertStringContainsString('unavailable', $exception->getMessage());
		$this->assertSame('server_unavailable', $exception->getContextValue('type'));
	}

	public function test_toolInvocationFailed_createsException(): void
	{
		$exception = McpConnectionException::toolInvocationFailed('server', 'file_read', 'Permission denied');

		$this->assertStringContainsString('file_read', $exception->getMessage());
		$this->assertStringContainsString('server', $exception->getMessage());
		$this->assertSame('tool_invocation_failed', $exception->getContextValue('type'));
		$this->assertSame('file_read', $exception->getContextValue('tool'));
	}

	public function test_exception_extendsAgentException(): void
	{
		$exception = McpConnectionException::serverUnavailable('test');

		$this->assertInstanceOf(AgentException::class, $exception);
	}

	public function test_exception_canBeCaughtAsAgentException(): void
	{
		$caught = false;

		try {
			throw McpConnectionException::timeout('server', 10);
		} catch (AgentException $e) {
			$caught = true;
			$this->assertSame('timeout', $e->getContextValue('type'));
		}

		$this->assertTrue($caught);
	}
}
