<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Integration\AiClient;

use PHPUnit\Framework\TestCase;
use WpAiAgent\Integration\AiClient\OpenAiSubscriptionRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;

/**
 * Unit tests for OpenAiSubscriptionRequestAuthentication.
 *
 * @covers \WpAiAgent\Integration\AiClient\OpenAiSubscriptionRequestAuthentication
 */
final class OpenAiSubscriptionRequestAuthenticationTest extends TestCase
{
	/**
	 * Tests that authenticateRequest adds the Bearer Authorization header.
	 */
	public function test_authenticateRequest_addsBearerAuthorizationHeader(): void
	{
		$token = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.test-token';
		$auth = new OpenAiSubscriptionRequestAuthentication($token);
		$request = new Request(HttpMethodEnum::POST(), 'https://api.openai.com/v1/chat/completions');

		$authenticated_request = $auth->authenticateRequest($request);

		$this->assertSame(
			['Bearer ' . $token],
			$authenticated_request->getHeader('Authorization')
		);
	}

	/**
	 * Tests that authenticateRequest does not add extra headers.
	 *
	 * Unlike AnthropicSubscriptionRequestAuthentication which adds an
	 * anthropic-version header, the OpenAI variant should only set
	 * the Authorization header.
	 */
	public function test_authenticateRequest_doesNotAddExtraHeaders(): void
	{
		$auth = new OpenAiSubscriptionRequestAuthentication('tok123');
		$request = new Request(HttpMethodEnum::POST(), 'https://api.openai.com/v1/chat/completions');

		$authenticated_request = $auth->authenticateRequest($request);

		$headers = $authenticated_request->getHeaders();

		$this->assertCount(1, $headers, 'Only the Authorization header should be present');
		$this->assertTrue(
			$authenticated_request->hasHeader('Authorization'),
			'Authorization header must be set'
		);
		$this->assertFalse(
			$authenticated_request->hasHeader('anthropic-version'),
			'anthropic-version header must not be set'
		);
	}

	/**
	 * Tests that the class extends ApiKeyRequestAuthentication.
	 */
	public function test_extendsApiKeyRequestAuthentication(): void
	{
		$auth = new OpenAiSubscriptionRequestAuthentication('tok123');

		$this->assertInstanceOf(ApiKeyRequestAuthentication::class, $auth);
	}

	/**
	 * Tests that authenticateRequest returns a new request instance.
	 */
	public function test_authenticateRequest_returnsNewRequestInstance(): void
	{
		$auth = new OpenAiSubscriptionRequestAuthentication('tok123');
		$request = new Request(HttpMethodEnum::POST(), 'https://api.openai.com/v1/chat/completions');

		$authenticated_request = $auth->authenticateRequest($request);

		$this->assertNotSame($request, $authenticated_request);
	}
}
