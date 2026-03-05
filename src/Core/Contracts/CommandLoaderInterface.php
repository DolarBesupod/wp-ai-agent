<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Contracts;

use Automattic\WpAiAgent\Core\Command\Command;

/**
 * Interface for loading commands from files or content.
 *
 * The command loader is responsible for parsing markdown files with
 * YAML frontmatter and creating Command instances from them.
 *
 * @since 0.1.0
 */
interface CommandLoaderInterface
{
	/**
	 * Loads a command from a file path.
	 *
	 * The file should be a markdown file with optional YAML frontmatter.
	 * The command name is derived from the filename (without extension).
	 *
	 * @param string $filepath The path to the command file.
	 *
	 * @return Command The loaded command.
	 *
	 * @throws \Automattic\WpAiAgent\Core\Exceptions\ParseException If the file cannot be parsed.
	 * @throws \RuntimeException If the file cannot be read.
	 */
	public function load(string $filepath): Command;

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
	 * @throws \Automattic\WpAiAgent\Core\Exceptions\ParseException If the content cannot be parsed.
	 */
	public function loadFromContent(string $name, string $content): Command;
}
