<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\Tool;

use WpAiAgent\Core\Contracts\ToolInterface;
use WpAiAgent\Core\Contracts\ToolRegistryInterface;
use WpAiAgent\Core\Tool\ToolRegistry;
use WpAiAgent\Integration\Tool\BuiltIn\BashTool;
use WpAiAgent\Integration\Tool\BuiltIn\GlobTool;
use WpAiAgent\Integration\Tool\BuiltIn\GrepTool;
use WpAiAgent\Integration\Tool\BuiltIn\ReadFileTool;
use WpAiAgent\Integration\Tool\BuiltIn\ThinkTool;
use WpAiAgent\Integration\Tool\BuiltIn\WriteFileTool;

/**
 * Registry factory for built-in tools.
 *
 * Provides convenient factory methods to create a ToolRegistry pre-populated
 * with all built-in tools. Supports excluding specific tools when needed.
 *
 * @since n.e.x.t
 */
final class BuiltInToolRegistry
{
	/**
	 * List of all built-in tool class names.
	 *
	 * @var array<class-string<ToolInterface>>
	 */
	private const BUILT_IN_TOOLS = [
		BashTool::class,
		ReadFileTool::class,
		WriteFileTool::class,
		GlobTool::class,
		GrepTool::class,
		ThinkTool::class,
	];

	/**
	 * Creates a ToolRegistry with all built-in tools registered.
	 *
	 * @return ToolRegistryInterface The registry with all tools.
	 */
	public static function createWithAllTools(): ToolRegistryInterface
	{
		return self::createWithExcluded([]);
	}

	/**
	 * Creates a ToolRegistry with specific tools excluded.
	 *
	 * @param array<string> $excluded_names Tool names to exclude.
	 *
	 * @return ToolRegistryInterface The registry with filtered tools.
	 */
	public static function createWithExcluded(array $excluded_names): ToolRegistryInterface
	{
		$registry = new ToolRegistry();

		foreach (self::BUILT_IN_TOOLS as $tool_class) {
			$tool = new $tool_class();
			if (!in_array($tool->getName(), $excluded_names, true)) {
				$registry->register($tool);
			}
		}

		return $registry;
	}

	/**
	 * Creates a ToolRegistry with only the specified tools.
	 *
	 * @param array<string> $include_names Tool names to include.
	 *
	 * @return ToolRegistryInterface The registry with only specified tools.
	 */
	public static function createWithOnly(array $include_names): ToolRegistryInterface
	{
		$registry = new ToolRegistry();

		foreach (self::BUILT_IN_TOOLS as $tool_class) {
			$tool = new $tool_class();
			if (in_array($tool->getName(), $include_names, true)) {
				$registry->register($tool);
			}
		}

		return $registry;
	}

	/**
	 * Returns the list of all built-in tool names.
	 *
	 * @return array<string> The tool names.
	 */
	public static function getAvailableToolNames(): array
	{
		$names = [];
		foreach (self::BUILT_IN_TOOLS as $tool_class) {
			$tool = new $tool_class();
			$names[] = $tool->getName();
		}

		return $names;
	}

	/**
	 * Returns an array of all built-in tool instances.
	 *
	 * @return array<ToolInterface> The tool instances.
	 */
	public static function getAllTools(): array
	{
		$tools = [];
		foreach (self::BUILT_IN_TOOLS as $tool_class) {
			$tools[] = new $tool_class();
		}

		return $tools;
	}

	/**
	 * Creates a specific built-in tool by name.
	 *
	 * @param string $name The tool name.
	 *
	 * @return ToolInterface|null The tool instance or null if not found.
	 */
	public static function createTool(string $name): ?ToolInterface
	{
		foreach (self::BUILT_IN_TOOLS as $tool_class) {
			$tool = new $tool_class();
			if ($tool->getName() === $name) {
				return $tool;
			}
		}

		return null;
	}
}
