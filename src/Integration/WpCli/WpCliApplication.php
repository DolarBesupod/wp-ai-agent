<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\WpCli;

use WpAiAgent\Core\Agent\Agent;
use WpAiAgent\Integration\Mcp\McpClientManager;

/**
 * WP-CLI application stub.
 *
 * Placeholder class to satisfy type requirements while the full implementation
 * is completed in a subsequent task (T1.3). Stores all wired dependencies as
 * public readonly properties so WpCliBootstrap can construct it and PHPStan
 * is satisfied at level 8.
 *
 * @since n.e.x.t
 */
class WpCliApplication
{
	/**
	 * Creates a new WpCliApplication.
	 *
	 * @param WpConfigConfiguration      $config             The configuration.
	 * @param Agent                      $agent              The agent.
	 * @param WpCliOutputHandler         $output             The output handler.
	 * @param WpCliConfirmationHandler   $confirmation       The confirmation handler.
	 * @param WpOptionsSessionRepository $session_repo       The session repository.
	 * @param McpClientManager|null      $mcp_client_manager MCP client manager (kept alive to prevent GC).
	 */
	public function __construct(
		public readonly WpConfigConfiguration $config,
		public readonly Agent $agent,
		public readonly WpCliOutputHandler $output,
		public readonly WpCliConfirmationHandler $confirmation,
		public readonly WpOptionsSessionRepository $session_repo,
		public readonly ?McpClientManager $mcp_client_manager = null
	) {
	}
}
