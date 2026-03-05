<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Core\Tool;

use PHPUnit\Framework\TestCase;
use Automattic\Automattic\WpAiAgent\Core\Contracts\ToolInterface;
use Automattic\Automattic\WpAiAgent\Core\Exceptions\DuplicateToolException;
use Automattic\Automattic\WpAiAgent\Core\Tool\ToolRegistry;
use Automattic\Automattic\WpAiAgent\Core\ValueObjects\ToolResult;

/**
 * Tests for ToolRegistry.
 *
 * @covers \Automattic\WpAiAgent\Core\Tool\ToolRegistry
 */
final class ToolRegistryTest extends TestCase
{
	private ToolRegistry $registry;

	protected function setUp(): void
	{
		$this->registry = new ToolRegistry();
	}

	public function test_register_storesTool(): void
	{
		$tool = $this->createMockTool('bash');

		$this->registry->register($tool);

		$this->assertTrue($this->registry->has('bash'));
	}

	public function test_register_throwsOnDuplicateName(): void
	{
		$tool1 = $this->createMockTool('bash');
		$tool2 = $this->createMockTool('bash');

		$this->registry->register($tool1);

		$this->expectException(DuplicateToolException::class);
		$this->expectExceptionMessage('A tool with name "bash" is already registered');

		$this->registry->register($tool2);
	}

	public function test_get_returnsRegisteredTool(): void
	{
		$tool = $this->createMockTool('read_file');

		$this->registry->register($tool);

		$retrieved = $this->registry->get('read_file');

		$this->assertSame($tool, $retrieved);
	}

	public function test_get_returnsNullForUnregisteredTool(): void
	{
		$result = $this->registry->get('nonexistent');

		$this->assertNull($result);
	}

	public function test_has_returnsTrueForRegisteredTool(): void
	{
		$tool = $this->createMockTool('bash');

		$this->registry->register($tool);

		$this->assertTrue($this->registry->has('bash'));
	}

	public function test_has_returnsFalseForUnregisteredTool(): void
	{
		$this->assertFalse($this->registry->has('unknown'));
	}

	public function test_all_returnsAllRegisteredTools(): void
	{
		$tool1 = $this->createMockTool('bash');
		$tool2 = $this->createMockTool('read_file');

		$this->registry->register($tool1);
		$this->registry->register($tool2);

		$all = $this->registry->all();

		$this->assertCount(2, $all);
		$this->assertArrayHasKey('bash', $all);
		$this->assertArrayHasKey('read_file', $all);
	}

	public function test_all_returnsEmptyArrayWhenNoTools(): void
	{
		$this->assertSame([], $this->registry->all());
	}

	public function test_remove_removesExistingTool(): void
	{
		$tool = $this->createMockTool('bash');

		$this->registry->register($tool);
		$result = $this->registry->remove('bash');

		$this->assertTrue($result);
		$this->assertFalse($this->registry->has('bash'));
	}

	public function test_remove_returnsFalseForNonexistentTool(): void
	{
		$result = $this->registry->remove('nonexistent');

		$this->assertFalse($result);
	}

	public function test_count_returnsCorrectCount(): void
	{
		$this->assertSame(0, $this->registry->count());

		$this->registry->register($this->createMockTool('tool1'));
		$this->assertSame(1, $this->registry->count());

		$this->registry->register($this->createMockTool('tool2'));
		$this->assertSame(2, $this->registry->count());

		$this->registry->remove('tool1');
		$this->assertSame(1, $this->registry->count());
	}

	public function test_clear_removesAllTools(): void
	{
		$this->registry->register($this->createMockTool('tool1'));
		$this->registry->register($this->createMockTool('tool2'));

		$this->registry->clear();

		$this->assertSame(0, $this->registry->count());
		$this->assertSame([], $this->registry->all());
	}

	public function test_getToolNames_returnsListOfNames(): void
	{
		$this->registry->register($this->createMockTool('bash'));
		$this->registry->register($this->createMockTool('read_file'));

		$names = $this->registry->getToolNames();

		$this->assertCount(2, $names);
		$this->assertContains('bash', $names);
		$this->assertContains('read_file', $names);
	}

	public function test_registerMultiple_registersAllTools(): void
	{
		$tools = [
			$this->createMockTool('tool1'),
			$this->createMockTool('tool2'),
			$this->createMockTool('tool3'),
		];

		$this->registry->registerMultiple($tools);

		$this->assertSame(3, $this->registry->count());
		$this->assertTrue($this->registry->has('tool1'));
		$this->assertTrue($this->registry->has('tool2'));
		$this->assertTrue($this->registry->has('tool3'));
	}

	public function test_getDeclarations_returnsFormattedDeclarations(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('getName')->willReturn('test_tool');
		$tool->method('getDescription')->willReturn('A test tool');
		$tool->method('getParametersSchema')->willReturn([
			'type' => 'object',
			'properties' => [
				'input' => ['type' => 'string'],
			],
			'required' => ['input'],
		]);

		$this->registry->register($tool);

		$declarations = $this->registry->getDeclarations();

		$this->assertCount(1, $declarations);
		$this->assertSame('test_tool', $declarations[0]['name']);
		$this->assertSame('A test tool', $declarations[0]['description']);
		$this->assertArrayHasKey('parameters', $declarations[0]);
	}

	public function test_getDeclarations_omitsNullParameters(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('getName')->willReturn('no_params');
		$tool->method('getDescription')->willReturn('No parameters');
		$tool->method('getParametersSchema')->willReturn(null);

		$this->registry->register($tool);

		$declarations = $this->registry->getDeclarations();

		$this->assertCount(1, $declarations);
		$this->assertArrayNotHasKey('parameters', $declarations[0]);
	}

	/**
	 * Creates a mock tool with the given name.
	 *
	 * @param string $name The tool name.
	 *
	 * @return ToolInterface
	 */
	private function createMockTool(string $name): ToolInterface
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('getName')->willReturn($name);
		$tool->method('getDescription')->willReturn('Mock tool: ' . $name);
		$tool->method('getParametersSchema')->willReturn(null);
		$tool->method('execute')->willReturn(ToolResult::success('Success'));
		$tool->method('requiresConfirmation')->willReturn(true);

		return $tool;
	}
}
