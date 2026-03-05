<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Exceptions;

/**
 * Exception thrown when a requested credential is not found in the repository.
 *
 * @since 0.1.0
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
	 * @since 0.1.0
	 */
	public static function forProvider(string $provider): self
	{
		return new self("Credential not found for provider: {$provider}");
	}
}
