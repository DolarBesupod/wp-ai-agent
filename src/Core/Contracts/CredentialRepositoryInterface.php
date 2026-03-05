<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Contracts;

use Automattic\WpAiAgent\Core\Credential\AuthMode;
use Automattic\WpAiAgent\Core\Credential\Credential;
use Automattic\WpAiAgent\Core\Exceptions\CredentialNotFoundException;

/**
 * Interface for persisting and retrieving credentials.
 *
 * The credential repository abstracts the storage mechanism for credentials,
 * allowing implementations to use WordPress options, database tables, or
 * in-memory storage without affecting core credential logic.
 *
 * @since 0.1.0
 */
interface CredentialRepositoryInterface
{
	/**
	 * Retrieves a credential for the given provider.
	 *
	 * @param string $provider The provider name (e.g. 'anthropic').
	 *
	 * @return Credential The stored credential.
	 *
	 * @throws CredentialNotFoundException If no credential exists for the provider.
	 *
	 * @since 0.1.0
	 */
	public function getCredential(string $provider): Credential;

	/**
	 * Stores a credential for the given provider.
	 *
	 * Creates a new credential or overwrites an existing one. On first write,
	 * both `created_at` and `updated_at` are set to the current time. On
	 * overwrite, only `updated_at` is refreshed.
	 *
	 * @param string $provider The provider name.
	 * @param AuthMode             $auth_mode The authentication mode.
	 * @param string $secret The secret value.
	 * @param array<string, mixed> $meta      Optional metadata.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function setCredential(string $provider, AuthMode $auth_mode, string $secret, array $meta = []): void;

	/**
	 * Deletes a credential for the given provider.
	 *
	 * @param string $provider The provider name.
	 *
	 * @return bool True if the credential existed and was deleted, false otherwise.
	 *
	 * @since 0.1.0
	 */
	public function deleteCredential(string $provider): bool;

	/**
	 * Checks whether a credential exists for the given provider.
	 *
	 * @param string $provider The provider name.
	 *
	 * @return bool True if a credential is stored for the provider.
	 *
	 * @since 0.1.0
	 */
	public function hasCredential(string $provider): bool;

	/**
	 * Returns the names of all providers that have stored credentials.
	 *
	 * @return array<int, string> An array of provider names.
	 *
	 * @since 0.1.0
	 */
	public function listProviders(): array;
}
