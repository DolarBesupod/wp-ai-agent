<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Integration\Mcp;

use GalatanOvidiu\PhpMcpClient\Core\Client\McpClient;
use GalatanOvidiu\PhpMcpClient\Core\Exception\JsonRpcException;
use GalatanOvidiu\PhpMcpClient\Core\Exception\McpException;
use GalatanOvidiu\PhpMcpClient\Core\Exception\TimeoutException;
use PhpCliAgent\Core\Contracts\ToolInterface;
use PhpCliAgent\Core\Exceptions\ToolExecutionException;
use PhpCliAgent\Integration\Mcp\McpToolAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for McpToolAdapter.
 *
 * @covers \PhpCliAgent\Integration\Mcp\McpToolAdapter
 */
final class McpToolAdapterTest extends TestCase
{
	/**
	 * Mock MCP client for tests.
	 */
	private McpClient $mock_client;

	/**
	 * Sets up the test environment.
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->mock_client = $this->createMock(McpClient::class);
	}

	/**
	 * Creates a McpToolAdapter instance for testing.
	 *
	 * @param string              $tool_name   The original tool name.
	 * @param string              $description The tool description.
	 * @param array<string,mixed> $input_schema The JSON schema for tool input.
	 * @param string              $server_name The MCP server name.
	 *
	 * @return McpToolAdapter
	 */
	private function createAdapter(
		string $tool_name = 'read_file',
		string $description = 'Read file contents',
		array $input_schema = [],
		string $server_name = 'filesystem'
	): McpToolAdapter {
		return new McpToolAdapter(
			$this->mock_client,
			$tool_name,
			$description,
			$input_schema,
			$server_name
		);
	}

	/**
	 * Tests that the adapter implements ToolInterface.
	 */
	public function test_implementsToolInterface(): void
	{
		$adapter = $this->createAdapter();

		$this->assertInstanceOf(ToolInterface::class, $adapter);
	}

	/**
	 * Tests getName returns prefixed name with mcp_{server}_{tool} format.
	 */
	public function test_getName_returnsCorrectlyPrefixedName(): void
	{
		$adapter = $this->createAdapter('read_file', 'Read file', [], 'filesystem');

		$this->assertSame('mcp_filesystem_read_file', $adapter->getName());
	}

	/**
	 * Tests getName handles server names with special characters.
	 */
	public function test_getName_normalizesServerName(): void
	{
		$adapter = $this->createAdapter('list', 'List items', [], 'my-server');

		$this->assertSame('mcp_my_server_list', $adapter->getName());
	}

	/**
	 * Tests getName handles tool names with hyphens.
	 */
	public function test_getName_normalizesToolName(): void
	{
		$adapter = $this->createAdapter('read-file', 'Read file', [], 'fs');

		$this->assertSame('mcp_fs_read_file', $adapter->getName());
	}

	/**
	 * Tests getOriginalName returns the original MCP tool name.
	 */
	public function test_getOriginalName_returnsOriginalToolName(): void
	{
		$adapter = $this->createAdapter('read_file', 'Read file', [], 'filesystem');

		$this->assertSame('read_file', $adapter->getOriginalName());
	}

	/**
	 * Tests getDescription returns the provided description.
	 */
	public function test_getDescription_returnsDescription(): void
	{
		$description = 'Read file contents from the filesystem';
		$adapter = $this->createAdapter('read_file', $description);

		$this->assertSame($description, $adapter->getDescription());
	}

	/**
	 * Tests getParametersSchema returns the input schema.
	 */
	public function test_getParametersSchema_returnsInputSchema(): void
	{
		$schema = [
			'type' => 'object',
			'properties' => [
				'path' => [
					'type' => 'string',
					'description' => 'File path to read',
				],
			],
			'required' => ['path'],
		];

		$adapter = $this->createAdapter('read_file', 'Read file', $schema);

		$this->assertSame($schema, $adapter->getParametersSchema());
	}

	/**
	 * Tests getParametersSchema returns null for empty schema.
	 */
	public function test_getParametersSchema_withEmptySchema_returnsNull(): void
	{
		$adapter = $this->createAdapter('ping', 'Ping server', []);

		$this->assertNull($adapter->getParametersSchema());
	}

	/**
	 * Tests requiresConfirmation returns true for MCP tools.
	 */
	public function test_requiresConfirmation_returnsTrue(): void
	{
		$adapter = $this->createAdapter();

		$this->assertTrue($adapter->requiresConfirmation());
	}

	/**
	 * Tests getServerName returns the server name.
	 */
	public function test_getServerName_returnsServerName(): void
	{
		$adapter = $this->createAdapter('read', 'Read', [], 'my-mcp-server');

		$this->assertSame('my-mcp-server', $adapter->getServerName());
	}

	/**
	 * Tests execute calls McpClient::callTool with correct parameters.
	 */
	public function test_execute_callsClientWithCorrectParameters(): void
	{
		$arguments = ['path' => '/tmp/test.txt'];

		$this->mock_client->expects($this->once())
			->method('callTool')
			->with('read_file', $arguments)
			->willReturn([
				'content' => [
					['type' => 'text', 'text' => 'File contents here'],
				],
			]);

		$adapter = $this->createAdapter('read_file', 'Read file', [], 'filesystem');
		$result = $adapter->execute($arguments);

		$this->assertTrue($result->isSuccess());
	}

	/**
	 * Tests execute returns successful ToolResult for valid response.
	 */
	public function test_execute_withValidResponse_returnsSuccessResult(): void
	{
		$this->mock_client->method('callTool')
			->willReturn([
				'content' => [
					['type' => 'text', 'text' => 'Operation completed successfully'],
				],
			]);

		$adapter = $this->createAdapter();
		$result = $adapter->execute([]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame('Operation completed successfully', $result->getOutput());
		$this->assertNull($result->getError());
	}

	/**
	 * Tests execute handles multiple content items.
	 */
	public function test_execute_withMultipleContent_concatenatesOutput(): void
	{
		$this->mock_client->method('callTool')
			->willReturn([
				'content' => [
					['type' => 'text', 'text' => 'Line 1'],
					['type' => 'text', 'text' => 'Line 2'],
					['type' => 'text', 'text' => 'Line 3'],
				],
			]);

		$adapter = $this->createAdapter();
		$result = $adapter->execute([]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('Line 1', $result->getOutput());
		$this->assertStringContainsString('Line 2', $result->getOutput());
		$this->assertStringContainsString('Line 3', $result->getOutput());
	}

	/**
	 * Tests execute handles isError flag in response.
	 */
	public function test_execute_withIsErrorTrue_returnsFailureResult(): void
	{
		$this->mock_client->method('callTool')
			->willReturn([
				'content' => [
					['type' => 'text', 'text' => 'File not found'],
				],
				'isError' => true,
			]);

		$adapter = $this->createAdapter();
		$result = $adapter->execute(['path' => '/nonexistent']);

		$this->assertFalse($result->isSuccess());
		$this->assertSame('File not found', $result->getError());
	}

	/**
	 * Tests execute handles McpException.
	 */
	public function test_execute_withMcpException_returnsFailureResult(): void
	{
		$this->mock_client->method('callTool')
			->willThrowException(new McpException('Server error'));

		$adapter = $this->createAdapter();
		$result = $adapter->execute([]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('Server error', $result->getError());
	}

	/**
	 * Tests execute handles TimeoutException.
	 */
	public function test_execute_withTimeoutException_returnsFailureResult(): void
	{
		$this->mock_client->method('callTool')
			->willThrowException(new TimeoutException('Request timed out'));

		$adapter = $this->createAdapter();
		$result = $adapter->execute([]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('timed out', $result->getError());
	}

	/**
	 * Tests execute handles JsonRpcException.
	 */
	public function test_execute_withJsonRpcException_returnsFailureResult(): void
	{
		$this->mock_client->method('callTool')
			->willThrowException(new JsonRpcException('Invalid params', -32602));

		$adapter = $this->createAdapter();
		$result = $adapter->execute([]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('Invalid params', $result->getError());
	}

	/**
	 * Tests execute handles empty content array.
	 */
	public function test_execute_withEmptyContent_returnsEmptyOutput(): void
	{
		$this->mock_client->method('callTool')
			->willReturn([
				'content' => [],
			]);

		$adapter = $this->createAdapter();
		$result = $adapter->execute([]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame('', $result->getOutput());
	}

	/**
	 * Tests execute handles non-text content types.
	 */
	public function test_execute_withImageContent_describesNonTextContent(): void
	{
		$this->mock_client->method('callTool')
			->willReturn([
				'content' => [
					[
						'type' => 'image',
						'data' => 'base64data...',
						'mimeType' => 'image/png',
					],
				],
			]);

		$adapter = $this->createAdapter();
		$result = $adapter->execute([]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('image', $result->getOutput());
	}

	/**
	 * Tests execute handles resource content type.
	 */
	public function test_execute_withResourceContent_includesResourceInfo(): void
	{
		$this->mock_client->method('callTool')
			->willReturn([
				'content' => [
					[
						'type' => 'resource',
						'resource' => [
							'uri' => 'file:///tmp/test.txt',
							'text' => 'Resource content',
						],
					],
				],
			]);

		$adapter = $this->createAdapter();
		$result = $adapter->execute([]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('Resource content', $result->getOutput());
	}

	/**
	 * Tests execute includes MCP response in data array.
	 */
	public function test_execute_includesRawResponseInData(): void
	{
		$response = [
			'content' => [
				['type' => 'text', 'text' => 'Test'],
			],
		];

		$this->mock_client->method('callTool')
			->willReturn($response);

		$adapter = $this->createAdapter();
		$result = $adapter->execute([]);

		$data = $result->getData();
		$this->assertArrayHasKey('mcp_response', $data);
		$this->assertSame($response, $data['mcp_response']);
	}

	/**
	 * Tests execute includes server name in data array.
	 */
	public function test_execute_includesServerNameInData(): void
	{
		$this->mock_client->method('callTool')
			->willReturn([
				'content' => [
					['type' => 'text', 'text' => 'Test'],
				],
			]);

		$adapter = $this->createAdapter('tool', 'Desc', [], 'my-server');
		$result = $adapter->execute([]);

		$data = $result->getData();
		$this->assertArrayHasKey('server_name', $data);
		$this->assertSame('my-server', $data['server_name']);
	}

	/**
	 * Tests execute with custom timeout.
	 */
	public function test_execute_withCustomTimeout_usesTimeout(): void
	{
		$this->mock_client->expects($this->once())
			->method('callTool')
			->with('test_tool', [], 120.0)
			->willReturn([
				'content' => [
					['type' => 'text', 'text' => 'Done'],
				],
			]);

		$adapter = new McpToolAdapter(
			$this->mock_client,
			'test_tool',
			'Test',
			[],
			'server',
			120.0
		);

		$result = $adapter->execute([]);

		$this->assertTrue($result->isSuccess());
	}
}
