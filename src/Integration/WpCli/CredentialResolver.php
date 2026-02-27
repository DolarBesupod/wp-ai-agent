<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\WpCli;

use WpAiAgent\Core\Contracts\CredentialRepositoryInterface;
use WpAiAgent\Core\Credential\AuthMode;
use WpAiAgent\Core\Credential\ResolvedCredential;
use WpAiAgent\Core\Exceptions\ConfigurationException;
use WpAiAgent\Core\Exceptions\CredentialNotFoundException;

/**
 * Resolves credentials for AI providers using a priority chain.
 *
 * Resolution order (highest to lowest):
 * 1. PHP constant (e.g. ANTHROPIC_API_KEY)
 * 2. Environment variable (e.g. ANTHROPIC_API_KEY)
 * 3. Subscription constant/env (e.g. ANTHROPIC_SUBSCRIPTION_KEY)
 * 4. Database credential via CredentialRepositoryInterface
 * 5. Throws ConfigurationException if none found
 *
 * @since n.e.x.t
 */
final class CredentialResolver
{
	/**
	 * Maps provider names to their corresponding PHP constant names.
	 *
	 * @var array<string, string>
	 */
	private const PROVIDER_CONSTANTS = [
		'anthropic' => 'ANTHROPIC_API_KEY',
		'openai'    => 'OPENAI_API_KEY',
		'google'    => 'GOOGLE_API_KEY',
	];

	/**
	 * Maps provider names to subscription credential constants.
	 *
	 * @var array<string, string>
	 */
	private const PROVIDER_SUBSCRIPTION_CONSTANTS = [
		'anthropic' => 'ANTHROPIC_SUBSCRIPTION_KEY',
		'openai'    => 'OPENAI_SUBSCRIPTION_KEY',
	];

	/**
	 * The credential repository for DB-backed credentials.
	 *
	 * @var CredentialRepositoryInterface
	 */
	private CredentialRepositoryInterface $repository;

	/**
	 * Custom environment variable getter for testing.
	 *
	 * @var callable
	 */
	private $env_getter;

	/**
	 * Custom constant checker for testing.
	 *
	 * Accepts a constant name and returns its value (string) or false if not defined.
	 *
	 * @var callable
	 */
	private $constant_checker;

	/**
	 * Creates a new CredentialResolver instance.
	 *
	 * @param CredentialRepositoryInterface $repository       The credential repository.
	 * @param callable|null                $env_getter        Optional env getter for testing. Defaults to getenv().
	 * @param callable|null                $constant_checker  Optional constant checker for testing.
	 *                                                        Receives a constant name and returns its value
	 *                                                        (string) or false if not defined. Defaults to
	 *                                                        checking via defined()/constant().
	 *
	 * @since n.e.x.t
	 */
	public function __construct(
		CredentialRepositoryInterface $repository,
		?callable $env_getter = null,
		?callable $constant_checker = null
	) {
		$this->repository = $repository;
		$this->env_getter = $env_getter ?? 'getenv';
		$this->constant_checker = $constant_checker ?? static function (string $name): string|false {
			if (defined($name) && is_string(constant($name)) && constant($name) !== '') {
				return constant($name);
			}

			return false;
		};
	}

	/**
	 * Resolves the credential for the given provider.
	 *
	 * Checks PHP constant, environment variable, and database storage in
	 * priority order. Returns a ResolvedCredential with the secret, auth mode,
	 * and source identifier.
	 *
	 * @param string $provider The provider name (e.g. 'anthropic').
	 *
	 * @return ResolvedCredential The resolved credential.
	 *
	 * @throws ConfigurationException If no credential is found from any source.
	 *
	 * @since n.e.x.t
	 */
	public function resolve(string $provider): ResolvedCredential
	{
		$constant_name = self::PROVIDER_CONSTANTS[$provider] ?? null;
		$subscription_constant_name = self::PROVIDER_SUBSCRIPTION_CONSTANTS[$provider] ?? null;

		// 1. PHP constant (highest priority).
		if ($constant_name !== null) {
			$constant_value = ($this->constant_checker)($constant_name);

			if ($constant_value !== false && $constant_value !== '') {
				return new ResolvedCredential($constant_value, AuthMode::API_KEY, 'constant');
			}
		}

		// 2. Environment variable.
		if ($constant_name !== null) {
			$env_value = ($this->env_getter)($constant_name);

			if ($env_value !== false && $env_value !== '') {
				return new ResolvedCredential($env_value, AuthMode::API_KEY, 'env');
			}
		}

		// 3. Subscription constant or environment variable.
		if ($subscription_constant_name !== null) {
			$subscription_constant = ($this->constant_checker)($subscription_constant_name);

			if ($subscription_constant !== false && $subscription_constant !== '') {
				return new ResolvedCredential($subscription_constant, AuthMode::SUBSCRIPTION, 'constant');
			}

			$subscription_env = ($this->env_getter)($subscription_constant_name);

			if ($subscription_env !== false && $subscription_env !== '') {
				return new ResolvedCredential($subscription_env, AuthMode::SUBSCRIPTION, 'env');
			}
		}

		// 4. Database credential.
		try {
			$credential = $this->repository->getCredential($provider);

			return new ResolvedCredential(
				$credential->getSecret(),
				$credential->getAuthMode(),
				'db'
			);
		} catch (CredentialNotFoundException) {
			// Fall through to exception.
		}

		// 5. No credential found.
		$key_name = $constant_name ?? strtoupper($provider) . '_API_KEY';
		$subscription_key_name = $subscription_constant_name ?? strtoupper($provider) . '_SUBSCRIPTION_KEY';
		throw new ConfigurationException(
			sprintf(
				'No API key found for provider "%s". '
				. 'Define %s or %s in wp-config.php, set them as environment variables, '
				. 'or run: wp agent auth set --provider=%s',
				$provider,
				$key_name,
				$subscription_key_name,
				$provider
			)
		);
	}

	/**
	 * Returns the resolution status for all known providers.
	 *
	 * Each entry contains the provider name, auth mode, source, and whether
	 * a credential was found. Useful for the `wp agent auth status` command.
	 *
	 * @return array<int, array{provider: string, auth_mode: string, source: string, available: bool}>
	 *
	 * @since n.e.x.t
	 */
	public function getStatus(): array
	{
		$providers = array_unique(array_merge(
			array_keys(self::PROVIDER_CONSTANTS),
			array_keys(self::PROVIDER_SUBSCRIPTION_CONSTANTS)
		));

		// Merge DB providers that may not be in the constant map.
		$db_providers = $this->repository->listProviders();
		foreach ($db_providers as $db_provider) {
			if (!in_array($db_provider, $providers, true)) {
				$providers[] = $db_provider;
			}
		}

		$status = [];

		foreach ($providers as $provider) {
			try {
				$resolved = $this->resolve($provider);
				$status[] = [
					'provider'  => $provider,
					'auth_mode' => $resolved->getAuthMode()->value,
					'source'    => $resolved->getSource(),
					'available' => true,
				];
			} catch (ConfigurationException) {
				$status[] = [
					'provider'  => $provider,
					'auth_mode' => '',
					'source'    => 'none',
					'available' => false,
				];
			}
		}

		return $status;
	}
}
