<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Core\Configuration;

use PhpCliAgent\Core\Configuration\ProviderConfiguration;
use PhpCliAgent\Core\Exceptions\ConfigurationException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ProviderConfiguration.
 *
 * @covers \PhpCliAgent\Core\Configuration\ProviderConfiguration
 */
final class ProviderConfigurationTest extends TestCase
{
	/**
	 * Tests constructor sets all properties correctly.
	 */
	public function test_constructor_setsAllProperties(): void
	{
		$config = new ProviderConfiguration(
			'anthropic',
			'sk-test-key',
			'claude-opus-4-20250514',
			16384
		);

		$this->assertSame('anthropic', $config->getType());
		$this->assertSame('sk-test-key', $config->getApiKey());
		$this->assertSame('claude-opus-4-20250514', $config->getModel());
		$this->assertSame(16384, $config->getMaxTokens());
	}

	/**
	 * Tests constructor uses defaults for optional parameters.
	 */
	public function test_constructor_usesDefaults(): void
	{
		$config = new ProviderConfiguration('openai', 'api-key');

		$this->assertSame('openai', $config->getType());
		$this->assertSame('api-key', $config->getApiKey());
		$this->assertSame('claude-sonnet-4-20250514', $config->getModel());
		$this->assertSame(8192, $config->getMaxTokens());
	}

	/**
	 * Tests fromArray creates configuration correctly with all fields.
	 */
	public function test_fromArray_withAllFields_createsConfiguration(): void
	{
		$array = [
			'type' => 'google',
			'api_key' => 'google-api-key',
			'model' => 'gemini-pro',
			'max_tokens' => 4096,
		];

		$config = ProviderConfiguration::fromArray($array);

		$this->assertSame('google', $config->getType());
		$this->assertSame('google-api-key', $config->getApiKey());
		$this->assertSame('gemini-pro', $config->getModel());
		$this->assertSame(4096, $config->getMaxTokens());
	}

	/**
	 * Tests fromArray uses defaults for optional fields.
	 */
	public function test_fromArray_withMinimalFields_createsConfiguration(): void
	{
		$array = [
			'api_key' => 'minimal-key',
		];

		$config = ProviderConfiguration::fromArray($array);

		$this->assertSame('anthropic', $config->getType());
		$this->assertSame('minimal-key', $config->getApiKey());
		$this->assertSame('claude-sonnet-4-20250514', $config->getModel());
		$this->assertSame(8192, $config->getMaxTokens());
	}

	/**
	 * Tests fromArray throws exception when api_key is missing.
	 */
	public function test_fromArray_withMissingApiKey_throwsException(): void
	{
		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('provider.api_key');

		ProviderConfiguration::fromArray(['type' => 'anthropic']);
	}

	/**
	 * Tests fromArray throws exception when api_key is empty.
	 */
	public function test_fromArray_withEmptyApiKey_throwsException(): void
	{
		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('provider.api_key');

		ProviderConfiguration::fromArray(['api_key' => '']);
	}

	/**
	 * Tests hasValidType returns true for valid provider types.
	 *
	 * @dataProvider validProviderTypesProvider
	 */
	public function test_hasValidType_withValidType_returnsTrue(string $type): void
	{
		$config = new ProviderConfiguration($type, 'api-key');

		$this->assertTrue($config->hasValidType());
	}

	/**
	 * Provides valid provider types.
	 *
	 * @return array<string, array{string}>
	 */
	public static function validProviderTypesProvider(): array
	{
		return [
			'anthropic' => ['anthropic'],
			'openai' => ['openai'],
			'google' => ['google'],
		];
	}

	/**
	 * Tests hasValidType returns false for invalid provider type.
	 */
	public function test_hasValidType_withInvalidType_returnsFalse(): void
	{
		$config = new ProviderConfiguration('invalid-provider', 'api-key');

		$this->assertFalse($config->hasValidType());
	}

	/**
	 * Tests isValid returns true for valid configuration.
	 */
	public function test_isValid_withValidConfiguration_returnsTrue(): void
	{
		$config = new ProviderConfiguration('anthropic', 'api-key');

		$this->assertTrue($config->isValid());
	}

	/**
	 * Tests isValid returns false when api_key is empty.
	 */
	public function test_isValid_withEmptyApiKey_returnsFalse(): void
	{
		$config = new ProviderConfiguration('anthropic', '');

		$this->assertFalse($config->isValid());
	}

	/**
	 * Tests isValid returns false when type is invalid.
	 */
	public function test_isValid_withInvalidType_returnsFalse(): void
	{
		$config = new ProviderConfiguration('invalid', 'api-key');

		$this->assertFalse($config->isValid());
	}

	/**
	 * Tests toArray returns correct array representation.
	 */
	public function test_toArray_returnsCorrectArray(): void
	{
		$config = new ProviderConfiguration(
			'anthropic',
			'sk-test',
			'claude-3-opus',
			16384
		);

		$expected = [
			'type' => 'anthropic',
			'api_key' => 'sk-test',
			'model' => 'claude-3-opus',
			'max_tokens' => 16384,
		];

		$this->assertSame($expected, $config->toArray());
	}

	/**
	 * Tests roundtrip through fromArray and toArray.
	 */
	public function test_fromArrayToArray_roundtrip(): void
	{
		$original = [
			'type' => 'openai',
			'api_key' => 'sk-roundtrip',
			'model' => 'gpt-4',
			'max_tokens' => 4096,
		];

		$config = ProviderConfiguration::fromArray($original);
		$result = $config->toArray();

		$this->assertSame($original, $result);
	}

	/**
	 * Tests type constants are defined.
	 */
	public function test_typeConstants_areDefined(): void
	{
		$this->assertSame('anthropic', ProviderConfiguration::TYPE_ANTHROPIC);
		$this->assertSame('openai', ProviderConfiguration::TYPE_OPENAI);
		$this->assertSame('google', ProviderConfiguration::TYPE_GOOGLE);
	}
}
