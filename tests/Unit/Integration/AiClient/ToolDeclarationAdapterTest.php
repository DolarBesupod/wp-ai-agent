<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Integration\AiClient;

use PhpCliAgent\Core\Contracts\ToolInterface;
use PhpCliAgent\Integration\AiClient\ToolDeclarationAdapter;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;

/**
 * Unit tests for ToolDeclarationAdapter in the Integration layer.
 *
 * @covers \PhpCliAgent\Integration\AiClient\ToolDeclarationAdapter
 */
final class ToolDeclarationAdapterTest extends TestCase
{
	private ToolDeclarationAdapter $adapter;

	protected function setUp(): void
	{
		$this->adapter = new ToolDeclarationAdapter();
	}

	public function test_toFunctionDeclaration_withParameters_returnsFunctionDeclaration(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('getName')->willReturn('bash');
		$tool->method('getDescription')->willReturn('Execute command');
		$tool->method('getParametersSchema')->willReturn([
			'type' => 'object',
			'properties' => [
				'command' => ['type' => 'string', 'description' => 'The command to execute'],
			],
			'required' => ['command'],
		]);

		$declaration = $this->adapter->toFunctionDeclaration($tool);

		$this->assertInstanceOf(FunctionDeclaration::class, $declaration);
		$this->assertSame('bash', $declaration->getName());
		$this->assertSame('Execute command', $declaration->getDescription());
		$this->assertIsArray($declaration->getParameters());
		$this->assertSame('object', $declaration->getParameters()['type']);
		$this->assertArrayHasKey('properties', $declaration->getParameters());
		$this->assertArrayHasKey('command', $declaration->getParameters()['properties']);
	}

	public function test_toFunctionDeclaration_withNullParameters_returnsNullParameters(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('getName')->willReturn('no_params_tool');
		$tool->method('getDescription')->willReturn('Tool without parameters');
		$tool->method('getParametersSchema')->willReturn(null);

		$declaration = $this->adapter->toFunctionDeclaration($tool);

		$this->assertInstanceOf(FunctionDeclaration::class, $declaration);
		$this->assertSame('no_params_tool', $declaration->getName());
		$this->assertSame('Tool without parameters', $declaration->getDescription());
		$this->assertNull($declaration->getParameters());
	}

	public function test_toFunctionDeclaration_withEmptyParameters_returnsNullParameters(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('getName')->willReturn('empty_params');
		$tool->method('getDescription')->willReturn('Tool with empty schema');
		$tool->method('getParametersSchema')->willReturn([]);

		$declaration = $this->adapter->toFunctionDeclaration($tool);

		$this->assertInstanceOf(FunctionDeclaration::class, $declaration);
		$this->assertNull($declaration->getParameters());
	}

	public function test_toFunctionDeclarations_withMultipleTools_returnsArrayOfDeclarations(): void
	{
		$tools = [];

		for ($i = 1; $i <= 5; $i++) {
			$tool = $this->createMock(ToolInterface::class);
			$tool->method('getName')->willReturn('tool_' . $i);
			$tool->method('getDescription')->willReturn('Tool number ' . $i);
			$tool->method('getParametersSchema')->willReturn([
				'type' => 'object',
				'properties' => [
					'param' => ['type' => 'string'],
				],
			]);
			$tools[] = $tool;
		}

		$declarations = $this->adapter->toFunctionDeclarations($tools);

		$this->assertCount(5, $declarations);

		foreach ($declarations as $index => $declaration) {
			$this->assertInstanceOf(FunctionDeclaration::class, $declaration);
			$this->assertSame('tool_' . ($index + 1), $declaration->getName());
			$this->assertSame('Tool number ' . ($index + 1), $declaration->getDescription());
		}
	}

	public function test_toFunctionDeclarations_withEmptyArray_returnsEmptyArray(): void
	{
		$declarations = $this->adapter->toFunctionDeclarations([]);

		$this->assertIsArray($declarations);
		$this->assertCount(0, $declarations);
	}

	public function test_toFunctionDeclarations_preservesToolOrder(): void
	{
		$tool1 = $this->createMock(ToolInterface::class);
		$tool1->method('getName')->willReturn('first');
		$tool1->method('getDescription')->willReturn('First tool');
		$tool1->method('getParametersSchema')->willReturn(null);

		$tool2 = $this->createMock(ToolInterface::class);
		$tool2->method('getName')->willReturn('second');
		$tool2->method('getDescription')->willReturn('Second tool');
		$tool2->method('getParametersSchema')->willReturn(null);

		$tool3 = $this->createMock(ToolInterface::class);
		$tool3->method('getName')->willReturn('third');
		$tool3->method('getDescription')->willReturn('Third tool');
		$tool3->method('getParametersSchema')->willReturn(null);

		$declarations = $this->adapter->toFunctionDeclarations([$tool1, $tool2, $tool3]);

		$this->assertSame('first', $declarations[0]->getName());
		$this->assertSame('second', $declarations[1]->getName());
		$this->assertSame('third', $declarations[2]->getName());
	}

	public function test_toArray_withParameters_returnsCorrectStructure(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('getName')->willReturn('read_file');
		$tool->method('getDescription')->willReturn('Read file contents');
		$tool->method('getParametersSchema')->willReturn([
			'type' => 'object',
			'properties' => [
				'path' => ['type' => 'string'],
			],
			'required' => ['path'],
		]);

		$array = $this->adapter->toArray($tool);

		$this->assertArrayHasKey('name', $array);
		$this->assertArrayHasKey('description', $array);
		$this->assertArrayHasKey('parameters', $array);
		$this->assertSame('read_file', $array['name']);
		$this->assertSame('Read file contents', $array['description']);
		$this->assertSame('object', $array['parameters']['type']);
	}

	public function test_toArray_withNullParameters_omitsParametersKey(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('getName')->willReturn('simple');
		$tool->method('getDescription')->willReturn('Simple tool');
		$tool->method('getParametersSchema')->willReturn(null);

		$array = $this->adapter->toArray($tool);

		$this->assertArrayHasKey('name', $array);
		$this->assertArrayHasKey('description', $array);
		$this->assertArrayNotHasKey('parameters', $array);
	}

	public function test_toArray_withEmptyParameters_omitsParametersKey(): void
	{
		$tool = $this->createMock(ToolInterface::class);
		$tool->method('getName')->willReturn('empty');
		$tool->method('getDescription')->willReturn('Empty params');
		$tool->method('getParametersSchema')->willReturn([]);

		$array = $this->adapter->toArray($tool);

		$this->assertArrayNotHasKey('parameters', $array);
	}

	public function test_toArrayMultiple_convertsAllTools(): void
	{
		$tool1 = $this->createMock(ToolInterface::class);
		$tool1->method('getName')->willReturn('tool1');
		$tool1->method('getDescription')->willReturn('First');
		$tool1->method('getParametersSchema')->willReturn(['type' => 'object']);

		$tool2 = $this->createMock(ToolInterface::class);
		$tool2->method('getName')->willReturn('tool2');
		$tool2->method('getDescription')->willReturn('Second');
		$tool2->method('getParametersSchema')->willReturn(null);

		$arrays = $this->adapter->toArrayMultiple([$tool1, $tool2]);

		$this->assertCount(2, $arrays);
		$this->assertSame('tool1', $arrays[0]['name']);
		$this->assertArrayHasKey('parameters', $arrays[0]);
		$this->assertSame('tool2', $arrays[1]['name']);
		$this->assertArrayNotHasKey('parameters', $arrays[1]);
	}

	public function test_toArrayMultiple_withEmptyArray_returnsEmptyArray(): void
	{
		$arrays = $this->adapter->toArrayMultiple([]);

		$this->assertIsArray($arrays);
		$this->assertCount(0, $arrays);
	}

	public function test_toFunctionDeclaration_withComplexSchema_preservesFullSchema(): void
	{
		$complex_schema = [
			'type' => 'object',
			'properties' => [
				'file_path' => [
					'type' => 'string',
					'description' => 'Path to the file',
				],
				'line_number' => [
					'type' => 'integer',
					'description' => 'Starting line number',
					'minimum' => 1,
				],
				'options' => [
					'type' => 'object',
					'properties' => [
						'encoding' => ['type' => 'string', 'default' => 'utf-8'],
						'include_line_numbers' => ['type' => 'boolean', 'default' => true],
					],
				],
			],
			'required' => ['file_path'],
		];

		$tool = $this->createMock(ToolInterface::class);
		$tool->method('getName')->willReturn('read_file_advanced');
		$tool->method('getDescription')->willReturn('Advanced file reader');
		$tool->method('getParametersSchema')->willReturn($complex_schema);

		$declaration = $this->adapter->toFunctionDeclaration($tool);

		$this->assertSame($complex_schema, $declaration->getParameters());
	}
}
