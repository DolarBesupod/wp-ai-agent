<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Contracts;

use Automattic\WpAiAgent\Core\Exceptions\ParseException;
use Automattic\WpAiAgent\Core\ValueObjects\ParsedMarkdown;

/**
 * Interface for parsing markdown content with YAML frontmatter.
 *
 * The parser extracts YAML frontmatter (between `---` delimiters) from
 * markdown content and returns a structured result containing both the
 * parsed frontmatter data and the remaining body content.
 *
 * @since 0.1.0
 */
interface MarkdownParserInterface
{
	/**
	 * Parses markdown content with optional YAML frontmatter.
	 *
	 * Extracts YAML frontmatter if present (content between `---` delimiters
	 * at the start of the content) and returns the parsed data along with
	 * the remaining body content.
	 *
	 * @param string $content The markdown content to parse.
	 *
	 * @return ParsedMarkdown The parsed result containing frontmatter and body.
	 *
	 * @throws ParseException If the frontmatter contains invalid YAML.
	 */
	public function parse(string $content): ParsedMarkdown;

	/**
	 * Parses a markdown file with optional YAML frontmatter.
	 *
	 * Reads the file content and parses it using the same logic as parse().
	 *
	 * @param string $path The path to the markdown file.
	 *
	 * @return ParsedMarkdown The parsed result containing frontmatter and body.
	 *
	 * @throws ParseException If the file cannot be read or contains invalid YAML.
	 */
	public function parseFile(string $path): ParsedMarkdown;
}
