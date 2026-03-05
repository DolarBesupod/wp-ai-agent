<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\Ability;

use WP_Ability;
use WP_Error;
use Automattic\WpAiAgent\Core\Tool\AbstractTool;
use Automattic\WpAiAgent\Core\ValueObjects\ToolResult;
use Throwable;

/**
 * Adapts a WordPress WP_Ability as a ToolInterface for use by the agent.
 *
 * Wraps a WP_Ability instance and exposes it through the agent's tool system.
 * The ability name is normalized with an `ability_` prefix, slashes and hyphens
 * are converted to underscores, and execution delegates to the underlying
 * WP_Ability after checking permissions.
 *
 * Follows the same adapter pattern as McpToolAdapter — constructor stores the
 * wrapped object, getName() normalizes the name, execute() wraps the external
 * call and converts errors to ToolResult.
 *
 * @since 0.1.0
 */
class AbilityToolAdapter extends AbstractTool
{
	/**
	 * Prefix applied to all ability-derived tool names.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	public const TOOL_PREFIX = 'ability_';

	/**
	 * The wrapped WordPress ability.
	 *
	 * @var WP_Ability
	 */
	private WP_Ability $ability;

	/**
	 * The pre-computed tool name with prefix and normalized separators.
	 *
	 * @var string
	 */
	private string $tool_name;

	/**
	 * The pre-computed description combining label and description.
	 *
	 * @var string
	 */
	private string $description;

	/**
	 * Creates a new AbilityToolAdapter.
	 *
	 * Pre-computes the normalized tool name and formatted description from
	 * the underlying WP_Ability so that repeated calls to getName() and
	 * getDescription() are cheap.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Ability $ability The WordPress ability to wrap.
	 */
	public function __construct(WP_Ability $ability)
	{
		$this->ability = $ability;
		$this->tool_name = self::TOOL_PREFIX . str_replace(['/', '-'], '_', $ability->get_name());

		$label = $ability->get_label();
		$desc = $ability->get_description();

		$this->description = ($label !== '')
			? sprintf('"%s" — %s', $label, $desc)
			: $desc;
	}

	/**
	 * Returns the unique tool name with the ability_ prefix.
	 *
	 * Slashes and hyphens in the original ability name are normalized to
	 * underscores. For example, `core/get-site-info` becomes
	 * `ability_core_get_site_info`.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->tool_name;
	}

	/**
	 * Returns the original WP_Ability name for debugging purposes.
	 *
	 * @since 0.1.0
	 *
	 * @return string The original ability name (e.g. 'core/get-site-info').
	 */
	public function getOriginalName(): string
	{
		return $this->ability->get_name();
	}

	/**
	 * Returns the tool description.
	 *
	 * When the ability has a non-empty label, the format is
	 * `"{label}" — {description}`. Otherwise, only the description is returned.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function getDescription(): string
	{
		return $this->description;
	}

	/**
	 * Returns the JSON Schema for the tool's parameters.
	 *
	 * Passes through the ability's input schema directly. Returns null when
	 * the schema is empty (ability accepts no parameters).
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>|null The JSON Schema or null for no parameters.
	 */
	public function getParametersSchema(): ?array
	{
		$schema = $this->ability->get_input_schema();

		return !empty($schema) ? $schema : null;
	}

	/**
	 * Returns whether this tool requires user confirmation before execution.
	 *
	 * Only abilities explicitly annotated as readonly skip confirmation.
	 * All other abilities (destructive, unknown, or missing annotations)
	 * require confirmation for safety.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True unless annotations.readonly is explicitly true.
	 */
	public function requiresConfirmation(): bool
	{
		$meta = $this->ability->get_meta();
		$readonly = $meta['annotations']['readonly'] ?? null;

		return $readonly !== true;
	}

	/**
	 * Executes the ability after checking permissions.
	 *
	 * Checks permissions first — returns a failure result if the check
	 * returns false or WP_Error. On success, executes the ability and
	 * converts the result to a ToolResult. Array results are JSON-encoded,
	 * string results are used directly, null becomes 'null', and other
	 * scalar values are cast to string. Any uncaught exception is caught
	 * and returned as a failure.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $arguments The arguments matching the parameters schema.
	 *
	 * @return ToolResult The result of the execution.
	 */
	public function execute(array $arguments): ToolResult
	{
		$input = empty($arguments) ? null : $arguments;

		try {
			$permission = $this->ability->check_permissions($input);

			if ($permission instanceof WP_Error) {
				return $this->failure(
					$permission->get_error_code() . ': ' . $permission->get_error_message()
				);
			}

			if ($permission === false) {
				return $this->failure(
					'Permission denied for ability: ' . $this->ability->get_name()
				);
			}

			$result = $this->ability->execute($input);

			if ($result instanceof WP_Error) {
				return $this->failure(
					$result->get_error_code() . ': ' . $result->get_error_message()
				);
			}

			return $this->success($this->encodeResult($result));
		} catch (Throwable $e) {
			return $this->failure('Ability execution failed: ' . $e->getMessage());
		}
	}

	/**
	 * Encodes the ability execution result as a string.
	 *
	 * Arrays are JSON-encoded, strings are returned as-is, null becomes
	 * the literal string 'null', and other scalar values are cast to string.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $result The raw result from WP_Ability::execute().
	 *
	 * @return string The encoded result.
	 */
	private function encodeResult(mixed $result): string
	{
		if (is_array($result)) {
			$encoded = wp_json_encode($result);

			return is_string($encoded) ? $encoded : '[]';
		}

		if (is_string($result)) {
			return $result;
		}

		if (is_null($result)) {
			return 'null';
		}

		return (string) $result;
	}
}
