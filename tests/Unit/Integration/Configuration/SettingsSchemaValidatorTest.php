<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Integration\Configuration;

use Automattic\WpAiAgent\Core\Exceptions\ConfigurationException;
use Automattic\WpAiAgent\Integration\Configuration\SettingsSchemaValidator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SettingsSchemaValidator.
 *
 * @covers \Automattic\WpAiAgent\Integration\Configuration\SettingsSchemaValidator
 */
final class SettingsSchemaValidatorTest extends TestCase
{
	/**
	 * Tests that valid configuration passes validation.
	 */
	public function test_validate_withValidConfig_passes(): void
	{
		$validator = new SettingsSchemaValidator();

		$config = [
			'provider' => [
				'type' => 'anthropic',
				'model' => 'claude-sonnet-4-20250514',
				'max_tokens' => 8192,
			],
			'max_turns' => 100,
			'permissions' => [
				'allow' => ['think', 'read_file'],
			],
			'debug' => false,
			'streaming' => true,
		];

		// Should not throw
		$validator->validate($config);
		$this->assertTrue(true);
	}

	/**
	 * Tests that max_tokens with string value throws type error.
	 */
	public function test_validate_withMaxTokensAsString_throwsTypeError(): void
	{
		$validator = new SettingsSchemaValidator();

		$config = [
			'provider' => [
				'max_tokens' => 'not a number',
			],
		];

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('provider.max_tokens');
		$this->expectExceptionMessage('integer');

		$validator->validate($config);
	}

	/**
	 * Tests that max_turns with negative value throws minimum value error.
	 */
	public function test_validate_withNegativeMaxTurns_throwsMinimumValueError(): void
	{
		$validator = new SettingsSchemaValidator();

		$config = [
			'max_turns' => -1,
		];

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('max_turns');
		$this->expectExceptionMessage('minimum');

		$validator->validate($config);
	}

	/**
	 * Tests that max_turns with zero throws minimum value error.
	 */
	public function test_validate_withZeroMaxTurns_throwsMinimumValueError(): void
	{
		$validator = new SettingsSchemaValidator();

		$config = [
			'max_turns' => 0,
		];

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('max_turns');
		$this->expectExceptionMessage('minimum');

		$validator->validate($config);
	}

	/**
	 * Tests that empty configuration passes validation.
	 */
	public function test_validate_withEmptyConfig_passes(): void
	{
		$validator = new SettingsSchemaValidator();

		// Empty config should pass - defaults will be applied by loader
		$validator->validate([]);
		$this->assertTrue(true);
	}

	/**
	 * Tests that provider.type with invalid type throws error.
	 */
	public function test_validate_withProviderTypeAsInteger_throwsTypeError(): void
	{
		$validator = new SettingsSchemaValidator();

		$config = [
			'provider' => [
				'type' => 123,
			],
		];

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('provider.type');
		$this->expectExceptionMessage('string');

		$validator->validate($config);
	}

	/**
	 * Tests that provider.model with invalid type throws error.
	 */
	public function test_validate_withProviderModelAsInteger_throwsTypeError(): void
	{
		$validator = new SettingsSchemaValidator();

		$config = [
			'provider' => [
				'model' => 123,
			],
		];

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('provider.model');
		$this->expectExceptionMessage('string');

		$validator->validate($config);
	}

	/**
	 * Tests that provider.max_tokens with zero throws minimum value error.
	 */
	public function test_validate_withZeroMaxTokens_throwsMinimumValueError(): void
	{
		$validator = new SettingsSchemaValidator();

		$config = [
			'provider' => [
				'max_tokens' => 0,
			],
		];

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('provider.max_tokens');
		$this->expectExceptionMessage('minimum');

		$validator->validate($config);
	}

	/**
	 * Tests that permissions.allow with non-array throws error.
	 */
	public function test_validate_withPermissionsAllowAsString_throwsTypeError(): void
	{
		$validator = new SettingsSchemaValidator();

		$config = [
			'permissions' => [
				'allow' => 'think',
			],
		];

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('permissions.allow');
		$this->expectExceptionMessage('array');

		$validator->validate($config);
	}

	/**
	 * Tests that permissions.allow with non-string items throws error.
	 */
	public function test_validate_withPermissionsAllowContainingNonString_throwsTypeError(): void
	{
		$validator = new SettingsSchemaValidator();

		$config = [
			'permissions' => [
				'allow' => ['think', 123, 'read_file'],
			],
		];

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('permissions.allow[1]');
		$this->expectExceptionMessage('string');

		$validator->validate($config);
	}

	/**
	 * Tests that debug with non-boolean throws error.
	 */
	public function test_validate_withDebugAsString_throwsTypeError(): void
	{
		$validator = new SettingsSchemaValidator();

		$config = [
			'debug' => 'true',
		];

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('debug');
		$this->expectExceptionMessage('boolean');

		$validator->validate($config);
	}

	/**
	 * Tests that streaming with non-boolean throws error.
	 */
	public function test_validate_withStreamingAsInteger_throwsTypeError(): void
	{
		$validator = new SettingsSchemaValidator();

		$config = [
			'streaming' => 1,
		];

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('streaming');
		$this->expectExceptionMessage('boolean');

		$validator->validate($config);
	}

	/**
	 * Tests that provider with non-object throws error.
	 */
	public function test_validate_withProviderAsString_throwsTypeError(): void
	{
		$validator = new SettingsSchemaValidator();

		$config = [
			'provider' => 'anthropic',
		];

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('provider');
		$this->expectExceptionMessage('object');

		$validator->validate($config);
	}

	/**
	 * Tests that max_turns with valid value passes.
	 */
	public function test_validate_withValidMaxTurns_passes(): void
	{
		$validator = new SettingsSchemaValidator();

		$config = [
			'max_turns' => 1,
		];

		$validator->validate($config);
		$this->assertTrue(true);
	}

	/**
	 * Tests that max_tokens with valid value passes.
	 */
	public function test_validate_withValidMaxTokens_passes(): void
	{
		$validator = new SettingsSchemaValidator();

		$config = [
			'provider' => [
				'max_tokens' => 1,
			],
		];

		$validator->validate($config);
		$this->assertTrue(true);
	}

	/**
	 * Tests that max_turns with float throws type error.
	 */
	public function test_validate_withMaxTurnsAsFloat_throwsTypeError(): void
	{
		$validator = new SettingsSchemaValidator();

		$config = [
			'max_turns' => 50.5,
		];

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('max_turns');
		$this->expectExceptionMessage('integer');

		$validator->validate($config);
	}

	// -----------------------------------------------------------------------
	// auto_confirm
	// -----------------------------------------------------------------------

	/**
	 * Tests that settings array with auto_confirm: true validates without errors.
	 */
	public function test_validate_withAutoConfirmTrue_passes(): void
	{
		$validator = new SettingsSchemaValidator();

		$config = [
			'auto_confirm' => true,
		];

		// Should not throw
		$validator->validate($config);
		$this->assertTrue(true);
	}

	/**
	 * Tests that settings array with auto_confirm: false validates without errors.
	 */
	public function test_validate_withAutoConfirmFalse_passes(): void
	{
		$validator = new SettingsSchemaValidator();

		$config = [
			'auto_confirm' => false,
		];

		// Should not throw
		$validator->validate($config);
		$this->assertTrue(true);
	}

	/**
	 * Tests getSchema returns the JSON schema.
	 */
	public function test_getSchema_returnsValidJsonSchema(): void
	{
		$validator = new SettingsSchemaValidator();

		$schema = $validator->getSchema();

		$this->assertIsArray($schema);
		$this->assertArrayHasKey('$schema', $schema);
		$this->assertArrayHasKey('type', $schema);
		$this->assertSame('object', $schema['type']);
		$this->assertArrayHasKey('properties', $schema);
	}

	/**
	 * Tests getDefaults returns the default values.
	 */
	public function test_getDefaults_returnsDefaultValues(): void
	{
		$validator = new SettingsSchemaValidator();

		$defaults = $validator->getDefaults();

		$this->assertIsArray($defaults);
		$this->assertArrayHasKey('provider', $defaults);
		$this->assertSame('anthropic', $defaults['provider']['type']);
		$this->assertArrayHasKey('max_turns', $defaults);
		$this->assertSame(100, $defaults['max_turns']);
		$this->assertArrayHasKey('debug', $defaults);
		$this->assertFalse($defaults['debug']);
		$this->assertArrayHasKey('streaming', $defaults);
		$this->assertTrue($defaults['streaming']);
	}
}
