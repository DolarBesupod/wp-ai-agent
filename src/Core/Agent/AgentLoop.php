<?php

declare(strict_types=1);

namespace PhpCliAgent\Core\Agent;

use PhpCliAgent\Core\Contracts\AiAdapterInterface;
use PhpCliAgent\Core\Contracts\AiResponseInterface;
use PhpCliAgent\Core\Contracts\AgentLoopInterface;
use PhpCliAgent\Core\Contracts\OutputHandlerInterface;
use PhpCliAgent\Core\Contracts\SessionInterface;
use PhpCliAgent\Core\Contracts\ToolExecutorInterface;
use PhpCliAgent\Core\Contracts\ToolRegistryInterface;
use PhpCliAgent\Core\Exceptions\AgentException;
use PhpCliAgent\Core\ValueObjects\Message;
use PhpCliAgent\Core\ValueObjects\ToolResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Implementation of the ReAct (Reasoning and Acting) agent loop.
 *
 * The agent loop implements the core reasoning cycle:
 * 1. THINK: Call AI adapter with messages and tool declarations
 * 2. Check finish reason:
 *    - If STOP (end_turn): Return completed state
 *    - If TOOL_CALLS: Continue to ACT
 * 3. ACT: For each tool call, execute via ToolExecutor (with confirmation)
 * 4. OBSERVE: Add tool results to context as messages
 * 5. Loop back to THINK (respecting max_turns limit)
 *
 * @since n.e.x.t
 */
final class AgentLoop implements AgentLoopInterface
{
	private const DEFAULT_MAX_ITERATIONS = 100;

	private AiAdapterInterface $ai_adapter;
	private ToolExecutorInterface $tool_executor;
	private ToolRegistryInterface $tool_registry;
	private OutputHandlerInterface $output_handler;
	private LoggerInterface $logger;

	private int $max_iterations = self::DEFAULT_MAX_ITERATIONS;
	private bool $is_running = false;
	private bool $stop_requested = false;

	/**
	 * The current context during loop execution.
	 *
	 * @var AgentContext|null
	 */
	private ?AgentContext $current_context = null;

	/**
	 * Creates a new AgentLoop instance.
	 *
	 * @param AiAdapterInterface     $ai_adapter     The AI adapter for model calls.
	 * @param ToolExecutorInterface  $tool_executor  The tool executor for running tools.
	 * @param ToolRegistryInterface  $tool_registry  The tool registry for declarations.
	 * @param OutputHandlerInterface $output_handler The output handler for user feedback.
	 * @param LoggerInterface|null   $logger         Optional logger.
	 */
	public function __construct(
		AiAdapterInterface $ai_adapter,
		ToolExecutorInterface $tool_executor,
		ToolRegistryInterface $tool_registry,
		OutputHandlerInterface $output_handler,
		?LoggerInterface $logger = null
	) {
		$this->ai_adapter = $ai_adapter;
		$this->tool_executor = $tool_executor;
		$this->tool_registry = $tool_registry;
		$this->output_handler = $output_handler;
		$this->logger = $logger ?? new NullLogger();
	}

	/**
	 * {@inheritDoc}
	 */
	public function run(SessionInterface $session): void
	{
		$this->logger->info('Starting agent loop', [
			'session_id' => $session->getId()->toString(),
			'max_iterations' => $this->max_iterations,
		]);

		$this->is_running = true;
		$this->stop_requested = false;

		$context = AgentContext::create($session, $this->ai_adapter, $this->max_iterations);
		$this->current_context = $context;

		try {
			$context = $this->executeLoop($context);
			$this->logCompletionState($context);
		} catch (Throwable $exception) {
			$this->logger->error('Agent loop failed with exception', [
				'session_id' => $session->getId()->toString(),
				'error' => $exception->getMessage(),
				'exception' => get_class($exception),
			]);

			$this->output_handler->writeError(
				sprintf('Agent loop error: %s', $exception->getMessage())
			);

			throw new AgentException(
				sprintf('Agent loop failed: %s', $exception->getMessage()),
				0,
				$exception
			);
		} finally {
			$this->is_running = false;
			$this->current_context = null;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function setMaxIterations(int $max_iterations): void
	{
		if ($max_iterations < 1) {
			throw new \InvalidArgumentException('Max iterations must be at least 1.');
		}

		$this->max_iterations = $max_iterations;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getMaxIterations(): int
	{
		return $this->max_iterations;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isRunning(): bool
	{
		return $this->is_running;
	}

	/**
	 * {@inheritDoc}
	 */
	public function stop(): void
	{
		$this->stop_requested = true;
		$this->logger->info('Stop requested for agent loop');
	}

	/**
	 * Returns the current context during execution.
	 *
	 * @return AgentContext|null
	 */
	public function getCurrentContext(): ?AgentContext
	{
		return $this->current_context;
	}

	/**
	 * Executes the main loop.
	 *
	 * @param AgentContext $context The initial context.
	 *
	 * @return AgentContext The final context after loop completion.
	 */
	private function executeLoop(AgentContext $context): AgentContext
	{
		while (!$context->getState()->isTerminal()) {
			// Check for stop request.
			if ($this->stop_requested) {
				$this->logger->info('Loop stopped by request');
				return $context->withCompleted();
			}

			// Check max turns before incrementing.
			if ($context->hasExceededMaxTurns()) {
				$this->logger->warning('Max turns reached', [
					'turns' => $context->getCurrentTurn(),
					'max' => $context->getMaxTurns(),
				]);

				$this->output_handler->writeStatus('Maximum iterations reached.');
				return $context->withMaxTurnsReached();
			}

			// Increment turn counter.
			$context = $context->withIncrementedTurn();

			$this->logger->debug('Starting turn', ['turn' => $context->getCurrentTurn()]);

			// THINK phase.
			$context = $this->think($context);

			if ($context->getState()->isTerminal()) {
				break;
			}

			// ACT phase (only if there are pending tool calls).
			if (count($context->getPendingToolResults()) === 0 && $context->getState() === AgentState::ACTING) {
				// This shouldn't happen, but handle gracefully.
				$context = $context->withCompleted();
				break;
			}

			// OBSERVE phase - add tool results to conversation.
			if (count($context->getPendingToolResults()) > 0) {
				$context = $this->observe($context);
			}

			$this->current_context = $context;
		}

		return $context;
	}

	/**
	 * THINK phase: Call AI adapter and process response.
	 *
	 * @param AgentContext $context The current context.
	 *
	 * @return AgentContext Updated context after thinking.
	 */
	private function think(AgentContext $context): AgentContext
	{
		$context = $context->withState(AgentState::THINKING);
		$this->output_handler->writeStatus('Thinking...');

		try {
			$response = $this->callAiAdapter($context);

			// Track token usage on the session.
			$usage = $response->getUsage();
			if ($context->getSession() instanceof \PhpCliAgent\Core\Session\Session) {
				$context->getSession()->addTokenUsage($usage['input_tokens'], $usage['output_tokens']);
			}

			// Add assistant response to conversation.
			$assistant_message = $response->toMessage();
			$context->addMessage($assistant_message);

			// Output any text content.
			$content = $response->getContent();
			if ('' !== $content) {
				$this->output_handler->writeAssistantResponse($content);
			}

			// Check if this is a final response (no tool calls).
			if ($response->isFinalResponse()) {
				$this->logger->debug('Received final response');
				return $context->withCompleted();
			}

			// Has tool calls - move to ACT phase.
			if ($response->hasToolCalls()) {
				$context = $context->withState(AgentState::ACTING);
				return $this->act($context, $response->getToolCalls());
			}

			// No tool calls and not marked final - treat as complete.
			return $context->withCompleted();
		} catch (Throwable $exception) {
			$this->logger->error('AI adapter call failed', [
				'error' => $exception->getMessage(),
			]);

			return $context->withError(
				sprintf('AI call failed: %s', $exception->getMessage()),
				$exception
			);
		}
	}

	/**
	 * ACT phase: Execute tool calls.
	 *
	 * @param AgentContext                                                       $context    The current context.
	 * @param array<int, array{id: string, name: string, arguments: array<string, mixed>}> $tool_calls The tool calls to execute.
	 *
	 * @return AgentContext Updated context with tool results.
	 */
	private function act(AgentContext $context, array $tool_calls): AgentContext
	{
		$this->output_handler->writeStatus('Executing tools...');

		$results = [];
		$user_denied = false;

		foreach ($tool_calls as $tool_call) {
			$tool_id = $tool_call['id'];
			$tool_name = $tool_call['name'];
			$arguments = $tool_call['arguments'];

			$this->logger->debug('Executing tool', [
				'tool_id' => $tool_id,
				'tool_name' => $tool_name,
			]);

			$this->output_handler->writeStatus(sprintf('Running tool: %s', $tool_name));

			try {
				$result = $this->tool_executor->execute($tool_name, $arguments);

				// Check if user denied execution.
				if (!$result->isSuccess() && $this->isUserDenialResult($result)) {
					$user_denied = true;
					$this->logger->info('User denied tool execution', ['tool' => $tool_name]);
				}

				$results[] = [
					'tool_call_id' => $tool_id,
					'tool_name' => $tool_name,
					'result' => $result,
				];

				$this->output_handler->writeToolResult($tool_name, $result);
			} catch (Throwable $exception) {
				$this->logger->error('Tool execution failed', [
					'tool' => $tool_name,
					'error' => $exception->getMessage(),
				]);

				$error_result = ToolResult::failure(
					sprintf('Tool execution failed: %s', $exception->getMessage())
				);

				$results[] = [
					'tool_call_id' => $tool_id,
					'tool_name' => $tool_name,
					'result' => $error_result,
				];

				$this->output_handler->writeToolResult($tool_name, $error_result);
			}

			// If user denied, stop processing more tools.
			if ($user_denied) {
				break;
			}
		}

		$context = $context->withPendingToolResults($results);

		if ($user_denied) {
			return $context->withUserCancelled();
		}

		return $context;
	}

	/**
	 * OBSERVE phase: Add tool results to conversation.
	 *
	 * @param AgentContext $context The current context with pending results.
	 *
	 * @return AgentContext Updated context with results added to messages.
	 */
	private function observe(AgentContext $context): AgentContext
	{
		$results = $context->getPendingToolResults();

		foreach ($results as $result_data) {
			$tool_call_id = $result_data['tool_call_id'];
			$tool_name = $result_data['tool_name'];
			$result = $result_data['result'];

			$message = Message::toolResult(
				$tool_call_id,
				$tool_name,
				$result->toPromptString()
			);

			$context->addMessage($message);

			$this->logger->debug('Added tool result to conversation', [
				'tool_call_id' => $tool_call_id,
				'tool_name' => $tool_name,
				'success' => $result->isSuccess(),
			]);
		}

		return $context->withClearedToolResults();
	}

	/**
	 * Calls the AI adapter with current context.
	 *
	 * @param AgentContext $context The current context.
	 *
	 * @return AiResponseInterface The AI response.
	 */
	private function callAiAdapter(AgentContext $context): AiResponseInterface
	{
		$messages = $context->getSession()->getMessages();
		$system_prompt = $context->getSystemPrompt();
		$tool_declarations = $this->tool_registry->getDeclarations();

		return $this->ai_adapter->chat(
			$messages,
			$system_prompt,
			$tool_declarations
		);
	}

	/**
	 * Checks if a tool result indicates user denial.
	 *
	 * @param ToolResult $result The tool result.
	 *
	 * @return bool True if user denied execution.
	 */
	private function isUserDenialResult(ToolResult $result): bool
	{
		$error = $result->getError();
		if ($error === null) {
			return false;
		}

		// Check for denial message pattern from ToolExecutor.
		return str_contains($error, 'User denied execution');
	}

	/**
	 * Logs the completion state of the loop.
	 *
	 * @param AgentContext $context The final context.
	 *
	 * @return void
	 */
	private function logCompletionState(AgentContext $context): void
	{
		$state = $context->getState();

		$this->logger->info('Agent loop completed', [
			'state' => $state->value,
			'turns' => $context->getCurrentTurn(),
			'description' => $state->getDescription(),
		]);

		if ($state === AgentState::MAX_TURNS_REACHED) {
			$this->output_handler->writeLine(
				sprintf('Loop stopped after %d iterations.', $context->getCurrentTurn())
			);
		}
	}
}
