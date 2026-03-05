<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\Ability;

use WP_Ability;
use Automattic\WpAiAgent\Core\Contracts\ConfirmationHandlerInterface;
use Automattic\WpAiAgent\Core\Tool\AbstractTool;
use Automattic\WpAiAgent\Core\ValueObjects\ToolResult;

/**
 * STRAP facade that replaces N individual ability tool registrations with one tool.
 *
 * Exposes three actions — list, describe, execute — so the agent can discover,
 * inspect, and invoke WordPress abilities through a single ToolInterface entry.
 * Abilities are lazily discovered on first use and cached for the rest of the
 * PHP process (WP-CLI request lifecycle).
 *
 * Non-readonly abilities require an explicit `confirmed: true` parameter to
 * execute. This prevents accidental data mutation without user consent.
 *
 * @since 0.1.0
 */
class AbilityStrapTool extends AbstractTool
{
	/**
	 * Valid action values for the tool.
	 *
	 * @since 0.1.0
	 *
	 * @var array<int, string>
	 */
	private const VALID_ACTIONS = ['list', 'describe', 'execute'];

	/**
	 * Callable that returns an array of WP_Ability instances.
	 *
	 * Defaults to the global `wp_get_abilities` function. Injecting a custom
	 * callable allows unit tests to run without defining WordPress globals.
	 *
	 * @var callable
	 */
	private $abilities_provider;

	/**
	 * Callable that checks whether a WordPress user is logged in.
	 *
	 * Defaults to the global `is_user_logged_in` function. Injecting a custom
	 * callable allows unit tests to control the logged-in state.
	 *
	 * @var callable
	 */
	private $is_user_logged_in;

	/**
	 * Confirmation handler for checking auto-confirm (yolo) mode.
	 *
	 * When set, the tool checks isAutoConfirm() before requiring the
	 * `confirmed` parameter on non-readonly abilities. When null, the
	 * original behavior is preserved (always require explicit confirmation).
	 *
	 * @var ConfirmationHandlerInterface|null
	 */
	private ?ConfirmationHandlerInterface $confirmation_handler;

	/**
	 * Lazily-populated catalog of ability adapters indexed by original ability name.
	 *
	 * Null means discovery has not yet occurred. An empty array means discovery
	 * ran but no abilities were found.
	 *
	 * @var array<string, AbilityToolAdapter>|null
	 */
	private ?array $catalog = null;

	/**
	 * Raw WP_Ability instances indexed by original ability name.
	 *
	 * Populated alongside the catalog during ensureCatalog() so that annotations
	 * and meta can be accessed without modifying AbilityToolAdapter.
	 *
	 * @var array<string, WP_Ability>
	 */
	private array $abilities = [];

	/**
	 * Creates a new AbilityStrapTool.
	 *
	 * @since 0.1.0
	 *
	 * @param callable|null $abilities_provider Optional callable that returns WP_Ability[].
	 *                                                             Defaults to 'wp_get_abilities'.
	 * @param callable|null $is_user_logged_in Optional callable that returns bool.
	 *                                                             Defaults to 'is_user_logged_in'.
	 * @param ConfirmationHandlerInterface|null $confirmation_handler Optional handler for auto-confirm checks.
	 */
	public function __construct(
		?callable $abilities_provider = null,
		?callable $is_user_logged_in = null,
		?ConfirmationHandlerInterface $confirmation_handler = null,
	) {
		$this->abilities_provider = $abilities_provider ?? 'wp_get_abilities';
		$this->is_user_logged_in = $is_user_logged_in ?? 'is_user_logged_in';
		$this->confirmation_handler = $confirmation_handler;
	}

	/**
	 * Returns the unique tool name.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return 'wordpress_abilities';
	}

	/**
	 * Returns the tool description including the STRAP usage pattern and safety protocol.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function getDescription(): string
	{
		$description = 'Interact with WordPress abilities. '
			. 'Workflow: 1) "list" to discover available abilities, '
			. '2) ALWAYS "describe" with ability_name to get the full parameter schema before executing, '
			. '3) "execute" with ability_name and params to run. '
			. 'Never skip describe -- you need the exact schema to build valid params.';

		if ($this->isAutoConfirmEnabled()) {
			$description .= ' Auto-confirm is active: all abilities execute without confirmation.';
		} else {
			$description .= ' Safety protocol: non-readonly abilities require confirmed: true in params. '
				. 'Describe what you plan to do, ask the user for confirmation, '
				. 'then re-call with confirmed: true.';
		}

		return $description;
	}

	/**
	 * Returns the JSON Schema for the tool's parameters.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function getParametersSchema(): array
	{
		return [
			'type' => 'object',
			'properties' => [
				'action' => [
					'type' => 'string',
					'enum' => self::VALID_ACTIONS,
					'description' => 'The action to perform: list, describe, or execute.',
				],
				'ability_name' => [
					'type' => 'string',
					'description' => 'The ability name (required for describe and execute).',
				],
				'params' => [
					'type' => 'object',
					'description' => 'Parameters to pass to the ability (optional, for execute).',
				],
			],
			'required' => ['action'],
		];
	}

	/**
	 * Returns whether this tool requires user confirmation before execution.
	 *
	 * The facade itself does not require confirmation. Confirmation is handled
	 * per-ability inside the execute action based on readonly annotations.
	 *
	 * @since 0.1.0
	 *
	 * @return bool Always false.
	 */
	public function requiresConfirmation(): bool
	{
		return false;
	}

	/**
	 * Executes the tool by routing to the appropriate action handler.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $arguments The tool arguments.
	 *
	 * @return ToolResult The result of the action.
	 */
	public function execute(array $arguments): ToolResult
	{
		$action = $this->getStringArgument($arguments, 'action');

		if (!in_array($action, self::VALID_ACTIONS, true)) {
			return $this->failure(
				sprintf(
					"Invalid action '%s'. Must be one of: %s.",
					$action,
					implode(', ', self::VALID_ACTIONS)
				)
			);
		}

		return match ($action) {
			'list' => $this->handleList(),
			'describe' => $this->handleDescribe($arguments),
			'execute' => $this->handleExecute($arguments),
		};
	}

	/**
	 * Handles the list action by returning a summary of all available abilities.
	 *
	 * @since 0.1.0
	 *
	 * @return ToolResult A success result with the abilities summary as JSON.
	 */
	private function handleList(): ToolResult
	{
		$this->ensureCatalog();
		assert($this->catalog !== null);

		$abilities = [];

		foreach ($this->catalog as $name => $adapter) {
			$entry = [
				'name' => $adapter->getOriginalName(),
				'description' => $adapter->getDescription(),
			];

			$annotations = $this->extractAnnotations($name);
			if ($annotations !== null) {
				$entry['annotations'] = $annotations;
			}

			$entry['key_params'] = $this->extractKeyParams($adapter->getParametersSchema());

			$abilities[] = $entry;
		}

		$response = [
			'abilities' => $abilities,
			'count' => count($abilities),
			'usage_hint' => "IMPORTANT: Always use action 'describe' with "
				. "ability_name to get the full parameter schema BEFORE "
				. "executing. Do not guess parameters from key_params "
				. "alone -- describe returns the complete schema with "
				. "types, required fields, and validation rules.",
		];

		$encoded = wp_json_encode($response);

		return $this->success(is_string($encoded) ? $encoded : '[]');
	}

	/**
	 * Handles the describe action by returning the full schema for a specific ability.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $arguments The tool arguments.
	 *
	 * @return ToolResult A success result with the ability details, or failure if not found.
	 */
	private function handleDescribe(array $arguments): ToolResult
	{
		$ability_name = $this->getStringArgument($arguments, 'ability_name');

		if ($ability_name === '') {
			return $this->failure("ability_name is required for 'describe' action.");
		}

		$this->ensureCatalog();

		$adapter = $this->catalog[$ability_name] ?? null;

		if ($adapter === null) {
			return $this->failure(
				sprintf(
					"Unknown ability: %s. Use action 'list' to see available abilities.",
					$ability_name
				)
			);
		}

		$response = [
			'name' => $adapter->getOriginalName(),
			'description' => $adapter->getDescription(),
		];

		$annotations = $this->extractAnnotations($ability_name);
		if ($annotations !== null) {
			$response['annotations'] = $annotations;
		}

		$response['input_schema'] = $adapter->getParametersSchema();

		$encoded = wp_json_encode($response);

		return $this->success(is_string($encoded) ? $encoded : '[]');
	}

	/**
	 * Handles the execute action by delegating to the ability adapter.
	 *
	 * Checks whether the ability is readonly. If not, requires the `confirmed`
	 * parameter to be truthy before proceeding. Strips `confirmed` from params
	 * before delegating to the adapter.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $arguments The tool arguments.
	 *
	 * @return ToolResult The result of the ability execution.
	 */
	private function handleExecute(array $arguments): ToolResult
	{
		if (!call_user_func($this->is_user_logged_in)) {
			return $this->failure(
				'No WordPress user context is set. '
				. 'Use the wordpress_users tool with action "set" to select a user before executing abilities.'
			);
		}

		$ability_name = $this->getStringArgument($arguments, 'ability_name');

		if ($ability_name === '') {
			return $this->failure("ability_name is required for 'execute' action.");
		}

		$this->ensureCatalog();

		$adapter = $this->catalog[$ability_name] ?? null;

		if ($adapter === null) {
			return $this->failure(
				sprintf(
					"Unknown ability: %s. Use action 'list' to see available abilities.",
					$ability_name
				)
			);
		}

		$params = $this->getArrayArgument($arguments, 'params');

		if ($adapter->requiresConfirmation() && !$this->isAutoConfirmEnabled()) {
			$confirmed = !empty($params['confirmed']);

			if (!$confirmed) {
				$confirmation = [
					'error_code' => 'confirmation_required',
					'ability_name' => $ability_name,
					'description' => $adapter->getDescription(),
					'message' => 'This operation may modify data. '
						. 'Describe what you plan to do, ask the user for confirmation, '
						. 'then re-call with confirmed: true in params.',
				];

				$encoded = wp_json_encode($confirmation);

				return $this->failure(is_string($encoded) ? $encoded : '[]');
			}
		}

		unset($params['confirmed']);

		return $adapter->execute($params);
	}

	/**
	 * Lazily discovers WordPress abilities and populates the catalog.
	 *
	 * Called on the first invocation of any action. If the abilities provider
	 * is not callable (e.g. WordPress < 6.9), the catalog is set to an empty array.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function ensureCatalog(): void
	{
		if ($this->catalog !== null) {
			return;
		}

		$this->catalog = [];

		if (!is_callable($this->abilities_provider)) {
			return;
		}

		/** @var mixed $abilities */
		$abilities = call_user_func($this->abilities_provider);

		if (!is_array($abilities)) {
			return;
		}

		foreach ($abilities as $ability) {
			if (!$ability instanceof WP_Ability) {
				continue;
			}

			$name = $ability->get_name();
			$this->abilities[$name] = $ability;
			$this->catalog[$name] = new AbilityToolAdapter($ability);
		}
	}

	/**
	 * Extracts top-level parameter names from a JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed>|null $schema The JSON Schema from the ability.
	 *
	 * @return array<int, string> The top-level property names, or empty array.
	 */
	private function extractKeyParams(?array $schema): array
	{
		if ($schema === null || !isset($schema['properties']) || !is_array($schema['properties'])) {
			return [];
		}

		return array_keys($schema['properties']);
	}

	/**
	 * Extracts annotations from the underlying WP_Ability's meta.
	 *
	 * Returns the annotations array if present and non-empty, null otherwise.
	 * When null is returned, the caller should omit the annotations key entirely.
	 *
	 * @since 0.1.0
	 *
	 * @param string $ability_name The original ability name used as catalog key.
	 *
	 * @return array<string, mixed>|null The annotations, or null if absent.
	 */
	private function extractAnnotations(string $ability_name): ?array
	{
		$ability = $this->abilities[$ability_name] ?? null;

		if ($ability === null) {
			return null;
		}

		$meta = $ability->get_meta();
		$annotations = $meta['annotations'] ?? null;

		if (!is_array($annotations) || $annotations === []) {
			return null;
		}

		return $annotations;
	}

	/**
	 * Checks whether auto-confirm (yolo) mode is active.
	 *
	 * Returns true when a confirmation handler is injected and its
	 * isAutoConfirm() method returns true, meaning all tool confirmations
	 * should be bypassed.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	private function isAutoConfirmEnabled(): bool
	{
		return $this->confirmation_handler !== null && $this->confirmation_handler->isAutoConfirm();
	}
}
