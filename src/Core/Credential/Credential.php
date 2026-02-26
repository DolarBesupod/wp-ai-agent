<?php

declare(strict_types=1);

namespace WpAiAgent\Core\Credential;

/**
 * Immutable value object representing a stored credential.
 *
 * A credential binds a provider name to an authentication mode and secret,
 * along with timestamps and an optional metadata bag for future extensibility.
 *
 * @since n.e.x.t
 */
final class Credential
{
	/**
	 * Creates a new Credential instance.
	 *
	 * @param string             $provider   The provider name (e.g. 'anthropic').
	 * @param AuthMode           $auth_mode  The authentication mode.
	 * @param string             $secret     The secret value.
	 * @param \DateTimeImmutable $created_at When the credential was first stored.
	 * @param \DateTimeImmutable $updated_at When the credential was last updated.
	 * @param array<string, mixed> $meta     Optional metadata bag.
	 *
	 * @since n.e.x.t
	 */
	public function __construct(
		private readonly string $provider,
		private readonly AuthMode $auth_mode,
		private readonly string $secret,
		private readonly \DateTimeImmutable $created_at,
		private readonly \DateTimeImmutable $updated_at,
		private readonly array $meta = [],
	) {
	}

	/**
	 * Returns the provider name.
	 *
	 * @return string
	 *
	 * @since n.e.x.t
	 */
	public function getProvider(): string
	{
		return $this->provider;
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
	 * Returns the secret value in plain text.
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
	 * Returns the creation timestamp.
	 *
	 * @return \DateTimeImmutable
	 *
	 * @since n.e.x.t
	 */
	public function getCreatedAt(): \DateTimeImmutable
	{
		return $this->created_at;
	}

	/**
	 * Returns the last-updated timestamp.
	 *
	 * @return \DateTimeImmutable
	 *
	 * @since n.e.x.t
	 */
	public function getUpdatedAt(): \DateTimeImmutable
	{
		return $this->updated_at;
	}

	/**
	 * Returns the metadata bag.
	 *
	 * @return array<string, mixed>
	 *
	 * @since n.e.x.t
	 */
	public function getMeta(): array
	{
		return $this->meta;
	}

	/**
	 * Returns a masked version of the secret for display purposes.
	 *
	 * Shows the first 8 characters followed by '****'. For secrets shorter
	 * than 8 characters, the full secret is shown followed by '****'.
	 *
	 * @return string
	 *
	 * @since n.e.x.t
	 */
	public function getMaskedSecret(): string
	{
		return substr($this->secret, 0, 8) . '****';
	}

	/**
	 * Serializes the credential to an array.
	 *
	 * The secret is included in plain text. Masking is a display concern only.
	 *
	 * @return array{
	 *     provider: string,
	 *     auth_mode: string,
	 *     secret: string,
	 *     created_at: string,
	 *     updated_at: string,
	 *     meta: array<string, mixed>
	 * }
	 *
	 * @since n.e.x.t
	 */
	public function toArray(): array
	{
		return [
			'provider'   => $this->provider,
			'auth_mode'  => $this->auth_mode->value,
			'secret'     => $this->secret,
			'created_at' => $this->created_at->format(\DateTimeInterface::ATOM),
			'updated_at' => $this->updated_at->format(\DateTimeInterface::ATOM),
			'meta'       => $this->meta,
		];
	}

	/**
	 * Reconstructs a Credential from a serialized array.
	 *
	 * Validates that all required fields are present and correctly typed.
	 *
	 * @param array<string, mixed> $data The serialized credential data.
	 *
	 * @return self
	 *
	 * @throws \InvalidArgumentException If required fields are missing or invalid.
	 *
	 * @since n.e.x.t
	 */
	public static function fromArray(array $data): self
	{
		if (!isset($data['provider']) || !is_string($data['provider'])) {
			throw new \InvalidArgumentException('Missing or invalid "provider" field.');
		}

		if (!isset($data['auth_mode']) || !is_string($data['auth_mode'])) {
			throw new \InvalidArgumentException('Missing or invalid "auth_mode" field.');
		}

		try {
			$auth_mode = AuthMode::fromString($data['auth_mode']);
		} catch (\ValueError $e) {
			throw new \InvalidArgumentException('Invalid "auth_mode" value: ' . $data['auth_mode'], 0, $e);
		}

		if (!isset($data['secret']) || !is_string($data['secret'])) {
			throw new \InvalidArgumentException('Missing or invalid "secret" field.');
		}

		if (!isset($data['created_at']) || !is_string($data['created_at'])) {
			throw new \InvalidArgumentException('Missing or invalid "created_at" field.');
		}

		$created_at = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $data['created_at']);
		if ($created_at === false) {
			throw new \InvalidArgumentException('Invalid "created_at" date format.');
		}

		if (!isset($data['updated_at']) || !is_string($data['updated_at'])) {
			throw new \InvalidArgumentException('Missing or invalid "updated_at" field.');
		}

		$updated_at = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $data['updated_at']);
		if ($updated_at === false) {
			throw new \InvalidArgumentException('Invalid "updated_at" date format.');
		}

		$meta = [];
		if (isset($data['meta']) && is_array($data['meta'])) {
			$meta = $data['meta'];
		}

		return new self($data['provider'], $auth_mode, $data['secret'], $created_at, $updated_at, $meta);
	}
}
