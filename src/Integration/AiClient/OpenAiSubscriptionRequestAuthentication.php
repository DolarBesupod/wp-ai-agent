<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\AiClient;

use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;

/**
 * Request authentication for OpenAI subscription credentials.
 *
 * Subscription credentials (Codex CLI OAuth tokens obtained via `codex login`)
 * authenticate against the ChatGPT backend API, not the standard OpenAI
 * platform API. This class:
 *
 * 1. Rewrites the request URL from `api.openai.com/v1` to
 *    `chatgpt.com/backend-api/codex`.
 * 2. Adds the `Authorization: Bearer` header with the OAuth access token.
 * 3. Adds the `ChatGPT-Account-ID` header extracted from the JWT payload.
 *
 * @since 0.1.0
 */
final class OpenAiSubscriptionRequestAuthentication extends ApiKeyRequestAuthentication
{
	/**
	 * The standard OpenAI API base URL that must be rewritten.
	 *
	 * @var string
	 */
	private const OPENAI_API_BASE = 'https://api.openai.com/v1';

	/**
	 * The ChatGPT backend API base URL for subscription tokens.
	 *
	 * @var string
	 */
	private const CHATGPT_API_BASE = 'https://chatgpt.com/backend-api/codex';

	/**
	 * JWT claim path containing the ChatGPT account ID.
	 *
	 * @var string
	 */
	private const JWT_AUTH_CLAIM = 'https://api.openai.com/auth';

	/**
	 * {@inheritDoc}
	 *
	 * Rewrites the request URL to the ChatGPT backend API, adds the
	 * subscription token as a Bearer header, and adds the ChatGPT
	 * account ID header extracted from the JWT payload.
	 *
	 * @since 0.1.0
	 */
	public function authenticateRequest(Request $request): Request
	{
		$uri = $request->getUri();
		$rewritten_uri = str_replace(self::OPENAI_API_BASE, self::CHATGPT_API_BASE, $uri);

		// Rebuild the request with the rewritten URI since the Request
		// class does not expose a withUri() method.
		$rewritten_request = new Request(
			$request->getMethod(),
			$rewritten_uri,
			$request->getHeaders(),
			$request->getData(),
			$request->getOptions()
		);

		$rewritten_request = $rewritten_request->withHeader('Authorization', 'Bearer ' . $this->apiKey);

		$account_id = $this->extractAccountIdFromJwt();
		if ($account_id !== null) {
			$rewritten_request = $rewritten_request->withHeader('ChatGPT-Account-ID', $account_id);
		}

		return $rewritten_request;
	}

	/**
	 * Extracts the ChatGPT account ID from the JWT access token payload.
	 *
	 * The Codex CLI OAuth access token is a JWT whose payload contains:
	 * `{"https://api.openai.com/auth": {"chatgpt_account_id": "..."}}`
	 *
	 * @since 0.1.0
	 *
	 * @return string|null The account ID, or null if extraction fails.
	 */
	private function extractAccountIdFromJwt(): ?string
	{
		$parts = explode('.', $this->apiKey);
		if (count($parts) !== 3) {
			return null;
		}

		// Base64url decode the payload segment.
		$payload_json = base64_decode(strtr($parts[1], '-_', '+/'), true);
		if ($payload_json === false) {
			return null;
		}

		$payload = json_decode($payload_json, true);
		if (!is_array($payload)) {
			return null;
		}

		$auth_claim = $payload[self::JWT_AUTH_CLAIM] ?? null;
		if (!is_array($auth_claim)) {
			return null;
		}

		$account_id = $auth_claim['chatgpt_account_id'] ?? null;

		return is_string($account_id) && $account_id !== '' ? $account_id : null;
	}
}
