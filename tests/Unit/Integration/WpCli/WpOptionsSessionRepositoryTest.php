<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Integration\WpCli;

use PHPUnit\Framework\TestCase;
use Tests\Stubs\WpOptionsStore;
use WpAiAgent\Core\Contracts\SessionRepositoryInterface;
use WpAiAgent\Core\Exceptions\SessionNotFoundException;
use WpAiAgent\Core\Exceptions\SessionPersistenceException;
use WpAiAgent\Core\Session\Session;
use WpAiAgent\Core\ValueObjects\Message;
use WpAiAgent\Core\ValueObjects\SessionId;
use WpAiAgent\Integration\WpCli\WpOptionsSessionRepository;

/**
 * Unit tests for WpOptionsSessionRepository.
 *
 * WordPress functions (get_option, update_option, delete_option) are provided
 * by the stub in tests/Stubs/WpFunctionsStub.php, which is loaded by
 * tests/bootstrap.php. WpOptionsStore::reset() is called in setUp() to ensure
 * complete isolation between test cases.
 *
 * @covers \WpAiAgent\Integration\WpCli\WpOptionsSessionRepository
 *
 * @since n.e.x.t
 */
final class WpOptionsSessionRepositoryTest extends TestCase
{
	private WpOptionsSessionRepository $repository;

	/**
	 * Resets the in-memory option store and creates a fresh repository before
	 * each test.
	 */
	protected function setUp(): void
	{
		parent::setUp();

		WpOptionsStore::reset();
		$this->repository = new WpOptionsSessionRepository();
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Creates a minimal test session with a known ID and one user message.
	 *
	 * @param string $id The session ID string.
	 *
	 * @return Session
	 */
	private function createTestSession(string $id): Session
	{
		$session = new Session(SessionId::fromString($id), 'Test system prompt');
		$session->addMessage(Message::user('Hello from test'));

		return $session;
	}

	// -----------------------------------------------------------------------
	// save()
	// -----------------------------------------------------------------------

	/**
	 * Tests that save() stores the session JSON under the expected option key.
	 */
	public function test_save_storesSessionAsOption(): void
	{
		$session = $this->createTestSession('abc123');

		$this->repository->save($session);

		$stored = WpOptionsStore::get('wp_ai_agent_session_abc123', false);

		$this->assertIsString($stored);
		$data = json_decode($stored, true);
		$this->assertSame('abc123', $data['id']);
	}

	/**
	 * Tests that save() adds the session ID to the index option.
	 */
	public function test_save_addsIdToIndex(): void
	{
		$session = $this->createTestSession('abc123');

		$this->repository->save($session);

		$ids = array_map(
			static fn (SessionId $id): string => $id->toString(),
			$this->repository->listAll()
		);

		$this->assertContains('abc123', $ids);
	}

	/**
	 * Tests that saving the same session twice results in the ID appearing only
	 * once in the index.
	 */
	public function test_save_twice_idAppearsOnceInIndex(): void
	{
		$session = $this->createTestSession('dup-id');

		$this->repository->save($session);
		$this->repository->save($session);

		$all = $this->repository->listAll();
		$matching = array_filter(
			$all,
			static fn (SessionId $id): bool => $id->toString() === 'dup-id'
		);

		$this->assertCount(1, $matching);
	}

	/**
	 * Tests that saving a session with messages preserves those messages after
	 * a subsequent load.
	 */
	public function test_save_persistsMessagesForLaterLoad(): void
	{
		$session = new Session(SessionId::fromString('msg-test'), 'system');
		$session->addMessage(Message::user('First question'));
		$session->addMessage(Message::assistant('First answer'));

		$this->repository->save($session);
		$loaded = $this->repository->load(SessionId::fromString('msg-test'));

		$this->assertSame(2, $loaded->getMessageCount());
	}

	// -----------------------------------------------------------------------
	// load()
	// -----------------------------------------------------------------------

	/**
	 * Tests that load() reconstructs a session with the correct ID and message
	 * content after a save.
	 */
	public function test_load_returnsReconstructedSession(): void
	{
		$session = $this->createTestSession('load-me');

		$this->repository->save($session);
		$loaded = $this->repository->load(SessionId::fromString('load-me'));

		$this->assertSame('load-me', $loaded->getId()->toString());
		$this->assertSame('Test system prompt', $loaded->getSystemPrompt());
		$this->assertSame(1, $loaded->getMessageCount());
		$this->assertSame('Hello from test', $loaded->getMessages()[0]->getContent());
	}

	/**
	 * Tests that load() throws SessionNotFoundException when the option does
	 * not exist.
	 */
	public function test_load_whenOptionMissing_throwsSessionNotFoundException(): void
	{
		$this->expectException(SessionNotFoundException::class);

		$this->repository->load(SessionId::fromString('does-not-exist'));
	}

	/**
	 * Tests that load() throws SessionPersistenceException when the stored
	 * option value is not valid JSON.
	 */
	public function test_load_withInvalidJson_throwsSessionPersistenceException(): void
	{
		// Inject malformed JSON directly into the store, bypassing save().
		WpOptionsStore::set('wp_ai_agent_session_bad-json', 'not-json');

		$this->expectException(SessionPersistenceException::class);

		$this->repository->load(SessionId::fromString('bad-json'));
	}

	// -----------------------------------------------------------------------
	// exists()
	// -----------------------------------------------------------------------

	/**
	 * Tests that exists() returns true after the session is saved.
	 */
	public function test_exists_returnsTrueAfterSave(): void
	{
		$session = $this->createTestSession('exists-yes');
		$this->repository->save($session);

		$this->assertTrue($this->repository->exists(SessionId::fromString('exists-yes')));
	}

	/**
	 * Tests that exists() returns false when the session has never been saved.
	 */
	public function test_exists_returnsFalseBeforeSave(): void
	{
		$this->assertFalse($this->repository->exists(SessionId::fromString('never-saved')));
	}

	// -----------------------------------------------------------------------
	// delete()
	// -----------------------------------------------------------------------

	/**
	 * Tests that delete() returns true when the session existed.
	 */
	public function test_delete_returnsTrueIfExisted(): void
	{
		$session = $this->createTestSession('del-me');
		$this->repository->save($session);

		$result = $this->repository->delete(SessionId::fromString('del-me'));

		$this->assertTrue($result);
	}

	/**
	 * Tests that delete() returns false when the session did not exist.
	 */
	public function test_delete_returnsFalseIfNotExisted(): void
	{
		$result = $this->repository->delete(SessionId::fromString('ghost'));

		$this->assertFalse($result);
	}

	/**
	 * Tests that delete() removes the session from the index so that listAll()
	 * no longer includes it.
	 */
	public function test_delete_removesFromIndex(): void
	{
		$session = $this->createTestSession('del-index');
		$this->repository->save($session);

		$this->repository->delete(SessionId::fromString('del-index'));

		$all = $this->repository->listAll();

		$this->assertEmpty($all);
	}

	/**
	 * Tests that delete() removes the per-session option so that exists()
	 * returns false afterwards.
	 */
	public function test_delete_removesSessionOption(): void
	{
		$session = $this->createTestSession('del-option');
		$this->repository->save($session);

		$this->repository->delete(SessionId::fromString('del-option'));

		$this->assertFalse($this->repository->exists(SessionId::fromString('del-option')));
	}

	// -----------------------------------------------------------------------
	// listAll()
	// -----------------------------------------------------------------------

	/**
	 * Tests that listAll() returns an empty array when no sessions have been
	 * saved.
	 */
	public function test_listAll_returnsEmptyArrayWhenNoSessions(): void
	{
		$result = $this->repository->listAll();

		$this->assertSame([], $result);
	}

	/**
	 * Tests that listAll() returns the correct number of SessionId objects
	 * after multiple saves.
	 */
	public function test_listAll_returnsAllSavedSessionIds(): void
	{
		$this->repository->save($this->createTestSession('s1'));
		$this->repository->save($this->createTestSession('s2'));
		$this->repository->save($this->createTestSession('s3'));

		$all = $this->repository->listAll();

		$this->assertCount(3, $all);

		$ids = array_map(static fn (SessionId $id): string => $id->toString(), $all);
		$this->assertContains('s1', $ids);
		$this->assertContains('s2', $ids);
		$this->assertContains('s3', $ids);
	}

	/**
	 * Tests that listAll() returns an empty array when the index option does
	 * not exist in the store (i.e., the store was never written to).
	 */
	public function test_listAll_whenIndexOptionMissing_returnsEmptyArray(): void
	{
		// The store was reset in setUp(); the index option is absent.
		$result = $this->repository->listAll();

		$this->assertSame([], $result);
	}

	// -----------------------------------------------------------------------
	// listWithMetadata()
	// -----------------------------------------------------------------------

	/**
	 * Tests that listWithMetadata() returns an entry with id and metadata for
	 * each saved session.
	 */
	public function test_listWithMetadata_returnsIdAndMetadataEntries(): void
	{
		$this->repository->save($this->createTestSession('meta-a'));
		$this->repository->save($this->createTestSession('meta-b'));

		$result = $this->repository->listWithMetadata();

		$this->assertCount(2, $result);

		foreach ($result as $entry) {
			$this->assertArrayHasKey('id', $entry);
			$this->assertArrayHasKey('metadata', $entry);
			$this->assertInstanceOf(SessionId::class, $entry['id']);
		}
	}

	// -----------------------------------------------------------------------
	// Interface contract
	// -----------------------------------------------------------------------

	/**
	 * Tests that WpOptionsSessionRepository implements SessionRepositoryInterface.
	 */
	public function test_implementsSessionRepositoryInterface(): void
	{
		$this->assertInstanceOf(SessionRepositoryInterface::class, $this->repository);
	}
}
