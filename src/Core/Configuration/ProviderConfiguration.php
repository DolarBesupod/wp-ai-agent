<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Configuration;

use Automattic\WpAiAgent\Core\Exceptions\ConfigurationException;

/**
 * Configuration for an AI provider.
 *
 * Holds all configuration needed to connect to an AI provider such as
 * Anthropic, OpenAI, or Google.
 *
 * @since n.e.x.t
 */
final class ProviderConfiguration
{
	/**
	 * Supported provider types.
	 */
	public const TYPE_ANTHROPIC = 'anthropic';
	public const TYPE_OPENAI = 'openai';
	public const TYPE_GOOGLE = 'google';

	/**
	 * List of valid provider types.
	 *
	 * @var array<string>
	 */
	private const VALID_TYPES = [
		self::TYPE_ANTHROPIC,
		self::TYPE_OPENAI,
		self::TYPE_GOOGLE,
	];

	/**
	 * The provider type (anthropic, openai, google).
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * The API key for authentication.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * The model identifier.
	 *
	 * @var string
	 */
	private string $model;

	/**
	 * The maximum tokens for AI responses.
	 *
	 * @var int
	 */
	private int $max_tokens;

	/**
	 * Creates a new provider configuration.
	 *
	 * @param string $type       The provider type (anthropic, openai, google).
	 * @param string $api_key    The API key for authentication.
	 * @param string $model      The model identifier.
	 * @param int    $max_tokens The maximum tokens for AI responses.
	 */
	public function __construct(
		string $type,
		string $api_key,
		string $model = 'claude-sonnet-4-20250514',
		int $max_tokens = 8192
	) {
		$this->type = $type;
		$this->api_key = $api_key;
		$this->model = $model;
		$this->max_tokens = $max_tokens;
	}

	/**
	 * Creates a configuration from an array.
	 *
	 * @param array<string, mixed> $config The configuration array.
	 *
	 * @return self
	 *
	 * @throws ConfigurationException If required fields are missing.
	 */
	public static function fromArray(array $config): self
	{
		if (! isset($config['api_key']) || ! is_string($config['api_key']) || $config['api_key'] === '') {
			throw ConfigurationException::missingKey('provider.api_key');
		}

		$type = isset($config['type']) && is_string($config['type'])
			? $config['type']
			: self::TYPE_ANTHROPIC;

		$model = isset($config['model']) && is_string($config['model'])
			? $config['model']
			: 'claude-sonnet-4-20250514';

		$max_tokens = isset($config['max_tokens']) && is_numeric($config['max_tokens'])
			? (int) $config['max_tokens']
			: 8192;

		return new self($type, $config['api_key'], $model, $max_tokens);
	}

	/**
	 * Gets the provider type.
	 *
	 * @return string
	 */
	public function getType(): string
	{
		return $this->type;
	}

	/**
	 * Gets the API key.
	 *
	 * @return string
	 */
	public function getApiKey(): string
	{
		return $this->api_key;
	}

	/**
	 * Gets the model identifier.
	 *
	 * @return string
	 */
	public function getModel(): string
	{
		return $this->model;
	}

	/**
	 * Gets the maximum tokens.
	 *
	 * @return int
	 */
	public function getMaxTokens(): int
	{
		return $this->max_tokens;
	}

	/**
	 * Checks if the provider type is valid.
	 *
	 * @return bool
	 */
	public function hasValidType(): bool
	{
		return in_array($this->type, self::VALID_TYPES, true);
	}

	/**
	 * Checks if the configuration is valid.
	 *
	 * A configuration is valid if it has a non-empty API key and valid type.
	 *
	 * @return bool
	 */
	public function isValid(): bool
	{
		return $this->api_key !== '' && $this->hasValidType();
	}

	/**
	 * Converts the configuration to an array.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'type' => $this->type,
			'api_key' => $this->api_key,
			'model' => $this->model,
			'max_tokens' => $this->max_tokens,
		];
	}
}
