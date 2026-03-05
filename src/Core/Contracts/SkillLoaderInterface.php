<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Contracts;

use Automattic\WpAiAgent\Core\Skill\Skill;

/**
 * Interface for loading skills from files or content.
 *
 * The skill loader is responsible for parsing markdown files with YAML
 * frontmatter and creating Skill instances from them.
 *
 * @since 0.1.0
 */
interface SkillLoaderInterface
{
	/**
	 * Loads a skill from a file path.
	 *
	 * The file should be a markdown file with optional YAML frontmatter.
	 * The skill name is derived from the filename (without extension).
	 *
	 * @param string $filepath The path to the skill file.
	 *
	 * @return Skill The loaded skill.
	 *
	 * @throws \Automattic\WpAiAgent\Core\Exceptions\ParseException If the file cannot be parsed.
	 * @throws \RuntimeException If the file cannot be read.
	 *
	 * @since 0.1.0
	 */
	public function load(string $filepath): Skill;
}
