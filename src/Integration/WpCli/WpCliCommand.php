<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\WpCli;

/**
 * WP-CLI command for the WP AI Agent.
 *
 * Thin dispatcher that delegates each subcommand to WpCliApplication via
 * WpCliBootstrap. No bootstrapping logic lives here.
 *
 * Subcommands:
 * - `wp agent chat` — interactive REPL session.
 * - `wp agent ask`  — one-shot message.
 * - `wp agent init` — write configuration constants to wp-config.php.
 * - `wp agent run`  — deprecated alias for `wp agent chat`.
 *
 * @since n.e.x.t
 */
class WpCliCommand
{
	/**
	 * Start an interactive agent REPL session.
	 *
	 * Starts (or resumes) a session, then reads lines from STDIN in a loop
	 * and passes each non-empty line to the agent. Type `/quit` or `/exit`
	 * inside the session to end it.
	 *
	 * ## OPTIONS
	 *
	 * [--session=<id>]
	 * : Resume an existing session by ID.
	 *
	 * [--[no-]save]
	 * : Whether to persist the session after each turn. Default: persist.
	 *
	 * [--debug]
	 * : Enable debug output.
	 *
	 * ## EXAMPLES
	 *
	 *     wp agent chat
	 *     wp agent chat --session=abc123
	 *     wp agent chat --no-save
	 *
	 * @subcommand chat
	 *
	 * @since n.e.x.t
	 *
	 * @param array<int, string>         $args       Positional arguments (unused).
	 * @param array<string, string|bool> $assoc_args Named arguments.
	 *
	 * @return void
	 */
	public function chat(array $args, array $assoc_args): void
	{
		WpCliBootstrap::createApplication()->chat($assoc_args);
	}

	/**
	 * Send a single message to the agent and print the response.
	 *
	 * Non-interactive one-shot mode: starts (or resumes) a session,
	 * sends the message through the full ReAct loop, then ends the session.
	 *
	 * ## OPTIONS
	 *
	 * <message>
	 * : The message to send to the agent.
	 *
	 * [--session=<id>]
	 * : Resume an existing session by ID instead of starting a new one.
	 *
	 * [--[no-]save]
	 * : Whether to persist the session. Default: persist.
	 *
	 * [--debug]
	 * : Enable debug output.
	 *
	 * ## EXAMPLES
	 *
	 *     wp agent ask "What plugins are active?"
	 *     wp agent ask "List posts from last week" --session=abc123
	 *     wp agent ask "Run a health check" --no-save
	 *
	 * @since n.e.x.t
	 *
	 * @param array<int, string>         $args       Positional arguments; $args[0] is the message.
	 * @param array<string, string|bool> $assoc_args Named arguments.
	 *
	 * @return void
	 */
	public function ask(array $args, array $assoc_args): void
	{
		if (empty($args[0])) {
			\WP_CLI::error('Please provide a message. Usage: wp agent ask "your message"');
			return;
		}

		WpCliBootstrap::createApplication()->ask($args[0], $assoc_args);
	}

	/**
	 * Write required agent constants to wp-config.php.
	 *
	 * Prompts for the Anthropic API key interactively and writes all agent
	 * constants that are not already defined. Already-defined constants are
	 * reported and skipped unless --force is given.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Overwrite constants that are already defined in wp-config.php.
	 *
	 * ## EXAMPLES
	 *
	 *     wp agent init
	 *     wp agent init --force
	 *
	 * @subcommand init
	 *
	 * @since n.e.x.t
	 *
	 * @param array<int, string>         $args       Positional arguments (unused).
	 * @param array<string, string|bool> $assoc_args Named arguments.
	 *
	 * @return void
	 */
	public function init(array $args, array $assoc_args): void
	{
		WpCliBootstrap::createApplication()->init($assoc_args);
	}

	/**
	 * Deprecated alias for `wp agent chat`.
	 *
	 * ## OPTIONS
	 *
	 * [--session=<id>]
	 * : Resume an existing session by ID.
	 *
	 * [--no-save]
	 * : Skip persisting the session after each turn.
	 *
	 * [--debug]
	 * : Enable debug output.
	 *
	 * ## EXAMPLES
	 *
	 *     wp agent run
	 *
	 * @subcommand run
	 *
	 * @since n.e.x.t
	 *
	 * @param array<int, string>         $args       Positional arguments (unused).
	 * @param array<string, string|bool> $assoc_args Named arguments.
	 *
	 * @return void
	 */
	public function run(array $args, array $assoc_args): void
	{
		\WP_CLI::warning('wp agent run is deprecated — use wp agent chat');
		$this->chat($args, $assoc_args);
	}
}
