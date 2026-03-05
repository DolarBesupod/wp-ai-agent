<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Integration\WpCli;

use Automattic\Automattic\WpAiAgent\Core\Contracts\CredentialRepositoryInterface;
use Automattic\Automattic\WpAiAgent\Core\Credential\AuthMode;
use Automattic\Automattic\WpAiAgent\Core\Credential\Credential;
use Automattic\Automattic\WpAiAgent\Core\Exceptions\CredentialNotFoundException;

/**
 * WordPress options-based credential repository.
 *
 * Persists credentials as individual WordPress options with autoload=false.
 * An index option tracks all stored provider names.
 *
 * Option naming:
 * - Index:          wp_ai_agent_credentials
 * - Per credential: wp_ai_agent_credential_{provider}
 *
 * @since n.e.x.t
 */
final class WpOptionsCredentialRepository implements CredentialRepositoryInterface
{
	/**
	 * WordPress option name for the provider name index.
	 */
	private const INDEX_OPTION = 'wp_ai_agent_credentials';

	/**
	 * Prefix for per-credential WordPress options.
	 */
	private const OPTION_PREFIX = 'wp_ai_agent_credential_';

	/**
	 * Retrieves a credential for the given provider.
	 *
	 * @param string $provider The provider name (e.g. 'anthropic').
	 *
	 * @return Credential The stored credential.
	 *
	 * @throws CredentialNotFoundException If no credential exists for the provider.
	 *
	 * @since n.e.x.t
	 */
	public function getCredential(string $provider): Credential
	{
		$option_key = self::OPTION_PREFIX . $provider;
		$value = \get_option($option_key, false);

		if (false === $value) {
			throw CredentialNotFoundException::forProvider($provider);
		}

		$data = json_decode(is_string($value) ? $value : '', true);

		if (!is_array($data)) {
			throw CredentialNotFoundException::forProvider($provider);
		}

		try {
			return Credential::fromArray($data);
		} catch (\InvalidArgumentException) {
			throw CredentialNotFoundException::forProvider($provider);
		}
	}

	/**
	 * Stores a credential for the given provider.
	 *
	 * Creates a new credential or overwrites an existing one. On first write,
	 * both `created_at` and `updated_at` are set to the current time. On
	 * overwrite, only `updated_at` is refreshed while `created_at` is preserved.
	 *
	 * @param string               $provider  The provider name.
	 * @param AuthMode             $auth_mode The authentication mode.
	 * @param string               $secret    The secret value.
	 * @param array<string, mixed> $meta      Optional metadata.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the secret or provider is empty.
	 *
	 * @since n.e.x.t
	 */
	public function setCredential(string $provider, AuthMode $auth_mode, string $secret, array $meta = []): void
	{
		if ('' === $provider) {
			throw new \InvalidArgumentException('Provider name must not be empty.');
		}

		if ('' === $secret) {
			throw new \InvalidArgumentException('Secret must not be empty.');
		}

		$now = new \DateTimeImmutable();
		$created_at = $now;

		$option_key = self::OPTION_PREFIX . $provider;
		$existing = \get_option($option_key, false);

		if (false !== $existing) {
			$existing_data = json_decode(is_string($existing) ? $existing : '', true);
			$has_created_at = is_array($existing_data)
				&& isset($existing_data['created_at'])
				&& is_string($existing_data['created_at']);

			if ($has_created_at) {
				$parsed = \DateTimeImmutable::createFromFormat(
					\DateTimeInterface::ATOM,
					$existing_data['created_at']
				);

				if ($parsed !== false) {
					$created_at = $parsed;
				}
			}
		}

		$credential = new Credential($provider, $auth_mode, $secret, $created_at, $now, $meta);

		\update_option($option_key, \wp_json_encode($credential->toArray()), false);

		$index = $this->loadIndex();

		if (!in_array($provider, $index, true)) {
			$index[] = $provider;
			\update_option(self::INDEX_OPTION, \wp_json_encode($index), false);
		}
	}

	/**
	 * Deletes a credential for the given provider.
	 *
	 * Removes the per-credential option and updates the index to exclude
	 * the provider name.
	 *
	 * @param string $provider The provider name.
	 *
	 * @return bool True if the credential existed and was deleted, false otherwise.
	 *
	 * @since n.e.x.t
	 */
	public function deleteCredential(string $provider): bool
	{
		$option_key = self::OPTION_PREFIX . $provider;

		$existed = \get_option($option_key, false) !== false;

		\delete_option($option_key);

		$index = $this->loadIndex();
		$filtered = array_values(array_filter($index, static function (string $entry) use ($provider): bool {
			return $entry !== $provider;
		}));

		\update_option(self::INDEX_OPTION, \wp_json_encode($filtered), false);

		return $existed;
	}

	/**
	 * Checks whether a credential exists for the given provider.
	 *
	 * @param string $provider The provider name.
	 *
	 * @return bool True if a credential is stored for the provider.
	 *
	 * @since n.e.x.t
	 */
	public function hasCredential(string $provider): bool
	{
		return \get_option(self::OPTION_PREFIX . $provider, false) !== false;
	}

	/**
	 * Returns the names of all providers that have stored credentials.
	 *
	 * Returns an empty array when the index option does not exist.
	 *
	 * @return array<int, string> An array of provider names.
	 *
	 * @since n.e.x.t
	 */
	public function listProviders(): array
	{
		$raw = \get_option(self::INDEX_OPTION, null);

		if (null === $raw) {
			return [];
		}

		$index = json_decode(is_string($raw) ? $raw : '[]', true);

		if (!is_array($index)) {
			return [];
		}

		return array_values(array_filter($index, 'is_string'));
	}

	/**
	 * Loads the current provider name index from WordPress options.
	 *
	 * @return string[] The list of stored provider names.
	 */
	private function loadIndex(): array
	{
		$raw = \get_option(self::INDEX_OPTION, null);

		if (null === $raw) {
			return [];
		}

		$index = json_decode(is_string($raw) ? $raw : '[]', true);

		if (!is_array($index)) {
			return [];
		}

		return array_values(array_filter($index, 'is_string'));
	}
}
