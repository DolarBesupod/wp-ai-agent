<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Core\Credential;

use PHPUnit\Framework\TestCase;
use Automattic\Automattic\WpAiAgent\Core\Credential\AuthMode;

/**
 * Unit tests for AuthMode enum.
 *
 * @covers \Automattic\WpAiAgent\Core\Credential\AuthMode
 *
 * @since n.e.x.t
 */
final class AuthModeTest extends TestCase
{
	/**
	 * Tests that fromString() with 'api_key' returns the API_KEY case.
	 */
	public function test_fromString_withValidApiKey_returnsApiKeyCase(): void
	{
		$mode = AuthMode::fromString('api_key');

		$this->assertSame(AuthMode::API_KEY, $mode);
	}

	/**
	 * Tests that fromString() with 'subscription' returns the SUBSCRIPTION case.
	 */
	public function test_fromString_withValidSubscription_returnsSubscriptionCase(): void
	{
		$mode = AuthMode::fromString('subscription');

		$this->assertSame(AuthMode::SUBSCRIPTION, $mode);
	}

	/**
	 * Tests that fromString() with an invalid value throws a ValueError.
	 */
	public function test_fromString_withInvalidValue_throwsException(): void
	{
		$this->expectException(\ValueError::class);
		$this->expectExceptionMessage('Invalid auth mode "oauth"');

		AuthMode::fromString('oauth');
	}

	/**
	 * Tests that the backing string values are correct.
	 */
	public function test_value_returnsBackingString(): void
	{
		$this->assertSame('api_key', AuthMode::API_KEY->value);
		$this->assertSame('subscription', AuthMode::SUBSCRIPTION->value);
	}
}
