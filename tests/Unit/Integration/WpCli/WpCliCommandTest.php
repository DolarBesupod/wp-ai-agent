<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Integration\WpCli;

use PHPUnit\Framework\TestCase;
use Automattic\WpAiAgent\Integration\WpCli\WpCliCommand;

/**
 * Unit tests for WpCliCommand.
 *
 * WpCliCommand is a thin dispatcher that delegates to WpCliBootstrap::createApplication().
 * Instantiating the real bootstrap requires a full WordPress runtime, so these
 * tests focus exclusively on guard/error paths that short-circuit before the
 * bootstrap is called.
 *
 * @covers \Automattic\WpAiAgent\Integration\WpCli\WpCliCommand
 *
 * @since n.e.x.t
 */
final class WpCliCommandTest extends TestCase
{
	/**
	 * Resets the WP_CLI stub state before each test to ensure isolation.
	 */
	protected function setUp(): void
	{
		\WP_CLI::$calls = [];
		\WP_CLI::$confirm_throws = false;
	}

	/**
	 * Tests that ask() with no positional arguments calls WP_CLI::error()
	 * and does not attempt to call the bootstrap.
	 *
	 * When $args[0] is missing (empty array), WpCliCommand must report an
	 * error via WP_CLI::error() before touching WpCliBootstrap.
	 */
	public function test_ask_withNoArgs_callsWpCliError(): void
	{
		$command = new WpCliCommand();

		$command->ask([], []);

		$error_calls = array_filter(
			\WP_CLI::$calls,
			static fn (array $c): bool => $c[0] === 'error'
		);

		$this->assertNotEmpty($error_calls, 'ask() with no args must call WP_CLI::error()');

		$first_error = array_values($error_calls)[0];
		$this->assertStringContainsString(
			'message',
			$first_error[1],
			'The error message must mention that a message is required'
		);
	}

	/**
	 * Tests that run() calls WP_CLI::warning() with a deprecation notice
	 * before forwarding to chat().
	 *
	 * The run() method is a deprecated alias for chat(). It must issue a
	 * WP_CLI::warning() so users know to switch to `wp agent chat`.
	 * The bootstrap will fail here (no WordPress), but the warning is
	 * emitted first, which is what this test verifies.
	 */
	public function test_run_callsWpCliWarningAboutDeprecation(): void
	{
		$command = new WpCliCommand();

		try {
			$command->run([], []);
		} catch (\Throwable $e) {
			// Bootstrap will throw because there is no WordPress runtime.
			// That is expected — we only care about the warning being recorded.
		}

		$warning_calls = array_filter(
			\WP_CLI::$calls,
			static fn (array $c): bool => $c[0] === 'warning'
		);

		$this->assertNotEmpty($warning_calls, 'run() must call WP_CLI::warning() with a deprecation notice');

		$first_warning = array_values($warning_calls)[0];
		$this->assertStringContainsString(
			'deprecated',
			$first_warning[1],
			'The deprecation warning must mention that wp agent run is deprecated'
		);
		$this->assertStringContainsString(
			'chat',
			$first_warning[1],
			'The deprecation warning must suggest wp agent chat as the replacement'
		);
	}

	/**
	 * Tests that ask() with an empty string in $args[0] also calls WP_CLI::error().
	 *
	 * An empty string is considered a missing message and must be rejected the
	 * same way as a completely absent positional argument.
	 */
	public function test_ask_withEmptyStringMessage_callsWpCliError(): void
	{
		$command = new WpCliCommand();

		$command->ask([''], []);

		$error_calls = array_filter(
			\WP_CLI::$calls,
			static fn (array $c): bool => $c[0] === 'error'
		);

		$this->assertNotEmpty($error_calls, 'ask() with an empty message string must call WP_CLI::error()');
	}
}
