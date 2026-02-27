<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\AiClient;

use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;

/**
 * Request authentication for OpenAI subscription credentials.
 *
 * Subscription credentials (e.g. Codex CLI OAuth tokens) are sent as
 * a standard Bearer token on OpenAI API requests.
 *
 * @since n.e.x.t
 */
final class OpenAiSubscriptionRequestAuthentication extends ApiKeyRequestAuthentication
{
	/**
	 * {@inheritDoc}
	 */
	public function authenticateRequest(Request $request): Request
	{
		return $request->withHeader('Authorization', 'Bearer ' . $this->apiKey);
	}
}
