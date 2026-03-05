<?php

/**
 * Plugin Name: WP AI Agent
 * Description: A CLI AI agent powered by Claude with MCP support. Exposes WP-CLI commands: wp agent run (interactive REPL) and wp agent ask (one-shot). Works standalone via `php agent` too.
 * Version: 0.1.0
 * Requires at least: 7.0
 * Requires PHP: 8.4
 * Author: Ovidiu Galatan
 * Author URI: https://developer.wordpress.org/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-ai-agent
 *
 * @package Automattic\WpAiAgent
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

// Block activation on WordPress < 7.0 (must be registered before the runtime guard).
register_activation_hook(__FILE__, function () {
	if (! class_exists('WordPress\AiClient\Builders\PromptBuilder')) {
		deactivate_plugins(plugin_basename(__FILE__));
		wp_die(
			'WP AI Agent requires WordPress 7.0 or later (bundled AI client not found).',
			'Plugin Activation Error',
			['back_link' => true]
		);
	}
});

// Runtime guard: WordPress 7.0+ bundled AI client (WordPress\AiClient namespace).
if (! class_exists('WordPress\AiClient\Builders\PromptBuilder')) {
	if (defined('WP_CLI') && WP_CLI) {
		WP_CLI::warning('WP AI Agent requires WordPress 7.0 or later (bundled AI client not found).');
	}
	return;
}

// Register WP-CLI commands.
if (defined('WP_CLI') && WP_CLI) {
	\WP_CLI::add_command('agent', \Automattic\WpAiAgent\Integration\WpCli\WpCliCommand::class);
	\WP_CLI::add_command('agent config', \Automattic\WpAiAgent\Integration\WpCli\WpCliConfigCommand::class);
	\WP_CLI::add_command('agent skills', \Automattic\WpAiAgent\Integration\WpCli\WpCliSkillCommand::class);
	\WP_CLI::add_command('agent auth', \Automattic\WpAiAgent\Integration\WpCli\WpCliAuthCommand::class);
}
