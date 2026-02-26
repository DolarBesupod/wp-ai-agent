<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\WpCli;

use WpAiAgent\Core\ValueObjects\SessionId;
use WpAiAgent\Integration\Cli\CliApplication;

/**
 * WP-CLI command for the PHP CLI Agent.
 *
 * Exposes the agent as `wp agent` with two subcommands:
 * - `wp agent run`  — interactive REPL session (same as `php agent` standalone)
 * - `wp agent ask`  — send a single message and get a response non-interactively
 *
 * Both subcommands bootstrap the agent via the existing `src/bootstrap.php`,
 * so MCP server configuration, session persistence, and tool confirmation all
 * behave identically to the standalone CLI.
 *
 * @since n.e.x.t
 */
class WpCliCommand
{
	/**
	 * Path to the bootstrap file, relative to this file.
	 *
	 * @var string
	 */
	private const BOOTSTRAP_PATH = __DIR__ . '/../../bootstrap.php';

	/**
	 * Start an interactive agent REPL session.
	 *
	 * Launches the full interactive REPL — identical to running `php agent`
	 * from the command line. Type `/help` inside the REPL for available commands.
	 *
	 * ## OPTIONS
	 *
	 * [--session=<id>]
	 * : Resume an existing session by ID.
	 *
	 * [--config=<path>]
	 * : Load configuration from the specified YAML file.
	 *
	 * [--save]
	 * : Persist the session to disk (default). Use --no-save to disable.
	 *
	 * [--debug]
	 * : Enable debug output.
	 *
	 * ## EXAMPLES
	 *
	 *     wp agent run
	 *     wp agent run --session=abc123
	 *     wp agent run --config=/path/to/agent.yaml
	 *     wp agent run --no-save
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
		$this->bridgeWpConfigConstants();

		$argv = $this->buildArgv($assoc_args);

		/** @var CliApplication $app */
		$app = require self::BOOTSTRAP_PATH;

		exit($app->run($argv));
	}

	/**
	 * Send a single message to the agent and print the response.
	 *
	 * Non-interactive one-shot mode: starts (or resumes) a session,
	 * sends the message through the full ReAct loop, then exits.
	 * Tool confirmation prompts still appear when a tool requires it.
	 *
	 * ## OPTIONS
	 *
	 * <message>
	 * : The message to send to the agent.
	 *
	 * [--session=<id>]
	 * : Resume an existing session by ID instead of starting a new one.
	 *
	 * [--save]
	 * : Persist the session to disk (default). Use --no-save to disable.
	 *
	 * [--debug]
	 * : Enable debug output.
	 *
	 * ## EXAMPLES
	 *
	 *     wp agent ask "What plugins are active?"
	 *     wp agent ask "List all posts from the last week" --session=abc123
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
			\WP_CLI::error('Please provide a message. Usage: wp agent ask "your message here"');
			return;
		}

		$message = $args[0];

		$this->bridgeWpConfigConstants();

		/** @var CliApplication $app */
		$app = require self::BOOTSTRAP_PATH;

		$agent = $app->getAgent();

		// Enable debug mode if requested.
		if (! empty($assoc_args['debug'])) {
			$app->getOutputHandler()->setDebugEnabled(true);
		}

		// Start or resume session.
		$session_id_string = isset($assoc_args['session']) ? (string) $assoc_args['session'] : '';
		if ($session_id_string !== '') {
			$agent->resumeSession(SessionId::fromString($session_id_string));
		} else {
			$agent->startSession();
		}

		// Disable auto-save if --no-save was passed (WP-CLI sets save=false for --no-save).
		if (isset($assoc_args['save']) && false === $assoc_args['save'] && method_exists($agent, 'setAutoSave')) {
			$agent->setAutoSave(false);
		}

		try {
			$agent->sendMessage($message);
		} catch (\Throwable $e) {
			$agent->endSession();
			\WP_CLI::error($e->getMessage());
			return;
		}

		$agent->endSession();
	}

	/**
	 * Prepares the process environment for bootstrapping the agent.
	 *
	 * - Bridges PHP constants from wp-config.php into the process environment
	 *   so ConfigurationResolver::getEnv() can pick them up via getenv().
	 * - Changes the working directory to the plugin root so that all config
	 *   lookups (.wp-ai-agent/settings.json, .wp-ai-agent/mcp.json) resolve
	 *   to the agent's own config rather than the WordPress install root.
	 *
	 * @since n.e.x.t
	 *
	 * @return void
	 */
	private function bridgeWpConfigConstants(): void
	{
		if (defined('ANTHROPIC_API_KEY') && getenv('ANTHROPIC_API_KEY') === false) {
			putenv('ANTHROPIC_API_KEY=' . constant('ANTHROPIC_API_KEY'));
		}

		// Move to the home directory so that .wp-ai-agent/ config is resolved
		// to ~/.wp-ai-agent/ — outside the webroot and never browser-accessible.
		// The plugin directory lives inside wp-content/plugins/ which is public;
		// the home directory is not served by the web server.
		$home = getenv('HOME');
		if ($home !== false && is_dir($home)) {
			chdir($home);
		}

		// If MCP servers are defined as a PHP array in wp-config.php, write them
		// to ~/.wp-ai-agent/mcp.json so the agent picks them up without any
		// credentials ever landing in a browser-accessible file.
		//
		// Example wp-config.php entry:
		//   define('PHP_CLI_AGENT_MCP_SERVERS', [
		//       'mcpServers' => [
		//           'wordpress' => [
		//               'url'          => 'http://wp-beta.test/wp-json/mcp/v1/full',
		//               'bearer_token' => 'my-token',
		//               'timeout'      => 30,
		//               'enabled'      => true,
		//           ],
		//       ],
		//   ]);
		$mcp_servers_defined = defined('PHP_CLI_AGENT_MCP_SERVERS') && is_array(constant('PHP_CLI_AGENT_MCP_SERVERS'));
		if ($mcp_servers_defined && $home !== false) {
			$mcp_json = json_encode(constant('PHP_CLI_AGENT_MCP_SERVERS'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
			if ($mcp_json !== false) {
				$config_dir = $home . DIRECTORY_SEPARATOR . '.wp-ai-agent';
				if (! is_dir($config_dir)) {
					mkdir($config_dir, 0700, true);
				}
				file_put_contents($config_dir . DIRECTORY_SEPARATOR . 'mcp.json', $mcp_json);
			}
		}
	}

	/**
	 * Builds the argv array passed to CliApplication::run() from WP-CLI assoc args.
	 *
	 * @since n.e.x.t
	 *
	 * @param array<string, string|bool> $assoc_args The WP-CLI named arguments.
	 *
	 * @return array<int, string> The argv array with 'agent' as the first element.
	 */
	private function buildArgv(array $assoc_args): array
	{
		$argv = [ 'agent' ];

		$session = isset($assoc_args['session']) ? (string) $assoc_args['session'] : '';
		if ($session !== '') {
			$argv[] = '--session=' . $session;
		}

		$config = isset($assoc_args['config']) ? (string) $assoc_args['config'] : '';
		if ($config !== '') {
			$argv[] = '--config=' . $config;
		}

		// WP-CLI sets save=false when --no-save is passed.
		if (isset($assoc_args['save']) && false === $assoc_args['save']) {
			$argv[] = '--no-save';
		}

		if (! empty($assoc_args['debug'])) {
			$argv[] = '--debug';
		}

		return $argv;
	}
}
