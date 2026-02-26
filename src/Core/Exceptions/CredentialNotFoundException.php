<?php

declare(strict_types=1);

namespace WpAiAgent\Core\Exceptions;

/**
 * Exception thrown when a requested credential is not found in the repository.
 *
 * @since n.e.x.t
 */
final class CredentialNotFoundException extends \RuntimeException
{
	/**
	 * Creates a CredentialNotFoundException for the given provider name.
	 *
	 * @param string $provider The name of the provider whose credential was not found.
	 *
	 * @return self
	 *
	 * @since n.e.x.t
	 */
	public static function forProvider(string $provider): self
	{
		return new self("Credential not found for provider: {$provider}");
	}
}
