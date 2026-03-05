<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Core\Credential;

use PHPUnit\Framework\TestCase;
use Automattic\WpAiAgent\Core\Credential\AuthMode;
use Automattic\WpAiAgent\Core\Credential\Credential;

/**
 * Unit tests for Credential value object.
 *
 * @covers \Automattic\WpAiAgent\Core\Credential\Credential
 *
 * @since n.e.x.t
 */
final class CredentialTest extends TestCase
{
	/**
	 * Tests that the constructor sets all properties correctly.
	 */
	public function test_constructor_setsAllProperties(): void
	{
		$created_at = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
		$updated_at = new \DateTimeImmutable('2026-01-02T00:00:00+00:00');
		$meta = ['region' => 'us-east-1'];

		$credential = new Credential(
			'anthropic',
			AuthMode::API_KEY,
			'sk-ant-test123456789',
			$created_at,
			$updated_at,
			$meta,
		);

		$this->assertSame('anthropic', $credential->getProvider());
		$this->assertSame(AuthMode::API_KEY, $credential->getAuthMode());
		$this->assertSame('sk-ant-test123456789', $credential->getSecret());
		$this->assertSame($created_at, $credential->getCreatedAt());
		$this->assertSame($updated_at, $credential->getUpdatedAt());
		$this->assertSame($meta, $credential->getMeta());
	}

	/**
	 * Tests that getMaskedSecret() masks secrets longer than 8 characters.
	 */
	public function test_getMaskedSecret_masksLongSecret(): void
	{
		$credential = $this->createCredential('sk-ant-test123456789');

		$this->assertSame('sk-ant-t****', $credential->getMaskedSecret());
	}

	/**
	 * Tests that getMaskedSecret() handles secrets shorter than 8 characters.
	 */
	public function test_getMaskedSecret_handlesShortSecret(): void
	{
		$credential = $this->createCredential('short');

		$this->assertSame('short****', $credential->getMaskedSecret());
	}

	/**
	 * Tests that toArray() serializes all fields correctly.
	 */
	public function test_toArray_serializesAllFields(): void
	{
		$created_at = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
		$updated_at = new \DateTimeImmutable('2026-01-02T12:30:00+00:00');
		$meta = ['tier' => 'premium'];

		$credential = new Credential(
			'anthropic',
			AuthMode::API_KEY,
			'sk-ant-test123',
			$created_at,
			$updated_at,
			$meta,
		);

		$array = $credential->toArray();

		$this->assertSame('anthropic', $array['provider']);
		$this->assertSame('api_key', $array['auth_mode']);
		$this->assertSame('sk-ant-test123', $array['secret']);
		$this->assertSame($created_at->format(\DateTimeInterface::ATOM), $array['created_at']);
		$this->assertSame($updated_at->format(\DateTimeInterface::ATOM), $array['updated_at']);
		$this->assertSame(['tier' => 'premium'], $array['meta']);
	}

	/**
	 * Tests that fromArray() correctly reconstructs a Credential from toArray() output.
	 */
	public function test_fromArray_reconstructsCredential(): void
	{
		$original = new Credential(
			'anthropic',
			AuthMode::API_KEY,
			'sk-ant-test123',
			new \DateTimeImmutable('2026-01-01T00:00:00+00:00'),
			new \DateTimeImmutable('2026-01-02T12:30:00+00:00'),
			['tier' => 'premium'],
		);

		$reconstructed = Credential::fromArray($original->toArray());

		$this->assertSame($original->getProvider(), $reconstructed->getProvider());
		$this->assertSame($original->getAuthMode(), $reconstructed->getAuthMode());
		$this->assertSame($original->getSecret(), $reconstructed->getSecret());
		$this->assertEquals($original->getCreatedAt(), $reconstructed->getCreatedAt());
		$this->assertEquals($original->getUpdatedAt(), $reconstructed->getUpdatedAt());
		$this->assertSame($original->getMeta(), $reconstructed->getMeta());
	}

	/**
	 * Tests that fromArray() throws when the provider field is missing.
	 */
	public function test_fromArray_withMissingProvider_throwsException(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Missing or invalid "provider" field');

		Credential::fromArray([
			'auth_mode'  => 'api_key',
			'secret'     => 'sk-test',
			'created_at' => '2026-01-01T00:00:00+00:00',
			'updated_at' => '2026-01-01T00:00:00+00:00',
		]);
	}

	/**
	 * Tests that fromArray() throws when the auth_mode value is invalid.
	 */
	public function test_fromArray_withInvalidAuthMode_throwsException(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid "auth_mode" value');

		Credential::fromArray([
			'provider'   => 'anthropic',
			'auth_mode'  => 'oauth',
			'secret'     => 'sk-test',
			'created_at' => '2026-01-01T00:00:00+00:00',
			'updated_at' => '2026-01-01T00:00:00+00:00',
		]);
	}

	/**
	 * Creates a Credential with the given secret and sensible defaults.
	 *
	 * @param string $secret The secret value.
	 *
	 * @return Credential
	 */
	private function createCredential(string $secret): Credential
	{
		return new Credential(
			'anthropic',
			AuthMode::API_KEY,
			$secret,
			new \DateTimeImmutable('2026-01-01T00:00:00+00:00'),
			new \DateTimeImmutable('2026-01-01T00:00:00+00:00'),
		);
	}
}
