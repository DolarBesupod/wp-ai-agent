<?php

declare(strict_types=1);

namespace WpAiAgent\Core\Contracts;

use WpAiAgent\Core\Skill\Skill;

/**
 * Interface for loading skills from files or content.
 *
 * The skill loader is responsible for parsing markdown files with YAML
 * frontmatter and creating Skill instances from them.
 *
 * @since n.e.x.t
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
	 * @throws \WpAiAgent\Core\Exceptions\ParseException If the file cannot be parsed.
	 * @throws \RuntimeException If the file cannot be read.
	 *
	 * @since n.e.x.t
	 */
	public function load(string $filepath): Skill;
}
