<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Core\ValueObjects;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PhpCliAgent\Core\ValueObjects\SessionId;

/**
 * Tests for SessionId value object.
 *
 * @covers \PhpCliAgent\Core\ValueObjects\SessionId
 */
final class SessionIdTest extends TestCase
{
	public function test_constructor_acceptsValidString(): void
	{
		$id = new SessionId('valid-id-123');

		$this->assertSame('valid-id-123', $id->toString());
	}

	public function test_constructor_throwsOnEmptyString(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Session ID cannot be empty');

		new SessionId('');
	}

	public function test_constructor_throwsOnWhitespaceOnly(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new SessionId('   ');
	}

	public function test_generate_createsUniqueId(): void
	{
		$id1 = SessionId::generate();
		$id2 = SessionId::generate();

		$this->assertNotSame($id1->toString(), $id2->toString());
	}

	public function test_generate_createsNonEmptyId(): void
	{
		$id = SessionId::generate();

		$this->assertNotEmpty($id->toString());
	}

	public function test_generate_creates32CharacterHexString(): void
	{
		$id = SessionId::generate();

		$this->assertSame(32, strlen($id->toString()));
		$this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $id->toString());
	}

	public function test_fromString_createsInstanceFromString(): void
	{
		$id = SessionId::fromString('from-string-id');

		$this->assertSame('from-string-id', $id->toString());
	}

	public function test_toString_returnsValue(): void
	{
		$id = new SessionId('test-value');

		$this->assertSame('test-value', $id->toString());
	}

	public function test_magicToString_returnsValue(): void
	{
		$id = new SessionId('magic-string');

		$this->assertSame('magic-string', (string) $id);
	}

	public function test_equals_returnsTrueForSameValue(): void
	{
		$id1 = new SessionId('same-id');
		$id2 = new SessionId('same-id');

		$this->assertTrue($id1->equals($id2));
		$this->assertTrue($id2->equals($id1));
	}

	public function test_equals_returnsFalseForDifferentValue(): void
	{
		$id1 = new SessionId('first-id');
		$id2 = new SessionId('second-id');

		$this->assertFalse($id1->equals($id2));
		$this->assertFalse($id2->equals($id1));
	}

	public function test_equals_returnsTrueForSameInstance(): void
	{
		$id = new SessionId('self-compare');

		$this->assertTrue($id->equals($id));
	}
}
