<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\Skill;

use Exception;
use Automattic\WpAiAgent\Core\Contracts\BashCommandExpanderInterface;
use Automattic\WpAiAgent\Core\Contracts\FileReferenceExpanderInterface;
use Automattic\WpAiAgent\Core\Contracts\ToolRegistryInterface;
use Automattic\WpAiAgent\Integration\WpCli\WpOptionsSkillRepository;

/**
 * Discovers and registers skill tools from WordPress options and bundled files.
 *
 * Skills are loaded from the WordPress options table (user-defined skills) and
 * registered as SkillTool instances in the tool registry. If the skill index
 * option has never been set (truly null), bundled skills from the plugin's
 * skills/ directory are loaded instead as a one-time fallback.
 *
 * The fallback fires only when the index option is null — not when it is an
 * empty array, which means the user intentionally cleared all skills.
 *
 * @since n.e.x.t
 */
final class SkillRegistry
{
	/**
	 * WordPress option name for the skill name index.
	 */
	private const INDEX_OPTION = 'wp_ai_agent_skills';

	/**
	 * The skill repository for WordPress options persistence.
	 *
	 * @var WpOptionsSkillRepository
	 */
	private WpOptionsSkillRepository $repository;

	/**
	 * The skill loader for reading markdown files.
	 *
	 * @var SkillLoader
	 */
	private SkillLoader $loader;

	/**
	 * The file reference expander for SkillTool construction.
	 *
	 * @var FileReferenceExpanderInterface
	 */
	private FileReferenceExpanderInterface $file_expander;

	/**
	 * The bash command expander for SkillTool construction.
	 *
	 * @var BashCommandExpanderInterface
	 */
	private BashCommandExpanderInterface $bash_expander;

	/**
	 * Absolute path to the plugin's bundled skills/ directory.
	 *
	 * @var string
	 */
	private string $bundled_skills_dir;

	/**
	 * Creates a new SkillRegistry instance.
	 *
	 * @param WpOptionsSkillRepository       $repository         The WordPress options skill repository.
	 * @param SkillLoader                    $loader             The skill loader for markdown files.
	 * @param FileReferenceExpanderInterface $file_expander      The file reference expander.
	 * @param BashCommandExpanderInterface   $bash_expander      The bash command expander.
	 * @param string                         $bundled_skills_dir Absolute path to the plugin's skills/ directory.
	 *
	 * @since n.e.x.t
	 */
	public function __construct(
		WpOptionsSkillRepository $repository,
		SkillLoader $loader,
		FileReferenceExpanderInterface $file_expander,
		BashCommandExpanderInterface $bash_expander,
		string $bundled_skills_dir
	) {
		$this->repository = $repository;
		$this->loader = $loader;
		$this->file_expander = $file_expander;
		$this->bash_expander = $bash_expander;
		$this->bundled_skills_dir = $bundled_skills_dir;
	}

	/**
	 * Discovers skills and registers them as SkillTool instances in the tool registry.
	 *
	 * When the wp_ai_agent_skills index option has never been set (returns null),
	 * falls back to loading bundled skills from the plugin's skills/ directory.
	 * An empty array means the user intentionally has no skills — no fallback fires.
	 *
	 * Collisions with existing tools are skipped with a warning. Individual load
	 * errors are logged as warnings and never propagated.
	 *
	 * @param ToolRegistryInterface $tool_registry The tool registry to register skills into.
	 *
	 * @return int The number of skills successfully registered.
	 *
	 * @since n.e.x.t
	 */
	public function discoverAndRegister(ToolRegistryInterface $tool_registry): int
	{
		$index_raw = \get_option(self::INDEX_OPTION, null);
		$count = 0;

		if (null !== $index_raw) {
			// Option exists (even if empty array): load user skills from the repository.
			$names = $this->repository->listNames();
			$count = $this->registerFromRepository($names, $tool_registry);
		} else {
			// Option has never been set: fall back to bundled skills.
			$count = $this->registerFromBundledDir($tool_registry);
		}

		\WP_CLI::log(sprintf('[Skills] Discovered %d skill(s)', $count));

		return $count;
	}

	/**
	 * Registers skills from the WordPress options repository by name list.
	 *
	 * @param string[]              $names         The skill names to load and register.
	 * @param ToolRegistryInterface $tool_registry The tool registry.
	 *
	 * @return int The number of skills successfully registered.
	 *
	 * @since n.e.x.t
	 */
	private function registerFromRepository(array $names, ToolRegistryInterface $tool_registry): int
	{
		$count = 0;

		foreach ($names as $name) {
			try {
				$skill = $this->repository->load($name);

				if ($tool_registry->has($name)) {
					\WP_CLI::warning(
						sprintf('[Skills] Skipping skill "%s": a tool with that name is already registered.', $name)
					);
					continue;
				}

				$tool_registry->register(new SkillTool($skill, $this->file_expander, $this->bash_expander));
				$count++;
			} catch (Exception $e) {
				\WP_CLI::warning(
					sprintf('[Skills] Failed to load skill "%s": %s', $name, $e->getMessage())
				);
				continue;
			}
		}

		return $count;
	}

	/**
	 * Registers skills from the plugin's bundled skills/ directory.
	 *
	 * Loads all .md files found directly in the bundled skills directory.
	 * This is only called when the skill index option has never been set.
	 *
	 * @param ToolRegistryInterface $tool_registry The tool registry.
	 *
	 * @return int The number of skills successfully registered.
	 *
	 * @since n.e.x.t
	 */
	private function registerFromBundledDir(ToolRegistryInterface $tool_registry): int
	{
		$count = 0;

		if (!is_dir($this->bundled_skills_dir)) {
			return $count;
		}

		$files = glob($this->bundled_skills_dir . '/*.md');

		if (false === $files) {
			return $count;
		}

		foreach ($files as $filepath) {
			try {
				$skill = $this->loader->load($filepath);
				$name = $skill->getName();

				if ($tool_registry->has($name)) {
					\WP_CLI::warning(sprintf(
						'[Skills] Skipping bundled skill "%s": a tool with that name is already registered.',
						$name
					));
					continue;
				}

				$tool_registry->register(new SkillTool($skill, $this->file_expander, $this->bash_expander));
				$this->repository->save($skill);
				$count++;
			} catch (Exception $e) {
				\WP_CLI::warning(
					sprintf('[Skills] Failed to load bundled skill from "%s": %s', $filepath, $e->getMessage())
				);
				continue;
			}
		}

		return $count;
	}
}
