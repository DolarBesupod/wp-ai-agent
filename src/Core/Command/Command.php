<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Core\Command;

/**
 * Immutable value object representing a loaded command.
 *
 * A command is a prompt template that can be invoked via slash syntax.
 * It contains the command body (prompt content), configuration from
 * frontmatter, and metadata about where it was loaded from.
 *
 * @since n.e.x.t
 */
final class Command
{
	/**
	 * The command name (used for invocation, e.g., "commit").
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * A human-readable description of what the command does.
	 *
	 * @var string
	 */
	private string $description;

	/**
	 * The command body content (the prompt template).
	 *
	 * @var string
	 */
	private string $body;

	/**
	 * The command configuration from frontmatter.
	 *
	 * @var CommandConfig
	 */
	private CommandConfig $config;

	/**
	 * The file path where the command was loaded from (null for built-in commands).
	 *
	 * @var string|null
	 */
	private ?string $filepath;

	/**
	 * The namespace the command belongs to (e.g., "project", "global").
	 *
	 * @var string|null
	 */
	private ?string $namespace;

	/**
	 * Creates a new Command instance.
	 *
	 * @param string        $name        The command name.
	 * @param string        $description A description of the command.
	 * @param string        $body        The command body content.
	 * @param CommandConfig $config      The command configuration.
	 * @param string|null   $filepath    The source file path (null for built-in).
	 * @param string|null   $namespace   The command namespace.
	 *
	 * @since n.e.x.t
	 */
	public function __construct(
		string $name,
		string $description,
		string $body,
		CommandConfig $config,
		?string $filepath = null,
		?string $namespace = null
	) {
		$this->name = $name;
		$this->description = $description;
		$this->body = $body;
		$this->config = $config;
		$this->filepath = $filepath;
		$this->namespace = $namespace;
	}

	/**
	 * Returns the command name.
	 *
	 * @return string
	 *
	 * @since n.e.x.t
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Returns the command description.
	 *
	 * @return string
	 *
	 * @since n.e.x.t
	 */
	public function getDescription(): string
	{
		return $this->description;
	}

	/**
	 * Returns the command body content.
	 *
	 * @return string
	 *
	 * @since n.e.x.t
	 */
	public function getBody(): string
	{
		return $this->body;
	}

	/**
	 * Returns the command configuration.
	 *
	 * @return CommandConfig
	 *
	 * @since n.e.x.t
	 */
	public function getConfig(): CommandConfig
	{
		return $this->config;
	}

	/**
	 * Returns the file path where the command was loaded from.
	 *
	 * @return string|null The file path, or null for built-in commands.
	 *
	 * @since n.e.x.t
	 */
	public function getFilePath(): ?string
	{
		return $this->filepath;
	}

	/**
	 * Returns the command namespace.
	 *
	 * @return string|null The namespace, or null if not set.
	 *
	 * @since n.e.x.t
	 */
	public function getNamespace(): ?string
	{
		return $this->namespace;
	}

	/**
	 * Checks if this is a built-in command.
	 *
	 * Built-in commands have no file path since they are defined in code.
	 *
	 * @return bool True if this is a built-in command.
	 *
	 * @since n.e.x.t
	 */
	public function isBuiltIn(): bool
	{
		return $this->filepath === null;
	}

	/**
	 * Returns a new Command instance with an updated body.
	 *
	 * This is useful for creating expanded versions of commands
	 * after argument substitution or template processing.
	 *
	 * @param string $body The new body content.
	 *
	 * @return self A new Command instance with the updated body.
	 *
	 * @since n.e.x.t
	 */
	public function withBody(string $body): self
	{
		return new self(
			$this->name,
			$this->description,
			$body,
			$this->config,
			$this->filepath,
			$this->namespace
		);
	}
}
