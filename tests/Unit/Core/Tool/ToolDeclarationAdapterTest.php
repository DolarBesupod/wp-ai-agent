<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Core\Tool;

use PHPUnit\Framework\TestCase;
use PhpCliAgent\Core\Contracts\ToolInterface;
use PhpCliAgent\Core\Tool\ToolDeclarationAdapter;

/**
 * Tests for ToolDeclarationAdapter.
 *
 * @covers \PhpCliAgent\Core\Tool\ToolDeclarationAdapter
 */
final class ToolDeclarationAdapterTest extends TestCase
{
	private ToolDeclarationAdapter $adapter;

	protected function setUp(): void
	{
		$this->adapter = new ToolDeclarationAdapter();
	}

	public function test_toDeclaration_convertsToolWithParameters(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('getName')->willReturn('read_file');
		$tool->method('getDescription')->willReturn('Reads a file from disk');
		$tool->method('getParametersSchema')->willReturn([
			'type' => 'object',
			'properties' => [
				'path' => ['type' => 'string', 'description' => 'File path'],
			],
			'required' => ['path'],
		]);

		$declaration = $this->adapter->toDeclaration($tool);

		$this->assertSame('read_file', $declaration['name']);
		$this->assertSame('Reads a file from disk', $declaration['description']);
		$this->assertArrayHasKey('parameters', $declaration);
		$this->assertSame('object', $declaration['parameters']['type']);
	}

	public function test_toDeclaration_omitsParametersWhenNull(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('getName')->willReturn('no_params');
		$tool->method('getDescription')->willReturn('Tool without parameters');
		$tool->method('getParametersSchema')->willReturn(null);

		$declaration = $this->adapter->toDeclaration($tool);

		$this->assertSame('no_params', $declaration['name']);
		$this->assertSame('Tool without parameters', $declaration['description']);
		$this->assertArrayNotHasKey('parameters', $declaration);
	}

	public function test_toDeclaration_omitsParametersWhenEmpty(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('getName')->willReturn('empty_params');
		$tool->method('getDescription')->willReturn('Tool with empty parameters');
		$tool->method('getParametersSchema')->willReturn([]);

		$declaration = $this->adapter->toDeclaration($tool);

		$this->assertArrayNotHasKey('parameters', $declaration);
	}

	public function test_toDeclarations_convertsMultipleTools(): void
	{
		$tool1 = $this->createMock(ToolInterface::class);
		$tool1->method('getName')->willReturn('tool1');
		$tool1->method('getDescription')->willReturn('First tool');
		$tool1->method('getParametersSchema')->willReturn(null);

		$tool2 = $this->createMock(ToolInterface::class);
		$tool2->method('getName')->willReturn('tool2');
		$tool2->method('getDescription')->willReturn('Second tool');
		$tool2->method('getParametersSchema')->willReturn(['type' => 'object']);

		$declarations = $this->adapter->toDeclarations([$tool1, $tool2]);

		$this->assertCount(2, $declarations);
		$this->assertSame('tool1', $declarations[0]['name']);
		$this->assertSame('tool2', $declarations[1]['name']);
	}

	public function test_toDeclarations_returnsEmptyArrayForNoTools(): void
	{
		$declarations = $this->adapter->toDeclarations([]);

		$this->assertSame([], $declarations);
	}

	public function test_toClaudeFormat_convertsToolWithParameters(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('getName')->willReturn('bash');
		$tool->method('getDescription')->willReturn('Executes bash commands');
		$tool->method('getParametersSchema')->willReturn([
			'type' => 'object',
			'properties' => [
				'command' => ['type' => 'string'],
			],
			'required' => ['command'],
		]);

		$declaration = $this->adapter->toClaudeFormat($tool);

		$this->assertSame('bash', $declaration['name']);
		$this->assertSame('Executes bash commands', $declaration['description']);
		$this->assertArrayHasKey('input_schema', $declaration);
		$this->assertSame('object', $declaration['input_schema']['type']);
	}

	public function test_toClaudeFormat_providesDefaultSchemaWhenNull(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('getName')->willReturn('simple');
		$tool->method('getDescription')->willReturn('Simple tool');
		$tool->method('getParametersSchema')->willReturn(null);

		$declaration = $this->adapter->toClaudeFormat($tool);

		$this->assertArrayHasKey('input_schema', $declaration);
		$this->assertSame('object', $declaration['input_schema']['type']);
		$this->assertInstanceOf(\stdClass::class, $declaration['input_schema']['properties']);
	}

	public function test_toClaudeFormatMultiple_convertsAllTools(): void
	{
		$tool1 = $this->createMock(ToolInterface::class);
		$tool1->method('getName')->willReturn('tool1');
		$tool1->method('getDescription')->willReturn('Tool 1');
		$tool1->method('getParametersSchema')->willReturn(null);

		$tool2 = $this->createMock(ToolInterface::class);
		$tool2->method('getName')->willReturn('tool2');
		$tool2->method('getDescription')->willReturn('Tool 2');
		$tool2->method('getParametersSchema')->willReturn(['type' => 'object']);

		$declarations = $this->adapter->toClaudeFormatMultiple([$tool1, $tool2]);

		$this->assertCount(2, $declarations);
		$this->assertArrayHasKey('input_schema', $declarations[0]);
		$this->assertArrayHasKey('input_schema', $declarations[1]);
	}
}
