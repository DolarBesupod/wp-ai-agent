<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Integration\Session;

use PHPUnit\Framework\TestCase;
use PhpCliAgent\Core\Exceptions\SessionPersistenceException;
use PhpCliAgent\Core\Session\Session;
use PhpCliAgent\Core\Session\SessionMetadata;
use PhpCliAgent\Core\ValueObjects\Message;
use PhpCliAgent\Core\ValueObjects\SessionId;
use PhpCliAgent\Integration\Session\JsonSessionSerializer;

/**
 * Tests for JsonSessionSerializer.
 *
 * @covers \PhpCliAgent\Integration\Session\JsonSessionSerializer
 */
final class JsonSessionSerializerTest extends TestCase
{
	private JsonSessionSerializer $serializer;

	protected function setUp(): void
	{
		parent::setUp();
		$this->serializer = new JsonSessionSerializer();
	}

	public function test_serialize_returnsValidJson(): void
	{
		$session = $this->createTestSession();

		$json = $this->serializer->serialize($session);

		$decoded = json_decode($json, true);
		$this->assertNotNull($decoded);
		$this->assertSame(JSON_ERROR_NONE, json_last_error());
	}

	public function test_serialize_containsRequiredFields(): void
	{
		$session = $this->createTestSession();

		$json = $this->serializer->serialize($session);
		$data = json_decode($json, true);

		$this->assertArrayHasKey('id', $data);
		$this->assertArrayHasKey('system_prompt', $data);
		$this->assertArrayHasKey('messages', $data);
		$this->assertArrayHasKey('metadata', $data);
		$this->assertArrayHasKey('token_usage', $data);
	}

	public function test_serialize_containsCorrectSessionId(): void
	{
		$session_id = SessionId::fromString('test-serializer-id');
		$session = new Session($session_id, 'Test prompt');

		$json = $this->serializer->serialize($session);
		$data = json_decode($json, true);

		$this->assertSame('test-serializer-id', $data['id']);
	}

	public function test_serialize_containsSystemPrompt(): void
	{
		$session = new Session(null, 'You are a helpful assistant.');

		$json = $this->serializer->serialize($session);
		$data = json_decode($json, true);

		$this->assertSame('You are a helpful assistant.', $data['system_prompt']);
	}

	public function test_serialize_containsMessages(): void
	{
		$session = new Session();
		$session->addMessage(Message::user('Hello'));
		$session->addMessage(Message::assistant('Hi there!'));

		$json = $this->serializer->serialize($session);
		$data = json_decode($json, true);

		$this->assertCount(2, $data['messages']);
		$this->assertSame('user', $data['messages'][0]['role']);
		$this->assertSame('Hello', $data['messages'][0]['content']);
		$this->assertSame('assistant', $data['messages'][1]['role']);
	}

	public function test_serialize_containsTokenUsage(): void
	{
		$session = new Session();
		$session->addTokenUsage(100, 50);

		$json = $this->serializer->serialize($session);
		$data = json_decode($json, true);

		$this->assertSame(['input' => 100, 'output' => 50], $data['token_usage']);
	}

	public function test_serialize_containsMetadataWithTimestamps(): void
	{
		$session = $this->createTestSession();

		$json = $this->serializer->serialize($session);
		$data = json_decode($json, true);

		$this->assertArrayHasKey('created_at', $data['metadata']);
		$this->assertArrayHasKey('updated_at', $data['metadata']);
		$this->assertArrayHasKey('working_directory', $data['metadata']);
	}

	public function test_serialize_outputsJsonWithPrettyPrint(): void
	{
		$session = $this->createTestSession();

		$json = $this->serializer->serialize($session);

		$this->assertStringContainsString("\n", $json);
		$this->assertStringContainsString('    ', $json);
	}

	public function test_deserialize_reconstructsSession(): void
	{
		$original = $this->createTestSession();
		$json = $this->serializer->serialize($original);

		$restored = $this->serializer->deserialize($json);

		$this->assertSame($original->getId()->toString(), $restored->getId()->toString());
		$this->assertSame($original->getSystemPrompt(), $restored->getSystemPrompt());
		$this->assertSame($original->getMessageCount(), $restored->getMessageCount());
	}

	public function test_deserialize_reconstructsMessages(): void
	{
		$original = new Session(SessionId::fromString('msg-test'));
		$original->addMessage(Message::user('Hello'));
		$original->addMessage(Message::assistant('Hi'));
		$json = $this->serializer->serialize($original);

		$restored = $this->serializer->deserialize($json);

		$messages = $restored->getMessages();
		$this->assertCount(2, $messages);
		$this->assertSame('Hello', $messages[0]->getContent());
		$this->assertSame('Hi', $messages[1]->getContent());
	}

	public function test_deserialize_reconstructsTokenUsage(): void
	{
		$original = new Session();
		$original->addTokenUsage(200, 100);
		$json = $this->serializer->serialize($original);

		$restored = $this->serializer->deserialize($json);

		$this->assertSame(200, $restored->getTokenUsage()['input']);
		$this->assertSame(100, $restored->getTokenUsage()['output']);
	}

	public function test_deserialize_reconstructsMetadata(): void
	{
		$metadata = new SessionMetadata(
			new \DateTimeImmutable('2024-01-01 10:00:00'),
			new \DateTimeImmutable('2024-01-01 12:00:00'),
			'/test/directory',
			['title' => 'Test Title']
		);
		$original = new Session(SessionId::fromString('meta-test'), 'Prompt', $metadata);
		$json = $this->serializer->serialize($original);

		$restored = $this->serializer->deserialize($json);

		$this->assertSame('/test/directory', $restored->getMetadata()->getWorkingDirectory());
		$this->assertSame('Test Title', $restored->getMetadata()->getTitle());
	}

	public function test_deserialize_throwsExceptionForEmptyJson(): void
	{
		$this->expectException(SessionPersistenceException::class);
		$this->expectExceptionMessage('Empty JSON data');

		$this->serializer->deserialize('');
	}

	public function test_deserialize_throwsExceptionForWhitespaceOnlyJson(): void
	{
		$this->expectException(SessionPersistenceException::class);
		$this->expectExceptionMessage('Empty JSON data');

		$this->serializer->deserialize('   ');
	}

	public function test_deserialize_throwsExceptionForInvalidJson(): void
	{
		$this->expectException(SessionPersistenceException::class);
		$this->expectExceptionMessage('JSON decoding failed');

		$this->serializer->deserialize('{invalid json}');
	}

	public function test_deserialize_throwsExceptionForNonObjectJson(): void
	{
		$this->expectException(SessionPersistenceException::class);
		$this->expectExceptionMessage('Invalid JSON structure');

		$this->serializer->deserialize('"just a string"');
	}

	public function test_deserialize_throwsExceptionForMissingIdField(): void
	{
		$json = json_encode([
			'system_prompt' => 'Prompt',
			'messages' => [],
			'metadata' => [],
		]);

		$this->expectException(SessionPersistenceException::class);
		$this->expectExceptionMessage('Missing required fields: id');

		$this->serializer->deserialize($json);
	}

	public function test_deserialize_throwsExceptionForMissingMultipleFields(): void
	{
		$json = json_encode([
			'id' => 'test-id',
		]);

		$this->expectException(SessionPersistenceException::class);
		$this->expectExceptionMessage('Missing required fields: system_prompt, messages, metadata');

		$this->serializer->deserialize($json);
	}

	public function test_deserialize_throwsExceptionForInvalidIdType(): void
	{
		$json = json_encode([
			'id' => 123,
			'system_prompt' => 'Prompt',
			'messages' => [],
			'metadata' => [],
		]);

		$this->expectException(SessionPersistenceException::class);
		$this->expectExceptionMessage('Field "id" must be a string');

		$this->serializer->deserialize($json);
	}

	public function test_deserialize_throwsExceptionForInvalidSystemPromptType(): void
	{
		$json = json_encode([
			'id' => 'test-id',
			'system_prompt' => ['invalid' => 'type'],
			'messages' => [],
			'metadata' => [],
		]);

		$this->expectException(SessionPersistenceException::class);
		$this->expectExceptionMessage('Field "system_prompt" must be a string');

		$this->serializer->deserialize($json);
	}

	public function test_deserialize_throwsExceptionForInvalidMessagesType(): void
	{
		$json = json_encode([
			'id' => 'test-id',
			'system_prompt' => 'Prompt',
			'messages' => 'not-an-array',
			'metadata' => [],
		]);

		$this->expectException(SessionPersistenceException::class);
		$this->expectExceptionMessage('Field "messages" must be an array');

		$this->serializer->deserialize($json);
	}

	public function test_deserialize_throwsExceptionForInvalidMetadataType(): void
	{
		$json = json_encode([
			'id' => 'test-id',
			'system_prompt' => 'Prompt',
			'messages' => [],
			'metadata' => 'not-an-array',
		]);

		$this->expectException(SessionPersistenceException::class);
		$this->expectExceptionMessage('Field "metadata" must be an array');

		$this->serializer->deserialize($json);
	}

	public function test_roundTrip_preservesCompleteSession(): void
	{
		$original = new Session(
			SessionId::fromString('round-trip-id'),
			'System prompt for round trip test'
		);
		$original->addMessage(Message::user('User message'));
		$original->addMessage(Message::assistant('Assistant response'));
		$original->addMessage(Message::toolResult('tool-call-1', 'bash', 'Command output'));
		$original->addTokenUsage(500, 250);
		$original->getMetadata()->setTitle('Round Trip Session');
		$original->getMetadata()->set('custom_key', 'custom_value');

		$json = $this->serializer->serialize($original);
		$restored = $this->serializer->deserialize($json);

		$this->assertSame($original->getId()->toString(), $restored->getId()->toString());
		$this->assertSame($original->getSystemPrompt(), $restored->getSystemPrompt());
		$this->assertSame($original->getMessageCount(), $restored->getMessageCount());
		$this->assertSame($original->getTokenUsage()['input'], $restored->getTokenUsage()['input']);
		$this->assertSame($original->getTokenUsage()['output'], $restored->getTokenUsage()['output']);
		$this->assertSame($original->getMetadata()->getTitle(), $restored->getMetadata()->getTitle());
		$this->assertSame('custom_value', $restored->getMetadata()->get('custom_key'));
	}

	public function test_serialize_handlesUnicodeContent(): void
	{
		$session = new Session();
		$session->addMessage(Message::user('Hello! 👋 Привет! 你好!'));

		$json = $this->serializer->serialize($session);
		$restored = $this->serializer->deserialize($json);

		$this->assertSame('Hello! 👋 Привет! 你好!', $restored->getMessages()[0]->getContent());
	}

	public function test_serialize_handlesEmptySession(): void
	{
		$session = new Session();

		$json = $this->serializer->serialize($session);
		$restored = $this->serializer->deserialize($json);

		$this->assertSame(0, $restored->getMessageCount());
		$this->assertSame('', $restored->getSystemPrompt());
	}

	/**
	 * Creates a test session with standard content.
	 */
	private function createTestSession(): Session
	{
		$session = new Session(
			SessionId::fromString('test-session-123'),
			'You are a test assistant.'
		);
		$session->addMessage(Message::user('Test message'));
		$session->addTokenUsage(100, 50);

		return $session;
	}
}
