<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Integration\WpCli;

use Automattic\Automattic\WpAiAgent\Core\Contracts\SkillRepositoryInterface;
use Automattic\Automattic\WpAiAgent\Core\Exceptions\SkillNotFoundException;
use Automattic\Automattic\WpAiAgent\Core\Skill\Skill;
use Automattic\Automattic\WpAiAgent\Core\Skill\SkillConfig;

/**
 * WordPress options-based skill repository.
 *
 * Persists skills as individual WordPress options with autoload=false.
 * An index option tracks all stored skill names.
 *
 * Option naming:
 * - Index:     wp_ai_agent_skills
 * - Per skill: wp_ai_agent_skill_{name}
 *
 * @since n.e.x.t
 */
final class WpOptionsSkillRepository implements SkillRepositoryInterface
{
	/**
	 * Prefix for per-skill WordPress options.
	 */
	private const OPTION_PREFIX = 'wp_ai_agent_skill_';

	/**
	 * WordPress option name for the skill name index.
	 */
	private const INDEX_OPTION = 'wp_ai_agent_skills';

	/**
	 * Persists a skill to the WordPress options table.
	 *
	 * Serializes the skill to JSON and stores it as a WordPress option with
	 * autoload disabled. Also adds the skill name to the index if not present.
	 * If a skill with the same name already exists it will be overwritten.
	 *
	 * @param Skill $skill The skill to save.
	 *
	 * @return void
	 *
	 * @since n.e.x.t
	 */
	public function save(Skill $skill): void
	{
		$name = $skill->getName();
		$option_key = self::OPTION_PREFIX . $name;

		$data = [
			'name'                 => $name,
			'description'          => $skill->getDescription(),
			'body'                 => $skill->getBody(),
			'parameters'           => $skill->getConfig()->getParameters(),
			'requires_confirmation' => $skill->getConfig()->requiresConfirmation(),
			'filepath'             => $skill->getFilePath(),
		];

		\update_option($option_key, \wp_json_encode($data), false);

		$index = $this->loadIndex();

		if (!in_array($name, $index, true)) {
			$index[] = $name;
			\update_option(self::INDEX_OPTION, \wp_json_encode($index), false);
		}
	}

	/**
	 * Loads a skill by name from the WordPress options table.
	 *
	 * @param string $name The skill name.
	 *
	 * @return Skill The loaded skill.
	 *
	 * @throws SkillNotFoundException If the skill does not exist.
	 *
	 * @since n.e.x.t
	 */
	public function load(string $name): Skill
	{
		$option_key = self::OPTION_PREFIX . $name;
		$value = \get_option($option_key, false);

		if (false === $value) {
			throw SkillNotFoundException::forName($name);
		}

		$data = json_decode(is_string($value) ? $value : '', true);

		if (!is_array($data)) {
			throw SkillNotFoundException::forName($name);
		}

		return $this->hydrateSkill($data);
	}

	/**
	 * Deletes a skill from the WordPress options table and removes it from the index.
	 *
	 * @param string $name The skill name.
	 *
	 * @return bool True if the skill existed and was deleted, false if it did not exist.
	 *
	 * @since n.e.x.t
	 */
	public function delete(string $name): bool
	{
		$option_key = self::OPTION_PREFIX . $name;

		$existed = \get_option($option_key, false) !== false;

		\delete_option($option_key);

		$index = $this->loadIndex();
		$filtered = array_values(array_filter($index, static function (string $entry) use ($name): bool {
			return $entry !== $name;
		}));

		\update_option(self::INDEX_OPTION, \wp_json_encode($filtered), false);

		return $existed;
	}

	/**
	 * Returns the names of all skills stored in the repository.
	 *
	 * Returns an empty array when the index option does not exist.
	 *
	 * @return string[] An array of skill names.
	 *
	 * @since n.e.x.t
	 */
	public function listNames(): array
	{
		$raw = \get_option(self::INDEX_OPTION, null);

		if (null === $raw) {
			return [];
		}

		$index = json_decode(is_string($raw) ? $raw : '[]', true);

		if (!is_array($index)) {
			return [];
		}

		return array_values(array_filter($index, 'is_string'));
	}

	/**
	 * Checks whether a skill with the given name exists in the repository.
	 *
	 * @param string $name The skill name.
	 *
	 * @return bool True if the skill exists.
	 *
	 * @since n.e.x.t
	 */
	public function exists(string $name): bool
	{
		return \get_option(self::OPTION_PREFIX . $name, false) !== false;
	}

	/**
	 * Loads the current skill name index from WordPress options.
	 *
	 * @return string[] The list of stored skill names.
	 */
	private function loadIndex(): array
	{
		$raw = \get_option(self::INDEX_OPTION, null);

		if (null === $raw) {
			return [];
		}

		$index = json_decode(is_string($raw) ? $raw : '[]', true);

		if (!is_array($index)) {
			return [];
		}

		return array_values(array_filter($index, 'is_string'));
	}

	/**
	 * Reconstructs a Skill instance from a decoded data array.
	 *
	 * @param array<string, mixed> $data The decoded skill data.
	 *
	 * @return Skill The reconstructed skill.
	 */
	private function hydrateSkill(array $data): Skill
	{
		$name = is_string($data['name'] ?? null) ? $data['name'] : '';
		$description = is_string($data['description'] ?? null) ? $data['description'] : '';
		$body = is_string($data['body'] ?? null) ? $data['body'] : '';

		$frontmatter = [
			'parameters'           => is_array($data['parameters'] ?? null) ? $data['parameters'] : [],
			'requires_confirmation' => is_bool($data['requires_confirmation'] ?? null)
				? $data['requires_confirmation']
				: true,
		];

		$config = SkillConfig::fromFrontmatter($frontmatter);

		$filepath = is_string($data['filepath'] ?? null) ? $data['filepath'] : null;

		return new Skill($name, $description, $body, $config, $filepath);
	}
}
