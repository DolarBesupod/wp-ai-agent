<?php

declare(strict_types=1);

namespace WpAiAgent\Core\Tool;

use WpAiAgent\Core\Contracts\ToolInterface;

/**
 * Adapter for converting tools to AI model declarations.
 *
 * This class handles the transformation of ToolInterface instances into
 * the array format expected by AI adapters for function/tool declarations.
 *
 * @since n.e.x.t
 */
final class ToolDeclarationAdapter
{
	/**
	 * Converts a single tool to a declaration array.
	 *
	 * @param ToolInterface $tool The tool to convert.
	 *
	 * @return array{name: string, description: string, parameters?: array<string, mixed>}
	 */
	public function toDeclaration(ToolInterface $tool): array
	{
		$declaration = [
			'name' => $tool->getName(),
			'description' => $tool->getDescription(),
		];

		$parameters = $tool->getParametersSchema();

		if ($parameters !== null && count($parameters) > 0) {
			$declaration['parameters'] = $parameters;
		}

		return $declaration;
	}

	/**
	 * Converts multiple tools to an array of declarations.
	 *
	 * @param array<ToolInterface> $tools The tools to convert.
	 *
	 * @return array<int, array{name: string, description: string, parameters?: array<string, mixed>}>
	 */
	public function toDeclarations(array $tools): array
	{
		$declarations = [];

		foreach ($tools as $tool) {
			$declarations[] = $this->toDeclaration($tool);
		}

		return $declarations;
	}

	/**
	 * Converts a tool to the format expected by Anthropic's Claude API.
	 *
	 * @param ToolInterface $tool The tool to convert.
	 *
	 * @return array{name: string, description: string, input_schema: array<string, mixed>}
	 */
	public function toClaudeFormat(ToolInterface $tool): array
	{
		$parameters = $tool->getParametersSchema();

		return [
			'name' => $tool->getName(),
			'description' => $tool->getDescription(),
			'input_schema' => $parameters ?? [
				'type' => 'object',
				'properties' => new \stdClass(),
			],
		];
	}

	/**
	 * Converts multiple tools to Claude API format.
	 *
	 * @param array<ToolInterface> $tools The tools to convert.
	 *
	 * @return array<int, array{name: string, description: string, input_schema: array<string, mixed>}>
	 */
	public function toClaudeFormatMultiple(array $tools): array
	{
		$declarations = [];

		foreach ($tools as $tool) {
			$declarations[] = $this->toClaudeFormat($tool);
		}

		return $declarations;
	}
}
