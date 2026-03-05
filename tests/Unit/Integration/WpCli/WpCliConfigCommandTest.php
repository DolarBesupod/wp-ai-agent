<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Integration\WpCli;

use PHPUnit\Framework\TestCase;
use Automattic\WpAiAgent\Integration\WpCli\WpCliConfigCommand;

/**
 * Unit tests for WpCliConfigCommand.
 *
 * WpCliConfigCommand reads PHP constants and delegates writes to WP-CLI's
 * `config set` command via WP_CLI::runcommand(). All WP_CLI calls are
 * captured by the stub in tests/Stubs/WpCliStub.php.
 *
 * @covers \Automattic\WpAiAgent\Integration\WpCli\WpCliConfigCommand
 *
 * @since n.e.x.t
 */
final class WpCliConfigCommandTest extends TestCase
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
	 * Tests that get() with an unknown key calls WP_CLI::error() and does not
	 * attempt to read any constant.
	 */
	public function test_get_withUnknownKey_callsWpCliError(): void
	{
		$command = new WpCliConfigCommand();

		$command->get(['totally-unknown-key'], []);

		$error_calls = array_filter(
			\WP_CLI::$calls,
			static fn (array $c): bool => $c[0] === 'error'
		);

		$this->assertNotEmpty($error_calls, 'get() with an unknown key must call WP_CLI::error()');

		$first_error = array_values($error_calls)[0];
		$this->assertStringContainsString(
			'totally-unknown-key',
			$first_error[1],
			'The error message must contain the unknown key name'
		);
	}

	/**
	 * Tests that set() with an unknown key calls WP_CLI::error() and does not
	 * call WP_CLI::runcommand().
	 */
	public function test_set_withUnknownKey_callsWpCliError(): void
	{
		$command = new WpCliConfigCommand();

		$command->set(['no-such-key', 'some-value'], []);

		$error_calls = array_filter(
			\WP_CLI::$calls,
			static fn (array $c): bool => $c[0] === 'error'
		);

		$this->assertNotEmpty($error_calls, 'set() with an unknown key must call WP_CLI::error()');

		$runcommand_calls = array_filter(
			\WP_CLI::$calls,
			static fn (array $c): bool => $c[0] === 'runcommand'
		);

		$this->assertEmpty($runcommand_calls, 'set() with an unknown key must not call WP_CLI::runcommand()');
	}

	/**
	 * Tests that set() with a valid key but no value argument calls WP_CLI::error().
	 *
	 * $args[1] is the value; when it is absent (or an empty string), the command
	 * must reject the call with an error rather than writing an empty constant.
	 */
	public function test_set_withMissingValue_callsWpCliError(): void
	{
		$command = new WpCliConfigCommand();

		// Only the key — no value positional argument.
		$command->set(['model'], []);

		$error_calls = array_filter(
			\WP_CLI::$calls,
			static fn (array $c): bool => $c[0] === 'error'
		);

		$this->assertNotEmpty($error_calls, 'set() without a value must call WP_CLI::error()');
	}

	/**
	 * Tests that set() with a valid key and value calls WP_CLI::runcommand()
	 * and then WP_CLI::success().
	 */
	public function test_set_withValidKeyAndValue_callsRuncommandAndSuccess(): void
	{
		$command = new WpCliConfigCommand();

		$command->set(['model', 'claude-opus-4-6'], []);

		$runcommand_calls = array_filter(
			\WP_CLI::$calls,
			static fn (array $c): bool => $c[0] === 'runcommand'
		);

		$this->assertNotEmpty(
			$runcommand_calls,
			'set() with a valid key and value must call WP_CLI::runcommand()'
		);

		$first_runcommand = array_values($runcommand_calls)[0];
		$this->assertStringContainsString(
			'WP_AI_AGENT_MODEL',
			$first_runcommand[1],
			'runcommand() must reference the constant name WP_AI_AGENT_MODEL'
		);
		$this->assertStringContainsString(
			'claude-opus-4-6',
			$first_runcommand[1],
			'runcommand() must include the new value'
		);

		$success_calls = array_filter(
			\WP_CLI::$calls,
			static fn (array $c): bool => $c[0] === 'success'
		);

		$this->assertNotEmpty($success_calls, 'set() must call WP_CLI::success() after writing the constant');
	}

	/**
	 * Tests that list() calls WP_CLI\Utils\format_items() with table format
	 * and the three expected column names.
	 */
	public function test_list_callsFormatItems(): void
	{
		$command = new WpCliConfigCommand();

		$command->list([], []);

		$format_calls = array_filter(
			\WP_CLI::$calls,
			static fn (array $c): bool => $c[0] === 'format_items'
		);

		$this->assertNotEmpty(
			$format_calls,
			'list() must call WP_CLI\Utils\format_items()'
		);

		$first_call = array_values($format_calls)[0];
		$this->assertSame('table', $first_call[1], 'list() must request table format');
		$this->assertSame(
			['key', 'constant', 'value'],
			$first_call[3],
			'list() must pass key, constant, value as columns'
		);
	}

	/**
	 * Tests that get() with the key 'model' calls WP_CLI::line() with the
	 * constant value (or the "(not set)" placeholder when undefined).
	 */
	public function test_get_withKnownKey_callsWpCliLine(): void
	{
		$command = new WpCliConfigCommand();

		$command->get(['model'], []);

		$line_calls = array_filter(
			\WP_CLI::$calls,
			static fn (array $c): bool => $c[0] === 'line'
		);

		$this->assertNotEmpty($line_calls, 'get() with a known key must call WP_CLI::line() with the value');
	}
}
