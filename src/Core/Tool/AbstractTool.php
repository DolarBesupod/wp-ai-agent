<?php

declare(strict_types=1);

namespace PhpCliAgent\Core\Tool;

use PhpCliAgent\Core\Contracts\ToolInterface;
use PhpCliAgent\Core\ValueObjects\ToolResult;

/**
 * Abstract base class for tools with common functionality.
 *
 * Provides default implementations and helper methods for building tools.
 * Concrete tool implementations should extend this class and implement
 * the abstract methods.
 *
 * @since n.e.x.t
 */
abstract class AbstractTool implements ToolInterface
{
	/**
	 * Returns whether this tool requires user confirmation before execution.
	 *
	 * Default implementation returns true for safety. Override in subclasses
	 * for tools that are safe to execute without confirmation.
	 *
	 * @return bool
	 */
	public function requiresConfirmation(): bool
	{
		return true;
	}

	/**
	 * Creates a successful result with the given output.
	 *
	 * @param string               $output The output text.
	 * @param array<string, mixed> $data   Optional structured data.
	 *
	 * @return ToolResult
	 */
	protected function success(string $output, array $data = []): ToolResult
	{
		return ToolResult::success($output, $data);
	}

	/**
	 * Creates a failed result with the given error message.
	 *
	 * @param string $error  The error message.
	 * @param string $output Optional output text.
	 *
	 * @return ToolResult
	 */
	protected function failure(string $error, string $output = ''): ToolResult
	{
		return ToolResult::failure($error, $output);
	}

	/**
	 * Validates that required arguments are present.
	 *
	 * @param array<string, mixed> $arguments The arguments to validate.
	 * @param array<int, string>   $required  List of required argument names.
	 *
	 * @return array<int, string> List of missing argument names.
	 */
	protected function validateRequiredArguments(array $arguments, array $required): array
	{
		$missing = [];

		foreach ($required as $name) {
			if (!array_key_exists($name, $arguments)) {
				$missing[] = $name;
			}
		}

		return $missing;
	}

	/**
	 * Gets an argument value with a default fallback.
	 *
	 * @param array<string, mixed> $arguments The arguments array.
	 * @param string               $name      The argument name.
	 * @param mixed                $default   The default value if not present.
	 *
	 * @return mixed
	 */
	protected function getArgument(array $arguments, string $name, mixed $default = null): mixed
	{
		return array_key_exists($name, $arguments) ? $arguments[$name] : $default;
	}

	/**
	 * Gets a string argument value.
	 *
	 * @param array<string, mixed> $arguments The arguments array.
	 * @param string               $name      The argument name.
	 * @param string               $default   The default value.
	 *
	 * @return string
	 */
	protected function getStringArgument(array $arguments, string $name, string $default = ''): string
	{
		$value = $this->getArgument($arguments, $name, $default);

		return is_string($value) ? $value : $default;
	}

	/**
	 * Gets an integer argument value.
	 *
	 * @param array<string, mixed> $arguments The arguments array.
	 * @param string               $name      The argument name.
	 * @param int                  $default   The default value.
	 *
	 * @return int
	 */
	protected function getIntArgument(array $arguments, string $name, int $default = 0): int
	{
		$value = $this->getArgument($arguments, $name, $default);

		return is_numeric($value) ? (int) $value : $default;
	}

	/**
	 * Gets a boolean argument value.
	 *
	 * @param array<string, mixed> $arguments The arguments array.
	 * @param string               $name      The argument name.
	 * @param bool                 $default   The default value.
	 *
	 * @return bool
	 */
	protected function getBoolArgument(array $arguments, string $name, bool $default = false): bool
	{
		$value = $this->getArgument($arguments, $name, $default);

		return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
	}

	/**
	 * Gets an array argument value.
	 *
	 * @param array<string, mixed> $arguments The arguments array.
	 * @param string               $name      The argument name.
	 * @param array<mixed>         $default   The default value.
	 *
	 * @return array<mixed>
	 */
	protected function getArrayArgument(array $arguments, string $name, array $default = []): array
	{
		$value = $this->getArgument($arguments, $name, $default);

		return is_array($value) ? $value : $default;
	}
}
