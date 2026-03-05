<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Core\Tool;

use Automattic\Automattic\WpAiAgent\Core\Contracts\ToolInterface;
use Automattic\Automattic\WpAiAgent\Core\Contracts\ToolRegistryInterface;
use Automattic\Automattic\WpAiAgent\Core\Exceptions\DuplicateToolException;

/**
 * Registry for managing tool registration and retrieval.
 *
 * This class maintains a collection of available tools, indexed by name,
 * and provides methods to register, retrieve, and list them.
 *
 * @since n.e.x.t
 */
final class ToolRegistry implements ToolRegistryInterface
{
	/**
	 * Registered tools indexed by name.
	 *
	 * @var array<string, ToolInterface>
	 */
	private array $tools = [];

	/**
	 * Adapter for converting tools to declarations.
	 *
	 * @var ToolDeclarationAdapter
	 */
	private ToolDeclarationAdapter $adapter;

	/**
	 * Creates a new ToolRegistry instance.
	 *
	 * @param ToolDeclarationAdapter|null $adapter Optional declaration adapter.
	 */
	public function __construct(?ToolDeclarationAdapter $adapter = null)
	{
		$this->adapter = $adapter ?? new ToolDeclarationAdapter();
	}

	/**
	 * Registers a tool with the registry.
	 *
	 * @param ToolInterface $tool The tool to register.
	 *
	 * @throws DuplicateToolException If a tool with the same name already exists.
	 */
	public function register(ToolInterface $tool): void
	{
		$name = $tool->getName();

		if (isset($this->tools[$name])) {
			throw new DuplicateToolException($name);
		}

		$this->tools[$name] = $tool;
	}

	/**
	 * Registers multiple tools at once.
	 *
	 * @param array<ToolInterface> $tools The tools to register.
	 *
	 * @throws DuplicateToolException If any tool name is duplicated.
	 */
	public function registerMultiple(array $tools): void
	{
		foreach ($tools as $tool) {
			$this->register($tool);
		}
	}

	/**
	 * Retrieves a tool by name.
	 *
	 * @param string $name The tool name.
	 *
	 * @return ToolInterface|null The tool or null if not found.
	 */
	public function get(string $name): ?ToolInterface
	{
		return $this->tools[$name] ?? null;
	}

	/**
	 * Checks if a tool with the given name exists.
	 *
	 * @param string $name The tool name.
	 *
	 * @return bool True if the tool exists.
	 */
	public function has(string $name): bool
	{
		return isset($this->tools[$name]);
	}

	/**
	 * Returns all registered tools.
	 *
	 * @return array<string, ToolInterface> Map of tool name to tool instance.
	 */
	public function all(): array
	{
		return $this->tools;
	}

	/**
	 * Returns tool declarations for the AI model.
	 *
	 * @return array<int, array{name: string, description: string, parameters?: array<string, mixed>}>
	 */
	public function getDeclarations(): array
	{
		return $this->adapter->toDeclarations(array_values($this->tools));
	}

	/**
	 * Returns tool declarations in Claude API format.
	 *
	 * @return array<int, array{name: string, description: string, input_schema: array<string, mixed>}>
	 */
	public function getClaudeDeclarations(): array
	{
		return $this->adapter->toClaudeFormatMultiple(array_values($this->tools));
	}

	/**
	 * Removes a tool from the registry.
	 *
	 * @param string $name The tool name.
	 *
	 * @return bool True if the tool was removed, false if it didn't exist.
	 */
	public function remove(string $name): bool
	{
		if (!isset($this->tools[$name])) {
			return false;
		}

		unset($this->tools[$name]);

		return true;
	}

	/**
	 * Returns the count of registered tools.
	 *
	 * @return int
	 */
	public function count(): int
	{
		return count($this->tools);
	}

	/**
	 * Clears all registered tools.
	 *
	 * @return void
	 */
	public function clear(): void
	{
		$this->tools = [];
	}

	/**
	 * Returns tool names as an array.
	 *
	 * @return array<int, string>
	 */
	public function getToolNames(): array
	{
		return array_keys($this->tools);
	}
}
