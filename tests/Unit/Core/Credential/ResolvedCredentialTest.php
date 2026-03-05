<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Core\Credential;

use PHPUnit\Framework\TestCase;
use Automattic\Automattic\WpAiAgent\Core\Credential\AuthMode;
use Automattic\Automattic\WpAiAgent\Core\Credential\ResolvedCredential;

/**
 * Unit tests for ResolvedCredential DTO.
 *
 * @covers \Automattic\WpAiAgent\Core\Credential\ResolvedCredential
 *
 * @since n.e.x.t
 */
final class ResolvedCredentialTest extends TestCase
{
	/**
	 * Tests that the constructor sets all properties correctly.
	 */
	public function test_constructor_setsAllProperties(): void
	{
		$resolved = new ResolvedCredential(
			'sk-ant-test123',
			AuthMode::API_KEY,
			'constant',
		);

		$this->assertSame('sk-ant-test123', $resolved->getSecret());
		$this->assertSame(AuthMode::API_KEY, $resolved->getAuthMode());
		$this->assertSame('constant', $resolved->getSource());
	}

	/**
	 * Tests that getters return the correct values for each source type.
	 */
	public function test_getters_returnCorrectValues(): void
	{
		$from_env = new ResolvedCredential(
			'sk-env-secret',
			AuthMode::SUBSCRIPTION,
			'env',
		);

		$this->assertSame('sk-env-secret', $from_env->getSecret());
		$this->assertSame(AuthMode::SUBSCRIPTION, $from_env->getAuthMode());
		$this->assertSame('env', $from_env->getSource());

		$from_db = new ResolvedCredential(
			'sk-db-secret',
			AuthMode::API_KEY,
			'db',
		);

		$this->assertSame('sk-db-secret', $from_db->getSecret());
		$this->assertSame(AuthMode::API_KEY, $from_db->getAuthMode());
		$this->assertSame('db', $from_db->getSource());
	}
}
