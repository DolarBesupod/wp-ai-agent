<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\AiClient;

use WpAiAgent\Core\Contracts\ToolInterface;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;

/**
 * Adapter for converting ToolInterface to FunctionDeclaration.
 *
 * This adapter bridges the core ToolInterface with the php-ai-client's
 * FunctionDeclaration class. It maps tool name, description, and parameter
 * schema to the format expected by the AI client library.
 *
 * @since n.e.x.t
 */
final class ToolDeclarationAdapter
{
	/**
	 * Converts a single ToolInterface to a FunctionDeclaration.
	 *
	 * @param ToolInterface $tool The tool to convert.
	 *
	 * @return FunctionDeclaration The function declaration for the AI model.
	 */
	public function toFunctionDeclaration(ToolInterface $tool): FunctionDeclaration
	{
		$parameters = $tool->getParametersSchema();

		// Return null for empty schemas to match php-ai-client expectations
		if ($parameters !== null && count($parameters) === 0) {
			$parameters = null;
		}

		return new FunctionDeclaration(
			$tool->getName(),
			$tool->getDescription(),
			$parameters
		);
	}

	/**
	 * Converts an array of ToolInterface instances to FunctionDeclaration objects.
	 *
	 * @param array<ToolInterface> $tools The tools to convert.
	 *
	 * @return array<int, FunctionDeclaration> Array of function declarations.
	 */
	public function toFunctionDeclarations(array $tools): array
	{
		$declarations = [];

		foreach ($tools as $tool) {
			$declarations[] = $this->toFunctionDeclaration($tool);
		}

		return $declarations;
	}

	/**
	 * Converts a single ToolInterface to the array format used by AiClientAdapter.
	 *
	 * This method provides the intermediate array format that can be passed
	 * to the AiClientAdapter::chat() method's tools parameter.
	 *
	 * @param ToolInterface $tool The tool to convert.
	 *
	 * @return array{name: string, description: string, parameters?: array<string, mixed>}
	 */
	public function toArray(ToolInterface $tool): array
	{
		$result = [
			'name' => $tool->getName(),
			'description' => $tool->getDescription(),
		];

		$parameters = $tool->getParametersSchema();

		if ($parameters !== null && count($parameters) > 0) {
			$result['parameters'] = $parameters;
		}

		return $result;
	}

	/**
	 * Converts an array of ToolInterface instances to the array format.
	 *
	 * @param array<ToolInterface> $tools The tools to convert.
	 *
	 * @return array<int, array{name: string, description: string, parameters?: array<string, mixed>}>
	 */
	public function toArrayMultiple(array $tools): array
	{
		$result = [];

		foreach ($tools as $tool) {
			$result[] = $this->toArray($tool);
		}

		return $result;
	}
}
