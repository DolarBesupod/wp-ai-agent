<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Integration\WpCli;

use PHPUnit\Framework\TestCase;
use Automattic\Automattic\WpAiAgent\Core\Contracts\ConfigurationInterface;
use Automattic\Automattic\WpAiAgent\Core\Exceptions\ConfigurationException;
use Automattic\Automattic\WpAiAgent\Integration\WpCli\WpConfigConfiguration;

/**
 * Unit tests for WpConfigConfiguration.
 *
 * WpConfigConfiguration reads from PHP constants and environment variables.
 * PHP constants cannot be undefined once set, so tests that exercise
 * "constant not defined" paths must run in a fresh process via
 * {@see @runInSeparateProcess} + {@see @preserveGlobalState disabled}.
 *
 * Tests that rely only on environment variables use putenv() because env
 * vars are mutable and do not require process isolation.
 *
 * @covers \Automattic\WpAiAgent\Integration\WpCli\WpConfigConfiguration
 *
 * @since n.e.x.t
 */
final class WpConfigConfigurationTest extends TestCase
{
	/**
	 * Clears any ANTHROPIC_API_KEY env var before/after each test to avoid
	 * leaking state between tests within the same process.
	 */
	protected function setUp(): void
	{
		parent::setUp();

		putenv('ANTHROPIC_API_KEY');
	}

	protected function tearDown(): void
	{
		parent::tearDown();

		putenv('ANTHROPIC_API_KEY');
	}

	// -----------------------------------------------------------------------
	// getModel()
	// -----------------------------------------------------------------------

	/**
	 * Tests that getModel() returns the default value when WP_AI_AGENT_MODEL
	 * is not defined.
	 *
	 * Runs in a separate process to guarantee the constant is absent.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_getModel_withoutConstant_returnsDefault(): void
	{
		$config = new WpConfigConfiguration();

		$this->assertSame('claude-sonnet-4-6', $config->getModel());
	}

	// -----------------------------------------------------------------------
	// getApiKey()
	// -----------------------------------------------------------------------

	/**
	 * Tests that getApiKey() returns the environment-variable value when the
	 * ANTHROPIC_API_KEY constant is not defined.
	 *
	 * Runs in a separate process to guarantee the constant is absent.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_getApiKey_withEnvVar_returnsEnvValue(): void
	{
		putenv('ANTHROPIC_API_KEY=test-key-from-env');

		$config = new WpConfigConfiguration();

		$this->assertSame('test-key-from-env', $config->getApiKey());
	}

	/**
	 * Tests that getApiKey() returns an empty string when neither the constant
	 * nor the environment variable is set.
	 *
	 * Runs in a separate process to guarantee the constant is absent.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_getApiKey_withNeitherConstantNorEnv_returnsEmpty(): void
	{
		// Ensure no env var is present.
		putenv('ANTHROPIC_API_KEY');

		$config = new WpConfigConfiguration();

		$this->assertSame('', $config->getApiKey());
	}

	// -----------------------------------------------------------------------
	// getBypassedTools()
	// -----------------------------------------------------------------------

	/**
	 * Tests that getBypassedTools() returns an empty array when the constant
	 * holds an empty string.
	 *
	 * Runs in a separate process so we can define the constant safely.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_getBypassedTools_withEmptyString_returnsEmptyArray(): void
	{
		define('WP_AI_AGENT_BYPASSED_TOOLS', '');

		$config = new WpConfigConfiguration();

		$this->assertSame([], $config->getBypassedTools());
	}

	/**
	 * Tests that getBypassedTools() splits a comma-separated string and trims
	 * surrounding whitespace from each element.
	 *
	 * Runs in a separate process so we can define the constant safely.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_getBypassedTools_withCommaSeparatedValues_returnsTrimmedArray(): void
	{
		define('WP_AI_AGENT_BYPASSED_TOOLS', 'bash, grep');

		$config = new WpConfigConfiguration();

		$this->assertSame(['bash', 'grep'], $config->getBypassedTools());
	}

	// -----------------------------------------------------------------------
	// getSessionStoragePath()
	// -----------------------------------------------------------------------

	/**
	 * Tests that getSessionStoragePath() always returns an empty string because
	 * the WordPress path stores sessions as options, not files.
	 */
	public function test_getSessionStoragePath_returnsEmptyString(): void
	{
		$config = new WpConfigConfiguration();

		$this->assertSame('', $config->getSessionStoragePath());
	}

	// -----------------------------------------------------------------------
	// set() / merge() / loadFromFile() — read-only guard
	// -----------------------------------------------------------------------

	/**
	 * Tests that set() throws ConfigurationException because the class is
	 * read-only.
	 */
	public function test_set_throwsConfigurationException(): void
	{
		$config = new WpConfigConfiguration();

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('read-only');

		$config->set('model', 'gpt-4');
	}

	/**
	 * Tests that merge() throws ConfigurationException because the class is
	 * read-only.
	 */
	public function test_merge_throwsConfigurationException(): void
	{
		$config = new WpConfigConfiguration();

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('read-only');

		$config->merge(['model' => 'gpt-4']);
	}

	/**
	 * Tests that loadFromFile() throws ConfigurationException because the
	 * class is read-only.
	 */
	public function test_loadFromFile_throwsConfigurationException(): void
	{
		$config = new WpConfigConfiguration();

		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('read-only');

		$config->loadFromFile('/some/path/settings.json');
	}

	// -----------------------------------------------------------------------
	// has() / get() / toArray()
	// -----------------------------------------------------------------------

	/**
	 * Tests that has() returns true for all known configuration keys.
	 */
	public function test_has_withKnownKey_returnsTrue(): void
	{
		$config = new WpConfigConfiguration();

		$this->assertTrue($config->has('model'));
		$this->assertTrue($config->has('api_key'));
		$this->assertTrue($config->has('max_tokens'));
		$this->assertTrue($config->has('temperature'));
		$this->assertTrue($config->has('system_prompt'));
		$this->assertTrue($config->has('debug'));
		$this->assertTrue($config->has('streaming'));
		$this->assertTrue($config->has('max_iterations'));
		$this->assertTrue($config->has('bypassed_tools'));
		$this->assertTrue($config->has('session_storage_path'));
	}

	/**
	 * Tests that has() returns false for an unknown key.
	 */
	public function test_has_withUnknownKey_returnsFalse(): void
	{
		$config = new WpConfigConfiguration();

		$this->assertFalse($config->has('unknown_key'));
	}

	/**
	 * Tests that get() returns the default value for an unknown key.
	 */
	public function test_get_withUnknownKey_returnsDefault(): void
	{
		$config = new WpConfigConfiguration();

		$this->assertSame('fallback', $config->get('unknown_key', 'fallback'));
	}

	/**
	 * Tests that toArray() contains every known configuration key.
	 */
	public function test_toArray_containsAllKnownKeys(): void
	{
		$config = new WpConfigConfiguration();
		$array = $config->toArray();

		$this->assertArrayHasKey('api_key', $array);
		$this->assertArrayHasKey('model', $array);
		$this->assertArrayHasKey('max_tokens', $array);
		$this->assertArrayHasKey('temperature', $array);
		$this->assertArrayHasKey('system_prompt', $array);
		$this->assertArrayHasKey('debug', $array);
		$this->assertArrayHasKey('streaming', $array);
		$this->assertArrayHasKey('max_iterations', $array);
		$this->assertArrayHasKey('bypassed_tools', $array);
		$this->assertArrayHasKey('session_storage_path', $array);
	}

	// -----------------------------------------------------------------------
	// Default values for numeric / boolean accessors
	// -----------------------------------------------------------------------

	/**
	 * Tests that getMaxTokens() returns 8192 when WP_AI_AGENT_MAX_TOKENS is
	 * not defined.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_getMaxTokens_withoutConstant_returnsDefault(): void
	{
		$config = new WpConfigConfiguration();

		$this->assertSame(8192, $config->getMaxTokens());
	}

	/**
	 * Tests that getTemperature() returns 1.0 when WP_AI_AGENT_TEMPERATURE is
	 * not defined.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_getTemperature_withoutConstant_returnsDefault(): void
	{
		$config = new WpConfigConfiguration();

		$this->assertSame(1.0, $config->getTemperature());
	}

	/**
	 * Tests that isDebugEnabled() returns false when WP_AI_AGENT_DEBUG is not
	 * defined.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_isDebugEnabled_withoutConstant_returnsFalse(): void
	{
		$config = new WpConfigConfiguration();

		$this->assertFalse($config->isDebugEnabled());
	}

	/**
	 * Tests that isStreamingEnabled() returns true when WP_AI_AGENT_STREAMING
	 * is not defined.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_isStreamingEnabled_withoutConstant_returnsTrue(): void
	{
		$config = new WpConfigConfiguration();

		$this->assertTrue($config->isStreamingEnabled());
	}

	/**
	 * Tests that getMaxIterations() returns 10 when WP_AI_AGENT_MAX_ITERATIONS
	 * is not defined.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_getMaxIterations_withoutConstant_returnsDefault(): void
	{
		$config = new WpConfigConfiguration();

		$this->assertSame(50, $config->getMaxIterations());
	}

	// -----------------------------------------------------------------------
	// getAutoConfirm()
	// -----------------------------------------------------------------------

	/**
	 * Tests that getAutoConfirm() returns true when WP_AI_AGENT_AUTO_CONFIRM
	 * is defined as true.
	 *
	 * Runs in a separate process so we can define the constant safely without
	 * affecting other tests (PHP constants cannot be undefined once defined).
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_getAutoConfirm_withConstantDefined_returnsTrue(): void
	{
		define('WP_AI_AGENT_AUTO_CONFIRM', true);

		$config = new WpConfigConfiguration();

		$this->assertTrue($config->getAutoConfirm());
	}

	/**
	 * Tests that getAutoConfirm() returns false when WP_AI_AGENT_AUTO_CONFIRM
	 * is not defined.
	 *
	 * Runs in a separate process to guarantee the constant is absent.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_getAutoConfirm_withoutConstant_returnsFalse(): void
	{
		$config = new WpConfigConfiguration();

		$this->assertFalse($config->getAutoConfirm());
	}

	// -----------------------------------------------------------------------
	// Interface contract
	// -----------------------------------------------------------------------

	/**
	 * Tests that WpConfigConfiguration implements ConfigurationInterface.
	 */
	public function test_implementsConfigurationInterface(): void
	{
		$config = new WpConfigConfiguration();

		$this->assertInstanceOf(ConfigurationInterface::class, $config);
	}
}
