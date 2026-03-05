<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\AiClient;

use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;

/**
 * Request authentication for Anthropic subscription credentials.
 *
 * Subscription credentials are sent as a Bearer token while preserving
 * Anthropic API version negotiation header.
 *
 * @since 0.1.0
 */
final class AnthropicSubscriptionRequestAuthentication extends ApiKeyRequestAuthentication
{
	/**
	 * Anthropic API version header value.
	 */
	private const ANTHROPIC_API_VERSION = '2023-06-01';

	/**
	 * {@inheritDoc}
	 */
	public function authenticateRequest(Request $request): Request
	{
		$request = $request->withHeader('anthropic-version', self::ANTHROPIC_API_VERSION);

		return $request->withHeader('Authorization', 'Bearer ' . $this->apiKey);
	}
}
