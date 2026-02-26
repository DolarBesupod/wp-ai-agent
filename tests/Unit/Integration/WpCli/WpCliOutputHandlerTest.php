<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Integration\WpCli;

use WpAiAgent\Core\Contracts\OutputHandlerInterface;
use WpAiAgent\Core\ValueObjects\ToolResult;
use WpAiAgent\Integration\WpCli\WpCliOutputHandler;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WpCliOutputHandler.
 *
 * WP_CLI is a static class only available in a real WP-CLI runtime. The stub
 * defined in tests/Stubs/WpCliStub.php (loaded by tests/bootstrap.php) records
 * every static call so tests can assert output routing without a live runtime.
 *
 * @covers \WpAiAgent\Integration\WpCli\WpCliOutputHandler
 *
 * @since n.e.x.t
 */
final class WpCliOutputHandlerTest extends TestCase
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
	 * Tests that writeLine() delegates to WP_CLI::line().
	 */
	public function test_writeLine_callsWpCliLine(): void
	{
		$handler = new WpCliOutputHandler();

		$handler->writeLine('Hello, world!');

		$this->assertSame([['line', 'Hello, world!']], \WP_CLI::$calls);
	}

	/**
	 * Tests that writeError() delegates to WP_CLI::error() with the non-fatal flag (false).
	 *
	 * The second argument must be false so WP-CLI does not call exit(), allowing
	 * the agent to continue execution after reporting an error.
	 */
	public function test_writeError_callsWpCliErrorWithNonFatalFlag(): void
	{
		$handler = new WpCliOutputHandler();

		$handler->writeError('something failed');

		$this->assertCount(1, \WP_CLI::$calls);
		$this->assertSame('error', \WP_CLI::$calls[0][0]);
		$this->assertSame('something failed', \WP_CLI::$calls[0][1]);
		$this->assertFalse(
			\WP_CLI::$calls[0][2],
			'writeError() must pass false as the second argument to keep WP_CLI::error() non-fatal'
		);
	}

	/**
	 * Tests that writeSuccess() delegates to WP_CLI::success().
	 */
	public function test_writeSuccess_callsWpCliSuccess(): void
	{
		$handler = new WpCliOutputHandler();

		$handler->writeSuccess('done');

		$this->assertSame([['success', 'done']], \WP_CLI::$calls);
	}

	/**
	 * Tests that writeWarning() delegates to WP_CLI::warning().
	 */
	public function test_writeWarning_callsWpCliWarning(): void
	{
		$handler = new WpCliOutputHandler();

		$handler->writeWarning('watch out');

		$this->assertSame([['warning', 'watch out']], \WP_CLI::$calls);
	}

	/**
	 * Tests that writeStatus() delegates to WP_CLI::log().
	 */
	public function test_writeStatus_callsWpCliLog(): void
	{
		$handler = new WpCliOutputHandler();

		$handler->writeStatus('Loading...');

		$this->assertSame([['log', 'Loading...']], \WP_CLI::$calls);
	}

	/**
	 * Tests that writeDebug() does not call WP_CLI::debug() when debug mode is disabled.
	 */
	public function test_writeDebug_whenDisabled_doesNotCallWpCliDebug(): void
	{
		$handler = new WpCliOutputHandler();
		// Debug is disabled by default — no call to setDebugEnabled(true).

		$handler->writeDebug('secret debug info');

		$debug_calls = array_filter(
			\WP_CLI::$calls,
			static fn (array $c): bool => $c[0] === 'debug'
		);
		$this->assertEmpty($debug_calls, 'writeDebug() must not call WP_CLI::debug() when debug is disabled');
	}

	/**
	 * Tests that writeDebug() calls WP_CLI::debug() with the 'wp-ai-agent' group when enabled.
	 */
	public function test_writeDebug_whenEnabled_callsWpCliDebugWithGroup(): void
	{
		$handler = new WpCliOutputHandler();
		$handler->setDebugEnabled(true);

		$handler->writeDebug('detailed trace');

		$this->assertCount(1, \WP_CLI::$calls);
		$this->assertSame('debug', \WP_CLI::$calls[0][0]);
		$this->assertSame('detailed trace', \WP_CLI::$calls[0][1]);
		$this->assertSame('wp-ai-agent', \WP_CLI::$calls[0][2]);
	}

	/**
	 * Tests that clearLine() is a pure no-op: no WP_CLI calls and no output.
	 */
	public function test_clearLine_doesNotThrowAndProducesNoOutput(): void
	{
		$handler = new WpCliOutputHandler();

		$handler->clearLine();

		$this->assertEmpty(\WP_CLI::$calls, 'clearLine() must not delegate to any WP_CLI method');
	}

	/**
	 * Tests that write() echoes text directly without a trailing newline.
	 *
	 * Output buffering is required because phpunit.xml sets
	 * beStrictAboutOutputDuringTests="true".
	 */
	public function test_write_echosTextWithoutNewline(): void
	{
		$handler = new WpCliOutputHandler();

		ob_start();
		$handler->write('streaming chunk');
		$output = ob_get_clean();

		$this->assertSame('streaming chunk', $output);
	}

	/**
	 * Tests that writeStreamChunk() echoes text directly without a trailing newline.
	 */
	public function test_writeStreamChunk_echosTextWithoutNewline(): void
	{
		$handler = new WpCliOutputHandler();

		ob_start();
		$handler->writeStreamChunk('partial response');
		$output = ob_get_clean();

		$this->assertSame('partial response', $output);
	}

	/**
	 * Tests that writeAssistantResponse() delegates to WP_CLI::line().
	 */
	public function test_writeAssistantResponse_callsWpCliLine(): void
	{
		$handler = new WpCliOutputHandler();

		$handler->writeAssistantResponse('I am an AI assistant.');

		$this->assertSame([['line', 'I am an AI assistant.']], \WP_CLI::$calls);
	}

	/**
	 * Tests that writeToolResult() for a successful result outputs [OK] prefix via WP_CLI::line().
	 */
	public function test_writeToolResult_withSuccess_callsWpCliLineWithOkPrefix(): void
	{
		$handler = new WpCliOutputHandler();
		$result = ToolResult::success('file contents here');

		$handler->writeToolResult('read_file', $result);

		$this->assertCount(1, \WP_CLI::$calls);
		$this->assertSame('line', \WP_CLI::$calls[0][0]);
		$this->assertStringContainsString('[OK]', \WP_CLI::$calls[0][1]);
		$this->assertStringContainsString('read_file', \WP_CLI::$calls[0][1]);
		$this->assertStringContainsString('file contents here', \WP_CLI::$calls[0][1]);
	}

	/**
	 * Tests that writeToolResult() for a failed result outputs [FAIL] prefix and the
	 * error message, not the captured stdout output.
	 */
	public function test_writeToolResult_withFailure_callsWpCliLineWithFailPrefixAndError(): void
	{
		$handler = new WpCliOutputHandler();
		$result = ToolResult::failure('Permission denied', 'some stdout');

		$handler->writeToolResult('bash', $result);

		$this->assertCount(1, \WP_CLI::$calls);
		$this->assertSame('line', \WP_CLI::$calls[0][0]);
		$this->assertStringContainsString('[FAIL]', \WP_CLI::$calls[0][1]);
		$this->assertStringContainsString('bash', \WP_CLI::$calls[0][1]);
		// Failed results must show the error message, not the captured stdout.
		$this->assertStringContainsString('Permission denied', \WP_CLI::$calls[0][1]);
	}

	/**
	 * Tests that writeToolResult() truncates output to at most 200 characters.
	 */
	public function test_writeToolResult_truncatesOutputTo200Characters(): void
	{
		$handler = new WpCliOutputHandler();
		$long_output = str_repeat('X', 300);
		$result = ToolResult::success($long_output);

		$handler->writeToolResult('grep', $result);

		$this->assertCount(1, \WP_CLI::$calls);
		// The line prefix is "[OK] grep: " — the output segment must not exceed 200 chars.
		$this->assertStringNotContainsString(str_repeat('X', 201), \WP_CLI::$calls[0][1]);
	}

	/**
	 * Tests that setDebugEnabled() stores the flag and isDebugEnabled() reflects changes.
	 */
	public function test_setDebugEnabled_persistsFlagAndIsDebugEnabledReflectsIt(): void
	{
		$handler = new WpCliOutputHandler();

		$this->assertFalse($handler->isDebugEnabled());

		$handler->setDebugEnabled(true);
		$this->assertTrue($handler->isDebugEnabled());

		$handler->setDebugEnabled(false);
		$this->assertFalse($handler->isDebugEnabled());
	}

	/**
	 * Tests that the handler implements OutputHandlerInterface.
	 */
	public function test_implementsOutputHandlerInterface(): void
	{
		$handler = new WpCliOutputHandler();

		$this->assertInstanceOf(OutputHandlerInterface::class, $handler);
	}
}
