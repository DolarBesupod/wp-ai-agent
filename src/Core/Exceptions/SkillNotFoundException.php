<?php

declare(strict_types=1);

namespace WpAiAgent\Core\Exceptions;

/**
 * Exception thrown when a requested skill is not found in the repository.
 *
 * @since n.e.x.t
 */
final class SkillNotFoundException extends \RuntimeException
{
	/**
	 * Creates a SkillNotFoundException for the given skill name.
	 *
	 * @param string $name The name of the skill that was not found.
	 *
	 * @return self
	 *
	 * @since n.e.x.t
	 */
	public static function forName(string $name): self
	{
		return new self("Skill not found: {$name}");
	}
}
