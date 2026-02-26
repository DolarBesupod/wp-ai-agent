<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\Ability;

use WpAiAgent\Core\Contracts\ToolRegistryInterface;
use WpAiAgent\Core\Exceptions\DuplicateToolException;

/**
 * Discovers all registered WordPress abilities and registers them as agent tools.
 *
 * Calls the abilities provider (defaults to `wp_get_abilities`) to retrieve the
 * full list of WP_Ability instances, wraps each one in an AbilityToolAdapter,
 * and registers it in the main ToolRegistry. Duplicate names are silently skipped
 * with a debug log, matching the collision-handling pattern used by McpToolRegistry.
 *
 * @since n.e.x.t
 */
class AbilityToolRegistry
{
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
	 * Creates a new AbilityToolRegistry.
	 *
	 * @since n.e.x.t
	 *
	 * @param callable|null $abilities_provider Optional callable that returns WP_Ability[].
	 *                                          Defaults to 'wp_get_abilities'.
	 */
	public function __construct(?callable $abilities_provider = null)
	{
		$this->abilities_provider = $abilities_provider ?? 'wp_get_abilities';
	}

	/**
	 * Discovers WordPress abilities and registers them as tools.
	 *
	 * Guards against non-callable providers (e.g. WordPress < 6.9 where
	 * `wp_get_abilities` does not exist) by returning 0 immediately.
	 * Each ability is wrapped in an AbilityToolAdapter before registration.
	 * Duplicate tool names are caught and logged via WP_CLI::debug() when
	 * the WP_CLI class is available.
	 *
	 * @since n.e.x.t
	 *
	 * @param ToolRegistryInterface $tool_registry The registry to register tools into.
	 *
	 * @return int The number of ability tools successfully registered.
	 */
	public function discoverAndRegister(ToolRegistryInterface $tool_registry): int
	{
		if (!is_callable($this->abilities_provider)) {
			return 0;
		}

		/** @var mixed $abilities */
		$abilities = call_user_func($this->abilities_provider);

		if (!is_array($abilities) || $abilities === []) {
			return 0;
		}

		$registered = 0;

		foreach ($abilities as $ability) {
			$adapter = new AbilityToolAdapter($ability);

			try {
				$tool_registry->register($adapter);
				$registered++;
			} catch (DuplicateToolException $exception) {
				if (class_exists('\WP_CLI')) {
					\WP_CLI::debug(
						sprintf(
							'Ability tool "%s" skipped: %s',
							$adapter->getName(),
							$exception->getMessage()
						)
					);
				}
			}
		}

		return $registered;
	}
}
