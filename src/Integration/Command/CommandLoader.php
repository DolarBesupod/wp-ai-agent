<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\Command;

use Automattic\WpAiAgent\Core\Command\Command;
use Automattic\WpAiAgent\Core\Command\CommandConfig;
use Automattic\WpAiAgent\Core\Contracts\CommandLoaderInterface;
use Automattic\WpAiAgent\Core\Contracts\MarkdownParserInterface;
use Automattic\WpAiAgent\Core\Exceptions\ParseException;

/**
 * Loads commands from markdown files with YAML frontmatter.
 *
 * The command loader parses markdown files and creates Command instances
 * from them. It extracts the command name from the filename, the namespace
 * from the directory structure, and configuration from the YAML frontmatter.
 *
 * @since 0.1.0
 */
final class CommandLoader implements CommandLoaderInterface
{
	/**
	 * The markdown parser.
	 *
	 * @var MarkdownParserInterface
	 */
	private MarkdownParserInterface $markdown_parser;

	/**
	 * Creates a new CommandLoader instance.
	 *
	 * @param MarkdownParserInterface $markdown_parser The markdown parser.
	 *
	 * @since 0.1.0
	 */
	public function __construct(MarkdownParserInterface $markdown_parser)
	{
		$this->markdown_parser = $markdown_parser;
	}

	/**
	 * Loads a command from a file path.
	 *
	 * The file should be a markdown file with optional YAML frontmatter.
	 * The command name is derived from the filename (without extension).
	 * The namespace is derived from parent directories under the commands folder.
	 *
	 * @param string $filepath The path to the command file.
	 *
	 * @return Command The loaded command.
	 *
	 * @throws ParseException If the file cannot be parsed.
	 * @throws \RuntimeException If the file cannot be read.
	 *
	 * @since 0.1.0
	 */
	public function load(string $filepath): Command
	{
		$parsed = $this->markdown_parser->parseFile($filepath);

		$name = $this->extractNameFromFilepath($filepath);
		$namespace = $this->extractNamespaceFromFilepath($filepath);
		$frontmatter = $parsed->getFrontmatter();
		$description = $this->extractDescription($frontmatter);
		$body = $parsed->getBody();
		$config = CommandConfig::fromFrontmatter($frontmatter);

		return new Command(
			$name,
			$description,
			$body,
			$config,
			$filepath,
			$namespace
		);
	}

	/**
	 * Loads a command from raw content.
	 *
	 * This is useful for creating commands from strings or for testing.
	 *
	 * @param string $name    The command name.
	 * @param string $content The raw command content (markdown with optional frontmatter).
	 *
	 * @return Command The loaded command.
	 *
	 * @throws ParseException If the content cannot be parsed.
	 *
	 * @since 0.1.0
	 */
	public function loadFromContent(string $name, string $content): Command
	{
		$parsed = $this->markdown_parser->parse($content);

		$frontmatter = $parsed->getFrontmatter();
		$description = $this->extractDescription($frontmatter);
		$body = $parsed->getBody();
		$config = CommandConfig::fromFrontmatter($frontmatter);

		return new Command(
			$name,
			$description,
			$body,
			$config,
			null,
			null
		);
	}

	/**
	 * Extracts the command name from a file path.
	 *
	 * The name is the filename without extension.
	 *
	 * @param string $filepath The file path.
	 *
	 * @return string The command name.
	 */
	private function extractNameFromFilepath(string $filepath): string
	{
		$filename = basename($filepath);
		$last_dot_pos = strrpos($filename, '.');

		if ($last_dot_pos === false || $last_dot_pos === 0) {
			return $filename;
		}

		return substr($filename, 0, $last_dot_pos);
	}

	/**
	 * Extracts the namespace from a file path.
	 *
	 * The namespace is determined by the directory structure between
	 * the 'commands' folder and the file.
	 *
	 * For example:
	 * - /project/.wp-ai-agent/commands/review.md -> null
	 * - /project/.wp-ai-agent/commands/frontend/review.md -> 'frontend'
	 * - /project/.wp-ai-agent/commands/frontend/components/button.md -> 'frontend/components'
	 *
	 * @param string $filepath The file path.
	 *
	 * @return string|null The namespace, or null for root-level commands.
	 */
	private function extractNamespaceFromFilepath(string $filepath): ?string
	{
		$dirname = dirname($filepath);
		$parts = explode('/', $dirname);

		// Find the 'commands' directory in the path
		$commands_index = array_search('commands', $parts, true);

		if ($commands_index === false) {
			return null;
		}

		// Get everything after 'commands'
		$namespace_parts = array_slice($parts, $commands_index + 1);

		if (count($namespace_parts) === 0) {
			return null;
		}

		return implode('/', $namespace_parts);
	}

	/**
	 * Extracts the description from frontmatter.
	 *
	 * @param array<string, mixed> $frontmatter The frontmatter data.
	 *
	 * @return string The description, or empty string if not set.
	 */
	private function extractDescription(array $frontmatter): string
	{
		$description = $frontmatter['description'] ?? null;

		if (!is_string($description)) {
			return '';
		}

		return $description;
	}
}
