<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Core\Contracts;

/**
 * Interface for managing tool registration and retrieval.
 *
 * The tool registry maintains a collection of available tools and provides
 * methods to register, retrieve, and list them. It also generates the tool
 * declarations needed for the AI model.
 *
 * @since n.e.x.t
 */
interface ToolRegistryInterface
{
	/**
	 * Registers a tool with the registry.
	 *
	 * @param ToolInterface $tool The tool to register.
	 *
	 * @return void
	 *
	 * @throws \Automattic\WpAiAgent\Core\Exceptions\DuplicateToolException If a tool with the same name exists.
	 */
	public function register(ToolInterface $tool): void;

	/**
	 * Retrieves a tool by name.
	 *
	 * @param string $name The tool name.
	 *
	 * @return ToolInterface|null The tool or null if not found.
	 */
	public function get(string $name): ?ToolInterface;

	/**
	 * Checks if a tool with the given name exists.
	 *
	 * @param string $name The tool name.
	 *
	 * @return bool True if the tool exists, false otherwise.
	 */
	public function has(string $name): bool;

	/**
	 * Returns all registered tools.
	 *
	 * @return array<string, ToolInterface> Map of tool name to tool instance.
	 */
	public function all(): array;

	/**
	 * Returns tool declarations for the AI model.
	 *
	 * The declarations include the tool name, description, and parameters
	 * schema in the format expected by the AI adapter.
	 *
	 * @return array<int, array{name: string, description: string, parameters?: array<string, mixed>}>
	 */
	public function getDeclarations(): array;

	/**
	 * Removes a tool from the registry.
	 *
	 * @param string $name The tool name.
	 *
	 * @return bool True if the tool was removed, false if it didn't exist.
	 */
	public function remove(string $name): bool;

	/**
	 * Returns the count of registered tools.
	 *
	 * @return int
	 */
	public function count(): int;
}
