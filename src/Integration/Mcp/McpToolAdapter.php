<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\Mcp;

use Automattic\PhpMcpClient\Client\McpClient;
use Automattic\WpAiAgent\Core\Contracts\ToolInterface;
use Automattic\WpAiAgent\Core\ValueObjects\ToolResult;
use Throwable;

/**
 * Adapts MCP tools to the ToolInterface for use by the agent.
 *
 * Wraps MCP tool metadata (name, description, inputSchema) and executes
 * tool calls via McpClient::callTool(). Tool names are prefixed with
 * mcp_{serverName}_ to avoid collisions with built-in tools.
 *
 * @since n.e.x.t
 */
class McpToolAdapter implements ToolInterface
{
	/**
	 * Default timeout for tool execution in seconds.
	 *
	 * @var float
	 */
	private const DEFAULT_TIMEOUT = 60.0;

	/**
	 * The MCP client to use for tool execution.
	 *
	 * @var McpClient
	 */
	private McpClient $client;

	/**
	 * The original MCP tool name.
	 *
	 * @var string
	 */
	private string $original_name;

	/**
	 * The tool description.
	 *
	 * @var string
	 */
	private string $description;

	/**
	 * The JSON schema for tool input parameters.
	 *
	 * @var array<string, mixed>
	 */
	private array $input_schema;

	/**
	 * The MCP server name.
	 *
	 * @var string
	 */
	private string $server_name;

	/**
	 * Timeout for tool execution in seconds.
	 *
	 * @var float
	 */
	private float $timeout;

	/**
	 * Creates a new McpToolAdapter.
	 *
	 * @param McpClient           $client       The MCP client for tool execution.
	 * @param string              $tool_name    The original MCP tool name.
	 * @param string              $description  The tool description.
	 * @param array<string,mixed> $input_schema The JSON schema for tool input.
	 * @param string              $server_name  The MCP server name (for prefixing).
	 * @param float               $timeout      Optional timeout in seconds.
	 */
	public function __construct(
		McpClient $client,
		string $tool_name,
		string $description,
		array $input_schema,
		string $server_name,
		float $timeout = self::DEFAULT_TIMEOUT
	) {
		$this->client = $client;
		$this->original_name = $tool_name;
		$this->description = $description;
		$this->input_schema = $input_schema;
		$this->server_name = $server_name;
		$this->timeout = $timeout;
	}

	/**
	 * Returns the unique name of the tool.
	 *
	 * The name is prefixed with mcp_{serverName}_ to avoid collisions
	 * with built-in tools. Server names and tool names with hyphens
	 * are normalized to underscores.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		$normalized_server = $this->normalizeName($this->server_name);
		$normalized_tool = $this->normalizeName($this->original_name);

		return sprintf('mcp_%s_%s', $normalized_server, $normalized_tool);
	}

	/**
	 * Returns the original MCP tool name.
	 *
	 * @return string
	 */
	public function getOriginalName(): string
	{
		return $this->original_name;
	}

	/**
	 * Returns the MCP server name.
	 *
	 * @return string
	 */
	public function getServerName(): string
	{
		return $this->server_name;
	}

	/**
	 * Returns a description of what the tool does.
	 *
	 * @return string
	 */
	public function getDescription(): string
	{
		return $this->description;
	}

	/**
	 * Returns the JSON Schema for the tool's parameters.
	 *
	 * @return array<string, mixed>|null The JSON Schema or null for no parameters.
	 */
	public function getParametersSchema(): ?array
	{
		if (count($this->input_schema) === 0) {
			return null;
		}

		return $this->input_schema;
	}

	/**
	 * Executes the tool with the given arguments.
	 *
	 * Calls the MCP server via McpClient::callTool() and converts the
	 * response to a ToolResult.
	 *
	 * @param array<string, mixed> $arguments The arguments matching the parameters schema.
	 *
	 * @return ToolResult The result of the execution.
	 */
	public function execute(array $arguments): ToolResult
	{
		try {
			$response = $this->client->callTool(
				$this->original_name,
				$arguments,
				$this->timeout
			);

			return $this->parseResponse($response);
		} catch (Throwable $exception) {
			return $this->createFailureFromException($exception);
		}
	}

	/**
	 * MCP tools always require confirmation as they are external.
	 *
	 * @return bool Always returns true.
	 */
	public function requiresConfirmation(): bool
	{
		return true;
	}

	/**
	 * Parses the MCP tool response into a ToolResult.
	 *
	 * @param array<string, mixed> $response The MCP response.
	 *
	 * @return ToolResult
	 */
	private function parseResponse(array $response): ToolResult
	{
		$raw_content = $response['content'] ?? [];
		$content_items = is_array($raw_content) ? $raw_content : [];
		$is_error = $response['isError'] ?? false;

		$output = $this->extractContent($content_items);

		$data = [
			'mcp_response' => $response,
			'server_name' => $this->server_name,
		];

		if ($is_error) {
			return new ToolResult(false, '', $output, $data);
		}

		return new ToolResult(true, $output, null, $data);
	}

	/**
	 * Extracts text content from MCP content items.
	 *
	 * @param array<int|string, mixed> $content_items The content items.
	 *
	 * @return string The extracted text.
	 */
	private function extractContent(array $content_items): string
	{
		$parts = [];

		foreach ($content_items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$type = isset($item['type']) && is_string($item['type'])
				? $item['type']
				: 'unknown';

			switch ($type) {
				case 'text':
					$text = isset($item['text']) && is_string($item['text'])
						? $item['text']
						: '';
					$parts[] = $text;
					break;

				case 'image':
					$mime_type = isset($item['mimeType']) && is_string($item['mimeType'])
						? $item['mimeType']
						: 'unknown';
					$parts[] = sprintf('[image: %s]', $mime_type);
					break;

				case 'resource':
					$resource = isset($item['resource']) && is_array($item['resource'])
						? $item['resource']
						: [];
					if (isset($resource['text']) && is_string($resource['text'])) {
						$parts[] = $resource['text'];
					} elseif (isset($resource['uri']) && is_string($resource['uri'])) {
						$parts[] = sprintf('[resource: %s]', $resource['uri']);
					}
					break;

				default:
					$parts[] = sprintf('[%s content]', $type);
					break;
			}
		}

		return implode("\n", $parts);
	}

	/**
	 * Creates a failure ToolResult from an exception.
	 *
	 * @param Throwable $exception The exception that occurred.
	 *
	 * @return ToolResult
	 */
	private function createFailureFromException(Throwable $exception): ToolResult
	{
		$error_message = sprintf(
			'MCP tool execution failed: %s',
			$exception->getMessage()
		);

		return ToolResult::failure($error_message);
	}

	/**
	 * Normalizes a name by replacing hyphens with underscores.
	 *
	 * @param string $name The name to normalize.
	 *
	 * @return string The normalized name.
	 */
	private function normalizeName(string $name): string
	{
		return str_replace('-', '_', $name);
	}
}
