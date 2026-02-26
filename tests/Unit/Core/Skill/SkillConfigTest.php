<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Core\Skill;

use PHPUnit\Framework\TestCase;
use WpAiAgent\Core\Skill\SkillConfig;

/**
 * Unit tests for SkillConfig.
 *
 * @covers \WpAiAgent\Core\Skill\SkillConfig
 *
 * @since n.e.x.t
 */
final class SkillConfigTest extends TestCase
{
	/**
	 * Tests that fromFrontmatter() with full parameter definitions builds the
	 * config with correct types, descriptions, required flags, and enum values.
	 */
	public function test_fromFrontmatter_withFullParams_buildsConfig(): void
	{
		$frontmatter = [
			'requires_confirmation' => false,
			'parameters' => [
				'content' => [
					'type'        => 'string',
					'description' => 'The text to summarize',
					'required'    => true,
				],
				'style' => [
					'type'    => 'string',
					'enum'    => ['brief', 'detailed', 'bullet-points'],
					'default' => 'brief',
				],
			],
		];

		$config = SkillConfig::fromFrontmatter($frontmatter);

		$this->assertFalse($config->requiresConfirmation());

		$params = $config->getParameters();
		$this->assertArrayHasKey('content', $params);
		$this->assertSame('string', $params['content']['type']);
		$this->assertSame('The text to summarize', $params['content']['description']);
		$this->assertTrue($params['content']['required']);

		$this->assertArrayHasKey('style', $params);
		$this->assertSame(['brief', 'detailed', 'bullet-points'], $params['style']['enum']);
		$this->assertSame('brief', $params['style']['default']);
	}

	/**
	 * Tests that fromFrontmatter() with no parameters key returns an empty
	 * parameters array.
	 */
	public function test_fromFrontmatter_withMissingParams_defaultsToEmpty(): void
	{
		$config = SkillConfig::fromFrontmatter([]);

		$this->assertSame([], $config->getParameters());
	}

	/**
	 * Tests that requires_confirmation defaults to true when not specified.
	 */
	public function test_requiresConfirmation_defaultsToTrue(): void
	{
		$config = SkillConfig::fromFrontmatter([]);

		$this->assertTrue($config->requiresConfirmation());
	}

	/**
	 * Tests that requires_confirmation can be overridden to false via frontmatter.
	 */
	public function test_requiresConfirmation_canBeOverriddenToFalse(): void
	{
		$config = SkillConfig::fromFrontmatter(['requires_confirmation' => false]);

		$this->assertFalse($config->requiresConfirmation());
	}

	/**
	 * Tests that a non-string/non-array parameter definition is silently ignored.
	 */
	public function test_fromFrontmatter_withInvalidParamDefinition_skipsEntry(): void
	{
		$frontmatter = [
			'parameters' => [
				'good' => ['type' => 'string'],
				'bad'  => 'not-an-array',
			],
		];

		$config = SkillConfig::fromFrontmatter($frontmatter);

		$params = $config->getParameters();
		$this->assertArrayHasKey('good', $params);
		$this->assertArrayNotHasKey('bad', $params);
	}

	/**
	 * Tests that isEmpty() returns true for a default-constructed config.
	 */
	public function test_isEmpty_returnsTrueForDefaultConfig(): void
	{
		$config = SkillConfig::fromFrontmatter([]);

		$this->assertTrue($config->isEmpty());
	}

	/**
	 * Tests that isEmpty() returns false when parameters are defined.
	 */
	public function test_isEmpty_returnsFalseWhenParamsDefined(): void
	{
		$config = SkillConfig::fromFrontmatter([
			'parameters' => ['name' => ['type' => 'string']],
		]);

		$this->assertFalse($config->isEmpty());
	}
}
