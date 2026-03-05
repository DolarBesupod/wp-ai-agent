<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\AiClient;

use Automattic\WpAiAgent\Core\Contracts\AiAdapterInterface;
use Automattic\WpAiAgent\Core\Credential\AuthMode;
use Automattic\WpAiAgent\Core\Exceptions\AiClientException;
use WordPress\AiClient\Providers\ProviderRegistry;

/**
 * Interface for AI client adapters in the Integration layer.
 *
 * This interface extends the core AiAdapterInterface with additional
 * methods specific to the php-ai-client integration, providing access
 * to the underlying provider registry and configuration options.
 *
 * @since 0.1.0
 */
interface AiClientAdapterInterface extends AiAdapterInterface
{
	/**
	 * Returns the provider registry used by this adapter.
	 *
	 * @since 0.1.0
	 *
	 * @return ProviderRegistry The configured provider registry.
	 */
	public function getProviderRegistry(): ProviderRegistry;

	/**
	 * Checks if the adapter is properly configured.
	 *
	 * Verifies that the API key is set and the provider is available.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True if properly configured, false otherwise.
	 */
	public function isConfigured(): bool;

	/**
	 * Returns the provider ID being used.
	 *
	 * @since 0.1.0
	 *
	 * @return string The provider ID (e.g., 'anthropic', 'openai', 'google').
	 */
	public function getProviderId(): string;

	/**
	 * Switches the active provider, registering it if not already present.
	 *
	 * Registers the new provider on the existing ProviderRegistry,
	 * creates the appropriate authentication, and updates internal state.
	 *
	 * @since 0.1.0
	 *
	 * @param string   $provider_id The provider identifier (e.g., 'openai', 'google', 'anthropic').
	 * @param string $api_key The API key for the new provider.
	 * @param AuthMode $auth_mode   Authentication mode (defaults to API_KEY).
	 *
	 * @return void
	 *
	 * @throws AiClientException If the provider is not supported or registration fails.
	 */
	public function switchProvider(string $provider_id, string $api_key, AuthMode $auth_mode = AuthMode::API_KEY): void;
}
