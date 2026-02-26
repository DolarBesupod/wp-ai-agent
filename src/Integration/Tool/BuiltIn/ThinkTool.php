<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\Tool\BuiltIn;

use WpAiAgent\Core\Tool\AbstractTool;
use WpAiAgent\Core\ValueObjects\ToolResult;

/**
 * Tool for internal LLM reasoning and reflection.
 *
 * The ThinkTool provides a scratchpad capability for the AI model to
 * express its internal reasoning, plan next steps, or work through
 * complex problems. The tool simply echoes back the input thought
 * without side effects.
 *
 * @since n.e.x.t
 */
class ThinkTool extends AbstractTool
{
	/**
	 * Returns the unique name of the tool.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return 'think';
	}

	/**
	 * Returns a description of what the tool does.
	 *
	 * @return string
	 */
	public function getDescription(): string
	{
		return 'Internal reasoning tool for expressing thoughts and planning. '
			. 'Use this to think through problems, plan next steps, or reflect on observations. '
			. 'The thought is recorded and returned without side effects.';
	}

	/**
	 * Returns the JSON Schema for the tool's parameters.
	 *
	 * @return array<string, mixed>
	 */
	public function getParametersSchema(): array
	{
		return [
			'type' => 'object',
			'properties' => [
				'thought' => [
					'type' => 'string',
					'description' => 'The reasoning or thought to record',
				],
			],
			'required' => ['thought'],
		];
	}

	/**
	 * Thinking is a safe operation that does not require confirmation.
	 *
	 * @return bool
	 */
	public function requiresConfirmation(): bool
	{
		return false;
	}

	/**
	 * Executes the think operation by returning the input thought.
	 *
	 * @param array<string, mixed> $arguments The tool arguments.
	 *
	 * @return ToolResult
	 */
	public function execute(array $arguments): ToolResult
	{
		$missing = $this->validateRequiredArguments($arguments, ['thought']);
		if (count($missing) > 0) {
			return $this->failure('Missing required argument: thought');
		}

		$thought = $this->getStringArgument($arguments, 'thought');

		if ($thought === '') {
			return $this->failure('Thought cannot be empty');
		}

		return $this->success($thought);
	}
}
