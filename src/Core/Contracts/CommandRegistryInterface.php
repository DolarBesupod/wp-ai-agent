<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Core\Contracts;

use Automattic\Automattic\WpAiAgent\Core\Command\Command;

/**
 * Interface for managing command registration and retrieval.
 *
 * The command registry maintains a collection of available commands and provides
 * methods to register, retrieve, and list them. It supports both built-in commands
 * and custom commands loaded from markdown files.
 *
 * @since n.e.x.t
 */
interface CommandRegistryInterface
{
	/**
	 * Registers a command with the registry.
	 *
	 * @param Command $command The command to register.
	 *
	 * @return void
	 */
	public function register(Command $command): void;

	/**
	 * Retrieves a command by name.
	 *
	 * @param string $name The command name.
	 *
	 * @return Command|null The command or null if not found.
	 */
	public function get(string $name): ?Command;

	/**
	 * Checks if a command with the given name exists.
	 *
	 * @param string $name The command name.
	 *
	 * @return bool True if the command exists, false otherwise.
	 */
	public function has(string $name): bool;

	/**
	 * Returns all registered commands.
	 *
	 * @return array<string, Command> Map of command name to command instance.
	 */
	public function all(): array;

	/**
	 * Returns only custom (non-built-in) commands.
	 *
	 * Custom commands are those loaded from markdown files, as opposed
	 * to built-in commands that are defined in code.
	 *
	 * @return array<string, Command> Map of command name to command instance.
	 */
	public function getCustomCommands(): array;
}
