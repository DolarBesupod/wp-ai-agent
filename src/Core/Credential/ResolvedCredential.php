<?php

declare(strict_types=1);

namespace WpAiAgent\Core\Credential;

/**
 * Immutable DTO returned by the credential resolver.
 *
 * Contains the resolved secret, its authentication mode, and the source
 * from which it was resolved (constant, environment variable, or database).
 *
 * @since n.e.x.t
 */
final class ResolvedCredential
{
	/**
	 * Creates a new ResolvedCredential instance.
	 *
	 * @param string   $secret    The resolved secret value.
	 * @param AuthMode $auth_mode The authentication mode.
	 * @param string   $source    The resolution source: 'constant', 'env', or 'db'.
	 *
	 * @since n.e.x.t
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
	 * @since n.e.x.t
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
	 * @since n.e.x.t
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
	 * @since n.e.x.t
	 */
	public function getSource(): string
	{
		return $this->source;
	}
}
