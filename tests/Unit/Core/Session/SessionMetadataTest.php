<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Core\Session;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Automattic\Automattic\WpAiAgent\Core\Session\SessionMetadata;

/**
 * Tests for SessionMetadata.
 *
 * @covers \Automattic\WpAiAgent\Core\Session\SessionMetadata
 */
final class SessionMetadataTest extends TestCase
{
	public function test_constructor_setsDefaultValues(): void
	{
		$before = new DateTimeImmutable();
		$metadata = new SessionMetadata();
		$after = new DateTimeImmutable();

		$this->assertGreaterThanOrEqual($before, $metadata->getCreatedAt());
		$this->assertLessThanOrEqual($after, $metadata->getCreatedAt());
		$this->assertGreaterThanOrEqual($before, $metadata->getUpdatedAt());
		$this->assertLessThanOrEqual($after, $metadata->getUpdatedAt());
		$this->assertNotEmpty($metadata->getWorkingDirectory());
		$this->assertSame([], $metadata->all());
	}

	public function test_constructor_acceptsCustomValues(): void
	{
		$created_at = new DateTimeImmutable('2024-01-01 10:00:00');
		$updated_at = new DateTimeImmutable('2024-01-02 15:30:00');
		$working_dir = '/custom/path';
		$custom = ['key' => 'value'];

		$metadata = new SessionMetadata($created_at, $updated_at, $working_dir, $custom);

		$this->assertSame($created_at, $metadata->getCreatedAt());
		$this->assertSame($updated_at, $metadata->getUpdatedAt());
		$this->assertSame($working_dir, $metadata->getWorkingDirectory());
		$this->assertSame($custom, $metadata->all());
	}

	public function test_setUpdatedAt_changesTimestamp(): void
	{
		$metadata = new SessionMetadata();
		$new_time = new DateTimeImmutable('2025-12-31 23:59:59');

		$metadata->setUpdatedAt($new_time);

		$this->assertSame($new_time, $metadata->getUpdatedAt());
	}

	public function test_setWorkingDirectory_changesPath(): void
	{
		$metadata = new SessionMetadata();

		$metadata->setWorkingDirectory('/new/path');

		$this->assertSame('/new/path', $metadata->getWorkingDirectory());
	}

	public function test_get_returnsValueForExistingKey(): void
	{
		$metadata = new SessionMetadata(null, null, null, ['existing' => 'found']);

		$this->assertSame('found', $metadata->get('existing'));
	}

	public function test_get_returnsDefaultForMissingKey(): void
	{
		$metadata = new SessionMetadata();

		$this->assertNull($metadata->get('missing'));
		$this->assertSame('default', $metadata->get('missing', 'default'));
	}

	public function test_set_storesValueAndUpdatesTimestamp(): void
	{
		$metadata = new SessionMetadata();
		$before_update = $metadata->getUpdatedAt();

		// Ensure time passes.
		usleep(1000);

		$metadata->set('new_key', 'new_value');

		$this->assertSame('new_value', $metadata->get('new_key'));
		$this->assertGreaterThan($before_update, $metadata->getUpdatedAt());
	}

	public function test_has_returnsTrueForExistingKey(): void
	{
		$metadata = new SessionMetadata(null, null, null, ['exists' => true]);

		$this->assertTrue($metadata->has('exists'));
	}

	public function test_has_returnsFalseForMissingKey(): void
	{
		$metadata = new SessionMetadata();

		$this->assertFalse($metadata->has('missing'));
	}

	public function test_remove_deletesKeyAndUpdatesTimestamp(): void
	{
		$metadata = new SessionMetadata(null, null, null, ['to_remove' => 'value']);
		$before_update = $metadata->getUpdatedAt();

		usleep(1000);

		$metadata->remove('to_remove');

		$this->assertFalse($metadata->has('to_remove'));
		$this->assertGreaterThan($before_update, $metadata->getUpdatedAt());
	}

	public function test_all_returnsAllCustomMetadata(): void
	{
		$custom = ['key1' => 'value1', 'key2' => 'value2'];
		$metadata = new SessionMetadata(null, null, null, $custom);

		$this->assertSame($custom, $metadata->all());
	}

	public function test_toArray_serializesCorrectly(): void
	{
		$created_at = new DateTimeImmutable('2024-01-01 10:00:00');
		$updated_at = new DateTimeImmutable('2024-01-02 15:30:00');
		$metadata = new SessionMetadata($created_at, $updated_at, '/test/path', ['key' => 'value']);

		$array = $metadata->toArray();

		$this->assertSame($created_at->format(DateTimeImmutable::ATOM), $array['created_at']);
		$this->assertSame($updated_at->format(DateTimeImmutable::ATOM), $array['updated_at']);
		$this->assertSame('/test/path', $array['working_directory']);
		$this->assertSame(['key' => 'value'], $array['custom']);
	}

	public function test_fromArray_deserializesCorrectly(): void
	{
		$data = [
			'created_at' => '2024-01-01T10:00:00+00:00',
			'updated_at' => '2024-01-02T15:30:00+00:00',
			'working_directory' => '/test/path',
			'custom' => ['key' => 'value'],
		];

		$metadata = SessionMetadata::fromArray($data);

		$this->assertSame('2024-01-01T10:00:00+00:00', $metadata->getCreatedAt()->format(DateTimeImmutable::ATOM));
		$this->assertSame('2024-01-02T15:30:00+00:00', $metadata->getUpdatedAt()->format(DateTimeImmutable::ATOM));
		$this->assertSame('/test/path', $metadata->getWorkingDirectory());
		$this->assertSame('value', $metadata->get('key'));
	}

	public function test_fromArray_handlesMinimalData(): void
	{
		$data = [];

		$metadata = SessionMetadata::fromArray($data);

		$this->assertInstanceOf(DateTimeImmutable::class, $metadata->getCreatedAt());
		$this->assertNotEmpty($metadata->getWorkingDirectory());
	}

	public function test_roundTrip_preservesData(): void
	{
		$original = new SessionMetadata(
			new DateTimeImmutable('2024-06-15 12:00:00'),
			new DateTimeImmutable('2024-06-16 08:00:00'),
			'/project/dir',
			['title' => 'Test Session', 'custom' => 123]
		);

		$serialized = $original->toArray();
		$restored = SessionMetadata::fromArray($serialized);

		$this->assertSame(
			$original->getCreatedAt()->format(DateTimeImmutable::ATOM),
			$restored->getCreatedAt()->format(DateTimeImmutable::ATOM)
		);
		$this->assertSame(
			$original->getUpdatedAt()->format(DateTimeImmutable::ATOM),
			$restored->getUpdatedAt()->format(DateTimeImmutable::ATOM)
		);
		$this->assertSame($original->getWorkingDirectory(), $restored->getWorkingDirectory());
		$this->assertSame($original->all(), $restored->all());
	}

	public function test_getTitle_returnsNullWhenNotSet(): void
	{
		$metadata = new SessionMetadata();

		$this->assertNull($metadata->getTitle());
	}

	public function test_setTitle_storesTitle(): void
	{
		$metadata = new SessionMetadata();

		$metadata->setTitle('My Session');

		$this->assertSame('My Session', $metadata->getTitle());
	}
}
