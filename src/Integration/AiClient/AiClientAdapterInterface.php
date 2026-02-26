<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\AiClient;

use WpAiAgent\Core\Contracts\AiAdapterInterface;
use WordPress\AiClient\Providers\ProviderRegistry;

/**
 * Interface for AI client adapters in the Integration layer.
 *
 * This interface extends the core AiAdapterInterface with additional
 * methods specific to the php-ai-client integration, providing access
 * to the underlying provider registry and configuration options.
 *
 * @since n.e.x.t
 */
interface AiClientAdapterInterface extends AiAdapterInterface
{
	/**
	 * Returns the provider registry used by this adapter.
	 *
	 * @return ProviderRegistry The configured provider registry.
	 */
	public function getProviderRegistry(): ProviderRegistry;

	/**
	 * Checks if the adapter is properly configured.
	 *
	 * Verifies that the API key is set and the provider is available.
	 *
	 * @return bool True if properly configured, false otherwise.
	 */
	public function isConfigured(): bool;

	/**
	 * Returns the provider ID being used.
	 *
	 * @return string The provider ID (e.g., 'anthropic').
	 */
	public function getProviderId(): string;
}
