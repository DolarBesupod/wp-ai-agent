<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Core\Session;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Automattic\WpAiAgent\Core\Session\Session;
use Automattic\WpAiAgent\Core\Session\SessionMetadata;
use Automattic\WpAiAgent\Core\ValueObjects\Message;
use Automattic\WpAiAgent\Core\ValueObjects\SessionId;

/**
 * Tests for Session.
 *
 * @covers \Automattic\WpAiAgent\Core\Session\Session
 */
final class SessionTest extends TestCase
{
	public function test_constructor_generatesIdWhenNotProvided(): void
	{
		$session = new Session();

		$this->assertNotEmpty($session->getId()->toString());
	}

	public function test_constructor_usesProvidedId(): void
	{
		$id = SessionId::fromString('test-session-id');

		$session = new Session($id);

		$this->assertTrue($id->equals($session->getId()));
	}

	public function test_constructor_setsSystemPrompt(): void
	{
		$session = new Session(null, 'You are a helpful assistant.');

		$this->assertSame('You are a helpful assistant.', $session->getSystemPrompt());
	}

	public function test_constructor_setsMetadata(): void
	{
		$metadata = new SessionMetadata(null, null, '/custom/path');

		$session = new Session(null, '', $metadata);

		$this->assertSame('/custom/path', $session->getMetadata()->getWorkingDirectory());
	}

	public function test_getMessages_returnsEmptyArrayInitially(): void
	{
		$session = new Session();

		$this->assertSame([], $session->getMessages());
	}

	public function test_addMessage_storesMessage(): void
	{
		$session = new Session();
		$message = Message::user('Hello');

		$session->addMessage($message);

		$messages = $session->getMessages();
		$this->assertCount(1, $messages);
		$this->assertSame($message, $messages[0]);
	}

	public function test_addMessage_appendsMultipleMessages(): void
	{
		$session = new Session();
		$msg1 = Message::user('First');
		$msg2 = Message::assistant('Second');
		$msg3 = Message::user('Third');

		$session->addMessage($msg1);
		$session->addMessage($msg2);
		$session->addMessage($msg3);

		$messages = $session->getMessages();
		$this->assertCount(3, $messages);
		$this->assertSame($msg1, $messages[0]);
		$this->assertSame($msg2, $messages[1]);
		$this->assertSame($msg3, $messages[2]);
	}

	public function test_addMessage_updatesMetadataTimestamp(): void
	{
		$session = new Session();
		$before = $session->getMetadata()->getUpdatedAt();

		usleep(1000);
		$session->addMessage(Message::user('Test'));

		$this->assertGreaterThan($before, $session->getMetadata()->getUpdatedAt());
	}

	public function test_addMessage_derivesTitleFromFirstUserMessage(): void
	{
		$session = new Session();

		$session->addMessage(Message::user('How do I install PHP?'));

		$this->assertSame('How do I install PHP?', $session->getMetadata()->getTitle());
	}

	public function test_addMessage_truncatesTitleWhenLong(): void
	{
		$session = new Session();
		$long_message = str_repeat('a', 100);

		$session->addMessage(Message::user($long_message));

		$title = $session->getMetadata()->getTitle();
		$this->assertLessThanOrEqual(50, mb_strlen($title));
		$this->assertStringEndsWith('...', $title);
	}

	public function test_addMessage_doesNotOverwriteExistingTitle(): void
	{
		$session = new Session();
		$session->getMetadata()->setTitle('Custom Title');

		$session->addMessage(Message::user('New message'));

		$this->assertSame('Custom Title', $session->getMetadata()->getTitle());
	}

	public function test_setSystemPrompt_changesPrompt(): void
	{
		$session = new Session();

		$session->setSystemPrompt('New prompt');

		$this->assertSame('New prompt', $session->getSystemPrompt());
	}

	public function test_clearMessages_removesAllMessages(): void
	{
		$session = new Session();
		$session->addMessage(Message::user('Message 1'));
		$session->addMessage(Message::assistant('Message 2'));

		$session->clearMessages();

		$this->assertSame([], $session->getMessages());
		$this->assertSame(0, $session->getMessageCount());
	}

	public function test_getMessageCount_returnsCorrectCount(): void
	{
		$session = new Session();
		$this->assertSame(0, $session->getMessageCount());

		$session->addMessage(Message::user('One'));
		$this->assertSame(1, $session->getMessageCount());

		$session->addMessage(Message::assistant('Two'));
		$this->assertSame(2, $session->getMessageCount());
	}

	public function test_getLastMessage_returnsNullWhenEmpty(): void
	{
		$session = new Session();

		$this->assertNull($session->getLastMessage());
	}

	public function test_getLastMessage_returnsLastMessage(): void
	{
		$session = new Session();
		$msg1 = Message::user('First');
		$msg2 = Message::assistant('Last');

		$session->addMessage($msg1);
		$session->addMessage($msg2);

		$this->assertSame($msg2, $session->getLastMessage());
	}

	public function test_getMessagesForApi_excludesSystemMessages(): void
	{
		$session = new Session();
		$session->addMessage(Message::system('System instruction'));
		$session->addMessage(Message::user('Hello'));
		$session->addMessage(Message::assistant('Hi there'));

		$api_messages = $session->getMessagesForApi();

		$this->assertCount(2, $api_messages);
		$this->assertSame('user', $api_messages[0]['role']);
		$this->assertSame('assistant', $api_messages[1]['role']);
	}

	public function test_addTokenUsage_accumulatesTokens(): void
	{
		$session = new Session();

		$session->addTokenUsage(100, 50);
		$session->addTokenUsage(200, 100);

		$usage = $session->getTokenUsage();
		$this->assertSame(300, $usage['input']);
		$this->assertSame(150, $usage['output']);
		$this->assertSame(450, $usage['total']);
	}

	public function test_getTokenUsage_returnsZerosInitially(): void
	{
		$session = new Session();

		$usage = $session->getTokenUsage();

		$this->assertSame(0, $usage['input']);
		$this->assertSame(0, $usage['output']);
		$this->assertSame(0, $usage['total']);
	}

	public function test_resetTokenUsage_clearsCounters(): void
	{
		$session = new Session();
		$session->addTokenUsage(500, 250);

		$session->resetTokenUsage();

		$usage = $session->getTokenUsage();
		$this->assertSame(0, $usage['input']);
		$this->assertSame(0, $usage['output']);
	}

	public function test_toArray_serializesCorrectly(): void
	{
		$id = SessionId::fromString('test-id');
		$session = new Session($id, 'System prompt');
		$session->addMessage(Message::user('Hello'));
		$session->addTokenUsage(100, 50);

		$array = $session->toArray();

		$this->assertSame('test-id', $array['id']);
		$this->assertSame('System prompt', $array['system_prompt']);
		$this->assertCount(1, $array['messages']);
		$this->assertArrayHasKey('metadata', $array);
		$this->assertSame(['input' => 100, 'output' => 50], $array['token_usage']);
	}

	public function test_fromArray_reconstructsSession(): void
	{
		$data = [
			'id' => 'restored-id',
			'system_prompt' => 'Restored prompt',
			'messages' => [
				['role' => 'user', 'content' => 'Hello'],
				['role' => 'assistant', 'content' => 'Hi'],
			],
			'metadata' => [
				'created_at' => '2024-01-01T10:00:00+00:00',
				'updated_at' => '2024-01-01T11:00:00+00:00',
				'working_directory' => '/restored/path',
				'custom' => ['title' => 'Test Session'],
			],
			'token_usage' => ['input' => 500, 'output' => 200],
		];

		$session = Session::fromArray($data);

		$this->assertSame('restored-id', $session->getId()->toString());
		$this->assertSame('Restored prompt', $session->getSystemPrompt());
		$this->assertCount(2, $session->getMessages());
		$this->assertSame('/restored/path', $session->getMetadata()->getWorkingDirectory());
		$this->assertSame(500, $session->getTokenUsage()['input']);
		$this->assertSame(200, $session->getTokenUsage()['output']);
	}

	public function test_fromArray_handlesMinimalData(): void
	{
		$data = [
			'id' => 'minimal-id',
			'system_prompt' => '',
			'messages' => [],
			'metadata' => [],
		];

		$session = Session::fromArray($data);

		$this->assertSame('minimal-id', $session->getId()->toString());
		$this->assertSame(0, $session->getMessageCount());
		$this->assertSame(0, $session->getTokenUsage()['total']);
	}

	public function test_roundTrip_preservesAllData(): void
	{
		$original = new Session(
			SessionId::fromString('round-trip-id'),
			'System prompt for testing'
		);
		$original->addMessage(Message::user('User question'));
		$original->addMessage(Message::assistant('Assistant answer'));
		$original->addTokenUsage(150, 75);
		$original->getMetadata()->setTitle('Round Trip Test');

		$serialized = $original->toArray();
		$restored = Session::fromArray($serialized);

		$this->assertSame($original->getId()->toString(), $restored->getId()->toString());
		$this->assertSame($original->getSystemPrompt(), $restored->getSystemPrompt());
		$this->assertSame($original->getMessageCount(), $restored->getMessageCount());
		$this->assertSame($original->getTokenUsage(), $restored->getTokenUsage());
		$this->assertSame(
			$original->getMetadata()->getTitle(),
			$restored->getMetadata()->getTitle()
		);
	}
}
