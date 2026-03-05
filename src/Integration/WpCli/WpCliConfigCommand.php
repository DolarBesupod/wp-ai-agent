<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\WpCli;

/**
 * WP-CLI command handler for the `wp agent config` subcommand group.
 *
 * Exposes three subcommands:
 * - `wp agent config list` — display all agent configuration values as a table.
 * - `wp agent config get <key>` — print a single configuration value.
 * - `wp agent config set <key> <value>` — write a constant to wp-config.php.
 *
 * @since 0.1.0
 */
class WpCliConfigCommand
{
	/**
	 * Map of short configuration keys to wp-config.php constant names.
	 *
	 * @var array<string, string>
	 */
	private const KEY_MAP = [
		'api-key'        => 'ANTHROPIC_API_KEY',
		'model'          => 'WP_AI_AGENT_MODEL',
		'max-tokens'     => 'WP_AI_AGENT_MAX_TOKENS',
		'temperature'    => 'WP_AI_AGENT_TEMPERATURE',
		'system-prompt'  => 'WP_AI_AGENT_SYSTEM_PROMPT',
		'debug'          => 'WP_AI_AGENT_DEBUG',
		'streaming'      => 'WP_AI_AGENT_STREAMING',
		'max-iterations' => 'WP_AI_AGENT_MAX_ITERATIONS',
		'bypassed-tools' => 'WP_AI_AGENT_BYPASSED_TOOLS',
	];

	/**
	 * List all agent configuration values in a table.
	 *
	 * Reads the current values of all agent constants from wp-config.php and
	 * displays them in a formatted table. The API key value is masked for
	 * security: only the first 8 characters are shown followed by `****`.
	 *
	 * ## EXAMPLES
	 *
	 *     wp agent config list
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
		$config = new WpConfigConfiguration();
		$rows   = [];

		foreach (self::KEY_MAP as $key => $constant) {
			$value = $this->readConstant($constant);

			if ($key === 'api-key') {
				$value = $this->maskApiKey($value);
			}

			$rows[] = [
				'key'      => $key,
				'constant' => $constant,
				'value'    => $value,
			];
		}

		// Use config to suppress "unused variable" warnings — the object provides
		// a typed read API but KEY_MAP drives the display loop above.
		unset($config);

		\WP_CLI\Utils\format_items('table', $rows, ['key', 'constant', 'value']);
	}

	/**
	 * Get a single agent configuration value.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : The configuration key. Run `wp agent config list` to see available keys.
	 *
	 * ## EXAMPLES
	 *
	 *     wp agent config get model
	 *     wp agent config get api-key
	 *
	 * @subcommand get
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, string>         $args       Positional arguments; $args[0] is the key.
	 * @param array<string, string|bool> $assoc_args Named arguments (unused).
	 *
	 * @return void
	 */
	public function get(array $args, array $assoc_args): void
	{
		$key = $args[0] ?? '';

		if (!isset(self::KEY_MAP[$key])) {
			\WP_CLI::error(\sprintf('Unknown config key: %s. Run `wp agent config list` to see available keys.', $key));
			return;
		}

		$constant = self::KEY_MAP[$key];
		$value    = $this->readConstant($constant);

		\WP_CLI::line($value);
	}

	/**
	 * Set an agent configuration value in wp-config.php.
	 *
	 * Writes the given value as a PHP constant to wp-config.php using the
	 * WP-CLI `config set` command.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : The configuration key. Run `wp agent config list` to see available keys.
	 *
	 * <value>
	 * : The value to set.
	 *
	 * ## EXAMPLES
	 *
	 *     wp agent config set model claude-opus-4-6
	 *     wp agent config set max-tokens 4096
	 *     wp agent config set api-key sk-ant-...
	 *
	 * @subcommand set
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, string>         $args       Positional arguments; $args[0] is the key, $args[1] the value.
	 * @param array<string, string|bool> $assoc_args Named arguments (unused).
	 *
	 * @return void
	 */
	public function set(array $args, array $assoc_args): void
	{
		$key   = $args[0] ?? '';
		$value = $args[1] ?? null;

		if (!isset(self::KEY_MAP[$key])) {
			\WP_CLI::error(\sprintf('Unknown config key: %s. Run `wp agent config list` to see available keys.', $key));
			return;
		}

		if ($value === null || $value === '') {
			\WP_CLI::error(\sprintf('Please provide a value for %s.', $key));
			return;
		}

		$constant = self::KEY_MAP[$key];

		\WP_CLI::runcommand(
			\sprintf('config set %s %s --type=constant', $constant, \escapeshellarg($value)),
			['return' => true]
		);

		\WP_CLI::success(\sprintf('Set %s successfully.', $key));
	}

	/**
	 * Reads the current value of a PHP constant.
	 *
	 * Returns the string representation of the constant when defined, or
	 * `(not set)` when the constant is not defined.
	 *
	 * @param string $constant The constant name to read.
	 *
	 * @return string The constant's string value, or '(not set)'.
	 *
	 * @since 0.1.0
	 */
	private function readConstant(string $constant): string
	{
		if (!\defined($constant)) {
			return '(not set)';
		}

		$raw = \constant($constant);

		if (\is_bool($raw)) {
			return $raw ? 'true' : 'false';
		}

		return (string) $raw;
	}

	/**
	 * Masks an API key value for display.
	 *
	 * Shows the first 8 characters followed by `****` when the value is
	 * non-empty. Returns `(not set)` when the value is empty or `(not set)`.
	 *
	 * @param string $value The raw API key value.
	 *
	 * @return string The masked key, or `(not set)`.
	 *
	 * @since 0.1.0
	 */
	private function maskApiKey(string $value): string
	{
		if ($value === '' || $value === '(not set)') {
			return '(not set)';
		}

		return \substr($value, 0, 8) . '****';
	}
}
