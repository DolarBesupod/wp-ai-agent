<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\WpCli;

use WpAiAgent\Core\Credential\AuthMode;
use WpAiAgent\Core\Exceptions\CredentialNotFoundException;

/**
 * WP-CLI command handler for the `wp agent auth` subcommand group.
 *
 * Exposes four subcommands:
 * - `wp agent auth set --provider=<provider> [--mode=<mode>]`   — prompts for secret, stores via repository.
 * - `wp agent auth get --provider=<provider>`                   — displays masked credential info.
 * - `wp agent auth delete --provider=<provider>`                — removes a credential.
 * - `wp agent auth status`                                      — shows table of all credentials.
 *
 * @since n.e.x.t
 */
final class WpCliAuthCommand
{
	/**
	 * The WordPress options credential repository.
	 *
	 * @var WpOptionsCredentialRepository
	 */
	private WpOptionsCredentialRepository $repository;

	/**
	 * The credential resolver.
	 *
	 * @var CredentialResolver
	 */
	private CredentialResolver $resolver;

	/**
	 * Callable that prompts the user for input.
	 *
	 * Signature: fn(string $question, string $default, string $marker, bool $hide): string
	 *
	 * @var callable
	 */
	private $prompt_callable;

	/**
	 * Creates a new WpCliAuthCommand instance.
	 *
	 * All parameters are optional so WP-CLI can instantiate this class without
	 * arguments when registering it via WP_CLI::add_command(). When omitted,
	 * concrete implementations are created automatically.
	 *
	 * @param WpOptionsCredentialRepository|null $repository      The credential repository.
	 * @param CredentialResolver|null            $resolver        The credential resolver.
	 * @param callable|null                      $prompt_callable Callable for prompting user input.
	 *                                                            Defaults to WP_CLI\Utils\prompt().
	 *
	 * @since n.e.x.t
	 */
	public function __construct(
		?WpOptionsCredentialRepository $repository = null,
		?CredentialResolver $resolver = null,
		?callable $prompt_callable = null
	) {
		$this->repository = $repository ?? new WpOptionsCredentialRepository();
		$this->resolver = $resolver ?? new CredentialResolver($this->repository);
		// WP_CLI\Utils\prompt() exists at runtime but is absent from the bundled stubs.
		$this->prompt_callable = $prompt_callable ?? static function (
			string $question,
			string $default = '',
			string $marker = ': ',
			bool $hide = false
		): string {
			// @phpstan-ignore function.notFound
			return \WP_CLI\Utils\prompt($question, $default, $marker, $hide);
		};
	}

	/**
	 * Sets a credential for a provider.
	 *
	 * Prompts for the secret with hidden input and stores it via the repository.
	 * If a credential already exists for the provider, it is overwritten.
	 *
	 * ## OPTIONS
	 *
	 * --provider=<provider>
	 * : The provider name (e.g. 'anthropic').
	 *
	 * [--mode=<mode>]
	 * : The authentication mode. Default: api_key.
	 * ---
	 * default: api_key
	 * options:
	 *   - api_key
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp agent auth set --provider=anthropic
	 *     wp agent auth set --provider=anthropic --mode=api_key
	 *
	 * @subcommand set
	 *
	 * @since n.e.x.t
	 *
	 * @param array<int, string>         $args       Positional arguments (unused).
	 * @param array<string, string|bool> $assoc_args Named arguments (--provider, --mode).
	 *
	 * @return void
	 */
	public function set(array $args, array $assoc_args): void
	{
		$provider = $this->extractProvider($assoc_args);

		if (null === $provider) {
			return;
		}

		$mode_string = isset($assoc_args['mode']) && is_string($assoc_args['mode'])
			? $assoc_args['mode']
			: 'api_key';

		try {
			$auth_mode = AuthMode::fromString($mode_string);
		} catch (\ValueError $e) {
			\WP_CLI::error(sprintf('Invalid auth mode: %s', $mode_string));
			return;
		}

		$secret = ($this->prompt_callable)('Enter secret', '', ': ', true);

		if ('' === $secret) {
			\WP_CLI::error('Secret must not be empty.');
			return;
		}

		try {
			$this->repository->setCredential($provider, $auth_mode, $secret);
			\WP_CLI::success(sprintf('Credential for provider "%s" saved successfully.', $provider));
		} catch (\InvalidArgumentException $e) {
			\WP_CLI::error(sprintf('Failed to save credential: %s', $e->getMessage()));
		}
	}

	/**
	 * Displays masked credential info for a provider.
	 *
	 * Shows the provider name, authentication mode, source, and masked secret.
	 * The raw secret is never displayed.
	 *
	 * ## OPTIONS
	 *
	 * --provider=<provider>
	 * : The provider name (e.g. 'anthropic').
	 *
	 * ## EXAMPLES
	 *
	 *     wp agent auth get --provider=anthropic
	 *
	 * @subcommand get
	 *
	 * @since n.e.x.t
	 *
	 * @param array<int, string>         $args       Positional arguments (unused).
	 * @param array<string, string|bool> $assoc_args Named arguments (--provider).
	 *
	 * @return void
	 */
	public function get(array $args, array $assoc_args): void
	{
		$provider = $this->extractProvider($assoc_args);

		if (null === $provider) {
			return;
		}

		if (!$this->repository->hasCredential($provider)) {
			\WP_CLI::error(sprintf('No credential found for provider "%s".', $provider));
			return;
		}

		try {
			$credential = $this->repository->getCredential($provider);
		} catch (CredentialNotFoundException $e) {
			\WP_CLI::error(sprintf('No credential found for provider "%s".', $provider));
			return;
		}

		\WP_CLI::log(sprintf('Provider:   %s', $credential->getProvider()));
		\WP_CLI::log(sprintf('Auth mode:  %s', $credential->getAuthMode()->value));
		\WP_CLI::log(sprintf('Secret:     %s', $credential->getMaskedSecret()));
		\WP_CLI::log(sprintf('Created at: %s', $credential->getCreatedAt()->format(\DateTimeInterface::ATOM)));
		\WP_CLI::log(sprintf('Updated at: %s', $credential->getUpdatedAt()->format(\DateTimeInterface::ATOM)));
	}

	/**
	 * Deletes a credential for a provider.
	 *
	 * Removes the stored credential from the database. If no credential exists
	 * for the provider, an error is displayed.
	 *
	 * ## OPTIONS
	 *
	 * --provider=<provider>
	 * : The provider name (e.g. 'anthropic').
	 *
	 * ## EXAMPLES
	 *
	 *     wp agent auth delete --provider=anthropic
	 *
	 * @subcommand delete
	 *
	 * @since n.e.x.t
	 *
	 * @param array<int, string>         $args       Positional arguments (unused).
	 * @param array<string, string|bool> $assoc_args Named arguments (--provider).
	 *
	 * @return void
	 */
	public function delete(array $args, array $assoc_args): void
	{
		$provider = $this->extractProvider($assoc_args);

		if (null === $provider) {
			return;
		}

		$deleted = $this->repository->deleteCredential($provider);

		if (!$deleted) {
			\WP_CLI::error(sprintf('No credential found for provider "%s".', $provider));
			return;
		}

		\WP_CLI::success(sprintf('Credential for provider "%s" deleted successfully.', $provider));
	}

	/**
	 * Displays the status of all known credentials.
	 *
	 * Shows a table of all providers with their auth mode, source, and masked
	 * secret. Sources include 'constant', 'env', 'db', or 'none'.
	 *
	 * ## EXAMPLES
	 *
	 *     wp agent auth status
	 *
	 * @subcommand status
	 *
	 * @since n.e.x.t
	 *
	 * @param array<int, string>         $args       Positional arguments (unused).
	 * @param array<string, string|bool> $assoc_args Named arguments (unused).
	 *
	 * @return void
	 */
	public function status(array $args, array $assoc_args): void
	{
		$statuses = $this->resolver->getStatus();

		if (empty($statuses)) {
			\WP_CLI::log('No credentials configured.');
			return;
		}

		$rows = [];

		foreach ($statuses as $status) {
			$masked_secret = '';

			if ($status['available']) {
				try {
					$resolved = $this->resolver->resolve($status['provider']);
					$masked_secret = $this->maskSecret($resolved->getSecret());
				} catch (\Exception) {
					$masked_secret = '(error)';
				}
			}

			$rows[] = [
				'provider'  => $status['provider'],
				'auth_mode' => $status['auth_mode'],
				'source'    => $status['source'],
				'secret'    => $masked_secret,
			];
		}

		\WP_CLI\Utils\format_items('table', $rows, ['provider', 'auth_mode', 'source', 'secret']);
	}

	/**
	 * Extracts and validates the provider name from associative arguments.
	 *
	 * Returns null and emits a WP_CLI::error() when the provider argument is
	 * missing or empty.
	 *
	 * @param array<string, string|bool> $assoc_args The named arguments.
	 *
	 * @return string|null The provider name, or null on failure.
	 *
	 * @since n.e.x.t
	 */
	private function extractProvider(array $assoc_args): ?string
	{
		$provider = isset($assoc_args['provider']) && is_string($assoc_args['provider'])
			? $assoc_args['provider']
			: '';

		if ('' === $provider) {
			\WP_CLI::error('The --provider argument is required.');
			return null;
		}

		return $provider;
	}

	/**
	 * Masks a secret for display purposes.
	 *
	 * Shows the first 8 characters followed by '****'. For secrets shorter
	 * than 8 characters, the full secret is shown followed by '****'.
	 *
	 * @param string $secret The raw secret value.
	 *
	 * @return string The masked secret.
	 *
	 * @since n.e.x.t
	 */
	private function maskSecret(string $secret): string
	{
		return substr($secret, 0, 8) . '****';
	}
}
