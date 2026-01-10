<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Core\Agent;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PhpCliAgent\Core\Agent\Agent;
use PhpCliAgent\Core\Contracts\AgentLoopInterface;
use PhpCliAgent\Core\Contracts\SessionInterface;
use PhpCliAgent\Core\Contracts\SessionRepositoryInterface;
use PhpCliAgent\Core\Exceptions\AgentException;
use PhpCliAgent\Core\Exceptions\SessionNotFoundException;
use PhpCliAgent\Core\Session\Session;
use PhpCliAgent\Core\ValueObjects\Message;
use PhpCliAgent\Core\ValueObjects\SessionId;

/**
 * Tests for Agent facade.
 *
 * @covers \PhpCliAgent\Core\Agent\Agent
 */
final class AgentTest extends TestCase
{
	private AgentLoopInterface&MockObject $agent_loop;
	private SessionRepositoryInterface&MockObject $session_repository;
	private Agent $agent;

	protected function setUp(): void
	{
		$this->agent_loop = $this->createMock(AgentLoopInterface::class);
		$this->session_repository = $this->createMock(SessionRepositoryInterface::class);

		$this->agent = new Agent(
			$this->agent_loop,
			$this->session_repository,
			'Default system prompt'
		);
	}

	public function test_startSession_createsNewSession(): void
	{
		$this->session_repository->expects($this->once())
			->method('save');

		$session_id = $this->agent->startSession();

		$this->assertInstanceOf(SessionId::class, $session_id);
		$this->assertNotNull($this->agent->getCurrentSession());
	}

	public function test_startSession_usesDefaultSystemPrompt(): void
	{
		$session_id = $this->agent->startSession();

		$session = $this->agent->getCurrentSession();
		$this->assertSame('Default system prompt', $session->getSystemPrompt());
	}

	public function test_resumeSession_loadsExistingSession(): void
	{
		$session_id = SessionId::fromString('existing-session');
		$existing_session = new Session($session_id, 'Existing prompt');
		$existing_session->addMessage(Message::user('Previous message'));

		$this->session_repository->method('exists')
			->with($session_id)
			->willReturn(true);

		$this->session_repository->method('load')
			->with($session_id)
			->willReturn($existing_session);

		$this->agent->resumeSession($session_id);

		$current = $this->agent->getCurrentSession();
		$this->assertSame($existing_session, $current);
		$this->assertSame(1, $current->getMessageCount());
	}

	public function test_resumeSession_throwsWhenSessionNotFound(): void
	{
		$session_id = SessionId::fromString('nonexistent');

		$this->session_repository->method('exists')
			->with($session_id)
			->willReturn(false);

		$this->expectException(SessionNotFoundException::class);
		$this->expectExceptionMessage('Session with ID "nonexistent" not found');

		$this->agent->resumeSession($session_id);
	}

	public function test_sendMessage_throwsWithoutActiveSession(): void
	{
		$this->expectException(AgentException::class);
		$this->expectExceptionMessage('No active session');

		$this->agent->sendMessage('Hello');
	}

	public function test_sendMessage_addsUserMessageAndRunsLoop(): void
	{
		$this->agent_loop->expects($this->once())
			->method('run')
			->with($this->isInstanceOf(SessionInterface::class));

		$this->session_repository->expects($this->atLeast(2))
			->method('save');

		$this->agent->startSession();
		$this->agent->sendMessage('Hello');

		$session = $this->agent->getCurrentSession();
		$messages = $session->getMessages();
		$this->assertCount(1, $messages);
		$this->assertSame(Message::ROLE_USER, $messages[0]->getRole());
		$this->assertSame('Hello', $messages[0]->getContent());
	}

	public function test_getCurrentSession_returnsNullInitially(): void
	{
		$this->assertNull($this->agent->getCurrentSession());
	}

	public function test_endSession_savesAndClearsSession(): void
	{
		$this->session_repository->expects($this->atLeast(2))
			->method('save');

		$this->agent->startSession();
		$this->agent->endSession();

		$this->assertNull($this->agent->getCurrentSession());
	}

	public function test_endSession_doesNothingWithoutSession(): void
	{
		$this->session_repository->expects($this->never())
			->method('save');

		$this->agent->endSession();
	}

	public function test_setDefaultSystemPrompt_changesPromptForNewSessions(): void
	{
		$this->agent->setDefaultSystemPrompt('New default prompt');

		$this->assertSame('New default prompt', $this->agent->getDefaultSystemPrompt());

		$this->agent->startSession();

		$this->assertSame('New default prompt', $this->agent->getCurrentSession()->getSystemPrompt());
	}

	public function test_setAutoSave_disablesSaving(): void
	{
		$this->agent->setAutoSave(false);

		$this->session_repository->expects($this->never())
			->method('save');

		$this->agent->startSession();
	}

	public function test_isAutoSaveEnabled_returnsCorrectState(): void
	{
		$this->assertTrue($this->agent->isAutoSaveEnabled());

		$this->agent->setAutoSave(false);

		$this->assertFalse($this->agent->isAutoSaveEnabled());
	}

	public function test_saveCurrentSession_savesSession(): void
	{
		$this->agent->setAutoSave(false);
		$this->agent->startSession();

		$this->session_repository->expects($this->once())
			->method('save');

		$this->agent->saveCurrentSession();
	}

	public function test_saveCurrentSession_throwsWithoutSession(): void
	{
		$this->expectException(AgentException::class);
		$this->expectExceptionMessage('No active session to save');

		$this->agent->saveCurrentSession();
	}

	public function test_deleteSession_deletesFromRepository(): void
	{
		$session_id = SessionId::fromString('to-delete');

		$this->session_repository->expects($this->once())
			->method('delete')
			->with($session_id)
			->willReturn(true);

		$result = $this->agent->deleteSession($session_id);

		$this->assertTrue($result);
	}

	public function test_deleteSession_clearsCurrentIfMatches(): void
	{
		$this->agent->startSession();
		$current_id = $this->agent->getCurrentSession()->getId();

		$this->session_repository->method('delete')->willReturn(true);

		$this->agent->deleteSession($current_id);

		$this->assertNull($this->agent->getCurrentSession());
	}

	public function test_listSessions_delegatesToRepository(): void
	{
		$expected = [
			[
				'id' => SessionId::fromString('session-1'),
				'metadata' => $this->createMock(\PhpCliAgent\Core\Contracts\SessionMetadataInterface::class),
			],
		];

		$this->session_repository->expects($this->once())
			->method('listWithMetadata')
			->willReturn($expected);

		$result = $this->agent->listSessions();

		$this->assertSame($expected, $result);
	}

	public function test_getAgentLoop_returnsLoop(): void
	{
		$this->assertSame($this->agent_loop, $this->agent->getAgentLoop());
	}

	public function test_setSessionSystemPrompt_changesPrompt(): void
	{
		$this->agent->startSession();

		$this->agent->setSessionSystemPrompt('Custom prompt');

		$this->assertSame('Custom prompt', $this->agent->getCurrentSession()->getSystemPrompt());
	}

	public function test_setSessionSystemPrompt_throwsWithoutSession(): void
	{
		$this->expectException(AgentException::class);
		$this->expectExceptionMessage('No active session');

		$this->agent->setSessionSystemPrompt('Prompt');
	}

	public function test_clearSessionHistory_clearsMessages(): void
	{
		$this->agent->startSession();
		$this->agent_loop->method('run');
		$this->agent->sendMessage('Hello');

		$this->assertSame(1, $this->agent->getCurrentSession()->getMessageCount());

		$this->agent->clearSessionHistory();

		$this->assertSame(0, $this->agent->getCurrentSession()->getMessageCount());
	}

	public function test_clearSessionHistory_throwsWithoutSession(): void
	{
		$this->expectException(AgentException::class);
		$this->expectExceptionMessage('No active session');

		$this->agent->clearSessionHistory();
	}

	public function test_stopProcessing_stopsRunningLoop(): void
	{
		$this->agent_loop->method('isRunning')->willReturn(true);

		$this->agent_loop->expects($this->once())
			->method('stop');

		$this->agent->stopProcessing();
	}

	public function test_stopProcessing_doesNothingWhenNotRunning(): void
	{
		$this->agent_loop->method('isRunning')->willReturn(false);

		$this->agent_loop->expects($this->never())
			->method('stop');

		$this->agent->stopProcessing();
	}

	public function test_isProcessing_delegatesToLoop(): void
	{
		$this->agent_loop->method('isRunning')
			->willReturnOnConsecutiveCalls(false, true);

		$this->assertFalse($this->agent->isProcessing());
		$this->assertTrue($this->agent->isProcessing());
	}

	public function test_constructor_acceptsEmptySystemPrompt(): void
	{
		$agent = new Agent(
			$this->agent_loop,
			$this->session_repository,
			''
		);

		$this->assertSame('', $agent->getDefaultSystemPrompt());
	}
}
