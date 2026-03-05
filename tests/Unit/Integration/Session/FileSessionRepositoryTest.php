<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Integration\Session;

use PHPUnit\Framework\TestCase;
use Automattic\Automattic\WpAiAgent\Core\Contracts\SessionMetadataInterface;
use Automattic\Automattic\WpAiAgent\Core\Exceptions\SessionNotFoundException;
use Automattic\Automattic\WpAiAgent\Core\Exceptions\SessionPersistenceException;
use Automattic\Automattic\WpAiAgent\Core\Session\Session;
use Automattic\Automattic\WpAiAgent\Core\Session\SessionMetadata;
use Automattic\Automattic\WpAiAgent\Core\ValueObjects\Message;
use Automattic\Automattic\WpAiAgent\Core\ValueObjects\SessionId;
use Automattic\Automattic\WpAiAgent\Integration\Session\FileSessionRepository;

/**
 * Tests for FileSessionRepository.
 *
 * @covers \Automattic\WpAiAgent\Integration\Session\FileSessionRepository
 */
final class FileSessionRepositoryTest extends TestCase
{
	private string $test_storage_path;
	private FileSessionRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();

		$this->test_storage_path = sys_get_temp_dir() . '/php-cli-agent-test-' . uniqid('', true);
		$this->repository = new FileSessionRepository($this->test_storage_path);
	}

	protected function tearDown(): void
	{
		parent::tearDown();

		$this->cleanupTestDirectory();
	}

	public function test_constructor_usesCustomStoragePath(): void
	{
		$custom_path = '/custom/storage/path';
		$repository = new FileSessionRepository($custom_path);

		$this->assertSame($custom_path, $repository->getStoragePath());
	}

	public function test_constructor_usesDefaultPathWhenNull(): void
	{
		$repository = new FileSessionRepository(null);

		$storage_path = $repository->getStoragePath();
		$this->assertStringContainsString('.wp-ai-agent', $storage_path);
		$this->assertStringContainsString('sessions', $storage_path);
	}

	public function test_save_createsSessionFile(): void
	{
		$session = $this->createTestSession('save-test-id');

		$this->repository->save($session);

		$file_path = $this->test_storage_path . '/save-test-id.json';
		$this->assertFileExists($file_path);
	}

	public function test_save_createsStorageDirectoryIfNotExists(): void
	{
		$this->assertDirectoryDoesNotExist($this->test_storage_path);

		$session = $this->createTestSession('create-dir-test');
		$this->repository->save($session);

		$this->assertDirectoryExists($this->test_storage_path);
	}

	public function test_save_writesValidJsonContent(): void
	{
		$session = $this->createTestSession('json-content-test');

		$this->repository->save($session);

		$file_path = $this->test_storage_path . '/json-content-test.json';
		$content = file_get_contents($file_path);
		$data = json_decode($content, true);

		$this->assertSame(JSON_ERROR_NONE, json_last_error());
		$this->assertSame('json-content-test', $data['id']);
	}

	public function test_save_updatesExistingSession(): void
	{
		$session = $this->createTestSession('update-test');
		$this->repository->save($session);

		$session->addMessage(Message::user('New message'));
		$this->repository->save($session);

		$loaded = $this->repository->load($session->getId());
		$this->assertSame(2, $loaded->getMessageCount());
	}

	public function test_load_returnsStoredSession(): void
	{
		$original = $this->createTestSession('load-test');
		$this->repository->save($original);

		$loaded = $this->repository->load($original->getId());

		$this->assertSame($original->getId()->toString(), $loaded->getId()->toString());
		$this->assertSame($original->getSystemPrompt(), $loaded->getSystemPrompt());
		$this->assertSame($original->getMessageCount(), $loaded->getMessageCount());
	}

	public function test_load_reconstructsMessages(): void
	{
		$original = $this->createTestSession('message-load-test');
		$original->addMessage(Message::assistant('Response'));
		$this->repository->save($original);

		$loaded = $this->repository->load($original->getId());

		$messages = $loaded->getMessages();
		$this->assertCount(2, $messages);
		$this->assertSame('Test message', $messages[0]->getContent());
		$this->assertSame('Response', $messages[1]->getContent());
	}

	public function test_load_reconstructsTokenUsage(): void
	{
		$original = $this->createTestSession('token-test');
		$original->addTokenUsage(200, 100);
		$this->repository->save($original);

		$loaded = $this->repository->load($original->getId());

		$this->assertSame(300, $loaded->getTokenUsage()['input']);
		$this->assertSame(150, $loaded->getTokenUsage()['output']);
	}

	public function test_load_throwsExceptionForNonExistentSession(): void
	{
		$session_id = SessionId::fromString('non-existent-id');

		$this->expectException(SessionNotFoundException::class);

		$this->repository->load($session_id);
	}

	public function test_exists_returnsTrueForExistingSession(): void
	{
		$session = $this->createTestSession('exists-test');
		$this->repository->save($session);

		$this->assertTrue($this->repository->exists($session->getId()));
	}

	public function test_exists_returnsFalseForNonExistentSession(): void
	{
		$session_id = SessionId::fromString('does-not-exist');

		$this->assertFalse($this->repository->exists($session_id));
	}

	public function test_delete_removesSessionFile(): void
	{
		$session = $this->createTestSession('delete-test');
		$this->repository->save($session);

		$result = $this->repository->delete($session->getId());

		$this->assertTrue($result);
		$this->assertFalse($this->repository->exists($session->getId()));
	}

	public function test_delete_returnsFalseForNonExistentSession(): void
	{
		$session_id = SessionId::fromString('never-existed');

		$result = $this->repository->delete($session_id);

		$this->assertFalse($result);
	}

	public function test_listAll_returnsEmptyArrayWhenNoSessions(): void
	{
		$result = $this->repository->listAll();

		$this->assertSame([], $result);
	}

	public function test_listAll_returnsAllSessionIds(): void
	{
		$this->repository->save($this->createTestSession('session-1'));
		$this->repository->save($this->createTestSession('session-2'));
		$this->repository->save($this->createTestSession('session-3'));

		$result = $this->repository->listAll();

		$this->assertCount(3, $result);

		$ids = array_map(fn(SessionId $id) => $id->toString(), $result);
		$this->assertContains('session-1', $ids);
		$this->assertContains('session-2', $ids);
		$this->assertContains('session-3', $ids);
	}

	public function test_listAll_returnsEmptyArrayWhenDirectoryNotExists(): void
	{
		$repository = new FileSessionRepository('/non/existent/path');

		$result = $repository->listAll();

		$this->assertSame([], $result);
	}

	public function test_listWithMetadata_returnsSessionsWithMetadata(): void
	{
		$session = $this->createTestSession('meta-list-test');
		$session->getMetadata()->setTitle('Test Session');
		$this->repository->save($session);

		$result = $this->repository->listWithMetadata();

		$this->assertCount(1, $result);
		$this->assertSame('meta-list-test', $result[0]['id']->toString());
		$this->assertInstanceOf(SessionMetadataInterface::class, $result[0]['metadata']);
		$this->assertSame('Test Session', $result[0]['metadata']->getTitle());
	}

	public function test_listWithMetadata_sortsByUpdatedAtDescending(): void
	{
		// Create sessions with different update times.
		$old_session = new Session(
			SessionId::fromString('old-session'),
			'',
			new SessionMetadata(
				new \DateTimeImmutable('2024-01-01 10:00:00'),
				new \DateTimeImmutable('2024-01-01 10:00:00')
			)
		);
		$new_session = new Session(
			SessionId::fromString('new-session'),
			'',
			new SessionMetadata(
				new \DateTimeImmutable('2024-01-02 10:00:00'),
				new \DateTimeImmutable('2024-01-02 10:00:00')
			)
		);

		$this->repository->save($old_session);
		$this->repository->save($new_session);

		$result = $this->repository->listWithMetadata();

		$this->assertSame('new-session', $result[0]['id']->toString());
		$this->assertSame('old-session', $result[1]['id']->toString());
	}

	public function test_findRecent_returnsLimitedSessions(): void
	{
		for ($i = 1; $i <= 15; $i++) {
			$session = new Session(
				SessionId::fromString("session-{$i}"),
				'',
				new SessionMetadata(
					new \DateTimeImmutable("2024-01-{$i} 10:00:00"),
					new \DateTimeImmutable("2024-01-{$i} 10:00:00")
				)
			);
			$this->repository->save($session);
		}

		$result = $this->repository->findRecent(5);

		$this->assertCount(5, $result);
		// Should return most recent first.
		$this->assertSame('session-15', $result[0]['id']->toString());
		$this->assertSame('session-14', $result[1]['id']->toString());
	}

	public function test_findRecent_returnsAllWhenLessThanLimit(): void
	{
		$this->repository->save($this->createTestSession('only-session'));

		$result = $this->repository->findRecent(10);

		$this->assertCount(1, $result);
	}

	public function test_findRecent_usesDefaultLimitOfTen(): void
	{
		for ($i = 1; $i <= 15; $i++) {
			$this->repository->save($this->createTestSession("session-{$i}"));
		}

		$result = $this->repository->findRecent();

		$this->assertCount(10, $result);
	}

	public function test_roundTrip_preservesCompleteSession(): void
	{
		$original = new Session(
			SessionId::fromString('round-trip-test'),
			'System prompt for testing'
		);
		$original->addMessage(Message::user('User question'));
		$original->addMessage(Message::assistant('Assistant answer'));
		$original->addMessage(Message::toolResult('call-1', 'bash', 'Output'));
		$original->addTokenUsage(500, 250);
		$original->getMetadata()->setTitle('Round Trip Test');
		$original->getMetadata()->set('custom_key', 'custom_value');

		$this->repository->save($original);
		$loaded = $this->repository->load($original->getId());

		$this->assertSame($original->getId()->toString(), $loaded->getId()->toString());
		$this->assertSame($original->getSystemPrompt(), $loaded->getSystemPrompt());
		$this->assertSame($original->getMessageCount(), $loaded->getMessageCount());
		$this->assertSame($original->getTokenUsage()['input'], $loaded->getTokenUsage()['input']);
		$this->assertSame($original->getTokenUsage()['output'], $loaded->getTokenUsage()['output']);
		$this->assertSame($original->getMetadata()->getTitle(), $loaded->getMetadata()->getTitle());
		$this->assertSame('custom_value', $loaded->getMetadata()->get('custom_key'));
	}

	public function test_save_handlesUnicodeContent(): void
	{
		$session = new Session(SessionId::fromString('unicode-test'));
		$session->addMessage(Message::user('Hello! 👋 Привет! 你好!'));

		$this->repository->save($session);
		$loaded = $this->repository->load($session->getId());

		$this->assertSame('Hello! 👋 Привет! 你好!', $loaded->getMessages()[0]->getContent());
	}

	public function test_save_handlesSpecialCharactersInContent(): void
	{
		$session = new Session(SessionId::fromString('special-chars-test'));
		$session->addMessage(Message::user("Line1\nLine2\tTabbed \"quoted\" 'single'"));

		$this->repository->save($session);
		$loaded = $this->repository->load($session->getId());

		$this->assertSame("Line1\nLine2\tTabbed \"quoted\" 'single'", $loaded->getMessages()[0]->getContent());
	}

	public function test_save_handlesLargeContent(): void
	{
		$large_content = str_repeat('A', 100000);
		$session = new Session(SessionId::fromString('large-content-test'));
		$session->addMessage(Message::user($large_content));

		$this->repository->save($session);
		$loaded = $this->repository->load($session->getId());

		$this->assertSame($large_content, $loaded->getMessages()[0]->getContent());
	}

	public function test_concurrentSaves_doNotCorruptData(): void
	{
		$session = $this->createTestSession('concurrent-test');

		// Simulate multiple rapid saves.
		for ($i = 0; $i < 10; $i++) {
			$session->addMessage(Message::user("Message {$i}"));
			$this->repository->save($session);
		}

		$loaded = $this->repository->load($session->getId());

		// Original message + 10 added messages.
		$this->assertSame(11, $loaded->getMessageCount());
	}

	/**
	 * Creates a test session with standard content.
	 */
	private function createTestSession(string $id): Session
	{
		$session = new Session(
			SessionId::fromString($id),
			'Test system prompt'
		);
		$session->addMessage(Message::user('Test message'));
		$session->addTokenUsage(100, 50);

		return $session;
	}

	/**
	 * Cleans up the test storage directory.
	 */
	private function cleanupTestDirectory(): void
	{
		if (!is_dir($this->test_storage_path)) {
			return;
		}

		$files = glob($this->test_storage_path . '/*');
		if (false !== $files) {
			foreach ($files as $file) {
				if (is_file($file)) {
					unlink($file);
				}
			}
		}

		rmdir($this->test_storage_path);
	}
}
