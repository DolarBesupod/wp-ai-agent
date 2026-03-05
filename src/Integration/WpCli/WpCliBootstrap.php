<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Integration\WpCli;

use Automattic\Automattic\WpAiAgent\Core\Agent\Agent;
use Automattic\Automattic\WpAiAgent\Core\Agent\AgentLoop;
use Automattic\Automattic\WpAiAgent\Core\Tool\ToolExecutor;
use Automattic\Automattic\WpAiAgent\Integration\AiClient\AiClientAdapter;
use Automattic\Automattic\WpAiAgent\Integration\AiClient\ProviderDetector;
use Automattic\Automattic\WpAiAgent\Integration\Configuration\MarkdownParser;
use Automattic\Automattic\WpAiAgent\Integration\Mcp\McpClientManager;
use Automattic\Automattic\WpAiAgent\Integration\Mcp\McpServerConfiguration;
use Automattic\Automattic\WpAiAgent\Integration\Mcp\McpToolRegistry;
use Automattic\Automattic\WpAiAgent\Integration\Settings\BashCommandExpander;
use Automattic\Automattic\WpAiAgent\Integration\Settings\FileReferenceExpander;
use Automattic\Automattic\WpAiAgent\Integration\Skill\SkillLoader;
use Automattic\Automattic\WpAiAgent\Integration\Skill\SkillRegistry;
use Automattic\Automattic\WpAiAgent\Integration\Ability\AbilityStrapTool;
use Automattic\Automattic\WpAiAgent\Integration\User\UserContextTool;
use Automattic\Automattic\WpAiAgent\Integration\Tool\BuiltInToolRegistry;

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
	 * 7. SkillRegistry — discovers skills from options; falls back to bundled skills/.
	 * 8. MCP servers — connects from PHP_CLI_AGENT_MCP_SERVERS constant if defined.
		 * 9. CredentialResolver — resolves credentials via constant, env, or DB credential.
		 *    AiClientAdapter — authenticated with resolved secret and auth mode.
	 * 10. AgentLoop — wires AI adapter, executor, registry, and output.
	 * 11. Agent — session orchestrator with system prompt.
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
		$confirmation = new WpCliConfirmationHandler(
			$config->getBypassedTools(),
			$config->getAutoConfirm()
		);

		// Step 4 — Session repository.
		$session_repo = new WpOptionsSessionRepository();

		// Step 5 — Tool registry.
		$tool_registry = BuiltInToolRegistry::createWithAllTools();

		// Step 6 — Tool executor.
		$tool_executor = new ToolExecutor($tool_registry, $confirmation);

		// Step 7 — Skills (options-backed, falls back to bundled skills/ on first run).
		self::discoverSkills($tool_registry);

		// Step 8 — MCP servers (optional, backward-compatible constant).
		$mcp_client_manager = self::connectMcpServers($tool_registry);

		// Step 8b — WordPress abilities (WP 6.9+, silently skipped otherwise).
		self::discoverAbilities($tool_registry, $confirmation);

		// Step 9 — Credential resolution and AI adapter.
		$credential_repository = new WpOptionsCredentialRepository();
		$credential_resolver = new CredentialResolver($credential_repository);
		$provider_id = ProviderDetector::detectFromModel($config->getModel());
		$resolved_credential = $credential_resolver->resolve($provider_id);

		$ai_adapter = new AiClientAdapter(
			$resolved_credential->getSecret(),
			$resolved_credential->getAuthMode(),
			$config->getModel(),
			$config->getMaxTokens(),
			provider_id: $provider_id
		);
		$ai_adapter->setTemperature($config->getTemperature());

		// Step 10 — Agent loop.
		$agent_loop = new AgentLoop($ai_adapter, $tool_executor, $tool_registry, $output);
		$agent_loop->setMaxIterations($config->getMaxIterations());

		// Step 11 — Agent.
		$agent = new Agent($agent_loop, $session_repo, $config->getSystemPrompt());

		return new WpCliApplication(
			$config,
			$agent,
			$output,
			$confirmation,
			$session_repo,
			$ai_adapter,
			$mcp_client_manager,
			$credential_resolver
		);
	}

	/**
	 * Discovers skills and registers them into the tool registry.
	 *
	 * Skills are loaded from the WordPress options table. When the skill index
	 * option has never been set, bundled skills from the plugin's skills/ directory
	 * are used as a first-run fallback. Discovery errors are reported via
	 * WP_CLI::warning() and never abort startup.
	 *
	 * @param \Automattic\WpAiAgent\Core\Contracts\ToolRegistryInterface $tool_registry The tool registry.
	 *
	 * @return void
	 *
	 * @since n.e.x.t
	 */
	private static function discoverSkills(
		\Automattic\WpAiAgent\Core\Contracts\ToolRegistryInterface $tool_registry
	): void {
		try {
			$markdown_parser = new MarkdownParser();
			$file_expander = new FileReferenceExpander();
			$bash_expander = new BashCommandExpander();
			$skill_loader = new SkillLoader($markdown_parser);
			$skill_repository = new WpOptionsSkillRepository();
			$bundled_skills_dir = dirname(__DIR__, 3) . '/skills';

			$skill_registry = new SkillRegistry(
				$skill_repository,
				$skill_loader,
				$file_expander,
				$bash_expander,
				$bundled_skills_dir
			);

			$skill_registry->discoverAndRegister($tool_registry);
		} catch (\Throwable $e) {
			\WP_CLI::warning(sprintf('[Skills] %s', $e->getMessage()));
		}
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
	 * @param \Automattic\WpAiAgent\Core\Contracts\ToolRegistryInterface $tool_registry The tool registry.
	 *
	 * @return McpClientManager|null The connected manager, or null when MCP is not configured.
	 *
	 * @since n.e.x.t
	 */
	private static function connectMcpServers(
		\Automattic\WpAiAgent\Core\Contracts\ToolRegistryInterface $tool_registry
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

	/**
	 * Registers the STRAP facade tool for WordPress abilities.
	 *
	 * Guarded by `function_exists('wp_get_abilities')` so the agent starts
	 * cleanly on WordPress versions before 6.9 where the Abilities API does
	 * not exist. Registers a single AbilityStrapTool that provides list,
	 * describe, and execute actions for all WordPress abilities.
	 *
	 * @since n.e.x.t
	 *
	 * @param \Automattic\WpAiAgent\Core\Contracts\ToolRegistryInterface $tool_registry Tool registry to register abilities into.
	 */
	private static function discoverAbilities(
		\Automattic\WpAiAgent\Core\Contracts\ToolRegistryInterface $tool_registry,
		\Automattic\WpAiAgent\Core\Contracts\ConfirmationHandlerInterface $confirmation_handler
	): void {
		if (!function_exists('wp_get_abilities')) {
			return;
		}

		$tool_registry->register(new AbilityStrapTool(
			confirmation_handler: $confirmation_handler
		));
		\WP_CLI::debug('[Abilities] STRAP facade registered');

		$tool_registry->register(new UserContextTool());
		\WP_CLI::debug('[Abilities] User context tool registered');
	}
}
