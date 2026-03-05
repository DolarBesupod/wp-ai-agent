<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Integration\Skill;

use RuntimeException;
use Automattic\Automattic\WpAiAgent\Core\Contracts\BashCommandExpanderInterface;
use Automattic\Automattic\WpAiAgent\Core\Contracts\FileReferenceExpanderInterface;
use Automattic\Automattic\WpAiAgent\Core\Skill\Skill;
use Automattic\Automattic\WpAiAgent\Core\Tool\AbstractTool;
use Automattic\Automattic\WpAiAgent\Core\ValueObjects\ToolResult;

/**
 * Tool wrapper for a loaded Skill value object.
 *
 * Exposes a skill as a ToolInterface so the agent can invoke it via the
 * normal tool-call mechanism. The skill body is treated as a template:
 *
 * 1. Named parameter substitution — $param_name placeholders are replaced
 *    with the values provided by the agent as tool arguments.
 * 2. File reference expansion — @path references are replaced with file
 *    contents (via FileReferenceExpanderInterface).
 * 3. Bash command expansion — !`cmd` references are replaced with command
 *    output (via BashCommandExpanderInterface).
 *
 * Expansion errors are captured inline as [Error: …] markers so that a
 * single bad reference never prevents the rest of the template from
 * rendering. No WordPress functions are called anywhere in this class.
 *
 * @since n.e.x.t
 */
final class SkillTool extends AbstractTool
{
	/**
	 * The skill value object being wrapped.
	 *
	 * @var Skill
	 */
	private Skill $skill;

	/**
	 * The file reference expander for @path expansion.
	 *
	 * @var FileReferenceExpanderInterface
	 */
	private FileReferenceExpanderInterface $file_reference_expander;

	/**
	 * The bash command expander for !`cmd` expansion.
	 *
	 * @var BashCommandExpanderInterface
	 */
	private BashCommandExpanderInterface $bash_command_expander;

	/**
	 * Creates a new SkillTool instance.
	 *
	 * @param Skill                          $skill                   The skill to wrap.
	 * @param FileReferenceExpanderInterface $file_reference_expander The file reference expander.
	 * @param BashCommandExpanderInterface   $bash_command_expander   The bash command expander.
	 *
	 * @since n.e.x.t
	 */
	public function __construct(
		Skill $skill,
		FileReferenceExpanderInterface $file_reference_expander,
		BashCommandExpanderInterface $bash_command_expander
	) {
		$this->skill = $skill;
		$this->file_reference_expander = $file_reference_expander;
		$this->bash_command_expander = $bash_command_expander;
	}

	/**
	 * Returns the unique name of this tool, derived from the skill name.
	 *
	 * @return string
	 *
	 * @since n.e.x.t
	 */
	public function getName(): string
	{
		return $this->skill->getName();
	}

	/**
	 * Returns the human-readable description of what this skill does.
	 *
	 * @return string
	 *
	 * @since n.e.x.t
	 */
	public function getDescription(): string
	{
		return $this->skill->getDescription();
	}

	/**
	 * Returns whether this tool requires user confirmation before execution.
	 *
	 * Delegates to the skill's configuration value.
	 *
	 * @return bool
	 *
	 * @since n.e.x.t
	 */
	public function requiresConfirmation(): bool
	{
		return $this->skill->getConfig()->requiresConfirmation();
	}

	/**
	 * Returns the JSON Schema for this tool's parameters.
	 *
	 * The schema is built dynamically from the skill's parameter definitions.
	 * Each parameter with required === true is added to the "required" array.
	 * Parameters that declare an "enum" list have that constraint included.
	 *
	 * @return array<string, mixed>
	 *
	 * @since n.e.x.t
	 */
	public function getParametersSchema(): array
	{
		$parameters = $this->skill->getConfig()->getParameters();
		$properties = [];
		$required = [];

		foreach ($parameters as $name => $schema) {
			$property = [];

			if (isset($schema['type'])) {
				$property['type'] = $schema['type'];
			}

			if (isset($schema['description'])) {
				$property['description'] = $schema['description'];
			}

			if (isset($schema['enum'])) {
				$property['enum'] = $schema['enum'];
			}

			$properties[$name] = $property;

			if (isset($schema['required']) && $schema['required'] === true) {
				$required[] = $name;
			}
		}

		$json_schema = [
			'type' => 'object',
			'properties' => empty($properties) ? new \stdClass() : $properties,
		];

		if (count($required) > 0) {
			$json_schema['required'] = $required;
		}

		return $json_schema;
	}

	/**
	 * Executes the skill by expanding its body template with the given arguments.
	 *
	 * Processing order:
	 * 1. Validate required parameters are present.
	 * 2. Apply defaults for optional parameters that were not provided.
	 * 3. Substitute named $param_name placeholders with argument values.
	 * 4. Expand @path file references (errors captured inline).
	 * 5. Expand !`cmd` bash references (errors captured inline).
	 *
	 * @param array<string, mixed> $arguments The tool arguments provided by the agent.
	 *
	 * @return ToolResult
	 *
	 * @since n.e.x.t
	 */
	public function execute(array $arguments): ToolResult
	{
		$parameters = $this->skill->getConfig()->getParameters();

		// Step 1: Validate required parameters.
		foreach ($parameters as $name => $schema) {
			if (isset($schema['required']) && $schema['required'] === true) {
				if (!array_key_exists($name, $arguments)) {
					return ToolResult::failure(sprintf('Missing required parameter: %s', $name));
				}
			}
		}

		// Step 2: Apply defaults for missing optional parameters.
		foreach ($parameters as $name => $schema) {
			if (!array_key_exists($name, $arguments) && array_key_exists('default', $schema)) {
				$arguments[$name] = $schema['default'];
			}
		}

		$content = $this->skill->getBody();
		$file_base_path = $this->resolveBasePath();
		$bash_working_dir = (string) getcwd();

		// Step 3: Named parameter substitution ($param_name → value).
		$content = $this->substituteParameters($content, $arguments);

		// Step 4: File reference expansion — relative @paths resolve from the skill file's directory.
		$file_expansion_result = $this->expandFileReferences($content, $file_base_path);
		$content = $file_expansion_result['content'];
		if ($file_expansion_result['error'] !== null) {
			$content = sprintf('[Error: %s] ', $file_expansion_result['error']) . $content;
		}

		// Step 5: Bash command expansion — commands run in the CWD where the agent was invoked.
		$bash_expansion_result = $this->expandBashCommands($content, $bash_working_dir);
		$content = $bash_expansion_result['content'];
		if ($bash_expansion_result['error'] !== null) {
			$content = sprintf('[Error: %s] ', $bash_expansion_result['error']) . $content;
		}

		return ToolResult::success($content);
	}

	/**
	 * Resolves the base path for @file reference expansion.
	 *
	 * When the skill was loaded from a file, relative @path references
	 * resolve from that file's directory. For skills with no file path,
	 * falls back to the current working directory.
	 *
	 * Note: bash command expansion always uses getcwd() so that !`wp ...`
	 * commands run in the directory where the agent was invoked.
	 *
	 * @return string The base path for file reference resolution.
	 *
	 * @since n.e.x.t
	 */
	private function resolveBasePath(): string
	{
		$filepath = $this->skill->getFilePath();

		if ($filepath !== null) {
			return dirname($filepath);
		}

		return (string) getcwd();
	}

	/**
	 * Replaces named $param_name placeholders in the content with argument values.
	 *
	 * Each parameter name is prefixed with $ and replaced by its string
	 * representation. Non-string values are JSON-encoded for readability.
	 * Placeholders are replaced longest-first to avoid partial substitutions
	 * when one parameter name is a prefix of another.
	 *
	 * @param string               $content   The template content.
	 * @param array<string, mixed> $arguments The argument values keyed by parameter name.
	 *
	 * @return string The content with named placeholders replaced.
	 *
	 * @since n.e.x.t
	 */
	private function substituteParameters(string $content, array $arguments): string
	{
		// Sort by placeholder length descending to prevent partial substitutions.
		$names = array_keys($arguments);
		usort($names, static fn(string $a, string $b): int => strlen($b) - strlen($a));

		foreach ($names as $name) {
			$value = $arguments[$name];
			$string_value = is_string($value) ? $value : (string) json_encode($value);
			$content = str_replace('$' . $name, $string_value, $content);
		}

		return $content;
	}

	/**
	 * Expands file references in content, capturing any errors.
	 *
	 * On success returns the expanded content with a null error.
	 * On failure returns the original content with the error message.
	 *
	 * @param string $content   The content containing @path references.
	 * @param string $base_path The base path for resolving relative references.
	 *
	 * @return array{content: string, error: string|null} The expansion result.
	 *
	 * @since n.e.x.t
	 */
	private function expandFileReferences(string $content, string $base_path): array
	{
		try {
			return [
				'content' => $this->file_reference_expander->expand($content, $base_path),
				'error' => null,
			];
		} catch (RuntimeException $exception) {
			return [
				'content' => $content,
				'error' => $exception->getMessage(),
			];
		}
	}

	/**
	 * Expands bash commands in content, capturing any errors.
	 *
	 * On success returns the expanded content with a null error.
	 * On failure returns the original content with the error message.
	 *
	 * @param string $content     The content containing !`cmd` references.
	 * @param string $working_dir The working directory for command execution.
	 *
	 * @return array{content: string, error: string|null} The expansion result.
	 *
	 * @since n.e.x.t
	 */
	private function expandBashCommands(string $content, string $working_dir): array
	{
		try {
			return [
				'content' => $this->bash_command_expander->expand($content, $working_dir),
				'error' => null,
			];
		} catch (RuntimeException $exception) {
			return [
				'content' => $content,
				'error' => $exception->getMessage(),
			];
		}
	}
}
