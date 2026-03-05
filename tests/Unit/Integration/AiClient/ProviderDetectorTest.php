<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Integration\AiClient;

use Automattic\WpAiAgent\Integration\AiClient\ProviderDetector;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ProviderDetector.
 *
 * @covers \Automattic\WpAiAgent\Integration\AiClient\ProviderDetector
 */
final class ProviderDetectorTest extends TestCase
{
	/**
	 * Tests that synthetic claude-code prefixed names still resolve to anthropic.
	 */
	public function test_detectFromModel_withSyntheticClaudeCodePrefix_returnsAnthropic(): void
	{
		$this->assertSame('anthropic', ProviderDetector::detectFromModel('claude-code/claude-sonnet-4-5'));
	}

	/**
	 * Tests that claude- prefix is detected as anthropic.
	 */
	public function test_detectFromModel_withClaudePrefix_returnsAnthropic(): void
	{
		$this->assertSame('anthropic', ProviderDetector::detectFromModel('claude-sonnet-4-6'));
	}

	/**
	 * Tests that claude model with full version string is detected as anthropic.
	 */
	public function test_detectFromModel_withClaudeFullVersion_returnsAnthropic(): void
	{
		$this->assertSame('anthropic', ProviderDetector::detectFromModel('claude-opus-4-20250514'));
	}

	/**
	 * Tests that gpt- prefix is detected as openai.
	 */
	public function test_detectFromModel_withGptPrefix_returnsOpenai(): void
	{
		$this->assertSame('openai', ProviderDetector::detectFromModel('gpt-4o'));
	}

	/**
	 * Tests that gpt-4 variant is detected as openai.
	 */
	public function test_detectFromModel_withGpt4_returnsOpenai(): void
	{
		$this->assertSame('openai', ProviderDetector::detectFromModel('gpt-4-turbo'));
	}

	/**
	 * Tests that o1- prefix is detected as openai.
	 */
	public function test_detectFromModel_withO1Prefix_returnsOpenai(): void
	{
		$this->assertSame('openai', ProviderDetector::detectFromModel('o1-mini'));
	}

	/**
	 * Tests that o3- prefix is detected as openai.
	 */
	public function test_detectFromModel_withO3Prefix_returnsOpenai(): void
	{
		$this->assertSame('openai', ProviderDetector::detectFromModel('o3-mini'));
	}

	/**
	 * Tests that o4- prefix is detected as openai.
	 */
	public function test_detectFromModel_withO4Prefix_returnsOpenai(): void
	{
		$this->assertSame('openai', ProviderDetector::detectFromModel('o4-mini'));
	}

	/**
	 * Tests that chatgpt- prefix is detected as openai.
	 */
	public function test_detectFromModel_withChatgptPrefix_returnsOpenai(): void
	{
		$this->assertSame('openai', ProviderDetector::detectFromModel('chatgpt-4o-latest'));
	}

	/**
	 * Tests that gemini- prefix is detected as google.
	 */
	public function test_detectFromModel_withGeminiPrefix_returnsGoogle(): void
	{
		$this->assertSame('google', ProviderDetector::detectFromModel('gemini-2.0-flash'));
	}

	/**
	 * Tests that models/gemini- prefix is detected as google.
	 */
	public function test_detectFromModel_withModelsGeminiPrefix_returnsGoogle(): void
	{
		$this->assertSame('google', ProviderDetector::detectFromModel('models/gemini-1.5-pro'));
	}

	/**
	 * Tests that unknown model names default to anthropic.
	 */
	public function test_detectFromModel_withUnknownModel_returnsDefaultAnthropic(): void
	{
		$this->assertSame('anthropic', ProviderDetector::detectFromModel('unknown-model'));
	}

	/**
	 * Tests that detection is case-insensitive for uppercase input.
	 */
	public function test_detectFromModel_withUppercaseGpt_returnsOpenai(): void
	{
		$this->assertSame('openai', ProviderDetector::detectFromModel('GPT-4O'));
	}

	/**
	 * Tests that detection is case-insensitive for mixed case input.
	 */
	public function test_detectFromModel_withMixedCaseClaude_returnsAnthropic(): void
	{
		$this->assertSame('anthropic', ProviderDetector::detectFromModel('Claude-Sonnet-4-6'));
	}

	/**
	 * Tests that detection is case-insensitive for gemini.
	 */
	public function test_detectFromModel_withUppercaseGemini_returnsGoogle(): void
	{
		$this->assertSame('google', ProviderDetector::detectFromModel('GEMINI-2.0-FLASH'));
	}

	/**
	 * Tests that an empty string defaults to anthropic.
	 */
	public function test_detectFromModel_withEmptyString_returnsDefaultAnthropic(): void
	{
		$this->assertSame('anthropic', ProviderDetector::detectFromModel(''));
	}

	/**
	 * Tests that isKnownProvider returns true for anthropic.
	 */
	public function test_isKnownProvider_withAnthropic_returnsTrue(): void
	{
		$this->assertTrue(ProviderDetector::isKnownProvider('anthropic'));
	}

	/**
	 * Tests that isKnownProvider returns true for claudeCode.
	 */
	public function test_isKnownProvider_withClaudeCode_returnsTrue(): void
	{
		$this->assertTrue(ProviderDetector::isKnownProvider('claudeCode'));
	}

	/**
	 * Tests that isKnownProvider returns true for openai.
	 */
	public function test_isKnownProvider_withOpenai_returnsTrue(): void
	{
		$this->assertTrue(ProviderDetector::isKnownProvider('openai'));
	}

	/**
	 * Tests that isKnownProvider returns true for google.
	 */
	public function test_isKnownProvider_withGoogle_returnsTrue(): void
	{
		$this->assertTrue(ProviderDetector::isKnownProvider('google'));
	}

	/**
	 * Tests that isKnownProvider returns false for an invalid provider.
	 */
	public function test_isKnownProvider_withInvalidProvider_returnsFalse(): void
	{
		$this->assertFalse(ProviderDetector::isKnownProvider('invalid'));
	}

	/**
	 * Tests that isKnownProvider returns false for an empty string.
	 */
	public function test_isKnownProvider_withEmptyString_returnsFalse(): void
	{
		$this->assertFalse(ProviderDetector::isKnownProvider(''));
	}

	/**
	 * Tests that DEFAULT_PROVIDER constant is anthropic.
	 */
	public function test_defaultProvider_isAnthropic(): void
	{
		$this->assertSame('anthropic', ProviderDetector::DEFAULT_PROVIDER);
	}

	/**
	 * Tests that KNOWN_PROVIDERS constant contains all supported providers.
	 */
	public function test_knownProviders_containsAllProviders(): void
	{
		$this->assertSame(['anthropic', 'claudeCode', 'openai', 'google'], ProviderDetector::KNOWN_PROVIDERS);
	}
}
