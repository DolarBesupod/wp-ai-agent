<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Integration\WpCli;

use WpAiAgent\Core\Contracts\ConfirmationHandlerInterface;
use WpAiAgent\Integration\WpCli\WpCliConfirmationHandler;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WpCliConfirmationHandler.
 *
 * WP_CLI is a static class only available in a real WP-CLI runtime. The stub
 * defined in tests/Stubs/WpCliStub.php (loaded by tests/bootstrap.php) records
 * every static call and can simulate WP_CLI\ExitException (user declines prompt)
 * via the `$confirm_throws` flag.
 *
 * @covers \WpAiAgent\Integration\WpCli\WpCliConfirmationHandler
 *
 * @since n.e.x.t
 */
final class WpCliConfirmationHandlerTest extends TestCase
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
	 * Tests that confirm() returns true immediately when auto-confirm is enabled,
	 * without invoking WP_CLI::confirm().
	 */
	public function test_confirm_whenAutoConfirm_returnsTrueWithoutCallingWpCliConfirm(): void
	{
		$handler = new WpCliConfirmationHandler([], true);

		$result = $handler->confirm('bash', ['command' => 'rm -rf /']);

		$this->assertTrue($result);
		$confirm_calls = array_filter(
			\WP_CLI::$calls,
			static fn (array $c): bool => $c[0] === 'confirm'
		);
		$this->assertEmpty($confirm_calls, 'WP_CLI::confirm() must not be called when auto-confirm is active');
	}

	/**
	 * Tests that confirm() returns true for a bypassed tool without calling WP_CLI::confirm().
	 */
	public function test_confirm_whenToolBypassed_returnsTrueWithoutCallingWpCliConfirm(): void
	{
		$handler = new WpCliConfirmationHandler(['read_file'], false);

		$result = $handler->confirm('read_file', ['path' => 'test.txt']);

		$this->assertTrue($result);
		$confirm_calls = array_filter(
			\WP_CLI::$calls,
			static fn (array $c): bool => $c[0] === 'confirm'
		);
		$this->assertEmpty($confirm_calls, 'WP_CLI::confirm() must not be called for a bypassed tool');
	}

	/**
	 * Tests that confirm() returns false when WP_CLI::confirm() throws ExitException.
	 *
	 * WP-CLI throws WP_CLI\ExitException when the user answers "no" to a prompt.
	 */
	public function test_confirm_whenExitExceptionThrown_returnsFalse(): void
	{
		\WP_CLI::$confirm_throws = true;
		$handler = new WpCliConfirmationHandler([], false);

		$result = $handler->confirm('bash', ['command' => 'dangerous']);

		$this->assertFalse($result);
	}

	/**
	 * Tests that confirm() returns true when WP_CLI::confirm() completes normally (user says yes).
	 */
	public function test_confirm_whenConfirmSucceeds_returnsTrue(): void
	{
		\WP_CLI::$confirm_throws = false;
		$handler = new WpCliConfirmationHandler([], false);

		$result = $handler->confirm('bash', ['command' => 'ls -la']);

		$this->assertTrue($result);
	}

	/**
	 * Tests that addBypass() adds a tool to the bypass list and shouldBypass() returns true.
	 */
	public function test_addBypass_and_shouldBypass_returnsTrue(): void
	{
		$handler = new WpCliConfirmationHandler([], false);

		$handler->addBypass('write_file');

		$this->assertTrue($handler->shouldBypass('write_file'));
	}

	/**
	 * Tests that addBypass() called twice for the same tool does not create a duplicate entry.
	 */
	public function test_addBypass_calledTwice_doesNotAddDuplicate(): void
	{
		$handler = new WpCliConfirmationHandler([], false);

		$handler->addBypass('bash');
		$handler->addBypass('bash');

		$this->assertCount(1, $handler->getBypasses());
	}

	/**
	 * Tests that removeBypass() removes a previously added tool and shouldBypass() returns false.
	 */
	public function test_removeBypass_and_shouldBypass_returnsFalse(): void
	{
		$handler = new WpCliConfirmationHandler(['bash'], false);

		$handler->removeBypass('bash');

		$this->assertFalse($handler->shouldBypass('bash'));
	}

	/**
	 * Tests that removeBypass() on a tool that is not in the list is a no-op.
	 */
	public function test_removeBypass_withAbsentTool_isNoOp(): void
	{
		$handler = new WpCliConfirmationHandler([], false);

		// Must not throw.
		$handler->removeBypass('nonexistent_tool');

		$this->assertFalse($handler->shouldBypass('nonexistent_tool'));
	}

	/**
	 * Tests that clearBypasses() empties the entire bypass list.
	 */
	public function test_clearBypasses_emptiesList(): void
	{
		$handler = new WpCliConfirmationHandler(['bash', 'write_file'], false);

		$handler->clearBypasses();

		$this->assertEmpty($handler->getBypasses());
	}

	/**
	 * Tests that the constructor pre-populates the bypass list with the supplied initial bypasses.
	 */
	public function test_constructor_initialBypassesArePrePopulated(): void
	{
		$handler = new WpCliConfirmationHandler(['read_file', 'grep'], false);

		$this->assertTrue($handler->shouldBypass('read_file'));
		$this->assertTrue($handler->shouldBypass('grep'));
		$this->assertFalse($handler->shouldBypass('bash'));
	}

	/**
	 * Tests that the default constructor (no arguments) starts with an empty bypass list
	 * and auto-confirm disabled.
	 */
	public function test_constructor_defaultArguments_startWithEmptyBypassListAndNoAutoConfirm(): void
	{
		$handler = new WpCliConfirmationHandler();

		$this->assertEmpty($handler->getBypasses());
		$this->assertFalse($handler->isAutoConfirm());
	}

	/**
	 * Tests that setAutoConfirm() stores the flag and isAutoConfirm() reflects it.
	 */
	public function test_setAutoConfirm_and_isAutoConfirm_reflectFlag(): void
	{
		$handler = new WpCliConfirmationHandler();

		$this->assertFalse($handler->isAutoConfirm());

		$handler->setAutoConfirm(true);
		$this->assertTrue($handler->isAutoConfirm());

		$handler->setAutoConfirm(false);
		$this->assertFalse($handler->isAutoConfirm());
	}

	/**
	 * Tests that getBypasses() returns all currently bypassed tool names.
	 */
	public function test_getBypasses_returnsCurrentBypassList(): void
	{
		$handler = new WpCliConfirmationHandler(['tool_a', 'tool_b'], false);
		$handler->addBypass('tool_c');

		$bypasses = $handler->getBypasses();

		$this->assertContains('tool_a', $bypasses);
		$this->assertContains('tool_b', $bypasses);
		$this->assertContains('tool_c', $bypasses);
	}

	/**
	 * Tests that shouldBypass() returns false for a tool not in the bypass list.
	 */
	public function test_shouldBypass_withUnknownTool_returnsFalse(): void
	{
		$handler = new WpCliConfirmationHandler(['read_file'], false);

		$this->assertFalse($handler->shouldBypass('unknown_tool'));
	}

	/**
	 * Tests that confirm() passes the tool name in the message forwarded to WP_CLI::confirm().
	 */
	public function test_confirm_passesToolNameInMessageToWpCliConfirm(): void
	{
		\WP_CLI::$confirm_throws = false;
		$handler = new WpCliConfirmationHandler([], false);

		$handler->confirm('my_special_tool', []);

		$confirm_calls = array_values(
			array_filter(
				\WP_CLI::$calls,
				static fn (array $c): bool => $c[0] === 'confirm'
			)
		);
		$this->assertNotEmpty($confirm_calls, 'WP_CLI::confirm() must be called for a non-bypassed tool');
		$this->assertStringContainsString('my_special_tool', (string) $confirm_calls[0][1]);
	}

	/**
	 * Tests that the handler implements ConfirmationHandlerInterface.
	 */
	public function test_implementsConfirmationHandlerInterface(): void
	{
		$handler = new WpCliConfirmationHandler();

		$this->assertInstanceOf(ConfirmationHandlerInterface::class, $handler);
	}
}
