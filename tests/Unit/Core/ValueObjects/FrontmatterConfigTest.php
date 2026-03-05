<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Core\ValueObjects;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Automattic\Automattic\WpAiAgent\Core\ValueObjects\FrontmatterConfig;

/**
 * Tests for FrontmatterConfig value object.
 *
 * @covers \Automattic\WpAiAgent\Core\ValueObjects\FrontmatterConfig
 */
final class FrontmatterConfigTest extends TestCase
{
	public function test_fromArray_createsInstanceFromValidData(): void
	{
		$data = [
			'name' => 'my-skill',
			'description' => 'A test skill',
			'allowed_tools' => ['Read', 'Write'],
		];

		$config = FrontmatterConfig::fromArray($data);

		$this->assertSame('my-skill', $config->getName());
		$this->assertSame('A test skill', $config->getDescription());
		$this->assertSame(['Read', 'Write'], $config->getAllowedTools());
	}

	public function test_fromArray_handlesMinimalData(): void
	{
		$config = FrontmatterConfig::fromArray([]);

		$this->assertNull($config->getName());
		$this->assertNull($config->getDescription());
		$this->assertSame([], $config->getAllowedTools());
	}

	public function test_getName_returnsName(): void
	{
		$config = FrontmatterConfig::fromArray(['name' => 'test-skill']);

		$this->assertSame('test-skill', $config->getName());
	}

	public function test_getName_returnsNullWhenMissing(): void
	{
		$config = FrontmatterConfig::fromArray([]);

		$this->assertNull($config->getName());
	}

	public function test_getDescription_returnsDescription(): void
	{
		$config = FrontmatterConfig::fromArray(['description' => 'Test description']);

		$this->assertSame('Test description', $config->getDescription());
	}

	public function test_getDescription_returnsNullWhenMissing(): void
	{
		$config = FrontmatterConfig::fromArray([]);

		$this->assertNull($config->getDescription());
	}

	public function test_getAllowedTools_returnsToolList(): void
	{
		$config = FrontmatterConfig::fromArray(['allowed_tools' => ['Bash', 'Grep', 'Glob']]);

		$this->assertSame(['Bash', 'Grep', 'Glob'], $config->getAllowedTools());
	}

	public function test_getAllowedTools_returnsEmptyArrayWhenMissing(): void
	{
		$config = FrontmatterConfig::fromArray([]);

		$this->assertSame([], $config->getAllowedTools());
	}

	public function test_getAllowedTools_handlesNonArrayValue(): void
	{
		$config = FrontmatterConfig::fromArray(['allowed_tools' => 'Read']);

		$this->assertSame([], $config->getAllowedTools());
	}

	public function test_getDisallowedTools_returnsToolList(): void
	{
		$config = FrontmatterConfig::fromArray(['disallowed_tools' => ['Bash']]);

		$this->assertSame(['Bash'], $config->getDisallowedTools());
	}

	public function test_getDisallowedTools_returnsEmptyArrayWhenMissing(): void
	{
		$config = FrontmatterConfig::fromArray([]);

		$this->assertSame([], $config->getDisallowedTools());
	}

	public function test_get_returnsValueForExistingKey(): void
	{
		$config = FrontmatterConfig::fromArray([
			'name' => 'test',
			'custom_field' => 'custom-value',
		]);

		$this->assertSame('custom-value', $config->get('custom_field'));
	}

	public function test_get_returnsNullForMissingKey(): void
	{
		$config = FrontmatterConfig::fromArray(['name' => 'test']);

		$this->assertNull($config->get('missing'));
	}

	public function test_get_returnsDefaultForMissingKey(): void
	{
		$config = FrontmatterConfig::fromArray([]);

		$this->assertSame('default', $config->get('missing', 'default'));
	}

	public function test_has_returnsTrueForExistingKey(): void
	{
		$config = FrontmatterConfig::fromArray(['name' => 'test']);

		$this->assertTrue($config->has('name'));
	}

	public function test_has_returnsFalseForMissingKey(): void
	{
		$config = FrontmatterConfig::fromArray(['name' => 'test']);

		$this->assertFalse($config->has('missing'));
	}

	public function test_toArray_returnsOriginalData(): void
	{
		$data = [
			'name' => 'test-skill',
			'description' => 'Test',
			'allowed_tools' => ['Read'],
			'custom' => 'value',
		];

		$config = FrontmatterConfig::fromArray($data);

		$this->assertSame($data, $config->toArray());
	}

	public function test_isToolAllowed_returnsTrueForAllowedTool(): void
	{
		$config = FrontmatterConfig::fromArray(['allowed_tools' => ['Read', 'Write']]);

		$this->assertTrue($config->isToolAllowed('Read'));
		$this->assertTrue($config->isToolAllowed('Write'));
	}

	public function test_isToolAllowed_returnsFalseForDisallowedTool(): void
	{
		$config = FrontmatterConfig::fromArray([
			'allowed_tools' => ['Read', 'Write'],
			'disallowed_tools' => ['Bash'],
		]);

		$this->assertFalse($config->isToolAllowed('Bash'));
	}

	public function test_isToolAllowed_returnsFalseForToolNotInList(): void
	{
		$config = FrontmatterConfig::fromArray(['allowed_tools' => ['Read', 'Write']]);

		$this->assertFalse($config->isToolAllowed('Bash'));
	}

	public function test_isToolAllowed_returnsTrueWhenNoRestrictions(): void
	{
		$config = FrontmatterConfig::fromArray([]);

		$this->assertTrue($config->isToolAllowed('Read'));
		$this->assertTrue($config->isToolAllowed('Bash'));
	}

	public function test_isToolAllowed_disallowedTakesPrecedenceOverAllowed(): void
	{
		$config = FrontmatterConfig::fromArray([
			'allowed_tools' => ['Read', 'Bash'],
			'disallowed_tools' => ['Bash'],
		]);

		$this->assertTrue($config->isToolAllowed('Read'));
		$this->assertFalse($config->isToolAllowed('Bash'));
	}

	public function test_immutability_dataCannotBeModifiedExternally(): void
	{
		$data = ['name' => 'original'];
		$config = FrontmatterConfig::fromArray($data);

		// Attempt to modify the original array
		$data['name'] = 'modified';

		// The value object should retain the original value
		$this->assertSame('original', $config->getName());
	}
}
