<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\AiClient;

/**
 * Static utility that maps model name prefixes to AI provider IDs.
 *
 * Used at boot time (WpCliBootstrap) and at runtime (/model command)
 * to auto-detect which provider to use based on the model name.
 *
 * @since n.e.x.t
 */
final class ProviderDetector
{
	/**
	 * Default provider when the model name does not match any known prefix.
	 *
	 * @var string
	 */
	public const DEFAULT_PROVIDER = 'anthropic';

	/**
	 * List of all supported provider IDs.
	 *
	 * @var array<int, string>
	 */
	public const KNOWN_PROVIDERS = ['anthropic', 'openai', 'google'];

	/**
	 * Ordered prefix-to-provider mapping, checked top-down.
	 *
	 * Each entry is [prefix, provider_id]. The first matching prefix wins.
	 * The `models/gemini-` entry must precede `gemini-` so that Google's
	 * fully-qualified model names are matched correctly.
	 *
	 * @var array<int, array{0: string, 1: string}>
	 */
	private const PREFIX_RULES = [
		['claude-', 'anthropic'],
		['gpt-', 'openai'],
		['o1-', 'openai'],
		['o3-', 'openai'],
		['o4-', 'openai'],
		['chatgpt-', 'openai'],
		['models/gemini-', 'google'],
		['gemini-', 'google'],
	];

	/**
	 * Detects the provider ID from a model name using prefix matching.
	 *
	 * Comparison is case-insensitive. Unknown models default to the
	 * DEFAULT_PROVIDER constant (anthropic) for backward compatibility.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $model The model name (e.g. 'gpt-4o', 'claude-sonnet-4-6').
	 *
	 * @return string The provider ID ('anthropic', 'openai', or 'google').
	 */
	public static function detectFromModel(string $model): string
	{
		$lower_model = strtolower($model);

		foreach (self::PREFIX_RULES as [$prefix, $provider_id]) {
			if (str_starts_with($lower_model, $prefix)) {
				return $provider_id;
			}
		}

		return self::DEFAULT_PROVIDER;
	}

	/**
	 * Checks whether the given provider ID is a known/supported provider.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $provider_id The provider ID to check.
	 *
	 * @return bool True if the provider is known, false otherwise.
	 */
	public static function isKnownProvider(string $provider_id): bool
	{
		return in_array($provider_id, self::KNOWN_PROVIDERS, true);
	}
}
