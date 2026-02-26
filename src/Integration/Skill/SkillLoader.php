<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\Skill;

use WpAiAgent\Core\Contracts\MarkdownParserInterface;
use WpAiAgent\Core\Contracts\SkillLoaderInterface;
use WpAiAgent\Core\Exceptions\ParseException;
use WpAiAgent\Core\Skill\Skill;
use WpAiAgent\Core\Skill\SkillConfig;

/**
 * Loads skills from markdown files with YAML frontmatter.
 *
 * The skill loader parses markdown files and creates Skill instances from them.
 * It extracts the skill name from the filename and configuration from the
 * YAML frontmatter.
 *
 * @since n.e.x.t
 */
final class SkillLoader implements SkillLoaderInterface
{
	/**
	 * The markdown parser.
	 *
	 * @var MarkdownParserInterface
	 */
	private MarkdownParserInterface $markdown_parser;

	/**
	 * Creates a new SkillLoader instance.
	 *
	 * @param MarkdownParserInterface $markdown_parser The markdown parser.
	 *
	 * @since n.e.x.t
	 */
	public function __construct(MarkdownParserInterface $markdown_parser)
	{
		$this->markdown_parser = $markdown_parser;
	}

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
	 * @throws ParseException    If the file cannot be parsed.
	 * @throws \RuntimeException If the file cannot be read.
	 *
	 * @since n.e.x.t
	 */
	public function load(string $filepath): Skill
	{
		$parsed = $this->markdown_parser->parseFile($filepath);

		$name = basename($filepath, '.md');
		$frontmatter = $parsed->getFrontmatter();
		$description = $this->extractDescription($frontmatter, $name);
		$body = $parsed->getBody();
		$config = SkillConfig::fromFrontmatter($frontmatter);

		return new Skill(
			$name,
			$description,
			$body,
			$config,
			$filepath
		);
	}

	/**
	 * Loads a skill from raw markdown content.
	 *
	 * This is useful when importing from a file passed via --file= where the
	 * caller reads the file and passes the content directly.
	 *
	 * @param string $name     The skill name.
	 * @param string $markdown The raw markdown content (with optional frontmatter).
	 *
	 * @return Skill The loaded skill.
	 *
	 * @throws ParseException If the content cannot be parsed.
	 *
	 * @since n.e.x.t
	 */
	public function loadFromMarkdown(string $name, string $markdown): Skill
	{
		$parsed = $this->markdown_parser->parse($markdown);

		$frontmatter = $parsed->getFrontmatter();
		$description = $this->extractDescription($frontmatter, $name);
		$body = $parsed->getBody();
		$config = SkillConfig::fromFrontmatter($frontmatter);

		return new Skill(
			$name,
			$description,
			$body,
			$config,
			null
		);
	}

	/**
	 * Extracts the description from frontmatter, falling back to a default.
	 *
	 * @param array<string, mixed> $frontmatter The frontmatter data.
	 * @param string               $name        The skill name used for the fallback default.
	 *
	 * @return string The description.
	 */
	private function extractDescription(array $frontmatter, string $name): string
	{
		$description = $frontmatter['description'] ?? null;

		if (!is_string($description)) {
			return "Skill: {$name}";
		}

		return $description;
	}
}
