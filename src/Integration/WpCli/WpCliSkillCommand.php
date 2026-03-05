<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\WpCli;

use Automattic\WpAiAgent\Core\Skill\Skill;
use Automattic\WpAiAgent\Core\Skill\SkillConfig;
use Automattic\WpAiAgent\Integration\Configuration\MarkdownParser;
use Automattic\WpAiAgent\Integration\Skill\SkillLoader;

/**
 * WP-CLI command handler for the `wp agent skills` subcommand group.
 *
 * Exposes four subcommands:
 * - `wp agent skills list`              — display all skills in a table.
 * - `wp agent skills add <name>`        — add or overwrite a skill.
 * - `wp agent skills remove <name>`     — delete a skill.
 * - `wp agent skills show <name>`       — print a skill's full definition.
 *
 * @since 0.1.0
 */
final class WpCliSkillCommand
{
	/**
	 * Regex for valid skill names.
	 */
	private const NAME_PATTERN = '/^[a-z0-9_]+$/';

	/**
	 * The WordPress options skill repository.
	 *
	 * @var WpOptionsSkillRepository
	 */
	private WpOptionsSkillRepository $repository;

	/**
	 * The skill loader for parsing markdown files.
	 *
	 * @var SkillLoader
	 */
	private SkillLoader $loader;

	/**
	 * Absolute path to the plugin's bundled skills/ directory.
	 *
	 * @var string
	 */
	private string $bundled_skills_dir;

	/**
	 * Creates a new WpCliSkillCommand instance.
	 *
	 * All parameters are optional so WP-CLI can instantiate this class without
	 * arguments when registering it via WP_CLI::add_command(). When omitted,
	 * concrete implementations are created automatically.
	 *
	 * @param WpOptionsSkillRepository|null $repository         The WordPress options skill repository.
	 * @param SkillLoader|null $loader The skill loader for markdown parsing.
	 * @param string|null                   $bundled_skills_dir Absolute path to the bundled skills/ directory.
	 *
	 * @since 0.1.0
	 */
	public function __construct(
		?WpOptionsSkillRepository $repository = null,
		?SkillLoader $loader = null,
		?string $bundled_skills_dir = null
	) {
		$this->repository = $repository ?? new WpOptionsSkillRepository();
		$this->loader = $loader ?? new SkillLoader(new MarkdownParser());
		$this->bundled_skills_dir = $bundled_skills_dir ?? dirname(__DIR__, 3) . '/skills';
	}

	/**
	 * Lists all registered skills in a table.
	 *
	 * Displays each skill's name, description, parameter count, whether it
	 * requires confirmation, and its source (user or bundled).
	 *
	 * ## EXAMPLES
	 *
	 *     wp agent skills list
	 *
	 * @subcommand list
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, string>         $args       Positional arguments (unused).
	 * @param array<string, string|bool> $assoc_args Named arguments (unused).
	 *
	 * @return void
	 */
	public function list(array $args, array $assoc_args): void
	{
		$this->seedBundledSkillsIfNeeded();

		$names = $this->repository->listNames();
		$rows = [];

		foreach ($names as $name) {
			try {
				$skill = $this->repository->load($name);
				$param_count = count($skill->getConfig()->getParameters());
				$confirmation = $skill->getConfig()->requiresConfirmation() ? 'yes' : 'no';
				$source = null !== $skill->getFilePath() ? 'bundled' : 'user';

				$rows[] = [
					'name'         => $skill->getName(),
					'description'  => $skill->getDescription(),
					'params'       => (string) $param_count,
					'confirmation' => $confirmation,
					'source'       => $source,
				];
			} catch (\Exception $e) {
				\WP_CLI::warning(sprintf('Could not load skill "%s": %s', $name, $e->getMessage()));
			}
		}

		if (empty($rows)) {
			\WP_CLI::log('No skills registered.');
			return;
		}

		\WP_CLI\Utils\format_items('table', $rows, ['name', 'description', 'params', 'confirmation', 'source']);
	}

	/**
	 * Adds a skill from a file or inline definition.
	 *
	 * Accepts a markdown file via --file= or an inline skill definition via
	 * --description= and --body=. The --force flag allows overwriting an
	 * existing skill with the same name.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The skill name. Must match /^[a-z0-9_]+$/.
	 *
	 * [--file=<path>]
	 * : Path to a markdown file containing the skill definition with optional YAML frontmatter.
	 *
	 * [--description=<text>]
	 * : A short description of the skill (used when --body is provided).
	 *
	 * [--body=<text>]
	 * : The skill prompt body (used when --file is not provided).
	 *
	 * [--force]
	 * : Overwrite the skill if it already exists.
	 *
	 * ## EXAMPLES
	 *
	 *     wp agent skills add summarize --file=/path/to/summarize.md
	 *     wp agent skills add greet --description="Greet the user" --body="Hello, \$name!"
	 *     wp agent skills add summarize --file=/path/to/summarize.md --force
	 *
	 * @subcommand add
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, string>         $args       Positional arguments; $args[0] is the skill name.
	 * @param array<string, string|bool> $assoc_args Named arguments (--file, --description, --body, --force).
	 *
	 * @return void
	 */
	public function add(array $args, array $assoc_args): void
	{
		$name = $args[0] ?? '';

		if ('' === $name) {
			\WP_CLI::error('Please provide a skill name.');
			return;
		}

		if (!preg_match(self::NAME_PATTERN, $name)) {
			\WP_CLI::error(
				sprintf('Invalid skill name "%s". Names must match /^[a-z0-9_]+$/.', $name)
			);
			return;
		}

		$force = isset($assoc_args['force']) && $assoc_args['force'] !== false;

		if ($this->repository->exists($name) && !$force) {
			\WP_CLI::error(
				sprintf('Skill "%s" already exists. Use --force to overwrite.', $name)
			);
			return;
		}

		$skill = $this->resolveSkillFromArgs($name, $assoc_args);

		if (null === $skill) {
			return;
		}

		try {
			$this->repository->save($skill);
			\WP_CLI::success(sprintf('Skill "%s" saved successfully.', $name));
		} catch (\Exception $e) {
			\WP_CLI::error(sprintf('Failed to save skill "%s": %s', $name, $e->getMessage()));
		}
	}

	/**
	 * Removes a skill by name.
	 *
	 * Bundled skills (those loaded from files rather than user options) cannot
	 * be removed via this command.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The skill name to remove.
	 *
	 * ## EXAMPLES
	 *
	 *     wp agent skills remove summarize
	 *
	 * @subcommand remove
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, string>         $args       Positional arguments; $args[0] is the skill name.
	 * @param array<string, string|bool> $assoc_args Named arguments (unused).
	 *
	 * @return void
	 */
	public function remove(array $args, array $assoc_args): void
	{
		$name = $args[0] ?? '';

		if ('' === $name) {
			\WP_CLI::error('Please provide a skill name.');
			return;
		}

		if (!$this->repository->exists($name)) {
			\WP_CLI::error(sprintf('Skill "%s" does not exist.', $name));
			return;
		}

		try {
			$skill = $this->repository->load($name);

			if (null !== $skill->getFilePath()) {
				\WP_CLI::error('Cannot remove bundled skill.');
				return;
			}
		} catch (\Exception $e) {
			\WP_CLI::warning(sprintf('Could not verify skill source for "%s": %s', $name, $e->getMessage()));
		}

		$this->repository->delete($name);
		\WP_CLI::success(sprintf('Skill "%s" removed successfully.', $name));
	}

	/**
	 * Shows the full definition of a skill.
	 *
	 * Prints the skill's description, parameters, body content, and source.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The skill name to display.
	 *
	 * ## EXAMPLES
	 *
	 *     wp agent skills show summarize
	 *
	 * @subcommand show
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, string>         $args       Positional arguments; $args[0] is the skill name.
	 * @param array<string, string|bool> $assoc_args Named arguments (unused).
	 *
	 * @return void
	 */
	public function show(array $args, array $assoc_args): void
	{
		$name = $args[0] ?? '';

		if ('' === $name) {
			\WP_CLI::error('Please provide a skill name.');
			return;
		}

		if (!$this->repository->exists($name)) {
			\WP_CLI::error(sprintf('Skill "%s" does not exist.', $name));
			return;
		}

		try {
			$skill = $this->repository->load($name);
		} catch (\Exception $e) {
			\WP_CLI::error(sprintf('Failed to load skill "%s": %s', $name, $e->getMessage()));
			return;
		}

		$source = null !== $skill->getFilePath()
			? sprintf('bundled (%s)', $skill->getFilePath())
			: 'user';

		\WP_CLI::log(sprintf('Name:         %s', $skill->getName()));
		\WP_CLI::log(sprintf('Description:  %s', $skill->getDescription()));
		\WP_CLI::log(sprintf('Confirmation: %s', $skill->getConfig()->requiresConfirmation() ? 'yes' : 'no'));
		\WP_CLI::log(sprintf('Source:       %s', $source));

		$parameters = $skill->getConfig()->getParameters();

		if (!empty($parameters)) {
			\WP_CLI::log('');
			\WP_CLI::log('Parameters:');

			foreach ($parameters as $param_name => $schema) {
				$type = $schema['type'] ?? 'string';
				$description = $schema['description'] ?? '';
				$required = (isset($schema['required']) && $schema['required']) ? 'required' : 'optional';
				\WP_CLI::log(sprintf('  %s (%s, %s): %s', $param_name, $type, $required, $description));
			}
		}

		\WP_CLI::log('');
		\WP_CLI::log('Body:');
		\WP_CLI::log($skill->getBody());
	}

	/**
	 * Resolves a Skill instance from the provided command arguments.
	 *
	 * Supports two modes:
	 * - --file=<path>: reads and parses a markdown file.
	 * - --description= + --body=: creates an inline skill.
	 *
	 * Returns null and emits a WP_CLI::error() when arguments are insufficient.
	 *
	 * @param string $name The validated skill name.
	 * @param array<string, string|bool> $assoc_args The named arguments from WP-CLI.
	 *
	 * @return Skill|null The resolved skill, or null on failure.
	 *
	 * @since 0.1.0
	 */
	private function resolveSkillFromArgs(string $name, array $assoc_args): ?Skill
	{
		$file = isset($assoc_args['file']) && is_string($assoc_args['file'])
			? $assoc_args['file']
			: null;

		if (null !== $file) {
			return $this->resolveSkillFromFile($name, $file);
		}

		$description = isset($assoc_args['description']) && is_string($assoc_args['description'])
			? $assoc_args['description']
			: null;

		$body = isset($assoc_args['body']) && is_string($assoc_args['body'])
			? $assoc_args['body']
			: null;

		if (null === $body) {
			\WP_CLI::error('Provide either --file=<path> or both --description=<text> and --body=<text>.');
			return null;
		}

		$config = SkillConfig::fromFrontmatter([]);

		return new Skill(
			$name,
			$description ?? "Skill: {$name}",
			$body,
			$config,
			null
		);
	}

	/**
	 * Loads a skill from a markdown file path.
	 *
	 * Reads the file contents and delegates to SkillLoader::loadFromMarkdown().
	 * Emits a WP_CLI::error() when the file cannot be read or parsed.
	 *
	 * @param string $name     The skill name to assign.
	 * @param string $filepath The path to the markdown file.
	 *
	 * @return Skill|null The loaded skill, or null on failure.
	 *
	 * @since 0.1.0
	 */
	private function resolveSkillFromFile(string $name, string $filepath): ?Skill
	{
		if (!file_exists($filepath)) {
			\WP_CLI::error(sprintf('File not found: %s', $filepath));
			return null;
		}

		if (!is_readable($filepath)) {
			\WP_CLI::error(sprintf('File is not readable: %s', $filepath));
			return null;
		}

		$content = file_get_contents($filepath);

		if (false === $content) {
			\WP_CLI::error(sprintf('Could not read file: %s', $filepath));
			return null;
		}

		try {
			return $this->loader->loadFromMarkdown($name, $content);
		} catch (\Exception $e) {
			\WP_CLI::error(sprintf('Failed to parse skill file "%s": %s', $filepath, $e->getMessage()));
			return null;
		}
	}

	/**
	 * Seeds bundled skills from the plugin's skills/ directory if the index has never been set.
	 *
	 * Only runs when the wp_ai_agent_skills option is null (not yet initialised).
	 * Saves each bundled skill to the repository so subsequent calls to listNames()
	 * return them without requiring the full agent bootstrap.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	private function seedBundledSkillsIfNeeded(): void
	{
		if (null !== \get_option('wp_ai_agent_skills', null)) {
			return;
		}

		if (!is_dir($this->bundled_skills_dir)) {
			return;
		}

		$files = glob($this->bundled_skills_dir . '/*.md');

		if (false === $files) {
			return;
		}

		foreach ($files as $filepath) {
			try {
				$skill = $this->loader->load($filepath);
				$this->repository->save($skill);
			} catch (\Exception $e) {
				\WP_CLI::warning(sprintf(
					'Could not seed bundled skill from "%s": %s',
					$filepath,
					$e->getMessage()
				));
			}
		}
	}
}
