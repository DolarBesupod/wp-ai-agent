<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Integration\Configuration;

use Automattic\Automattic\WpAiAgent\Core\Exceptions\ConfigurationException;

/**
 * Validates settings configuration against a JSON schema.
 *
 * Performs type checking and value constraints validation for the settings.json
 * configuration file.
 *
 * @since n.e.x.t
 */
final class SettingsSchemaValidator
{
	/**
	 * The JSON schema for settings validation.
	 *
	 * @var array<string, mixed>
	 */
	private array $schema;

	/**
	 * The default configuration values.
	 *
	 * @var array<string, mixed>
	 */
	private array $defaults;

	/**
	 * Creates a new schema validator.
	 *
	 * @since n.e.x.t
	 */
	public function __construct()
	{
		$this->schema = $this->buildSchema();
		$this->defaults = $this->buildDefaults();
	}

	/**
	 * Validates the configuration against the schema.
	 *
	 * @param array<string, mixed> $config The configuration array to validate.
	 *
	 * @throws ConfigurationException If validation fails.
	 *
	 * @since n.e.x.t
	 */
	public function validate(array $config): void
	{
		$this->validateObject($config, $this->schema['properties'] ?? [], '');
	}

	/**
	 * Returns the JSON schema.
	 *
	 * @return array<string, mixed> The schema definition.
	 *
	 * @since n.e.x.t
	 */
	public function getSchema(): array
	{
		return $this->schema;
	}

	/**
	 * Returns the default configuration values.
	 *
	 * @return array<string, mixed> The default values.
	 *
	 * @since n.e.x.t
	 */
	public function getDefaults(): array
	{
		return $this->defaults;
	}

	/**
	 * Validates an object against its properties schema.
	 *
	 * @param array<string, mixed> $object     The object to validate.
	 * @param array<string, mixed> $properties The properties schema.
	 * @param string               $path       The current property path.
	 *
	 * @throws ConfigurationException If validation fails.
	 */
	private function validateObject(array $object, array $properties, string $path): void
	{
		foreach ($object as $key => $value) {
			$property_path = $path !== '' ? $path . '.' . $key : $key;

			if (isset($properties[$key])) {
				$this->validateProperty($value, $properties[$key], $property_path);
			}
		}
	}

	/**
	 * Validates a single property value against its schema.
	 *
	 * @param mixed                $value        The value to validate.
	 * @param array<string, mixed> $property_def The property schema definition.
	 * @param string               $path         The property path.
	 *
	 * @throws ConfigurationException If validation fails.
	 */
	private function validateProperty(mixed $value, array $property_def, string $path): void
	{
		$type = $property_def['type'] ?? null;

		if ($type === null) {
			return;
		}

		$this->validateType($value, $type, $path, $property_def);

		// Validate constraints based on type
		if ($type === 'integer') {
			$this->validateIntegerConstraints($value, $property_def, $path);
		} elseif ($type === 'object' && is_array($value)) {
			$nested_properties = $property_def['properties'] ?? [];
			$this->validateObject($value, $nested_properties, $path);
		} elseif ($type === 'array' && is_array($value)) {
			$this->validateArrayItems($value, $property_def, $path);
		}
	}

	/**
	 * Validates the type of a value.
	 *
	 * @param mixed                $value        The value to check.
	 * @param string               $expected     The expected type.
	 * @param string               $path         The property path.
	 * @param array<string, mixed> $property_def The property definition.
	 *
	 * @throws ConfigurationException If type does not match.
	 */
	private function validateType(mixed $value, string $expected, string $path, array $property_def): void
	{
		$actual_type = $this->getPhpType($value);
		$is_valid = false;

		switch ($expected) {
			case 'string':
				$is_valid = is_string($value);
				break;

			case 'integer':
				$is_valid = is_int($value);
				break;

			case 'boolean':
				$is_valid = is_bool($value);
				break;

			case 'object':
				$is_valid = is_array($value) && $this->isAssociativeArray($value);
				break;

			case 'array':
				$is_valid = is_array($value) && ! $this->isAssociativeArray($value);
				break;

			case 'number':
				$is_valid = is_int($value) || is_float($value);
				break;
		}

		if (! $is_valid) {
			throw ConfigurationException::schemaTypeError($path, $expected, $actual_type);
		}
	}

	/**
	 * Validates integer constraints (minimum, maximum).
	 *
	 * @param int                  $value        The integer value.
	 * @param array<string, mixed> $property_def The property definition.
	 * @param string               $path         The property path.
	 *
	 * @throws ConfigurationException If constraints are violated.
	 */
	private function validateIntegerConstraints(int $value, array $property_def, string $path): void
	{
		if (isset($property_def['minimum'])) {
			$minimum = (int) $property_def['minimum'];
			if ($value < $minimum) {
				throw ConfigurationException::schemaMinimumError($path, $minimum, $value);
			}
		}

		if (isset($property_def['maximum'])) {
			$maximum = (int) $property_def['maximum'];
			if ($value > $maximum) {
				throw ConfigurationException::invalidValue($path, "must not exceed {$maximum}");
			}
		}
	}

	/**
	 * Validates array items against their schema.
	 *
	 * @param array<mixed>         $array        The array to validate.
	 * @param array<string, mixed> $property_def The property definition.
	 * @param string               $path         The property path.
	 *
	 * @throws ConfigurationException If any item is invalid.
	 */
	private function validateArrayItems(array $array, array $property_def, string $path): void
	{
		$items_def = $property_def['items'] ?? null;

		if ($items_def === null) {
			return;
		}

		foreach ($array as $index => $item) {
			$item_path = $path . '[' . $index . ']';
			$this->validateProperty($item, $items_def, $item_path);
		}
	}

	/**
	 * Gets the PHP type name for a value.
	 *
	 * @param mixed $value The value.
	 *
	 * @return string The type name.
	 */
	private function getPhpType(mixed $value): string
	{
		$type = gettype($value);

		return match ($type) {
			'integer' => 'integer',
			'double' => 'float',
			'boolean' => 'boolean',
			'string' => 'string',
			'array' => $this->isAssociativeArray($value) ? 'object' : 'array',
			'NULL' => 'null',
			default => $type,
		};
	}

	/**
	 * Checks if an array is associative.
	 *
	 * @param array<mixed> $array The array to check.
	 *
	 * @return bool True if associative, false if indexed.
	 */
	private function isAssociativeArray(array $array): bool
	{
		if ([] === $array) {
			// Empty array is considered indexed (non-associative)
			return false;
		}

		return array_keys($array) !== range(0, count($array) - 1);
	}

	/**
	 * Builds the JSON schema definition.
	 *
	 * @return array<string, mixed> The schema.
	 */
	private function buildSchema(): array
	{
		return [
			'$schema' => 'http://json-schema.org/draft-07/schema#',
			'type' => 'object',
			'properties' => [
				'provider' => [
					'type' => 'object',
					'properties' => [
						'type' => [
							'type' => 'string',
							'default' => 'anthropic',
						],
						'model' => [
							'type' => 'string',
						],
						'max_tokens' => [
							'type' => 'integer',
							'minimum' => 1,
						],
					],
				],
				'max_turns' => [
					'type' => 'integer',
					'minimum' => 1,
					'default' => 100,
				],
				'debug' => [
					'type' => 'boolean',
					'default' => false,
				],
				'streaming' => [
					'type' => 'boolean',
					'default' => true,
				],
				'auto_confirm' => [
					'type' => 'boolean',
					'default' => false,
				],
				'session_storage_path' => [
					'type' => 'string',
				],
				'log_path' => [
					'type' => 'string',
				],
				'default_system_prompt' => [
					'type' => 'string',
				],
				'mcp_servers' => [
					'type' => 'array',
				],
				'permissions' => [
					'type' => 'object',
					'properties' => [
						'allow' => [
							'type' => 'array',
							'items' => [
								'type' => 'string',
							],
						],
						'deny' => [
							'type' => 'array',
							'items' => [
								'type' => 'string',
							],
						],
					],
				],
			],
		];
	}

	/**
	 * Builds the default configuration values.
	 *
	 * @return array<string, mixed> The defaults.
	 */
	private function buildDefaults(): array
	{
		return [
			'provider' => [
				'type' => 'anthropic',
				'model' => 'claude-sonnet-4-20250514',
				'max_tokens' => 8192,
			],
			'max_turns' => 100,
			'debug' => false,
			'streaming' => true,
			'auto_confirm' => false,
			'session_storage_path' => '~/.wp-ai-agent/sessions',
			'log_path' => '~/.wp-ai-agent/logs',
			'default_system_prompt' => '',
			'mcp_servers' => [],
			'permissions' => [
				'allow' => [],
				'deny' => [],
			],
		];
	}
}
