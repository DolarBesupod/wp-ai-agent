<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Core\Skill;

/**
 * Immutable value object providing typed access to skill frontmatter configuration.
 *
 * This class wraps the raw frontmatter array and provides type-safe accessors
 * for the fields defined in skill definitions, including parameter schemas and
 * confirmation requirements.
 *
 * @phpstan-type ParameterSchema array{
 *     type?: string,
 *     description?: string,
 *     required?: bool,
 *     enum?: array<string>,
 *     default?: mixed
 * }
 *
 * @since n.e.x.t
 */
final class SkillConfig
{
	/**
	 * The parsed parameter definitions.
	 *
	 * @var array<string, ParameterSchema>
	 */
	private array $parameters;

	/**
	 * Whether this skill requires user confirmation before execution.
	 *
	 * @var bool
	 */
	private bool $requires_confirmation;

	/**
	 * Creates a new SkillConfig instance.
	 *
	 * @param array<string, ParameterSchema> $parameters           The parameter definitions.
	 * @param bool                           $requires_confirmation Whether the skill requires confirmation.
	 */
	private function __construct(array $parameters, bool $requires_confirmation)
	{
		$this->parameters = $parameters;
		$this->requires_confirmation = $requires_confirmation;
	}

	/**
	 * Creates a SkillConfig from a frontmatter array.
	 *
	 * Missing or malformed keys are handled gracefully: parameters default to
	 * an empty array and requires_confirmation defaults to true.
	 *
	 * @param array<string, mixed> $frontmatter The frontmatter data array.
	 *
	 * @return self
	 *
	 * @since n.e.x.t
	 */
	public static function fromFrontmatter(array $frontmatter): self
	{
		$requires_confirmation = true;

		if (isset($frontmatter['requires_confirmation']) && is_bool($frontmatter['requires_confirmation'])) {
			$requires_confirmation = $frontmatter['requires_confirmation'];
		}

		$parameters = [];
		$raw_params = $frontmatter['parameters'] ?? [];

		if (is_array($raw_params)) {
			foreach ($raw_params as $param_name => $param_def) {
				if (!is_string($param_name) || !is_array($param_def)) {
					continue;
				}

				$entry = [];

				if (isset($param_def['type']) && is_string($param_def['type'])) {
					$entry['type'] = $param_def['type'];
				}

				if (isset($param_def['description']) && is_string($param_def['description'])) {
					$entry['description'] = $param_def['description'];
				}

				if (isset($param_def['required']) && is_bool($param_def['required'])) {
					$entry['required'] = $param_def['required'];
				}

				if (isset($param_def['enum']) && is_array($param_def['enum'])) {
					$enum_values = array_values(array_filter($param_def['enum'], 'is_string'));
					$entry['enum'] = $enum_values;
				}

				if (array_key_exists('default', $param_def)) {
					$entry['default'] = $param_def['default'];
				}

				$parameters[$param_name] = $entry;
			}
		}

		return new self($parameters, $requires_confirmation);
	}

	/**
	 * Returns the parameter definitions for this skill.
	 *
	 * Each entry maps a parameter name to its schema definition, which may
	 * include type, description, required flag, enum values, and a default.
	 *
	 * @return array<string, ParameterSchema>
	 *
	 * @since n.e.x.t
	 */
	public function getParameters(): array
	{
		return $this->parameters;
	}

	/**
	 * Returns whether this skill requires user confirmation before execution.
	 *
	 * @return bool True if the skill requires confirmation.
	 *
	 * @since n.e.x.t
	 */
	public function requiresConfirmation(): bool
	{
		return $this->requires_confirmation;
	}

	/**
	 * Checks if the configuration is empty.
	 *
	 * A configuration is considered empty when no parameters are defined
	 * and the requires_confirmation flag is at its default value (true).
	 *
	 * @return bool True if no non-default configuration data is present.
	 *
	 * @since n.e.x.t
	 */
	public function isEmpty(): bool
	{
		return count($this->parameters) === 0 && $this->requires_confirmation === true;
	}
}
