<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Integration\AiClient;

use PHPUnit\Framework\TestCase;
use Automattic\WpAiAgent\Integration\AiClient\OpenAiSubscriptionRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;

/**
 * Unit tests for OpenAiSubscriptionRequestAuthentication.
 *
 * @covers \Automattic\WpAiAgent\Integration\AiClient\OpenAiSubscriptionRequestAuthentication
 */
final class OpenAiSubscriptionRequestAuthenticationTest extends TestCase
{
	/**
	 * A test JWT containing a chatgpt_account_id claim.
	 *
	 * Payload: {"aud":["https://api.openai.com/v1"],
	 *           "https://api.openai.com/auth":{"chatgpt_account_id":"acct-test-12345","chatgpt_plan_type":"pro"},
	 *           "exp":9999999999}
	 */
	private const TEST_JWT = 'eyJhbGciOiAiUlMyNTYiLCAidHlwIjogIkpXVCJ9'
		. '.eyJhdWQiOiBbImh0dHBzOi8vYXBpLm9wZW5haS5jb20vdjEiXSwgImh0dH'
		. 'BzOi8vYXBpLm9wZW5haS5jb20vYXV0aCI6IHsiY2hhdGdwdF9hY2NvdW50'
		. 'X2lkIjogImFjY3QtdGVzdC0xMjM0NSIsICJjaGF0Z3B0X3BsYW5fdHlwZS'
		. 'I6ICJwcm8ifSwgImV4cCI6IDk5OTk5OTk5OTl9'
		. '.ZmFrZS1zaWduYXR1cmU';

	private const TEST_ACCOUNT_ID = 'acct-test-12345';

	/**
	 * Tests that authenticateRequest adds the Bearer Authorization header.
	 */
	public function test_authenticateRequest_addsBearerAuthorizationHeader(): void
	{
		$auth = new OpenAiSubscriptionRequestAuthentication(self::TEST_JWT);
		$request = new Request(HttpMethodEnum::POST(), 'https://api.openai.com/v1/responses');

		$authenticated_request = $auth->authenticateRequest($request);

		$this->assertSame(
			['Bearer ' . self::TEST_JWT],
			$authenticated_request->getHeader('Authorization')
		);
	}

	/**
	 * Tests that authenticateRequest adds the ChatGPT-Account-ID header
	 * extracted from the JWT payload.
	 */
	public function test_authenticateRequest_addsChatGptAccountIdHeader(): void
	{
		$auth = new OpenAiSubscriptionRequestAuthentication(self::TEST_JWT);
		$request = new Request(HttpMethodEnum::POST(), 'https://api.openai.com/v1/responses');

		$authenticated_request = $auth->authenticateRequest($request);

		$this->assertSame(
			[self::TEST_ACCOUNT_ID],
			$authenticated_request->getHeader('ChatGPT-Account-ID')
		);
	}

	/**
	 * Tests that authenticateRequest rewrites the URL from api.openai.com
	 * to the ChatGPT backend API for subscription tokens.
	 */
	public function test_authenticateRequest_rewritesUrlToChatGptBackend(): void
	{
		$auth = new OpenAiSubscriptionRequestAuthentication(self::TEST_JWT);
		$request = new Request(
			HttpMethodEnum::POST(),
			'https://api.openai.com/v1/responses',
			['Content-Type' => 'application/json'],
			['model' => 'gpt-4o', 'input' => [['role' => 'user', 'content' => 'Hello']]]
		);

		$authenticated_request = $auth->authenticateRequest($request);

		$this->assertSame(
			'https://chatgpt.com/backend-api/codex/responses',
			$authenticated_request->getUri()
		);
	}

	/**
	 * Tests that the URL rewrite preserves the request body data.
	 */
	public function test_authenticateRequest_preservesRequestData(): void
	{
		$auth = new OpenAiSubscriptionRequestAuthentication(self::TEST_JWT);
		$data = ['model' => 'gpt-4o', 'input' => [['role' => 'user', 'content' => 'Hello']]];
		$request = new Request(
			HttpMethodEnum::POST(),
			'https://api.openai.com/v1/responses',
			['Content-Type' => 'application/json'],
			$data
		);

		$authenticated_request = $auth->authenticateRequest($request);

		$this->assertSame($data, $authenticated_request->getData());
	}

	/**
	 * Tests that the URL rewrite preserves the Content-Type header.
	 */
	public function test_authenticateRequest_preservesContentTypeHeader(): void
	{
		$auth = new OpenAiSubscriptionRequestAuthentication(self::TEST_JWT);
		$request = new Request(
			HttpMethodEnum::POST(),
			'https://api.openai.com/v1/responses',
			['Content-Type' => 'application/json']
		);

		$authenticated_request = $auth->authenticateRequest($request);

		$this->assertSame(['application/json'], $authenticated_request->getHeader('Content-Type'));
	}

	/**
	 * Tests that non-OpenAI URLs are not rewritten.
	 */
	public function test_authenticateRequest_doesNotRewriteNonOpenAiUrls(): void
	{
		$auth = new OpenAiSubscriptionRequestAuthentication(self::TEST_JWT);
		$url = 'https://example.com/v1/responses';
		$request = new Request(HttpMethodEnum::POST(), $url);

		$authenticated_request = $auth->authenticateRequest($request);

		$this->assertSame($url, $authenticated_request->getUri());
	}

	/**
	 * Tests that ChatGPT-Account-ID is omitted when the token is not a JWT.
	 */
	public function test_authenticateRequest_omitsAccountIdForNonJwtToken(): void
	{
		$auth = new OpenAiSubscriptionRequestAuthentication('sk-not-a-jwt-token');
		$request = new Request(HttpMethodEnum::POST(), 'https://api.openai.com/v1/responses');

		$authenticated_request = $auth->authenticateRequest($request);

		$this->assertFalse($authenticated_request->hasHeader('ChatGPT-Account-ID'));
		$this->assertTrue($authenticated_request->hasHeader('Authorization'));
	}

	/**
	 * Tests that ChatGPT-Account-ID is omitted when the JWT lacks the auth claim.
	 */
	public function test_authenticateRequest_omitsAccountIdWhenJwtLacksClaim(): void
	{
		// JWT with payload {"sub": "user123"} (no auth claim)
		$header = base64_encode('{"alg":"none"}');
		$payload = base64_encode('{"sub":"user123"}');
		$jwt = $header . '.' . $payload . '.signature';

		$auth = new OpenAiSubscriptionRequestAuthentication($jwt);
		$request = new Request(HttpMethodEnum::POST(), 'https://api.openai.com/v1/responses');

		$authenticated_request = $auth->authenticateRequest($request);

		$this->assertFalse($authenticated_request->hasHeader('ChatGPT-Account-ID'));
	}

	/**
	 * Tests that the class extends ApiKeyRequestAuthentication.
	 */
	public function test_extendsApiKeyRequestAuthentication(): void
	{
		$auth = new OpenAiSubscriptionRequestAuthentication(self::TEST_JWT);

		$this->assertInstanceOf(ApiKeyRequestAuthentication::class, $auth);
	}

	/**
	 * Tests that authenticateRequest returns a new request instance.
	 */
	public function test_authenticateRequest_returnsNewRequestInstance(): void
	{
		$auth = new OpenAiSubscriptionRequestAuthentication(self::TEST_JWT);
		$request = new Request(HttpMethodEnum::POST(), 'https://api.openai.com/v1/responses');

		$authenticated_request = $auth->authenticateRequest($request);

		$this->assertNotSame($request, $authenticated_request);
	}
}
