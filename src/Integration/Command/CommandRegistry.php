<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\Command;

use Automattic\WpAiAgent\Core\Command\Command;
use Automattic\WpAiAgent\Core\Contracts\CommandLoaderInterface;
use Automattic\WpAiAgent\Core\Contracts\CommandRegistryInterface;
use Automattic\WpAiAgent\Core\Contracts\SettingsDiscoveryInterface;

/**
 * Registry for managing command registration and retrieval.
 *
 * The registry maintains a collection of available commands and provides
 * methods to register, retrieve, and list them. It supports auto-discovery
 * of commands from markdown files in .wp-ai-agent directories.
 *
 * @since 0.1.0
 */
final class CommandRegistry implements CommandRegistryInterface
{
	/**
	 * The command loader.
	 *
	 * @var CommandLoaderInterface
	 */
	private CommandLoaderInterface $loader;

	/**
	 * The settings discovery service.
	 *
	 * @var SettingsDiscoveryInterface
	 */
	private SettingsDiscoveryInterface $discovery;

	/**
	 * The registered commands.
	 *
	 * @var array<string, Command>
	 */
	private array $commands = [];

	/**
	 * Creates a new CommandRegistry instance.
	 *
	 * @param CommandLoaderInterface $loader The command loader.
	 * @param SettingsDiscoveryInterface $discovery The settings discovery service.
	 *
	 * @since 0.1.0
	 */
	public function __construct(
		CommandLoaderInterface $loader,
		SettingsDiscoveryInterface $discovery
	) {
		$this->loader = $loader;
		$this->discovery = $discovery;
	}

	/**
	 * Registers a command with the registry.
	 *
	 * The command is stored using its full name (namespace:name if namespaced).
	 *
	 * @param Command $command The command to register.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function register(Command $command): void
	{
		$key = $this->buildCommandKey($command);
		$this->commands[$key] = $command;
	}

	/**
	 * Retrieves a command by name.
	 *
	 * @param string $name The command name (can include namespace, e.g., 'frontend:review').
	 *
	 * @return Command|null The command or null if not found.
	 *
	 * @since 0.1.0
	 */
	public function get(string $name): ?Command
	{
		return $this->commands[$name] ?? null;
	}

	/**
	 * Checks if a command with the given name exists.
	 *
	 * @param string $name The command name (can include namespace).
	 *
	 * @return bool True if the command exists, false otherwise.
	 *
	 * @since 0.1.0
	 */
	public function has(string $name): bool
	{
		return isset($this->commands[$name]);
	}

	/**
	 * Returns all registered commands.
	 *
	 * @return array<string, Command> Map of command name to command instance.
	 *
	 * @since 0.1.0
	 */
	public function all(): array
	{
		return $this->commands;
	}

	/**
	 * Returns only custom (non-built-in) commands.
	 *
	 * Custom commands are those loaded from markdown files, as opposed
	 * to built-in commands that are defined in code.
	 *
	 * @return array<string, Command> Map of command name to command instance.
	 *
	 * @since 0.1.0
	 */
	public function getCustomCommands(): array
	{
		return array_filter(
			$this->commands,
			static fn(Command $command): bool => !$command->isBuiltIn()
		);
	}

	/**
	 * Discovers and loads commands from .wp-ai-agent directories.
	 *
	 * Searches for command files in both user and project .wp-ai-agent/commands
	 * directories. Project commands override user commands with the same name.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function discover(): void
	{
		$files = $this->discovery->discover('commands', 'md');

		foreach ($files as $filepath) {
			try {
				$command = $this->loader->load($filepath);
				$this->register($command);
			} catch (\Throwable $exception) {
				// Skip files that fail to load
				// In production, this would be logged
				continue;
			}
		}
	}

	/**
	 * Builds the registry key for a command.
	 *
	 * For namespaced commands, the key is 'namespace:name'.
	 * For root-level commands, the key is just 'name'.
	 *
	 * @param Command $command The command.
	 *
	 * @return string The registry key.
	 */
	private function buildCommandKey(Command $command): string
	{
		$namespace = $command->getNamespace();
		$name = $command->getName();

		if ($namespace === null) {
			return $name;
		}

		return $namespace . ':' . $name;
	}
}
