<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\Configuration;

use Automattic\WpAiAgent\Core\Contracts\MarkdownParserInterface;
use Automattic\WpAiAgent\Core\Exceptions\ParseException;
use Automattic\WpAiAgent\Core\ValueObjects\ParsedMarkdown;
use Symfony\Component\Yaml\Exception\ParseException as YamlParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Parses markdown content with YAML frontmatter.
 *
 * Uses Symfony YAML component for parsing the frontmatter section.
 * The frontmatter is expected to be enclosed between `---` delimiters
 * at the start of the content.
 *
 * @since 0.1.0
 */
final class MarkdownParser implements MarkdownParserInterface
{
	/**
	 * The frontmatter delimiter.
	 */
	private const DELIMITER = '---';

	/**
	 * Parses markdown content with optional YAML frontmatter.
	 *
	 * @param string $content The markdown content to parse.
	 *
	 * @return ParsedMarkdown The parsed result containing frontmatter and body.
	 *
	 * @throws ParseException If the frontmatter contains invalid YAML.
	 */
	public function parse(string $content): ParsedMarkdown
	{
		// Normalize line endings
		$content = str_replace("\r\n", "\n", $content);
		$content = str_replace("\r", "\n", $content);

		// Check if content starts with frontmatter delimiter
		if (!str_starts_with($content, self::DELIMITER)) {
			return new ParsedMarkdown([], trim($content));
		}

		// Find the closing delimiter
		$first_delimiter_end = strlen(self::DELIMITER);
		$closing_position = strpos($content, "\n" . self::DELIMITER, $first_delimiter_end);

		if ($closing_position === false) {
			// No closing delimiter found, treat everything as body
			return new ParsedMarkdown([], trim($content));
		}

		// Extract the frontmatter YAML (between the delimiters)
		$frontmatter_yaml = substr($content, $first_delimiter_end, $closing_position - $first_delimiter_end);
		$frontmatter_yaml = trim($frontmatter_yaml);

		// Extract the body (after the closing delimiter)
		$body_start = $closing_position + strlen("\n" . self::DELIMITER);
		$body = substr($content, $body_start);
		$body = trim($body);

		// Handle empty frontmatter
		if ($frontmatter_yaml === '') {
			return new ParsedMarkdown([], $body);
		}

		// Parse the YAML frontmatter
		try {
			$parsed_yaml = Yaml::parse($frontmatter_yaml);

			// YAML might parse to null for empty content or scalar values
			if (!is_array($parsed_yaml)) {
				return new ParsedMarkdown([], $body);
			}

			return new ParsedMarkdown($parsed_yaml, $body);
		} catch (YamlParseException $exception) {
			throw ParseException::invalidFrontmatter($exception->getMessage(), $exception);
		}
	}

	/**
	 * Parses a markdown file with optional YAML frontmatter.
	 *
	 * @param string $path The path to the markdown file.
	 *
	 * @return ParsedMarkdown The parsed result containing frontmatter and body.
	 *
	 * @throws ParseException If the file cannot be read or contains invalid YAML.
	 */
	public function parseFile(string $path): ParsedMarkdown
	{
		if (!file_exists($path)) {
			throw ParseException::fileNotFound($path);
		}

		if (!is_readable($path)) {
			throw ParseException::fileNotReadable($path);
		}

		$content = @file_get_contents($path);

		if ($content === false) {
			throw ParseException::fileNotReadable($path);
		}

		return $this->parse($content);
	}
}
