<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Core\Command;

use PHPUnit\Framework\TestCase;
use Automattic\WpAiAgent\Core\Command\CommandConfig;

/**
 * Tests for CommandConfig value object.
 *
 * @covers \Automattic\WpAiAgent\Core\Command\CommandConfig
 */
final class CommandConfigTest extends TestCase
{
	public function test_fromFrontmatter_createsInstanceFromValidData(): void
	{
		$frontmatter = [
			'description' => 'A test command',
			'argument_hint' => '<file>',
			'allowed_tools' => ['Read', 'Write'],
			'model' => 'claude-3-opus',
		];

		$config = CommandConfig::fromFrontmatter($frontmatter);

		$this->assertSame('A test command', $config->getDescription());
		$this->assertSame('<file>', $config->getArgumentHint());
		$this->assertSame(['Read', 'Write'], $config->getAllowedTools());
		$this->assertSame('claude-3-opus', $config->getModel());
	}

	public function test_fromFrontmatter_handlesEmptyData(): void
	{
		$config = CommandConfig::fromFrontmatter([]);

		$this->assertNull($config->getDescription());
		$this->assertNull($config->getArgumentHint());
		$this->assertNull($config->getAllowedTools());
		$this->assertNull($config->getModel());
	}

	public function test_getDescription_returnsDescription(): void
	{
		$config = CommandConfig::fromFrontmatter(['description' => 'Test description']);

		$this->assertSame('Test description', $config->getDescription());
	}

	public function test_getDescription_returnsNullWhenMissing(): void
	{
		$config = CommandConfig::fromFrontmatter([]);

		$this->assertNull($config->getDescription());
	}

	public function test_getDescription_returnsNullForNonString(): void
	{
		$config = CommandConfig::fromFrontmatter(['description' => 123]);

		$this->assertNull($config->getDescription());
	}

	public function test_getArgumentHint_returnsHint(): void
	{
		$config = CommandConfig::fromFrontmatter(['argument_hint' => '<filename>']);

		$this->assertSame('<filename>', $config->getArgumentHint());
	}

	public function test_getArgumentHint_returnsNullWhenMissing(): void
	{
		$config = CommandConfig::fromFrontmatter([]);

		$this->assertNull($config->getArgumentHint());
	}

	public function test_getArgumentHint_returnsNullForNonString(): void
	{
		$config = CommandConfig::fromFrontmatter(['argument_hint' => ['array']]);

		$this->assertNull($config->getArgumentHint());
	}

	public function test_getAllowedTools_returnsToolsList(): void
	{
		$config = CommandConfig::fromFrontmatter(['allowed_tools' => ['Bash', 'Grep', 'Glob']]);

		$this->assertSame(['Bash', 'Grep', 'Glob'], $config->getAllowedTools());
	}

	public function test_getAllowedTools_returnsNullWhenMissing(): void
	{
		$config = CommandConfig::fromFrontmatter([]);

		$this->assertNull($config->getAllowedTools());
	}

	public function test_getAllowedTools_returnsNullForNonArray(): void
	{
		$config = CommandConfig::fromFrontmatter(['allowed_tools' => 'Read']);

		$this->assertNull($config->getAllowedTools());
	}

	public function test_getAllowedTools_filtersNonStringValues(): void
	{
		$config = CommandConfig::fromFrontmatter(['allowed_tools' => ['Read', 123, 'Write', null]]);

		$this->assertSame(['Read', 'Write'], $config->getAllowedTools());
	}

	public function test_getModel_returnsModel(): void
	{
		$config = CommandConfig::fromFrontmatter(['model' => 'claude-3-sonnet']);

		$this->assertSame('claude-3-sonnet', $config->getModel());
	}

	public function test_getModel_returnsNullWhenMissing(): void
	{
		$config = CommandConfig::fromFrontmatter([]);

		$this->assertNull($config->getModel());
	}

	public function test_getModel_returnsNullForNonString(): void
	{
		$config = CommandConfig::fromFrontmatter(['model' => 42]);

		$this->assertNull($config->getModel());
	}

	public function test_get_returnsValueForExistingKey(): void
	{
		$config = CommandConfig::fromFrontmatter([
			'description' => 'Test',
			'custom_field' => 'custom-value',
		]);

		$this->assertSame('custom-value', $config->get('custom_field'));
	}

	public function test_get_returnsNullForMissingKey(): void
	{
		$config = CommandConfig::fromFrontmatter(['description' => 'Test']);

		$this->assertNull($config->get('missing'));
	}

	public function test_get_returnsDefaultForMissingKey(): void
	{
		$config = CommandConfig::fromFrontmatter([]);

		$this->assertSame('default', $config->get('missing', 'default'));
	}

	public function test_has_returnsTrueForExistingKey(): void
	{
		$config = CommandConfig::fromFrontmatter(['description' => 'Test']);

		$this->assertTrue($config->has('description'));
	}

	public function test_has_returnsFalseForMissingKey(): void
	{
		$config = CommandConfig::fromFrontmatter(['description' => 'Test']);

		$this->assertFalse($config->has('missing'));
	}

	public function test_toArray_returnsOriginalData(): void
	{
		$data = [
			'description' => 'Test command',
			'argument_hint' => '<file>',
			'allowed_tools' => ['Read'],
			'custom' => 'value',
		];

		$config = CommandConfig::fromFrontmatter($data);

		$this->assertSame($data, $config->toArray());
	}

	public function test_immutability_dataCannotBeModifiedExternally(): void
	{
		$data = ['description' => 'original'];
		$config = CommandConfig::fromFrontmatter($data);

		// Attempt to modify the original array
		$data['description'] = 'modified';

		// The value object should retain the original value
		$this->assertSame('original', $config->getDescription());
	}

	public function test_isEmpty_returnsTrueForEmptyConfig(): void
	{
		$config = CommandConfig::fromFrontmatter([]);

		$this->assertTrue($config->isEmpty());
	}

	public function test_isEmpty_returnsFalseForNonEmptyConfig(): void
	{
		$config = CommandConfig::fromFrontmatter(['description' => 'Test']);

		$this->assertFalse($config->isEmpty());
	}
}
