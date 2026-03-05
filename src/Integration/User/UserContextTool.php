<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\User;

use Automattic\WpAiAgent\Core\Tool\AbstractTool;
use Automattic\WpAiAgent\Core\ValueObjects\ToolResult;

/**
 * Tool for discovering and setting the active WordPress user context.
 *
 * WordPress abilities require an authenticated user but WP-CLI defaults to
 * user ID 0. This tool lets the agent discover available users and set the
 * active user before executing abilities.
 *
 * Exposes three actions -- list, set, current -- following the same
 * multi-action pattern as AbilityStrapTool. All WordPress function calls
 * are injected as callables for testability.
 *
 * @since 0.1.0
 */
class UserContextTool extends AbstractTool
{
	/**
	 * Valid action values for the tool.
	 *
	 * @since 0.1.0
	 *
	 * @var array<int, string>
	 */
	private const VALID_ACTIONS = ['list', 'set', 'current'];

	/**
	 * Hard cap for the number of users returned by the list action.
	 *
	 * @since 0.1.0
	 *
	 * @var int
	 */
	private const MAX_RESULTS = 25;

	/**
	 * Callable that lists WordPress users.
	 *
	 * Signature:
	 *     (string $role, string $search) => array<int, array{...}>
	 *
	 * @var callable
	 */
	private $list_users_fn;

	/**
	 * Callable that resolves and sets a WordPress user.
	 *
	 * Signature:
	 *     (string $identifier) => array{...}|false
	 *
	 * @var callable
	 */
	private $set_user_fn;

	/**
	 * Callable that returns the current WordPress user context.
	 *
	 * Signature:
	 *     () => array{...}
	 *
	 * @var callable
	 */
	private $get_current_user_fn;

	/**
	 * Creates a new UserContextTool.
	 *
	 * @since 0.1.0
	 *
	 * @param callable|null $list_users_fn Optional callable for listing users.
	 * @param callable|null $set_user_fn Optional callable for setting the active user.
	 * @param callable|null $get_current_user_fn Optional callable for getting current user.
	 */
	public function __construct(
		?callable $list_users_fn = null,
		?callable $set_user_fn = null,
		?callable $get_current_user_fn = null,
	) {
		$this->list_users_fn = $list_users_fn ?? self::defaultListUsersFn();
		$this->set_user_fn = $set_user_fn ?? self::defaultSetUserFn();
		$this->get_current_user_fn = $get_current_user_fn ?? self::defaultGetCurrentUserFn();
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
		return 'wordpress_users';
	}

	/**
	 * Returns the tool description explaining the list/set/current action pattern.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function getDescription(): string
	{
		return 'Manage WordPress user context for ability execution. '
			. 'Use action "list" to discover available users (defaults to administrators only). '
			. 'Use action "set" with a user parameter (ID, login, or email) to set the active user. '
			. 'Use action "current" to see the currently active user. '
			. 'Use the "search" parameter with "list" to find specific users across all roles.';
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
					'description' => 'The action to perform: list, set, or current.',
				],
				'user' => [
					'type' => 'string',
					'description' => 'User identifier for set action: numeric ID, login name, or email address.',
				],
				'role' => [
					'type' => 'string',
					'description' => 'WordPress role to filter by for list action (default: administrator).',
				],
				'search' => [
					'type' => 'string',
					'description' => 'Search term to find users by name, login, or email across all roles.',
				],
			],
			'required' => ['action'],
		];
	}

	/**
	 * Returns whether this tool requires user confirmation before execution.
	 *
	 * User context changes are session-local and non-destructive.
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
			'list' => $this->handleList($arguments),
			'set' => $this->handleSet($arguments),
			'current' => $this->handleCurrent(),
		};
	}

	/**
	 * Handles the list action by returning matching WordPress users.
	 *
	 * Defaults to administrators. When a search term is provided, searches
	 * across all roles. Results are capped at 25 with a has_more flag.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $arguments The tool arguments.
	 *
	 * @return ToolResult A success result with the users as JSON.
	 */
	private function handleList(array $arguments): ToolResult
	{
		$role = $this->getStringArgument($arguments, 'role', 'administrator');
		$search = $this->getStringArgument($arguments, 'search');

		/** @var mixed $raw_users */
		$raw_users = call_user_func($this->list_users_fn, $role, $search);

		if (!is_array($raw_users)) {
			$raw_users = [];
		}

		$has_more = count($raw_users) > self::MAX_RESULTS;
		$users = array_slice($raw_users, 0, self::MAX_RESULTS);

		$response = [
			'users' => $users,
			'count' => count($users),
		];

		if ($has_more) {
			$response['has_more'] = true;
			$response['hint'] = "More users exist. Use the 'search' parameter to narrow results.";
		}

		$encoded = \wp_json_encode($response);

		return $this->success(is_string($encoded) ? $encoded : '[]');
	}

	/**
	 * Handles the set action by resolving and activating a WordPress user.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $arguments The tool arguments.
	 *
	 * @return ToolResult A success result with the user data, or failure if not found.
	 */
	private function handleSet(array $arguments): ToolResult
	{
		$user = $this->getStringArgument($arguments, 'user');

		if ($user === '') {
			return $this->failure("user parameter is required for 'set' action.");
		}

		/** @var mixed $result */
		$result = call_user_func($this->set_user_fn, $user);

		if ($result === false) {
			return $this->failure(
				sprintf(
					"User not found: %s. Use action 'list' to see available users.",
					$user
				)
			);
		}

		if (!is_array($result)) {
			return $this->failure(
				sprintf(
					"User not found: %s. Use action 'list' to see available users.",
					$user
				)
			);
		}

		$encoded = \wp_json_encode($result);

		return $this->success(is_string($encoded) ? $encoded : '[]');
	}

	/**
	 * Handles the current action by returning the active user context.
	 *
	 * @since 0.1.0
	 *
	 * @return ToolResult A success result with the current user data as JSON.
	 */
	private function handleCurrent(): ToolResult
	{
		/** @var mixed $result */
		$result = call_user_func($this->get_current_user_fn);

		if (!is_array($result)) {
			$result = [
				'id' => 0,
				'login' => '',
				'display_name' => '',
				'email' => '',
				'roles' => [],
				'logged_in' => false,
			];
		}

		$encoded = \wp_json_encode($result);

		return $this->success(is_string($encoded) ? $encoded : '[]');
	}

	/**
	 * Returns the default callable for listing WordPress users.
	 *
	 * Wraps get_users() with role/search filtering and maps WP_User objects
	 * to plain arrays. Always fetches MAX_RESULTS + 1 to detect overflow.
	 *
	 * @since 0.1.0
	 *
	 * @return \Closure(string, string): array<int, array{
	 *     id: int, login: string, display_name: string, email: string, roles: string[]
	 * }>
	 */
	private static function defaultListUsersFn(): \Closure
	{
		return static function (string $role, string $search): array {
			$args = [
				'number' => self::MAX_RESULTS + 1,
			];

			if ($search !== '') {
				$args['search'] = '*' . $search . '*';
				$args['search_columns'] = ['user_login', 'user_email', 'display_name'];
			} else {
				$args['role'] = $role;
			}

			/** @var array<int, \WP_User> $wp_users */
			$wp_users = \get_users($args);

			$users = [];
			foreach ($wp_users as $wp_user) {
				$users[] = [
					'id' => $wp_user->ID,
					'login' => $wp_user->user_login,
					'display_name' => $wp_user->display_name,
					'email' => $wp_user->user_email,
					'roles' => array_values($wp_user->roles),
				];
			}

			return $users;
		};
	}

	/**
	 * Returns the default callable for resolving and setting a WordPress user.
	 *
	 * Resolution order: numeric -> get_user_by('id'), contains @ -> get_user_by('email'),
	 * otherwise -> get_user_by('login'). Calls wp_set_current_user() on success.
	 *
	 * @since 0.1.0
	 *
	 * @return \Closure(string): (array{
	 *     id: int, login: string, display_name: string, email: string, roles: string[]
	 * }|false)
	 */
	private static function defaultSetUserFn(): \Closure
	{
		return static function (string $identifier): array|false {
			if (is_numeric($identifier)) {
				$wp_user = \get_user_by('id', (int) $identifier);
			} elseif (str_contains($identifier, '@')) {
				$wp_user = \get_user_by('email', $identifier);
			} else {
				$wp_user = \get_user_by('login', $identifier);
			}

			if ($wp_user === false) {
				return false;
			}

			\wp_set_current_user($wp_user->ID);

			return [
				'id' => $wp_user->ID,
				'login' => $wp_user->user_login,
				'display_name' => $wp_user->display_name,
				'email' => $wp_user->user_email,
				'roles' => array_values($wp_user->roles),
			];
		};
	}

	/**
	 * Returns the default callable for getting the current WordPress user.
	 *
	 * @since 0.1.0
	 *
	 * @return \Closure(): array{
	 *     id: int, login: string, display_name: string, email: string, roles: string[], logged_in: bool
	 * }
	 */
	private static function defaultGetCurrentUserFn(): \Closure
	{
		return static function (): array {
			$wp_user = \wp_get_current_user();
			$logged_in = \is_user_logged_in();

			return [
				'id' => $wp_user->ID,
				'login' => $wp_user->user_login,
				'display_name' => $wp_user->display_name,
				'email' => $wp_user->user_email,
				'roles' => array_values($wp_user->roles),
				'logged_in' => $logged_in,
			];
		};
	}
}
