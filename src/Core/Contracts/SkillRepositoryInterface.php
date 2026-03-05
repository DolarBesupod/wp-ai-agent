<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Contracts;

use Automattic\WpAiAgent\Core\Skill\Skill;

/**
 * Interface for persisting and retrieving skills.
 *
 * The skill repository abstracts the storage mechanism for skills,
 * allowing implementations to use file-based, database, or in-memory
 * storage without affecting core skill logic.
 *
 * @since 0.1.0
 */
interface SkillRepositoryInterface
{
	/**
	 * Persists a skill to the repository.
	 *
	 * If a skill with the same name already exists it will be overwritten.
	 *
	 * @param Skill $skill The skill to save.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function save(Skill $skill): void;

	/**
	 * Loads a skill by name from the repository.
	 *
	 * @param string $name The skill name.
	 *
	 * @return Skill The loaded skill.
	 *
	 * @throws \RuntimeException If the skill cannot be found or loaded.
	 *
	 * @since 0.1.0
	 */
	public function load(string $name): Skill;

	/**
	 * Deletes a skill from the repository.
	 *
	 * @param string $name The skill name.
	 *
	 * @return bool True if the skill was deleted, false if it did not exist.
	 *
	 * @since 0.1.0
	 */
	public function delete(string $name): bool;

	/**
	 * Returns the names of all skills stored in the repository.
	 *
	 * @return string[] An array of skill names.
	 *
	 * @since 0.1.0
	 */
	public function listNames(): array;

	/**
	 * Checks whether a skill with the given name exists in the repository.
	 *
	 * @param string $name The skill name.
	 *
	 * @return bool True if the skill exists.
	 *
	 * @since 0.1.0
	 */
	public function exists(string $name): bool;
}
