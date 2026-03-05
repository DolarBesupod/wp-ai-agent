<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Tool;

use Automattic\WpAiAgent\Core\Contracts\ConfirmationHandlerInterface;
use Automattic\WpAiAgent\Core\Contracts\ToolExecutorInterface;
use Automattic\WpAiAgent\Core\Contracts\ToolRegistryInterface;
use Automattic\WpAiAgent\Core\Exceptions\ToolExecutionException;
use Automattic\WpAiAgent\Core\ValueObjects\ToolResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Executes tools with confirmation handling.
 *
 * This class is responsible for looking up tools in the registry,
 * handling user confirmation when required, and executing tools
 * with proper error handling.
 *
 * @since 0.1.0
 */
final class ToolExecutor implements ToolExecutorInterface
{
	private ToolRegistryInterface $registry;
	private ConfirmationHandlerInterface $confirmation_handler;
	private LoggerInterface $logger;

	/**
	 * Creates a new ToolExecutor instance.
	 *
	 * @param ToolRegistryInterface $registry The tool registry.
	 * @param ConfirmationHandlerInterface $confirmation_handler The confirmation handler.
	 * @param LoggerInterface|null $logger Optional logger.
	 */
	public function __construct(
		ToolRegistryInterface $registry,
		ConfirmationHandlerInterface $confirmation_handler,
		?LoggerInterface $logger = null
	) {
		$this->registry = $registry;
		$this->confirmation_handler = $confirmation_handler;
		$this->logger = $logger ?? new NullLogger();
	}

	/**
	 * Executes a tool by name with the given arguments.
	 *
	 * @param string               $tool_name The name of the tool to execute.
	 * @param array<string, mixed> $arguments The arguments for the tool.
	 *
	 * @return ToolResult The execution result.
	 *
	 * @throws ToolExecutionException If the tool is not found.
	 */
	public function execute(string $tool_name, array $arguments): ToolResult
	{
		$this->logger->debug('Executing tool', [
			'tool' => $tool_name,
			'arguments' => $this->sanitizeArgumentsForLogging($arguments),
		]);

		$tool = $this->registry->get($tool_name);

		if ($tool === null) {
			$this->logger->warning('Tool not found', ['tool' => $tool_name]);

			throw new ToolExecutionException(
				$tool_name,
				sprintf('Tool "%s" is not registered.', $tool_name),
				$arguments
			);
		}

		if ($this->requiresConfirmation($tool_name)) {
			$confirmed = $this->confirmation_handler->confirm($tool_name, $arguments);

			if (!$confirmed) {
				$this->logger->info('Tool execution denied by user', ['tool' => $tool_name]);

				return ToolResult::failure(
					sprintf('User denied execution of tool "%s".', $tool_name)
				);
			}
		}

		try {
			$result = $tool->execute($arguments);

			$this->logger->debug('Tool execution completed', [
				'tool' => $tool_name,
				'success' => $result->isSuccess(),
			]);

			return $result;
		} catch (ToolExecutionException $exception) {
			$this->logger->error('Tool execution failed', [
				'tool' => $tool_name,
				'error' => $exception->getMessage(),
			]);

			throw $exception;
		} catch (Throwable $exception) {
			$this->logger->error('Unexpected error during tool execution', [
				'tool' => $tool_name,
				'error' => $exception->getMessage(),
				'exception' => get_class($exception),
			]);

			return ToolResult::failure(
				sprintf('Tool execution failed: %s', $exception->getMessage())
			);
		}
	}

	/**
	 * Executes multiple tool calls in sequence.
	 *
	 * @param array<int, array{name: string, arguments: array<string, mixed>}> $tool_calls The tool calls.
	 *
	 * @return array<int, array{name: string, result: ToolResult}>
	 */
	public function executeMultiple(array $tool_calls): array
	{
		$results = [];

		foreach ($tool_calls as $index => $call) {
			$name = $call['name'];
			$arguments = $call['arguments'];

			try {
				$result = $this->execute($name, $arguments);
			} catch (ToolExecutionException $exception) {
				$result = ToolResult::failure($exception->getMessage());
			}

			$results[$index] = [
				'name' => $name,
				'result' => $result,
			];
		}

		return $results;
	}

	/**
	 * Checks if a tool can be executed without confirmation.
	 *
	 * @param string $tool_name The tool name.
	 *
	 * @return bool True if the tool can execute without confirmation.
	 */
	public function canExecuteWithoutConfirmation(string $tool_name): bool
	{
		return !$this->requiresConfirmation($tool_name);
	}

	/**
	 * Determines if a tool requires confirmation for execution.
	 *
	 * @param string $tool_name The tool name.
	 *
	 * @return bool True if confirmation is required.
	 */
	private function requiresConfirmation(string $tool_name): bool
	{
		if ($this->confirmation_handler->isAutoConfirm()) {
			return false;
		}

		if ($this->confirmation_handler->shouldBypass($tool_name)) {
			return false;
		}

		$tool = $this->registry->get($tool_name);

		if ($tool === null) {
			return true;
		}

		return $tool->requiresConfirmation();
	}

	/**
	 * Sanitizes arguments for safe logging by redacting sensitive values.
	 *
	 * @param array<string, mixed> $arguments The arguments to sanitize.
	 *
	 * @return array<string, mixed> Sanitized arguments.
	 */
	private function sanitizeArgumentsForLogging(array $arguments): array
	{
		$sensitive_keys = ['password', 'token', 'api_key', 'secret', 'credential', 'auth'];
		$sanitized = [];

		foreach ($arguments as $key => $value) {
			$lower_key = strtolower((string) $key);
			$is_sensitive = false;

			foreach ($sensitive_keys as $sensitive) {
				if (str_contains($lower_key, $sensitive)) {
					$is_sensitive = true;
					break;
				}
			}

			if ($is_sensitive) {
				$sanitized[$key] = '[REDACTED]';
			} elseif (is_array($value)) {
				$sanitized[$key] = $this->sanitizeArgumentsForLogging($value);
			} else {
				$sanitized[$key] = $value;
			}
		}

		return $sanitized;
	}
}
