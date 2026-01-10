<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Integration\Tool;

use PHPUnit\Framework\TestCase;
use PhpCliAgent\Core\Contracts\ToolRegistryInterface;
use PhpCliAgent\Integration\Tool\BuiltInToolRegistry;

/**
 * Tests for BuiltInToolRegistry.
 *
 * @covers \PhpCliAgent\Integration\Tool\BuiltInToolRegistry
 */
final class BuiltInToolRegistryTest extends TestCase
{
	/**
	 * Expected built-in tool names.
	 *
	 * @var array<string>
	 */
	private const EXPECTED_TOOLS = [
		'bash',
		'read_file',
		'write_file',
		'glob',
		'grep',
		'think',
	];

	public function test_createWithAllTools_returnsRegistryWithAllBuiltInTools(): void
	{
		$registry = BuiltInToolRegistry::createWithAllTools();

		$this->assertInstanceOf(ToolRegistryInterface::class, $registry);
		$this->assertCount(6, $registry->all());

		foreach (self::EXPECTED_TOOLS as $tool_name) {
			$this->assertTrue(
				$registry->has($tool_name),
				sprintf('Registry should have tool: %s', $tool_name)
			);
		}
	}

	public function test_createWithAllTools_returnsWorkingRegistry(): void
	{
		$registry = BuiltInToolRegistry::createWithAllTools();

		$declarations = $registry->getDeclarations();

		$this->assertCount(6, $declarations);
		foreach ($declarations as $declaration) {
			$this->assertArrayHasKey('name', $declaration);
			$this->assertArrayHasKey('description', $declaration);
		}
	}

	public function test_createWithExcluded_excludesSpecifiedTools(): void
	{
		$registry = BuiltInToolRegistry::createWithExcluded(['bash', 'write_file']);

		$this->assertCount(4, $registry->all());
		$this->assertFalse($registry->has('bash'));
		$this->assertFalse($registry->has('write_file'));
		$this->assertTrue($registry->has('read_file'));
		$this->assertTrue($registry->has('glob'));
		$this->assertTrue($registry->has('grep'));
		$this->assertTrue($registry->has('think'));
	}

	public function test_createWithExcluded_withEmptyArray_returnsAllTools(): void
	{
		$registry = BuiltInToolRegistry::createWithExcluded([]);

		$this->assertCount(6, $registry->all());
	}

	public function test_createWithExcluded_withNonExistentTool_ignoresUnknownNames(): void
	{
		$registry = BuiltInToolRegistry::createWithExcluded(['nonexistent_tool']);

		$this->assertCount(6, $registry->all());
	}

	public function test_createWithOnly_includesOnlySpecifiedTools(): void
	{
		$registry = BuiltInToolRegistry::createWithOnly(['read_file', 'think']);

		$this->assertCount(2, $registry->all());
		$this->assertTrue($registry->has('read_file'));
		$this->assertTrue($registry->has('think'));
		$this->assertFalse($registry->has('bash'));
		$this->assertFalse($registry->has('write_file'));
		$this->assertFalse($registry->has('glob'));
		$this->assertFalse($registry->has('grep'));
	}

	public function test_createWithOnly_withEmptyArray_returnsEmptyRegistry(): void
	{
		$registry = BuiltInToolRegistry::createWithOnly([]);

		$this->assertCount(0, $registry->all());
	}

	public function test_createWithOnly_withNonExistentTool_ignoresUnknownNames(): void
	{
		$registry = BuiltInToolRegistry::createWithOnly(['read_file', 'nonexistent_tool']);

		$this->assertCount(1, $registry->all());
		$this->assertTrue($registry->has('read_file'));
	}

	public function test_getAvailableToolNames_returnsAllToolNames(): void
	{
		$names = BuiltInToolRegistry::getAvailableToolNames();

		$this->assertIsArray($names);
		$this->assertCount(6, $names);

		foreach (self::EXPECTED_TOOLS as $expected_name) {
			$this->assertContains(
				$expected_name,
				$names,
				sprintf('Should contain tool name: %s', $expected_name)
			);
		}
	}

	public function test_getAllTools_returnsAllToolInstances(): void
	{
		$tools = BuiltInToolRegistry::getAllTools();

		$this->assertIsArray($tools);
		$this->assertCount(6, $tools);

		$names = array_map(
			fn ($tool) => $tool->getName(),
			$tools
		);

		foreach (self::EXPECTED_TOOLS as $expected_name) {
			$this->assertContains(
				$expected_name,
				$names,
				sprintf('Should contain tool: %s', $expected_name)
			);
		}
	}

	public function test_createTool_withValidName_returnsToolInstance(): void
	{
		$tool = BuiltInToolRegistry::createTool('think');

		$this->assertNotNull($tool);
		$this->assertSame('think', $tool->getName());
	}

	public function test_createTool_withInvalidName_returnsNull(): void
	{
		$tool = BuiltInToolRegistry::createTool('nonexistent_tool');

		$this->assertNull($tool);
	}

	public function test_createTool_createsNewInstanceEachTime(): void
	{
		$tool1 = BuiltInToolRegistry::createTool('think');
		$tool2 = BuiltInToolRegistry::createTool('think');

		$this->assertNotSame($tool1, $tool2);
		$this->assertSame($tool1->getName(), $tool2->getName());
	}

	/**
	 * @dataProvider toolConfirmationProvider
	 */
	public function test_tools_haveExpectedConfirmationRequirements(
		string $tool_name,
		bool $expected_confirmation
	): void {
		$tool = BuiltInToolRegistry::createTool($tool_name);

		$this->assertNotNull($tool, sprintf('Tool %s should exist', $tool_name));
		$this->assertSame(
			$expected_confirmation,
			$tool->requiresConfirmation(),
			sprintf(
				'Tool %s should %s confirmation',
				$tool_name,
				$expected_confirmation ? 'require' : 'not require'
			)
		);
	}

	/**
	 * Provides tool names and their expected confirmation requirements.
	 *
	 * @return array<string, array{string, bool}>
	 */
	public static function toolConfirmationProvider(): array
	{
		return [
			'bash requires confirmation' => ['bash', true],
			'read_file no confirmation' => ['read_file', false],
			'write_file requires confirmation' => ['write_file', true],
			'glob no confirmation' => ['glob', false],
			'grep no confirmation' => ['grep', false],
			'think no confirmation' => ['think', false],
		];
	}

	public function test_registeredTools_haveSameNamesAsExpected(): void
	{
		$registry = BuiltInToolRegistry::createWithAllTools();
		$registered_names = array_keys($registry->all());

		sort($registered_names);
		$expected = self::EXPECTED_TOOLS;
		sort($expected);

		$this->assertSame($expected, $registered_names);
	}
}
