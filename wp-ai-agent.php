<?php

/**
 * Plugin Name: WP AI Agent
 * Description: A CLI AI agent powered by Claude with MCP support. Exposes WP-CLI commands: wp agent run (interactive REPL) and wp agent ask (one-shot). Works standalone via `php agent` too.
 * Version: 0.1.0
 * Requires PHP: 8.1
 *
 * @package WpAiAgent
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

// Load Composer dependencies (MCP client, Guzzle, Symfony YAML, etc.).
// Safe when running under WordPress: php-ai-client classes already loaded by WP core
// via wp-includes/php-ai-client/autoload.php won't be re-loaded by Composer.
$wp_ai_agent_autoloader = __DIR__ . '/vendor/autoload.php';

if (! file_exists($wp_ai_agent_autoloader)) {
	if (defined('WP_CLI') && WP_CLI) {
		WP_CLI::warning('PHP CLI Agent: Dependencies not installed. Run `composer install` inside ' . __DIR__);
	}
	return;
}

require_once $wp_ai_agent_autoloader;

// Register WP-CLI commands.
if (defined('WP_CLI') && WP_CLI) {
	\WP_CLI::add_command('agent', \WpAiAgent\Integration\WpCli\WpCliCommand::class);
	\WP_CLI::add_command('agent config', \WpAiAgent\Integration\WpCli\WpCliConfigCommand::class);
	\WP_CLI::add_command('agent skills', \WpAiAgent\Integration\WpCli\WpCliSkillCommand::class);
}
