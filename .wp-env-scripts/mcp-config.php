<?php
/**
 * MCP server configuration for wp-env E2E testing.
 *
 * Mapped into the WordPress root via .wp-env.json mappings and
 * loaded from wp-config.php via the WORDPRESS_CONFIG_EXTRA mechanism
 * or directly required from a mu-plugin.
 *
 * This file defines the PHP_CLI_AGENT_MCP_SERVERS constant used by
 * WpCliBootstrap::connectMcpServers() to connect stdio MCP servers.
 */

if ( ! defined( 'PHP_CLI_AGENT_MCP_SERVERS' ) ) {
	define( 'PHP_CLI_AGENT_MCP_SERVERS', [
		'mcpServers' => [
			'everything' => [
				'command' => 'mcp-server-everything',
				'args'    => [],
				'timeout' => 15,
				'enabled' => true,
			],
			'filesystem' => [
				'command' => 'mcp-server-filesystem',
				'args'    => [ '/var/www/html' ],
				'timeout' => 15,
				'enabled' => true,
			],
		],
	] );
}
