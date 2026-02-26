<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\WpCli;

use WpAiAgent\Core\Agent\Agent;
use WpAiAgent\Core\Agent\AgentLoop;
use WpAiAgent\Core\Tool\ToolExecutor;
use WpAiAgent\Integration\AiClient\AiClientAdapter;
use WpAiAgent\Integration\Mcp\McpClientManager;
use WpAiAgent\Integration\Mcp\McpServerConfiguration;
use WpAiAgent\Integration\Mcp\McpToolRegistry;
use WpAiAgent\Integration\Tool\BuiltInToolRegistry;

/**
 * Static factory that wires all WP-CLI-specific implementations into a WpCliApplication.
 *
 * This is the sole entry point for constructing the WP-CLI application. It reads
 * configuration from wp-config.php constants via WpConfigConfiguration and wires
 * every dependency before handing the fully-configured object to the caller.
 *
 * MCP server configuration is read from the PHP_CLI_AGENT_MCP_SERVERS constant
 * (kept for backward compatibility with existing wp-config.php files). Connection
 * failures are reported via WP_CLI::warning() and do not abort startup.
 *
 * @since n.e.x.t
 */
final class WpCliBootstrap
{
	/**
	 * Creates and wires a fully-configured WpCliApplication.
	 *
	 * Wiring order:
	 * 1. WpConfigConfiguration — reads all config from wp-config.php constants.
	 * 2. WpCliOutputHandler — WP-CLI output; debug mode enabled when configured.
	 * 3. WpCliConfirmationHandler — uses bypassed tools from config.
	 * 4. WpOptionsSessionRepository — WordPress options-backed session storage.
	 * 5. BuiltInToolRegistry — all built-in tools registered.
	 * 6. ToolExecutor — uses registry + confirmation handler.
	 * 7. MCP servers — connects from PHP_CLI_AGENT_MCP_SERVERS constant if defined.
	 * 8. AiClientAdapter — authenticated with API key, model, and token limits.
	 * 9. AgentLoop — wires AI adapter, executor, registry, and output.
	 * 10. Agent — session orchestrator with system prompt.
	 *
	 * @return WpCliApplication The fully-wired application.
	 *
	 * @since n.e.x.t
	 */
	public static function createApplication(): WpCliApplication
	{
		// Step 1 — Configuration.
		$config = new WpConfigConfiguration();

		// Step 2 — Output handler.
		$output = new WpCliOutputHandler();
		if ($config->isDebugEnabled()) {
			$output->setDebugEnabled(true);
		}

		// Step 3 — Confirmation handler.
		$confirmation = new WpCliConfirmationHandler($config->getBypassedTools());

		// Step 4 — Session repository.
		$session_repo = new WpOptionsSessionRepository();

		// Step 5 — Tool registry.
		$tool_registry = BuiltInToolRegistry::createWithAllTools();

		// Step 6 — Tool executor.
		$tool_executor = new ToolExecutor($tool_registry, $confirmation);

		// Step 7 — MCP servers (optional, backward-compatible constant).
		$mcp_client_manager = self::connectMcpServers($tool_registry);

		// Step 8 — AI adapter.
		$ai_adapter = new AiClientAdapter(
			$config->getApiKey(),
			$config->getModel(),
			$config->getMaxTokens()
		);
		$ai_adapter->setTemperature($config->getTemperature());

		// Step 9 — Agent loop.
		$agent_loop = new AgentLoop($ai_adapter, $tool_executor, $tool_registry, $output);
		$agent_loop->setMaxIterations($config->getMaxIterations());

		// Step 10 — Agent.
		$agent = new Agent($agent_loop, $session_repo, $config->getSystemPrompt());

		return new WpCliApplication($config, $agent, $output, $confirmation, $session_repo, $mcp_client_manager);
	}

	/**
	 * Connects to MCP servers defined in PHP_CLI_AGENT_MCP_SERVERS.
	 *
	 * Reads the constant as a PHP array (same format used by the legacy
	 * WpCliCommand::bridgeWpConfigConstants() method). Returns null when the
	 * constant is not defined or no servers are configured. Connection failures
	 * are reported via WP_CLI::warning() but do not abort startup.
	 *
	 * Example wp-config.php entry:
	 * <code>
	 * define('PHP_CLI_AGENT_MCP_SERVERS', [
	 *     'mcpServers' => [
	 *         'my-server' => ['url' => '...', 'bearer_token' => '...'],
	 *     ],
	 * ]);
	 * </code>
	 *
	 * @param \WpAiAgent\Core\Contracts\ToolRegistryInterface $tool_registry The tool registry.
	 *
	 * @return McpClientManager|null The connected manager, or null when MCP is not configured.
	 *
	 * @since n.e.x.t
	 */
	private static function connectMcpServers(
		\WpAiAgent\Core\Contracts\ToolRegistryInterface $tool_registry
	): ?McpClientManager {
		if (!defined('PHP_CLI_AGENT_MCP_SERVERS') || !is_array(constant('PHP_CLI_AGENT_MCP_SERVERS'))) {
			return null;
		}

		/** @var array<string, mixed> $raw */
		$raw = constant('PHP_CLI_AGENT_MCP_SERVERS');

		/** @var array<string, array<string, mixed>> $servers */
		$servers = isset($raw['mcpServers']) && is_array($raw['mcpServers']) ? $raw['mcpServers'] : [];

		if (count($servers) === 0) {
			return null;
		}

		$configs = [];
		foreach ($servers as $server_name => $server_config) {
			$configs[] = McpServerConfiguration::fromArray($server_name, $server_config);
		}

		try {
			$mcp_client_manager = new McpClientManager();
			$failures = $mcp_client_manager->connectAll($configs);

			foreach ($failures as $server_name => $error) {
				\WP_CLI::warning(sprintf(
					'[MCP] Failed to connect to %s: %s',
					$server_name,
					$error->getMessage()
				));
			}

			$connected_servers = $mcp_client_manager->getConnectedServers();
			if (count($connected_servers) > 0) {
				$mcp_tool_registry = new McpToolRegistry($mcp_client_manager, $tool_registry);
				$tools_registered = $mcp_tool_registry->discoverAndRegister();

				if ($tools_registered > 0) {
					\WP_CLI::warning(sprintf(
						'[MCP] Registered %d tool(s) from %d server(s)',
						$tools_registered,
						count($connected_servers)
					));
				}
			}

			return $mcp_client_manager;
		} catch (\Throwable $e) {
			\WP_CLI::warning(sprintf('[MCP] %s', $e->getMessage()));
			return null;
		}
	}
}
