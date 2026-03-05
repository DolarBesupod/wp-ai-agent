<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\AiClient;

use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AnthropicAiProvider\Provider\AnthropicProvider;

/**
 * Anthropic-backed provider wrapper with a dedicated `claudeCode` ID.
 *
 * This allows routing Claude Code subscription auth separately from the
 * standard `anthropic` provider while reusing Anthropic model metadata and
 * transport behavior.
 *
 * @since n.e.x.t
 */
final class ClaudeCodeProvider extends AnthropicProvider
{
	/**
	 * {@inheritDoc}
	 */
	protected static function createProviderMetadata(): ProviderMetadata
	{
		return new ProviderMetadata(
			'claudeCode',
			'Claude Code',
			ProviderTypeEnum::cloud(),
			'https://claude.ai/settings/profile',
			RequestAuthenticationMethod::apiKey()
		);
	}
}
