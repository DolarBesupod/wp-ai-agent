<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Integration\AiClient;

use PHPUnit\Framework\TestCase;
use Automattic\WpAiAgent\Integration\AiClient\ClaudeCodeSubscriptionRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;

/**
 * Unit tests for ClaudeCodeSubscriptionRequestAuthentication.
 *
 * @covers \Automattic\WpAiAgent\Integration\AiClient\ClaudeCodeSubscriptionRequestAuthentication
 */
final class ClaudeCodeSubscriptionRequestAuthenticationTest extends TestCase
{
	private const TEST_TOKEN = 'sk-ant-oat01-' . 'abcdefghijklmnopqrstuvwxyz0123456789token';

	/**
	 * Tests that request authentication adds Bearer Authorization.
	 */
	public function test_authenticateRequest_addsBearerAuthorizationHeader(): void
	{
		$auth = new ClaudeCodeSubscriptionRequestAuthentication(self::TEST_TOKEN);
		$request = new Request(HttpMethodEnum::POST(), 'https://api.anthropic.com/v1/messages');

		$authenticated_request = $auth->authenticateRequest($request);

		$this->assertSame(['Bearer ' . self::TEST_TOKEN], $authenticated_request->getHeader('Authorization'));
	}

	/**
	 * Tests that Claude Code contract headers are attached.
	 */
	public function test_authenticateRequest_addsClaudeCodeHeaders(): void
	{
		$auth = new ClaudeCodeSubscriptionRequestAuthentication(self::TEST_TOKEN);
		$request = new Request(HttpMethodEnum::POST(), 'https://api.anthropic.com/v1/messages');

		$authenticated_request = $auth->authenticateRequest($request);

		$this->assertSame(['2023-06-01'], $authenticated_request->getHeader('anthropic-version'));
		$this->assertSame(['application/json'], $authenticated_request->getHeader('accept'));
		$this->assertSame(['true'], $authenticated_request->getHeader('anthropic-dangerous-direct-browser-access'));
		$this->assertStringContainsString(
			'claude-cli/2.1.2',
			(string) $authenticated_request->getHeaderAsString('user-agent')
		);
		$this->assertSame(['cli'], $authenticated_request->getHeader('x-app'));
		$this->assertStringContainsString(
			'claude-code-20250219',
			(string) $authenticated_request->getHeaderAsString('anthropic-beta')
		);
	}

	/**
	 * Tests that authentication preserves request URI and body data.
	 */
	public function test_authenticateRequest_preservesRequestUriAndData(): void
	{
		$data = ['model' => 'claude-opus-4-1', 'max_tokens' => 4096];
		$request = new Request(
			HttpMethodEnum::POST(),
			'https://api.anthropic.com/v1/messages',
			['Content-Type' => 'application/json'],
			$data
		);
		$auth = new ClaudeCodeSubscriptionRequestAuthentication(self::TEST_TOKEN);

		$authenticated_request = $auth->authenticateRequest($request);

		$this->assertSame('https://api.anthropic.com/v1/messages', $authenticated_request->getUri());
		$this->assertSame($data, $authenticated_request->getData());
		$this->assertSame(['application/json'], $authenticated_request->getHeader('Content-Type'));
	}

	/**
	 * Tests that the class extends ApiKeyRequestAuthentication.
	 */
	public function test_extendsApiKeyRequestAuthentication(): void
	{
		$auth = new ClaudeCodeSubscriptionRequestAuthentication(self::TEST_TOKEN);

		$this->assertInstanceOf(ApiKeyRequestAuthentication::class, $auth);
	}
}
