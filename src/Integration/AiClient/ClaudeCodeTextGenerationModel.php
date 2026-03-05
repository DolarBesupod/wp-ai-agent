<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\AiClient;

use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AnthropicAiProvider\Authentication\AnthropicApiKeyRequestAuthentication;
use WordPress\AnthropicAiProvider\Models\AnthropicTextGenerationModel;

/**
 * Anthropic text model variant for the dedicated `claudeCode` provider path.
 *
 * The upstream Anthropic model wraps any ApiKeyRequestAuthentication into
 * AnthropicApiKeyRequestAuthentication. For Claude Code subscription we need
 * to preserve the dedicated auth implementation and its custom headers.
 *
 * @since 0.1.0
 */
final class ClaudeCodeTextGenerationModel extends AnthropicTextGenerationModel
{
	/**
	 * {@inheritDoc}
	 */
	public function getRequestAuthentication(): RequestAuthenticationInterface
	{
		$request_authentication = $this->getRawRequestAuthentication();

		if ($request_authentication instanceof ClaudeCodeSubscriptionRequestAuthentication) {
			return $request_authentication;
		}

		if (!$request_authentication instanceof ApiKeyRequestAuthentication) {
			return $request_authentication;
		}

		return new AnthropicApiKeyRequestAuthentication($request_authentication->getApiKey());
	}

	/**
	 * Reads the raw request authentication bound by ProviderRegistry.
	 *
	 * @return RequestAuthenticationInterface
	 */
	private function getRawRequestAuthentication(): RequestAuthenticationInterface
	{
		$raw_getter = \Closure::bind(
			function (): ?RequestAuthenticationInterface {
				return $this->requestAuthentication;
			},
			$this,
			\WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel::class
		);

		$request_authentication = $raw_getter();

		if ($request_authentication === null) {
			throw new RuntimeException(
				'RequestAuthenticationInterface instance not set. ' .
				'Make sure you use the AiClient class for all requests.'
			);
		}

		return $request_authentication;
	}
}
