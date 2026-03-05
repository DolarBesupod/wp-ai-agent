<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Agent;

use Automattic\WpAiAgent\Core\Contracts\AgentInterface;
use Automattic\WpAiAgent\Core\Contracts\AgentLoopInterface;
use Automattic\WpAiAgent\Core\Contracts\SessionInterface;
use Automattic\WpAiAgent\Core\Contracts\SessionRepositoryInterface;
use Automattic\WpAiAgent\Core\Exceptions\AgentException;
use Automattic\WpAiAgent\Core\Exceptions\SessionNotFoundException;
use Automattic\WpAiAgent\Core\Session\Session;
use Automattic\WpAiAgent\Core\ValueObjects\Message;
use Automattic\WpAiAgent\Core\ValueObjects\SessionId;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Main agent facade coordinating sessions and the agent loop.
 *
 * The Agent class is the primary entry point for interacting with the AI system.
 * It manages session lifecycle (create, resume, end) and delegates message
 * processing to the AgentLoop.
 *
 * @since n.e.x.t
 */
final class Agent implements AgentInterface
{
	private AgentLoopInterface $agent_loop;
	private SessionRepositoryInterface $session_repository;
	private LoggerInterface $logger;

	/**
	 * The default system prompt for new sessions.
	 *
	 * @var string
	 */
	private string $default_system_prompt;

	/**
	 * The current active session.
	 *
	 * @var SessionInterface|null
	 */
	private ?SessionInterface $current_session = null;

	/**
	 * Whether to auto-save sessions after each message.
	 *
	 * @var bool
	 */
	private bool $auto_save = true;

	/**
	 * Creates a new Agent instance.
	 *
	 * @param AgentLoopInterface         $agent_loop            The agent loop for processing.
	 * @param SessionRepositoryInterface $session_repository    The repository for session persistence.
	 * @param string                     $default_system_prompt The default system prompt.
	 * @param LoggerInterface|null       $logger                Optional logger.
	 */
	public function __construct(
		AgentLoopInterface $agent_loop,
		SessionRepositoryInterface $session_repository,
		string $default_system_prompt = '',
		?LoggerInterface $logger = null
	) {
		$this->agent_loop = $agent_loop;
		$this->session_repository = $session_repository;
		$this->default_system_prompt = $default_system_prompt;
		$this->logger = $logger ?? new NullLogger();
	}

	/**
	 * {@inheritDoc}
	 */
	public function startSession(): SessionId
	{
		$session = new Session(
			null,
			$this->default_system_prompt
		);

		$this->current_session = $session;
		$session_id = $session->getId();

		$this->logger->info('Started new session', [
			'session_id' => $session_id->toString(),
		]);

		if ($this->auto_save) {
			$this->saveCurrentSession();
		}

		return $session_id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function resumeSession(SessionId $session_id): void
	{
		$this->logger->debug('Resuming session', [
			'session_id' => $session_id->toString(),
		]);

		if (!$this->session_repository->exists($session_id)) {
			throw new SessionNotFoundException($session_id);
		}

		$this->current_session = $this->session_repository->load($session_id);

		$this->logger->info('Session resumed', [
			'session_id' => $session_id->toString(),
			'message_count' => $this->current_session->getMessageCount(),
		]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function sendMessage(string $message): void
	{
		if ($this->current_session === null) {
			throw new AgentException('No active session. Call startSession() or resumeSession() first.');
		}

		$this->logger->debug('Processing user message', [
			'session_id' => $this->current_session->getId()->toString(),
			'message_length' => strlen($message),
		]);

		// Add user message to session.
		$user_message = Message::user($message);
		$this->current_session->addMessage($user_message);

		// Run the agent loop.
		$this->agent_loop->run($this->current_session);

		// Auto-save if enabled.
		if ($this->auto_save && $this->current_session !== null) {
			$this->saveCurrentSession();
		}

		if ($this->current_session !== null) {
			$this->logger->info('Message processed', [
				'session_id' => $this->current_session->getId()->toString(),
				'total_messages' => $this->current_session->getMessageCount(),
			]);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function getCurrentSession(): ?SessionInterface
	{
		return $this->current_session;
	}

	/**
	 * {@inheritDoc}
	 */
	public function endSession(): void
	{
		if ($this->current_session === null) {
			$this->logger->debug('No session to end');
			return;
		}

		$session_id = $this->current_session->getId();

		$this->logger->info('Ending session', [
			'session_id' => $session_id->toString(),
			'message_count' => $this->current_session->getMessageCount(),
		]);

		if ($this->auto_save) {
			$this->saveCurrentSession();
		}

		$this->current_session = null;
	}

	/**
	 * Sets the default system prompt for new sessions.
	 *
	 * @param string $prompt The system prompt.
	 *
	 * @return void
	 */
	public function setDefaultSystemPrompt(string $prompt): void
	{
		$this->default_system_prompt = $prompt;
	}

	/**
	 * Returns the default system prompt.
	 *
	 * @return string
	 */
	public function getDefaultSystemPrompt(): string
	{
		return $this->default_system_prompt;
	}

	/**
	 * Sets whether to auto-save sessions after each message.
	 *
	 * @param bool $auto_save Whether to auto-save.
	 *
	 * @return void
	 */
	public function setAutoSave(bool $auto_save): void
	{
		$this->auto_save = $auto_save;
	}

	/**
	 * Checks if auto-save is enabled.
	 *
	 * @return bool
	 */
	public function isAutoSaveEnabled(): bool
	{
		return $this->auto_save;
	}

	/**
	 * Manually saves the current session.
	 *
	 * @return void
	 *
	 * @throws AgentException If no session is active.
	 */
	public function saveCurrentSession(): void
	{
		if ($this->current_session === null) {
			throw new AgentException('No active session to save.');
		}

		$this->session_repository->save($this->current_session);

		$this->logger->debug('Session saved', [
			'session_id' => $this->current_session->getId()->toString(),
		]);
	}

	/**
	 * Deletes a session from storage.
	 *
	 * @param SessionId $session_id The session to delete.
	 *
	 * @return bool True if deleted, false if not found.
	 */
	public function deleteSession(SessionId $session_id): bool
	{
		$this->logger->info('Deleting session', [
			'session_id' => $session_id->toString(),
		]);

		// If deleting the current session, clear it.
		if (
			$this->current_session !== null &&
			$this->current_session->getId()->equals($session_id)
		) {
			$this->current_session = null;
		}

		return $this->session_repository->delete($session_id);
	}

	/**
	 * Lists all available sessions.
	 *
	 * @return array<int, array{id: SessionId, metadata: \Automattic\WpAiAgent\Core\Contracts\SessionMetadataInterface}>
	 */
	public function listSessions(): array
	{
		return $this->session_repository->listWithMetadata();
	}

	/**
	 * Returns the agent loop instance.
	 *
	 * Useful for configuration or monitoring.
	 *
	 * @return AgentLoopInterface
	 */
	public function getAgentLoop(): AgentLoopInterface
	{
		return $this->agent_loop;
	}

	/**
	 * Sets the system prompt for the current session.
	 *
	 * @param string $prompt The system prompt.
	 *
	 * @return void
	 *
	 * @throws AgentException If no session is active.
	 */
	public function setSessionSystemPrompt(string $prompt): void
	{
		if ($this->current_session === null) {
			throw new AgentException('No active session.');
		}

		$this->current_session->setSystemPrompt($prompt);
	}

	/**
	 * Clears the current session's message history.
	 *
	 * @return void
	 *
	 * @throws AgentException If no session is active.
	 */
	public function clearSessionHistory(): void
	{
		if ($this->current_session === null) {
			throw new AgentException('No active session.');
		}

		$this->current_session->clearMessages();

		$this->logger->info('Session history cleared', [
			'session_id' => $this->current_session->getId()->toString(),
		]);
	}

	/**
	 * Stops the currently running agent loop.
	 *
	 * @return void
	 */
	public function stopProcessing(): void
	{
		if ($this->agent_loop->isRunning()) {
			$this->agent_loop->stop();
			$this->logger->info('Processing stop requested');
		}
	}

	/**
	 * Checks if the agent is currently processing.
	 *
	 * @return bool
	 */
	public function isProcessing(): bool
	{
		return $this->agent_loop->isRunning();
	}
}
