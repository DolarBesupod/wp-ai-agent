<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Core\Session;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WpAiAgent\Core\Session\SessionMessage;
use WpAiAgent\Core\ValueObjects\Message;

/**
 * Tests for SessionMessage.
 *
 * @covers \WpAiAgent\Core\Session\SessionMessage
 */
final class SessionMessageTest extends TestCase
{
	public function test_constructor_setsMessage(): void
	{
		$message = Message::user('Hello');

		$session_message = new SessionMessage($message);

		$this->assertSame($message, $session_message->getMessage());
	}

	public function test_constructor_setsDefaultAddedAt(): void
	{
		$before = new DateTimeImmutable();
		$session_message = new SessionMessage(Message::user('Test'));
		$after = new DateTimeImmutable();

		$this->assertGreaterThanOrEqual($before, $session_message->getAddedAt());
		$this->assertLessThanOrEqual($after, $session_message->getAddedAt());
	}

	public function test_constructor_acceptsCustomAddedAt(): void
	{
		$custom_time = new DateTimeImmutable('2024-01-15 14:30:00');

		$session_message = new SessionMessage(Message::user('Test'), $custom_time);

		$this->assertSame($custom_time, $session_message->getAddedAt());
	}

	public function test_constructor_acceptsAttributes(): void
	{
		$attributes = ['key' => 'value', 'count' => 5];

		$session_message = new SessionMessage(Message::user('Test'), null, $attributes);

		$this->assertSame($attributes, $session_message->getAttributes());
	}

	public function test_fromMessage_createsInstance(): void
	{
		$message = Message::assistant('Response');

		$session_message = SessionMessage::fromMessage($message);

		$this->assertSame($message, $session_message->getMessage());
		$this->assertInstanceOf(DateTimeImmutable::class, $session_message->getAddedAt());
	}

	public function test_getRole_delegatesToMessage(): void
	{
		$session_message = new SessionMessage(Message::user('Test'));

		$this->assertSame('user', $session_message->getRole());
	}

	public function test_getContent_delegatesToMessage(): void
	{
		$session_message = new SessionMessage(Message::user('Hello world'));

		$this->assertSame('Hello world', $session_message->getContent());
	}

	public function test_getAttribute_returnsValueForExistingKey(): void
	{
		$session_message = new SessionMessage(
			Message::user('Test'),
			null,
			['existing' => 'found']
		);

		$this->assertSame('found', $session_message->getAttribute('existing'));
	}

	public function test_getAttribute_returnsDefaultForMissingKey(): void
	{
		$session_message = new SessionMessage(Message::user('Test'));

		$this->assertNull($session_message->getAttribute('missing'));
		$this->assertSame('default', $session_message->getAttribute('missing', 'default'));
	}

	public function test_withAttribute_returnsNewInstanceWithAttribute(): void
	{
		$original = new SessionMessage(Message::user('Test'));

		$updated = $original->withAttribute('new_key', 'new_value');

		$this->assertNotSame($original, $updated);
		$this->assertFalse($original->hasAttribute('new_key'));
		$this->assertTrue($updated->hasAttribute('new_key'));
		$this->assertSame('new_value', $updated->getAttribute('new_key'));
	}

	public function test_withAttribute_preservesExistingAttributes(): void
	{
		$original = new SessionMessage(Message::user('Test'), null, ['key1' => 'value1']);

		$updated = $original->withAttribute('key2', 'value2');

		$this->assertSame('value1', $updated->getAttribute('key1'));
		$this->assertSame('value2', $updated->getAttribute('key2'));
	}

	public function test_hasAttribute_returnsTrueForExistingKey(): void
	{
		$session_message = new SessionMessage(
			Message::user('Test'),
			null,
			['exists' => true]
		);

		$this->assertTrue($session_message->hasAttribute('exists'));
	}

	public function test_hasAttribute_returnsFalseForMissingKey(): void
	{
		$session_message = new SessionMessage(Message::user('Test'));

		$this->assertFalse($session_message->hasAttribute('missing'));
	}

	public function test_toArray_serializesCorrectly(): void
	{
		$added_at = new DateTimeImmutable('2024-06-15 10:00:00');
		$session_message = new SessionMessage(
			Message::user('Test content'),
			$added_at,
			['attr' => 'value']
		);

		$array = $session_message->toArray();

		$this->assertSame('user', $array['message']['role']);
		$this->assertSame('Test content', $array['message']['content']);
		$this->assertSame($added_at->format(DateTimeImmutable::ATOM), $array['added_at']);
		$this->assertSame(['attr' => 'value'], $array['attributes']);
	}

	public function test_fromArray_deserializesCorrectly(): void
	{
		$data = [
			'message' => ['role' => 'assistant', 'content' => 'Response'],
			'added_at' => '2024-06-15T10:00:00+00:00',
			'attributes' => ['processed' => true],
		];

		$session_message = SessionMessage::fromArray($data);

		$this->assertSame('assistant', $session_message->getRole());
		$this->assertSame('Response', $session_message->getContent());
		$this->assertSame('2024-06-15T10:00:00+00:00', $session_message->getAddedAt()->format(DateTimeImmutable::ATOM));
		$this->assertTrue($session_message->getAttribute('processed'));
	}

	public function test_fromArray_handlesMinimalData(): void
	{
		$data = [
			'message' => ['role' => 'user', 'content' => 'Simple'],
		];

		$session_message = SessionMessage::fromArray($data);

		$this->assertSame('user', $session_message->getRole());
		$this->assertSame('Simple', $session_message->getContent());
		$this->assertInstanceOf(DateTimeImmutable::class, $session_message->getAddedAt());
		$this->assertSame([], $session_message->getAttributes());
	}

	public function test_roundTrip_preservesData(): void
	{
		$original = new SessionMessage(
			Message::toolResult('tool-123', 'bash', '{"result": "success"}'),
			new DateTimeImmutable('2024-06-15 12:00:00'),
			['duration_ms' => 150, 'cached' => false]
		);

		$serialized = $original->toArray();
		$restored = SessionMessage::fromArray($serialized);

		$this->assertSame($original->getRole(), $restored->getRole());
		$this->assertSame($original->getContent(), $restored->getContent());
		$this->assertSame(
			$original->getAddedAt()->format(DateTimeImmutable::ATOM),
			$restored->getAddedAt()->format(DateTimeImmutable::ATOM)
		);
		$this->assertSame($original->getAttributes(), $restored->getAttributes());
	}
}
