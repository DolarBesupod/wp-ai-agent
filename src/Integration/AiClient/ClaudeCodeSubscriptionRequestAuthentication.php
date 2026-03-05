<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\AiClient;

use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;

/**
 * Request authentication for Claude Code subscription credentials.
 *
 * Claude Code setup-tokens use Bearer authentication against Anthropic's
 * messages endpoint with additional identity/beta headers expected by the
 * Claude Code transport contract.
 *
 * @since 0.1.0
 */
final class ClaudeCodeSubscriptionRequestAuthentication extends ApiKeyRequestAuthentication
{
	/**
	 * Anthropic API version header value.
	 */
	private const ANTHROPIC_API_VERSION = '2023-06-01';

	/**
	 * Required Anthropic beta feature flags for Claude Code transport.
	 */
	private const ANTHROPIC_BETA =
		'claude-code-20250219,oauth-2025-04-20,fine-grained-tool-streaming-2025-05-14';

	/**
	 * Claude Code user-agent identity.
	 */
	private const USER_AGENT = 'claude-cli/2.1.2 (external, cli)';

	/**
	 * {@inheritDoc}
	 */
	public function authenticateRequest(Request $request): Request
	{
		$request = $request->withHeader('Authorization', 'Bearer ' . $this->apiKey);
		$request = $request->withHeader('anthropic-version', self::ANTHROPIC_API_VERSION);
		$request = $request->withHeader('accept', 'application/json');
		$request = $request->withHeader('anthropic-dangerous-direct-browser-access', 'true');
		$request = $request->withHeader('user-agent', self::USER_AGENT);
		$request = $request->withHeader('x-app', 'cli');

		return $request->withHeader('anthropic-beta', self::ANTHROPIC_BETA);
	}
}
