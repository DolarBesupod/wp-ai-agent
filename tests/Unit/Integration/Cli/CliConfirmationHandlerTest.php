<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Integration\Cli;

use PhpCliAgent\Core\Contracts\ConfirmationHandlerInterface;
use PhpCliAgent\Integration\Cli\CliConfirmationHandler;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CliConfirmationHandler.
 *
 * @covers \PhpCliAgent\Integration\Cli\CliConfirmationHandler
 */
final class CliConfirmationHandlerTest extends TestCase
{
	/**
	 * @var resource
	 */
	private $output_stream;

	/**
	 * @var resource
	 */
	private $input_stream;

	/**
	 * Sets up the test fixtures.
	 */
	protected function setUp(): void
	{
		$this->output_stream = fopen('php://memory', 'r+');
		$this->input_stream = fopen('php://memory', 'r+');
	}

	/**
	 * Cleans up test resources.
	 */
	protected function tearDown(): void
	{
		if (is_resource($this->output_stream)) {
			fclose($this->output_stream);
		}
		if (is_resource($this->input_stream)) {
			fclose($this->input_stream);
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
	 * Helper to write input to the input stream.
	 *
	 * @param string $input The input to write.
	 *
	 * @return void
	 */
	private function setInput(string $input): void
	{
		rewind($this->input_stream);
		fwrite($this->input_stream, $input . "\n");
		rewind($this->input_stream);
	}

	/**
	 * Creates a handler instance for testing.
	 *
	 * @param bool $colors_enabled Whether to enable colors.
	 *
	 * @return CliConfirmationHandler
	 */
	private function createHandler(bool $colors_enabled = false): CliConfirmationHandler
	{
		return new CliConfirmationHandler(
			$this->output_stream,
			$this->input_stream,
			$colors_enabled
		);
	}

	/**
	 * Tests that handler implements ConfirmationHandlerInterface.
	 */
	public function test_implementsConfirmationHandlerInterface(): void
	{
		$handler = $this->createHandler();

		$this->assertInstanceOf(ConfirmationHandlerInterface::class, $handler);
	}

	/**
	 * Tests that confirm returns true when user types 'y'.
	 */
	public function test_confirm_withYesResponse_returnsTrue(): void
	{
		$handler = $this->createHandler();
		$this->setInput('y');

		$result = $handler->confirm('bash', ['command' => 'ls -la']);

		$this->assertTrue($result);
	}

	/**
	 * Tests that confirm returns true when user types 'yes'.
	 */
	public function test_confirm_withFullYesResponse_returnsTrue(): void
	{
		$handler = $this->createHandler();
		$this->setInput('yes');

		$result = $handler->confirm('bash', ['command' => 'ls -la']);

		$this->assertTrue($result);
	}

	/**
	 * Tests that confirm returns false when user types 'n'.
	 */
	public function test_confirm_withNoResponse_returnsFalse(): void
	{
		$handler = $this->createHandler();
		$this->setInput('n');

		$result = $handler->confirm('bash', ['command' => 'rm -rf /']);

		$this->assertFalse($result);
	}

	/**
	 * Tests that confirm returns false when user types 'no'.
	 */
	public function test_confirm_withFullNoResponse_returnsFalse(): void
	{
		$handler = $this->createHandler();
		$this->setInput('no');

		$result = $handler->confirm('bash', ['command' => 'rm -rf /']);

		$this->assertFalse($result);
	}

	/**
	 * Tests that confirm with 'a' adds tool to session bypass and returns true.
	 */
	public function test_confirm_withAlwaysResponse_addsBypassAndReturnsTrue(): void
	{
		$handler = $this->createHandler();
		$this->setInput('a');

		$result = $handler->confirm('bash', ['command' => 'ls']);

		$this->assertTrue($result);
		$this->assertContains('bash', $handler->getSessionBypasses());
	}

	/**
	 * Tests that confirm with 'always' adds tool to session bypass.
	 */
	public function test_confirm_withFullAlwaysResponse_addsBypass(): void
	{
		$handler = $this->createHandler();
		$this->setInput('always');

		$result = $handler->confirm('write_file', ['path' => 'test.txt']);

		$this->assertTrue($result);
		$this->assertContains('write_file', $handler->getSessionBypasses());
	}

	/**
	 * Tests that tool added via 'always' auto-approves on subsequent calls.
	 */
	public function test_confirm_afterAlwaysResponse_autoApproves(): void
	{
		$handler = $this->createHandler();
		$this->setInput('a');

		// First call with 'always'.
		$handler->confirm('bash', ['command' => 'ls']);

		// Subsequent call should auto-approve without prompting.
		$result = $handler->confirm('bash', ['command' => 'pwd']);

		$this->assertTrue($result);

		// Output should only contain one prompt (from first call).
		$output = $this->getStreamContents($this->output_stream);
		$prompt_count = substr_count($output, 'Tool Execution Request');
		$this->assertSame(1, $prompt_count);
	}

	/**
	 * Tests that default bypass list tools skip confirmation.
	 */
	public function test_confirm_withDefaultBypassTool_skipsConfirmation(): void
	{
		$handler = $this->createHandler();

		$result = $handler->confirm('read_file', ['path' => 'test.txt']);

		$this->assertTrue($result);

		$output = $this->getStreamContents($this->output_stream);
		$this->assertEmpty($output);
	}

	/**
	 * Tests that 'think' is in default bypass list.
	 */
	public function test_confirm_withThinkTool_skipsConfirmation(): void
	{
		$handler = $this->createHandler();

		$result = $handler->confirm('think', ['thought' => 'reasoning...']);

		$this->assertTrue($result);
	}

	/**
	 * Tests that 'glob' is in default bypass list.
	 */
	public function test_confirm_withGlobTool_skipsConfirmation(): void
	{
		$handler = $this->createHandler();

		$result = $handler->confirm('glob', ['pattern' => '*.php']);

		$this->assertTrue($result);
	}

	/**
	 * Tests that 'grep' is in default bypass list.
	 */
	public function test_confirm_withGrepTool_skipsConfirmation(): void
	{
		$handler = $this->createHandler();

		$result = $handler->confirm('grep', ['pattern' => 'function']);

		$this->assertTrue($result);
	}

	/**
	 * Tests that shouldBypass returns true for default bypass tools.
	 */
	public function test_shouldBypass_withDefaultBypassTool_returnsTrue(): void
	{
		$handler = $this->createHandler();

		$this->assertTrue($handler->shouldBypass('read_file'));
		$this->assertTrue($handler->shouldBypass('think'));
		$this->assertTrue($handler->shouldBypass('glob'));
		$this->assertTrue($handler->shouldBypass('grep'));
	}

	/**
	 * Tests that shouldBypass returns false for non-bypass tools.
	 */
	public function test_shouldBypass_withNonBypassTool_returnsFalse(): void
	{
		$handler = $this->createHandler();

		$this->assertFalse($handler->shouldBypass('bash'));
		$this->assertFalse($handler->shouldBypass('write_file'));
	}

	/**
	 * Tests that shouldBypass is case-insensitive.
	 */
	public function test_shouldBypass_isCaseInsensitive(): void
	{
		$handler = $this->createHandler();

		$this->assertTrue($handler->shouldBypass('READ_FILE'));
		$this->assertTrue($handler->shouldBypass('Read_File'));
		$this->assertTrue($handler->shouldBypass('THINK'));
	}

	/**
	 * Tests that addBypass adds a tool to session bypass list.
	 */
	public function test_addBypass_addsToolToSessionBypasses(): void
	{
		$handler = $this->createHandler();

		$handler->addBypass('bash');

		$this->assertTrue($handler->shouldBypass('bash'));
		$this->assertContains('bash', $handler->getSessionBypasses());
	}

	/**
	 * Tests that removeBypass removes a tool from session bypass list.
	 */
	public function test_removeBypass_removesToolFromSessionBypasses(): void
	{
		$handler = $this->createHandler();
		$handler->addBypass('bash');

		$handler->removeBypass('bash');

		$this->assertFalse($handler->shouldBypass('bash'));
		$this->assertNotContains('bash', $handler->getSessionBypasses());
	}

	/**
	 * Tests that removeBypass does not affect default bypasses.
	 */
	public function test_removeBypass_doesNotAffectDefaultBypasses(): void
	{
		$handler = $this->createHandler();

		$handler->removeBypass('read_file');

		// Default bypasses cannot be removed.
		$this->assertTrue($handler->shouldBypass('read_file'));
	}

	/**
	 * Tests that getBypasses returns all bypass tool names.
	 */
	public function test_getBypasses_returnsAllBypasses(): void
	{
		$handler = $this->createHandler();
		$handler->addBypass('bash');

		$bypasses = $handler->getBypasses();

		$this->assertContains('think', $bypasses);
		$this->assertContains('read_file', $bypasses);
		$this->assertContains('glob', $bypasses);
		$this->assertContains('grep', $bypasses);
		$this->assertContains('bash', $bypasses);
	}

	/**
	 * Tests that clearBypasses clears session bypasses but keeps defaults.
	 */
	public function test_clearBypasses_clearsSessionBypassesOnly(): void
	{
		$handler = $this->createHandler();
		$handler->addBypass('bash');
		$handler->addBypass('write_file');

		$handler->clearBypasses();

		$this->assertEmpty($handler->getSessionBypasses());
		$this->assertTrue($handler->shouldBypass('read_file'));
		$this->assertFalse($handler->shouldBypass('bash'));
	}

	/**
	 * Tests that setAutoConfirm enables auto-confirm mode.
	 */
	public function test_setAutoConfirm_enablesAutoConfirmMode(): void
	{
		$handler = $this->createHandler();

		$handler->setAutoConfirm(true);

		$this->assertTrue($handler->isAutoConfirm());
	}

	/**
	 * Tests that setAutoConfirm disables auto-confirm mode.
	 */
	public function test_setAutoConfirm_disablesAutoConfirmMode(): void
	{
		$handler = $this->createHandler();
		$handler->setAutoConfirm(true);

		$handler->setAutoConfirm(false);

		$this->assertFalse($handler->isAutoConfirm());
	}

	/**
	 * Tests that isAutoConfirm defaults to false.
	 */
	public function test_isAutoConfirm_defaultIsFalse(): void
	{
		$handler = $this->createHandler();

		$this->assertFalse($handler->isAutoConfirm());
	}

	/**
	 * Tests that confirm skips prompting when auto-confirm is enabled.
	 */
	public function test_confirm_withAutoConfirmEnabled_skipsPrompting(): void
	{
		$handler = $this->createHandler();
		$handler->setAutoConfirm(true);

		$result = $handler->confirm('dangerous_tool', ['delete' => 'all']);

		$this->assertTrue($result);

		$output = $this->getStreamContents($this->output_stream);
		$this->assertEmpty($output);
	}

	/**
	 * Tests that confirm displays tool name and arguments.
	 */
	public function test_confirm_displaysToolNameAndArguments(): void
	{
		$handler = $this->createHandler();
		$this->setInput('y');

		$handler->confirm('bash', ['command' => 'ls -la']);

		$output = $this->getStreamContents($this->output_stream);

		$this->assertStringContainsString('Tool Execution Request', $output);
		$this->assertStringContainsString('Tool: bash', $output);
		$this->assertStringContainsString('Arguments:', $output);
		$this->assertStringContainsString('command', $output);
		$this->assertStringContainsString('ls -la', $output);
	}

	/**
	 * Tests that confirm displays formatted box.
	 */
	public function test_confirm_displaysFormattedBox(): void
	{
		$handler = $this->createHandler();
		$this->setInput('y');

		$handler->confirm('test', []);

		$output = $this->getStreamContents($this->output_stream);

		$this->assertStringContainsString("\u{250C}", $output); // Top-left corner.
		$this->assertStringContainsString("\u{2514}", $output); // Bottom-left corner.
		$this->assertStringContainsString("\u{2502}", $output); // Vertical line.
	}

	/**
	 * Tests that confirm displays prompt options.
	 */
	public function test_confirm_displaysPromptOptions(): void
	{
		$handler = $this->createHandler();
		$this->setInput('n');

		$handler->confirm('bash', []);

		$output = $this->getStreamContents($this->output_stream);

		$this->assertStringContainsString('(y)es', $output);
		$this->assertStringContainsString('(n)o', $output);
		$this->assertStringContainsString('(a)lways', $output);
	}

	/**
	 * Tests that confirm returns false for unknown response.
	 */
	public function test_confirm_withUnknownResponse_returnsFalse(): void
	{
		$handler = $this->createHandler();
		$this->setInput('maybe');

		$result = $handler->confirm('bash', []);

		$this->assertFalse($result);
	}

	/**
	 * Tests that confirm handles empty response.
	 */
	public function test_confirm_withEmptyResponse_returnsFalse(): void
	{
		$handler = $this->createHandler();
		$this->setInput('');

		$result = $handler->confirm('bash', []);

		$this->assertFalse($result);
	}

	/**
	 * Tests that confirm handles uppercase response.
	 */
	public function test_confirm_withUppercaseResponse_handlesCorrectly(): void
	{
		$handler = $this->createHandler();
		$this->setInput('Y');

		$result = $handler->confirm('bash', []);

		$this->assertTrue($result);
	}

	/**
	 * Tests that getDefaultBypasses returns default bypass list.
	 */
	public function test_getDefaultBypasses_returnsDefaultList(): void
	{
		$handler = $this->createHandler();

		$defaults = $handler->getDefaultBypasses();

		$this->assertContains('think', $defaults);
		$this->assertContains('read_file', $defaults);
		$this->assertContains('glob', $defaults);
		$this->assertContains('grep', $defaults);
	}

	/**
	 * Tests that getSessionBypasses is initially empty.
	 */
	public function test_getSessionBypasses_isInitiallyEmpty(): void
	{
		$handler = $this->createHandler();

		$this->assertEmpty($handler->getSessionBypasses());
	}

	/**
	 * Tests that constructor accepts additional default bypasses.
	 */
	public function test_constructor_withAdditionalDefaultBypasses_mergesList(): void
	{
		$handler = new CliConfirmationHandler(
			$this->output_stream,
			$this->input_stream,
			false,
			['custom_safe_tool', 'another_safe']
		);

		$this->assertTrue($handler->shouldBypass('custom_safe_tool'));
		$this->assertTrue($handler->shouldBypass('another_safe'));
		$this->assertTrue($handler->shouldBypass('read_file'));
	}

	/**
	 * Tests that colors are applied when enabled.
	 */
	public function test_confirm_withColorsEnabled_appliesAnsiCodes(): void
	{
		$handler = new CliConfirmationHandler(
			$this->output_stream,
			$this->input_stream,
			true
		);
		$this->setInput('y');

		$handler->confirm('bash', []);

		$output = $this->getStreamContents($this->output_stream);
		$this->assertStringContainsString("\033[33m", $output);
		$this->assertStringContainsString("\033[0m", $output);
	}

	/**
	 * Tests that colors are not applied when disabled.
	 */
	public function test_confirm_withColorsDisabled_noAnsiCodes(): void
	{
		$handler = $this->createHandler();
		$this->setInput('y');

		$handler->confirm('bash', []);

		$output = $this->getStreamContents($this->output_stream);
		$this->assertStringNotContainsString("\033[33m", $output);
	}

	/**
	 * Tests isColorsEnabled getter.
	 */
	public function test_isColorsEnabled_returnsCorrectValue(): void
	{
		$handler = new CliConfirmationHandler(
			$this->output_stream,
			$this->input_stream,
			true
		);

		$this->assertTrue($handler->isColorsEnabled());
	}

	/**
	 * Tests setColorsEnabled setter.
	 */
	public function test_setColorsEnabled_setsValue(): void
	{
		$handler = $this->createHandler();

		$handler->setColorsEnabled(true);

		$this->assertTrue($handler->isColorsEnabled());
	}

	/**
	 * Tests that confirm displays success message when 'always' is selected.
	 */
	public function test_confirm_withAlwaysResponse_displaysSuccessMessage(): void
	{
		$handler = $this->createHandler();
		$this->setInput('a');

		$handler->confirm('bash', []);

		$output = $this->getStreamContents($this->output_stream);
		$this->assertStringContainsString('auto-approved', $output);
		$this->assertStringContainsString('bash', $output);
	}

	/**
	 * Tests that confirm displays skip message when 'no' is selected.
	 */
	public function test_confirm_withNoResponse_displaysSkipMessage(): void
	{
		$handler = $this->createHandler();
		$this->setInput('n');

		$handler->confirm('bash', []);

		$output = $this->getStreamContents($this->output_stream);
		$this->assertStringContainsString('skipped', $output);
	}

	/**
	 * Tests complex arguments are formatted as JSON.
	 */
	public function test_confirm_withComplexArguments_formatsAsJson(): void
	{
		$handler = $this->createHandler();
		$this->setInput('y');

		$handler->confirm('api_call', [
			'method' => 'POST',
			'url' => 'https://api.example.com',
			'headers' => ['Content-Type' => 'application/json'],
		]);

		$output = $this->getStreamContents($this->output_stream);
		$this->assertStringContainsString('method', $output);
		$this->assertStringContainsString('POST', $output);
		$this->assertStringContainsString('url', $output);
		$this->assertStringContainsString('headers', $output);
	}

	/**
	 * Tests that handler respects NO_COLOR environment variable.
	 */
	public function test_constructor_withNoColorEnv_disablesColors(): void
	{
		$original = getenv('NO_COLOR');
		putenv('NO_COLOR=1');

		try {
			$handler = new CliConfirmationHandler($this->output_stream, $this->input_stream);
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
	 */
	public function test_constructor_withForceColorEnv_enablesColors(): void
	{
		$original_no_color = getenv('NO_COLOR');
		$original_force_color = getenv('FORCE_COLOR');

		putenv('NO_COLOR');
		putenv('FORCE_COLOR=1');

		try {
			$handler = new CliConfirmationHandler($this->output_stream, $this->input_stream);
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
}
