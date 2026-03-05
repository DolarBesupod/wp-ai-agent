<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Core\ValueObjects;

/**
 * Value object representing the result of a tool execution.
 *
 * @since n.e.x.t
 */
final class ToolResult
{
	private bool $success;
	private string $output;

	/**
	 * Optional error message if the execution failed.
	 *
	 * @var string|null
	 */
	private ?string $error;

	/**
	 * Optional structured data from the tool execution.
	 *
	 * @var array<string, mixed>
	 */
	private array $data;

	/**
	 * Creates a new ToolResult instance.
	 *
	 * @param bool                 $success Whether the execution was successful.
	 * @param string               $output  The output text from the tool.
	 * @param string|null          $error   Optional error message.
	 * @param array<string, mixed> $data    Optional structured data.
	 */
	public function __construct(
		bool $success,
		string $output,
		?string $error = null,
		array $data = []
	) {
		$this->success = $success;
		$this->output = $output;
		$this->error = $error;
		$this->data = $data;
	}

	/**
	 * Creates a successful result.
	 *
	 * @param string               $output The output text.
	 * @param array<string, mixed> $data   Optional structured data.
	 *
	 * @return self
	 */
	public static function success(string $output, array $data = []): self
	{
		return new self(true, $output, null, $data);
	}

	/**
	 * Creates a failed result.
	 *
	 * @param string $error  The error message.
	 * @param string $output Optional output text.
	 *
	 * @return self
	 */
	public static function failure(string $error, string $output = ''): self
	{
		return new self(false, $output, $error);
	}

	/**
	 * Checks if the execution was successful.
	 *
	 * @return bool
	 */
	public function isSuccess(): bool
	{
		return $this->success;
	}

	/**
	 * Returns the output text.
	 *
	 * @return string
	 */
	public function getOutput(): string
	{
		return $this->output;
	}

	/**
	 * Returns the error message if present.
	 *
	 * @return string|null
	 */
	public function getError(): ?string
	{
		return $this->error;
	}

	/**
	 * Returns the structured data.
	 *
	 * @return array<string, mixed>
	 */
	public function getData(): array
	{
		return $this->data;
	}

	/**
	 * Returns a string representation suitable for the AI model.
	 *
	 * @return string
	 */
	public function toPromptString(): string
	{
		if (!$this->success) {
			return sprintf("Error: %s\n%s", $this->error ?? 'Unknown error', $this->output);
		}

		return $this->output;
	}

	/**
	 * Converts the result to an array representation.
	 *
	 * @return array{success: bool, output: string, error?: string, data?: array<string, mixed>}
	 */
	public function toArray(): array
	{
		$result = [
			'success' => $this->success,
			'output' => $this->output,
		];

		if ($this->error !== null) {
			$result['error'] = $this->error;
		}

		if (count($this->data) > 0) {
			$result['data'] = $this->data;
		}

		return $result;
	}
}
