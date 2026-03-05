<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Skill;

/**
 * Immutable value object representing a loaded skill.
 *
 * A skill is a reusable prompt template that can be invoked by the agent
 * or other commands. It contains the skill body (prompt content), configuration
 * from frontmatter, and metadata about where it was loaded from.
 *
 * @since 0.1.0
 */
final class Skill
{
	/**
	 * The skill name (used for invocation, e.g., "myvoice").
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * A human-readable description of what the skill does.
	 *
	 * @var string
	 */
	private string $description;

	/**
	 * The skill body content (the prompt template).
	 *
	 * @var string
	 */
	private string $body;

	/**
	 * The skill configuration from frontmatter.
	 *
	 * @var SkillConfig
	 */
	private SkillConfig $config;

	/**
	 * The file path where the skill was loaded from (null for built-in skills).
	 *
	 * @var string|null
	 */
	private ?string $filepath;

	/**
	 * Creates a new Skill instance.
	 *
	 * @param string $name The skill name.
	 * @param string      $description A description of the skill.
	 * @param string $body The skill body content.
	 * @param SkillConfig $config      The skill configuration.
	 * @param string|null $filepath    The source file path (null for built-in).
	 *
	 * @since 0.1.0
	 */
	public function __construct(
		string $name,
		string $description,
		string $body,
		SkillConfig $config,
		?string $filepath = null
	) {
		$this->name = $name;
		$this->description = $description;
		$this->body = $body;
		$this->config = $config;
		$this->filepath = $filepath;
	}

	/**
	 * Returns the skill name.
	 *
	 * @return string
	 *
	 * @since 0.1.0
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Returns the skill description.
	 *
	 * @return string
	 *
	 * @since 0.1.0
	 */
	public function getDescription(): string
	{
		return $this->description;
	}

	/**
	 * Returns the skill body content.
	 *
	 * @return string
	 *
	 * @since 0.1.0
	 */
	public function getBody(): string
	{
		return $this->body;
	}

	/**
	 * Returns the skill configuration.
	 *
	 * @return SkillConfig
	 *
	 * @since 0.1.0
	 */
	public function getConfig(): SkillConfig
	{
		return $this->config;
	}

	/**
	 * Returns the file path where the skill was loaded from.
	 *
	 * @return string|null The file path, or null for built-in skills.
	 *
	 * @since 0.1.0
	 */
	public function getFilePath(): ?string
	{
		return $this->filepath;
	}

	/**
	 * Returns a new Skill instance with an updated body.
	 *
	 * This is useful for creating expanded versions of skills after argument
	 * substitution or template processing, preserving immutability.
	 *
	 * @param string $body The new body content.
	 *
	 * @return self A new Skill instance with the updated body.
	 *
	 * @since 0.1.0
	 */
	public function withBody(string $body): self
	{
		return new self(
			$this->name,
			$this->description,
			$body,
			$this->config,
			$this->filepath
		);
	}
}
