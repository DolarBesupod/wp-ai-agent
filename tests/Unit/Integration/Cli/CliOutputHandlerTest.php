<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Integration\Cli;

use PhpCliAgent\Core\ValueObjects\ToolResult;
use PhpCliAgent\Integration\Cli\CliOutputHandler;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CliOutputHandler.
 *
 * @covers \PhpCliAgent\Integration\Cli\CliOutputHandler
 */
final class CliOutputHandlerTest extends TestCase
{
	/**
	 * @var resource
	 */
	private $output_stream;

	/**
	 * @var resource
	 */
	private $error_stream;

	/**
	 * Sets up the test fixtures.
	 */
	protected function setUp(): void
	{
		$this->output_stream = fopen('php://memory', 'r+');
		$this->error_stream = fopen('php://memory', 'r+');
	}

	/**
	 * Cleans up test resources.
	 */
	protected function tearDown(): void
	{
		if (is_resource($this->output_stream)) {
			fclose($this->output_stream);
		}
		if (is_resource($this->error_stream)) {
			fclose($this->error_stream);
		}
	}

	/**
	 * Helper to get output from memory stream.
	 *
	 * @param resource $stream The stream to read from.
	 *
	 * @return string The stream contents.
	 */
	private function getStreamContents($stream): string
	{
		rewind($stream);
		return stream_get_contents($stream);
	}

	/**
	 * Tests that write outputs text without newline.
	 */
	public function test_write_outputsTextWithoutNewline(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);

		$handler->write('Hello');

		$output = $this->getStreamContents($this->output_stream);
		$this->assertSame('Hello', $output);
	}

	/**
	 * Tests that consecutive writes are concatenated.
	 */
	public function test_write_consecutiveCalls_concatenatesOutput(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);

		$handler->write('Hello');
		$handler->write(' World');

		$output = $this->getStreamContents($this->output_stream);
		$this->assertSame('Hello World', $output);
	}

	/**
	 * Tests that writeLine adds newline after text.
	 */
	public function test_writeLine_addsNewlineAfterText(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);

		$handler->writeLine('Hello');

		$output = $this->getStreamContents($this->output_stream);
		$this->assertSame("Hello\n", $output);
	}

	/**
	 * Tests that writeError outputs red text when colors enabled.
	 */
	public function test_writeError_withColorsEnabled_outputsRedText(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, true);

		$handler->writeError('Failed');

		$output = $this->getStreamContents($this->error_stream);
		$this->assertStringContainsString("\033[31m", $output);
		$this->assertStringContainsString('Failed', $output);
		$this->assertStringContainsString("\033[0m", $output);
	}

	/**
	 * Tests that writeError outputs plain text when colors disabled.
	 */
	public function test_writeError_withColorsDisabled_outputsPlainText(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);

		$handler->writeError('Failed');

		$output = $this->getStreamContents($this->error_stream);
		$this->assertSame("Failed\n", $output);
	}

	/**
	 * Tests that writeError goes to error stream.
	 */
	public function test_writeError_writesToErrorStream(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);

		$handler->writeError('Error message');

		$stdout_output = $this->getStreamContents($this->output_stream);
		$stderr_output = $this->getStreamContents($this->error_stream);

		$this->assertEmpty($stdout_output);
		$this->assertStringContainsString('Error message', $stderr_output);
	}

	/**
	 * Tests that writeSuccess outputs green text.
	 */
	public function test_writeSuccess_withColorsEnabled_outputsGreenText(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, true);

		$handler->writeSuccess('Done!');

		$output = $this->getStreamContents($this->output_stream);
		$this->assertStringContainsString("\033[32m", $output);
		$this->assertStringContainsString('Done!', $output);
		$this->assertStringContainsString("\033[0m", $output);
	}

	/**
	 * Tests that writeSuccess outputs plain text when colors disabled.
	 */
	public function test_writeSuccess_withColorsDisabled_outputsPlainText(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);

		$handler->writeSuccess('Done!');

		$output = $this->getStreamContents($this->output_stream);
		$this->assertSame("Done!\n", $output);
	}

	/**
	 * Tests that writeWarning outputs yellow text.
	 */
	public function test_writeWarning_withColorsEnabled_outputsYellowText(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, true);

		$handler->writeWarning('Caution!');

		$output = $this->getStreamContents($this->output_stream);
		$this->assertStringContainsString("\033[33m", $output);
		$this->assertStringContainsString('Caution!', $output);
		$this->assertStringContainsString("\033[0m", $output);
	}

	/**
	 * Tests that writeWarning outputs plain text when colors disabled.
	 */
	public function test_writeWarning_withColorsDisabled_outputsPlainText(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);

		$handler->writeWarning('Caution!');

		$output = $this->getStreamContents($this->output_stream);
		$this->assertSame("Caution!\n", $output);
	}

	/**
	 * Tests that writeToolResult displays formatted box for success.
	 */
	public function test_writeToolResult_withSuccess_displaysFormattedBox(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);
		$result = ToolResult::success('file.txt');

		$handler->writeToolResult('bash', $result);

		$output = $this->getStreamContents($this->output_stream);

		$this->assertStringContainsString('bash', $output);
		$this->assertStringContainsString('SUCCESS', $output);
		$this->assertStringContainsString('file.txt', $output);
		$this->assertStringContainsString('┌', $output);
		$this->assertStringContainsString('└', $output);
		$this->assertStringContainsString('│', $output);
	}

	/**
	 * Tests that writeToolResult displays formatted box for failure.
	 */
	public function test_writeToolResult_withFailure_displaysFormattedBox(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);
		$result = ToolResult::failure('Command not found');

		$handler->writeToolResult('bash', $result);

		$output = $this->getStreamContents($this->output_stream);

		$this->assertStringContainsString('bash', $output);
		$this->assertStringContainsString('FAILED', $output);
		$this->assertStringContainsString('Command not found', $output);
	}

	/**
	 * Tests that writeToolResult uses green color for success with colors enabled.
	 */
	public function test_writeToolResult_withSuccessAndColors_usesGreenBorder(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, true);
		$result = ToolResult::success('output');

		$handler->writeToolResult('test', $result);

		$output = $this->getStreamContents($this->output_stream);
		$this->assertStringContainsString("\033[32m", $output);
	}

	/**
	 * Tests that writeToolResult uses red color for failure with colors enabled.
	 */
	public function test_writeToolResult_withFailureAndColors_usesRedBorder(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, true);
		$result = ToolResult::failure('error');

		$handler->writeToolResult('test', $result);

		$output = $this->getStreamContents($this->output_stream);
		$this->assertStringContainsString("\033[31m", $output);
	}

	/**
	 * Tests that writeToolResult shows error message for failed result.
	 */
	public function test_writeToolResult_withFailure_showsErrorMessage(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);
		$result = ToolResult::failure('Permission denied', 'some output');

		$handler->writeToolResult('bash', $result);

		$output = $this->getStreamContents($this->output_stream);
		$this->assertStringContainsString('Permission denied', $output);
	}

	/**
	 * Tests that writeToolResult handles empty output.
	 */
	public function test_writeToolResult_withEmptyOutput_showsPlaceholder(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);
		$result = ToolResult::success('');

		$handler->writeToolResult('bash', $result);

		$output = $this->getStreamContents($this->output_stream);
		$this->assertStringContainsString('(no output)', $output);
	}

	/**
	 * Tests that writeToolResult wraps long lines.
	 */
	public function test_writeToolResult_withLongOutput_wrapsLines(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);
		$long_output = str_repeat('A', 200);
		$result = ToolResult::success($long_output);

		$handler->writeToolResult('bash', $result);

		$output = $this->getStreamContents($this->output_stream);
		// The output should be contained within the box (wrapped).
		$this->assertStringContainsString('A', $output);
		// Box should contain multiple content lines due to wrapping.
		$lines = explode("\n", $output);
		$content_lines = array_filter($lines, fn ($line) => str_contains($line, '│'));
		$this->assertGreaterThan(1, count($content_lines));
	}

	/**
	 * Tests that writeAssistantResponse outputs text.
	 */
	public function test_writeAssistantResponse_outputsText(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);

		$handler->writeAssistantResponse('Hello, I am your assistant.');

		$output = $this->getStreamContents($this->output_stream);
		$this->assertStringContainsString('Hello, I am your assistant.', $output);
	}

	/**
	 * Tests that writeStreamChunk outputs without newline.
	 */
	public function test_writeStreamChunk_outputsWithoutNewline(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);

		$handler->writeStreamChunk('Hello');
		$handler->writeStreamChunk(' World');

		$output = $this->getStreamContents($this->output_stream);
		$this->assertSame('Hello World', $output);
	}

	/**
	 * Tests that writeStatus outputs with dim style.
	 */
	public function test_writeStatus_withColorsEnabled_outputsDimText(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, true);

		$handler->writeStatus('Loading...');

		$output = $this->getStreamContents($this->output_stream);
		$this->assertStringContainsString("\033[2m", $output);
		$this->assertStringContainsString('Loading...', $output);
	}

	/**
	 * Tests that writeStatus outputs plain text when colors disabled.
	 */
	public function test_writeStatus_withColorsDisabled_outputsPlainText(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);

		$handler->writeStatus('Loading...');

		$output = $this->getStreamContents($this->output_stream);
		$this->assertSame("Loading...\n", $output);
	}

	/**
	 * Tests that writeDebug outputs nothing when debug is disabled.
	 */
	public function test_writeDebug_whenDisabled_outputsNothing(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);

		$handler->writeDebug('Debug info');

		$output = $this->getStreamContents($this->error_stream);
		$this->assertEmpty($output);
	}

	/**
	 * Tests that writeDebug outputs when debug is enabled.
	 */
	public function test_writeDebug_whenEnabled_outputsToStderr(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);
		$handler->setDebugEnabled(true);

		$handler->writeDebug('Debug info');

		$output = $this->getStreamContents($this->error_stream);
		$this->assertStringContainsString('[DEBUG]', $output);
		$this->assertStringContainsString('Debug info', $output);
	}

	/**
	 * Tests that writeDebug uses dim style when colors enabled.
	 */
	public function test_writeDebug_withColorsEnabled_usesDimStyle(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, true);
		$handler->setDebugEnabled(true);

		$handler->writeDebug('Debug info');

		$output = $this->getStreamContents($this->error_stream);
		$this->assertStringContainsString("\033[2m", $output);
	}

	/**
	 * Tests clearLine with colors enabled.
	 */
	public function test_clearLine_withColorsEnabled_outputsAnsiClearSequence(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, true);

		$handler->clearLine();

		$output = $this->getStreamContents($this->output_stream);
		$this->assertStringContainsString("\r\033[K", $output);
	}

	/**
	 * Tests clearLine with colors disabled.
	 */
	public function test_clearLine_withColorsDisabled_outputsSpaces(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);

		$handler->clearLine();

		$output = $this->getStreamContents($this->output_stream);
		$this->assertStringStartsWith("\r", $output);
		$this->assertStringContainsString(' ', $output);
	}

	/**
	 * Tests setDebugEnabled enables debug mode.
	 */
	public function test_setDebugEnabled_enablesDebugMode(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);

		$handler->setDebugEnabled(true);

		$this->assertTrue($handler->isDebugEnabled());
	}

	/**
	 * Tests setDebugEnabled disables debug mode.
	 */
	public function test_setDebugEnabled_disablesDebugMode(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);
		$handler->setDebugEnabled(true);
		$handler->setDebugEnabled(false);

		$this->assertFalse($handler->isDebugEnabled());
	}

	/**
	 * Tests isDebugEnabled default is false.
	 */
	public function test_isDebugEnabled_defaultIsFalse(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);

		$this->assertFalse($handler->isDebugEnabled());
	}

	/**
	 * Tests setColorsEnabled enables colors.
	 */
	public function test_setColorsEnabled_enablesColors(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);

		$handler->setColorsEnabled(true);

		$this->assertTrue($handler->isColorsEnabled());
	}

	/**
	 * Tests setColorsEnabled disables colors.
	 */
	public function test_setColorsEnabled_disablesColors(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, true);

		$handler->setColorsEnabled(false);

		$this->assertFalse($handler->isColorsEnabled());
	}

	/**
	 * Tests that handler respects NO_COLOR environment variable.
	 *
	 * Note: This test modifies environment variables.
	 */
	public function test_constructor_withNoColorEnv_disablesColors(): void
	{
		$original = getenv('NO_COLOR');
		putenv('NO_COLOR=1');

		try {
			$handler = new CliOutputHandler($this->output_stream, $this->error_stream);
			$this->assertFalse($handler->isColorsEnabled());
		} finally {
			if ($original !== false) {
				putenv('NO_COLOR=' . $original);
			} else {
				putenv('NO_COLOR');
			}
		}
	}

	/**
	 * Tests that handler respects FORCE_COLOR environment variable.
	 *
	 * Note: This test modifies environment variables.
	 */
	public function test_constructor_withForceColorEnv_enablesColors(): void
	{
		$original_no_color = getenv('NO_COLOR');
		$original_force_color = getenv('FORCE_COLOR');

		// Make sure NO_COLOR is not set.
		putenv('NO_COLOR');
		putenv('FORCE_COLOR=1');

		try {
			$handler = new CliOutputHandler($this->output_stream, $this->error_stream);
			$this->assertTrue($handler->isColorsEnabled());
		} finally {
			if ($original_no_color !== false) {
				putenv('NO_COLOR=' . $original_no_color);
			}
			if ($original_force_color !== false) {
				putenv('FORCE_COLOR=' . $original_force_color);
			} else {
				putenv('FORCE_COLOR');
			}
		}
	}

	/**
	 * Tests that constructor with explicit colors overrides auto-detection.
	 */
	public function test_constructor_withExplicitColors_overridesAutoDetection(): void
	{
		$original = getenv('NO_COLOR');
		putenv('NO_COLOR=1');

		try {
			$handler = new CliOutputHandler($this->output_stream, $this->error_stream, true);
			$this->assertTrue($handler->isColorsEnabled());
		} finally {
			if ($original !== false) {
				putenv('NO_COLOR=' . $original);
			} else {
				putenv('NO_COLOR');
			}
		}
	}

	/**
	 * Tests that writeToolResult handles multiline output.
	 */
	public function test_writeToolResult_withMultilineOutput_displaysAllLines(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);
		$multiline_output = "Line 1\nLine 2\nLine 3";
		$result = ToolResult::success($multiline_output);

		$handler->writeToolResult('bash', $result);

		$output = $this->getStreamContents($this->output_stream);
		$this->assertStringContainsString('Line 1', $output);
		$this->assertStringContainsString('Line 2', $output);
		$this->assertStringContainsString('Line 3', $output);
	}

	/**
	 * Tests that color constants are accessible.
	 */
	public function test_colorConstants_areAccessible(): void
	{
		$this->assertSame("\033[31m", CliOutputHandler::COLOR_RED);
		$this->assertSame("\033[32m", CliOutputHandler::COLOR_GREEN);
		$this->assertSame("\033[33m", CliOutputHandler::COLOR_YELLOW);
		$this->assertSame("\033[36m", CliOutputHandler::COLOR_CYAN);
		$this->assertSame("\033[1m", CliOutputHandler::STYLE_BOLD);
		$this->assertSame("\033[2m", CliOutputHandler::STYLE_DIM);
		$this->assertSame("\033[0m", CliOutputHandler::RESET);
	}

	/**
	 * Tests that handler implements OutputHandlerInterface.
	 */
	public function test_implementsOutputHandlerInterface(): void
	{
		$handler = new CliOutputHandler($this->output_stream, $this->error_stream, false);

		$this->assertInstanceOf(\PhpCliAgent\Core\Contracts\OutputHandlerInterface::class, $handler);
	}
}
