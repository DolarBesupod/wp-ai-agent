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
			$declaration['parameters'] = $this->normalizeSchema($parameters);
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

		$schema = $parameters ?? [
			'type' => 'object',
			'properties' => new \stdClass(),
		];

		return [
			'name' => $tool->getName(),
			'description' => $tool->getDescription(),
			'input_schema' => $this->normalizeSchema($schema),
		];
	}

	/**
	 * Normalizes a JSON Schema to ensure API compatibility.
	 *
	 * MCP servers may return `"properties": []` (empty array) instead of
	 * `"properties": {}` (empty object). The Claude API requires properties
	 * to be a dictionary, so this method recursively normalizes array fields
	 * that must be objects per the JSON Schema spec.
	 *
	 * @param array<string, mixed> $schema The schema to normalize.
	 *
	 * @return array<string, mixed> The normalized schema.
	 */
	private function normalizeSchema(array $schema): array
	{
		$object_fields = ['properties', 'patternProperties', 'definitions', '$defs'];

		foreach ($object_fields as $field) {
			if (!array_key_exists($field, $schema)) {
				continue;
			}

			if (is_array($schema[$field]) && $schema[$field] === []) {
				$schema[$field] = new \stdClass();
			} elseif (is_array($schema[$field])) {
				$schema[$field] = $this->normalizeSchemaProperties($schema[$field]);
			}
		}

		return $schema;
	}

	/**
	 * Recursively normalizes nested property definitions.
	 *
	 * @param array<string, mixed> $properties The properties to normalize.
	 *
	 * @return array<string, mixed> The normalized properties.
	 */
	private function normalizeSchemaProperties(array $properties): array
	{
		foreach ($properties as $key => $value) {
			if (is_array($value)) {
				$properties[$key] = $this->normalizeSchema($value);
			}
		}

		return $properties;
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
