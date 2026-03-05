<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Credential;

/**
 * Immutable DTO returned by the credential resolver.
 *
 * Contains the resolved secret, its authentication mode, and the source
 * from which it was resolved (constant, environment variable, or database).
 *
 * @since 0.1.0
 */
final class ResolvedCredential
{
	/**
	 * Creates a new ResolvedCredential instance.
	 *
	 * @param string $secret The resolved secret value.
	 * @param AuthMode $auth_mode The authentication mode.
	 * @param string $source The resolution source: 'constant', 'env', or 'db'.
	 *
	 * @since 0.1.0
	 */
	public function __construct(
		private readonly string $secret,
		private readonly AuthMode $auth_mode,
		private readonly string $source,
	) {
	}

	/**
	 * Returns the resolved secret value.
	 *
	 * @return string
	 *
	 * @since 0.1.0
	 */
	public function getSecret(): string
	{
		return $this->secret;
	}

	/**
	 * Returns the authentication mode.
	 *
	 * @return AuthMode
	 *
	 * @since 0.1.0
	 */
	public function getAuthMode(): AuthMode
	{
		return $this->auth_mode;
	}

	/**
	 * Returns the source from which the credential was resolved.
	 *
	 * One of: 'constant', 'env', 'db'.
	 *
	 * @return string
	 *
	 * @since 0.1.0
	 */
	public function getSource(): string
	{
		return $this->source;
	}
}
