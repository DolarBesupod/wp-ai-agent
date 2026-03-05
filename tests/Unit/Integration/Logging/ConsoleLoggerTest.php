<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Integration\Logging;

use Automattic\WpAiAgent\Integration\Logging\ConsoleLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

/**
 * Tests for ConsoleLogger.
 *
 * @since n.e.x.t
 */
#[CoversClass(ConsoleLogger::class)]
final class ConsoleLoggerTest extends TestCase
{
	/**
	 * @var resource
	 */
	private $output_stream;

	/**
	 * @var resource
	 */
	private $error_stream;

	protected function setUp(): void
	{
		parent::setUp();

		$output = fopen('php://memory', 'rw');
		$error = fopen('php://memory', 'rw');

		if ($output === false || $error === false) {
			$this->fail('Failed to create memory streams');
		}

		$this->output_stream = $output;
		$this->error_stream = $error;
	}

	protected function tearDown(): void
	{
		if (is_resource($this->output_stream)) {
			fclose($this->output_stream);
		}
		if (is_resource($this->error_stream)) {
			fclose($this->error_stream);
		}

		parent::tearDown();
	}

	/**
	 * Gets the content written to a stream.
	 *
	 * @param resource $stream The stream.
	 *
	 * @return string
	 */
	private function getStreamContent($stream): string
	{
		rewind($stream);
		$content = stream_get_contents($stream);
		return $content !== false ? $content : '';
	}

	/**
	 * Creates a logger with test streams.
	 *
	 * @param string $min_level     Minimum log level.
	 * @param bool   $use_colors    Whether to use colors.
	 * @param bool   $show_times    Whether to show timestamps.
	 * @param bool   $use_stderr    Whether to use stderr for errors.
	 *
	 * @return ConsoleLogger
	 */
	private function createLogger(
		string $min_level = LogLevel::DEBUG,
		bool $use_colors = false,
		bool $show_times = false,
		bool $use_stderr = true
	): ConsoleLogger {
		return new ConsoleLogger(
			$min_level,
			$use_colors,
			$show_times,
			$use_stderr,
			'H:i:s',
			$this->output_stream,
			$this->error_stream
		);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function logLevelProvider(): array
	{
		return [
			'emergency' => [LogLevel::EMERGENCY],
			'alert' => [LogLevel::ALERT],
			'critical' => [LogLevel::CRITICAL],
			'error' => [LogLevel::ERROR],
			'warning' => [LogLevel::WARNING],
			'notice' => [LogLevel::NOTICE],
			'info' => [LogLevel::INFO],
			'debug' => [LogLevel::DEBUG],
		];
	}

	#[DataProvider('logLevelProvider')]
	public function test_log_writesMessageWithLevel(string $level): void
	{
		$logger = $this->createLogger();
		$message = 'Test message for ' . $level;

		$logger->log($level, $message);

		$output = $this->getStreamContent($this->output_stream);
		$error = $this->getStreamContent($this->error_stream);
		$combined = $output . $error;

		$this->assertStringContainsString(strtoupper($level), $combined);
		$this->assertStringContainsString($message, $combined);
	}

	public function test_log_writesErrorsToStderr(): void
	{
		$logger = $this->createLogger(LogLevel::DEBUG, false, false, true);

		$logger->error('Error message');
		$logger->info('Info message');

		$output = $this->getStreamContent($this->output_stream);
		$error = $this->getStreamContent($this->error_stream);

		$this->assertStringContainsString('Info message', $output);
		$this->assertStringContainsString('Error message', $error);
	}

	public function test_log_writesAllToStdout_whenStderrDisabled(): void
	{
		$logger = $this->createLogger(LogLevel::DEBUG, false, false, false);

		$logger->error('Error message');
		$logger->info('Info message');

		$output = $this->getStreamContent($this->output_stream);
		$error = $this->getStreamContent($this->error_stream);

		$this->assertStringContainsString('Error message', $output);
		$this->assertStringContainsString('Info message', $output);
		$this->assertEmpty($error);
	}

	public function test_log_respectsMinLevel(): void
	{
		$logger = $this->createLogger(LogLevel::WARNING);

		$logger->debug('Debug message');
		$logger->info('Info message');
		$logger->warning('Warning message');

		$output = $this->getStreamContent($this->output_stream);

		$this->assertStringNotContainsString('Debug message', $output);
		$this->assertStringNotContainsString('Info message', $output);
		$this->assertStringContainsString('Warning message', $output);
	}

	public function test_setMinLevel_changesMinimum(): void
	{
		$logger = $this->createLogger(LogLevel::ERROR);

		$logger->warning('Warning before');
		$logger->setMinLevel(LogLevel::DEBUG);
		$logger->warning('Warning after');

		$output = $this->getStreamContent($this->output_stream);

		$this->assertStringNotContainsString('Warning before', $output);
		$this->assertStringContainsString('Warning after', $output);
	}

	public function test_log_interpolatesContext(): void
	{
		$logger = $this->createLogger();

		$logger->info('User {name} from {city}', [
			'name' => 'Alice',
			'city' => 'Boston',
		]);

		$output = $this->getStreamContent($this->output_stream);

		$this->assertStringContainsString('User Alice from Boston', $output);
	}

	public function test_log_showsTimestamps_whenEnabled(): void
	{
		$logger = $this->createLogger(LogLevel::DEBUG, false, true);

		$logger->info('Timestamped message');

		$output = $this->getStreamContent($this->output_stream);

		// Should contain time-like pattern.
		$this->assertMatchesRegularExpression('/\d{2}:\d{2}:\d{2}/', $output);
	}

	public function test_log_hidesTimestamps_whenDisabled(): void
	{
		$logger = $this->createLogger(LogLevel::DEBUG, false, false);

		$logger->info('No timestamp message');

		$output = $this->getStreamContent($this->output_stream);

		// Should not contain time pattern at the start.
		$this->assertDoesNotMatchRegularExpression('/^\d{2}:\d{2}:\d{2}/', $output);
	}

	public function test_setColorsEnabled_changesColorState(): void
	{
		$logger = $this->createLogger(LogLevel::DEBUG, false);

		$this->assertFalse($logger->isColorsEnabled());

		$logger->setColorsEnabled(true);

		$this->assertTrue($logger->isColorsEnabled());

		$logger->setColorsEnabled(false);

		$this->assertFalse($logger->isColorsEnabled());
	}

	public function test_setTimestampsEnabled_changesTimestampState(): void
	{
		$logger = $this->createLogger(LogLevel::DEBUG, false, false);

		$logger->info('Before');
		$logger->setTimestampsEnabled(true);
		$logger->info('After');

		$output = $this->getStreamContent($this->output_stream);

		// Only the second message should have a timestamp.
		$lines = explode("\n", trim($output));
		$this->assertCount(2, $lines);

		$this->assertDoesNotMatchRegularExpression('/^\d{2}:\d{2}:\d{2}/', $lines[0]);
		$this->assertMatchesRegularExpression('/\d{2}:\d{2}:\d{2}/', $lines[1]);
	}

	public function test_log_handlesNullValue(): void
	{
		$logger = $this->createLogger();

		$logger->info('Value is {val}', ['val' => null]);

		$output = $this->getStreamContent($this->output_stream);

		$this->assertStringContainsString('Value is null', $output);
	}

	public function test_log_handlesBooleanValue(): void
	{
		$logger = $this->createLogger();

		$logger->info('Active: {active}', ['active' => true]);

		$output = $this->getStreamContent($this->output_stream);

		$this->assertStringContainsString('Active: true', $output);
	}

	public function test_log_handlesArrayValue(): void
	{
		$logger = $this->createLogger();

		$logger->info('Data: {data}', ['data' => ['a', 'b', 'c']]);

		$output = $this->getStreamContent($this->output_stream);

		$this->assertStringContainsString('["a","b","c"]', $output);
	}

	public function test_log_handlesExceptionValue(): void
	{
		$logger = $this->createLogger();
		$exception = new \RuntimeException('Test error');

		$logger->error('Failed: {err}', ['err' => $exception]);

		$error = $this->getStreamContent($this->error_stream);

		$this->assertStringContainsString('RuntimeException', $error);
		$this->assertStringContainsString('Test error', $error);
	}

	public function test_log_handlesObjectWithToString(): void
	{
		$logger = $this->createLogger();
		$object = new class {
			public function __toString(): string
			{
				return 'CustomObject';
			}
		};

		$logger->info('Object: {obj}', ['obj' => $object]);

		$output = $this->getStreamContent($this->output_stream);

		$this->assertStringContainsString('Object: CustomObject', $output);
	}

	public function test_log_handlesObjectWithoutToString(): void
	{
		$logger = $this->createLogger();
		$object = new \stdClass();

		$logger->info('Object: {obj}', ['obj' => $object]);

		$output = $this->getStreamContent($this->output_stream);

		$this->assertStringContainsString('[object stdClass]', $output);
	}

	public function test_convenienceMethods_work(): void
	{
		$logger = $this->createLogger();

		$logger->emergency('Emergency');
		$logger->alert('Alert');
		$logger->critical('Critical');
		$logger->error('Error');
		$logger->warning('Warning');
		$logger->notice('Notice');
		$logger->info('Info');
		$logger->debug('Debug');

		$output = $this->getStreamContent($this->output_stream);
		$error = $this->getStreamContent($this->error_stream);
		$combined = $output . $error;

		$this->assertStringContainsString('[EMERGENCY]', $combined);
		$this->assertStringContainsString('[ALERT]', $combined);
		$this->assertStringContainsString('[CRITICAL]', $combined);
		$this->assertStringContainsString('[ERROR]', $combined);
		$this->assertStringContainsString('[WARNING]', $combined);
		$this->assertStringContainsString('[NOTICE]', $combined);
		$this->assertStringContainsString('[INFO]', $combined);
		$this->assertStringContainsString('[DEBUG]', $combined);
	}

	public function test_log_withColors_includesAnsiCodes(): void
	{
		// Create logger with colors disabled, then enable them manually.
		$logger = $this->createLogger(LogLevel::DEBUG, false);
		$logger->setColorsEnabled(true);

		$logger->info('Colored message');

		$output = $this->getStreamContent($this->output_stream);

		// Should contain ANSI escape codes.
		$this->assertStringContainsString("\033[", $output);
		// Should contain reset code.
		$this->assertStringContainsString("\033[0m", $output);
	}

	public function test_log_withoutColors_excludesAnsiCodes(): void
	{
		$logger = $this->createLogger(LogLevel::DEBUG, false);

		$logger->info('Plain message');

		$output = $this->getStreamContent($this->output_stream);

		// Should not contain ANSI escape codes.
		$this->assertStringNotContainsString("\033[", $output);
	}

	public function test_log_errorLevels_goToStderr(): void
	{
		$logger = $this->createLogger(LogLevel::DEBUG, false, false, true);

		$logger->emergency('Emergency');
		$logger->alert('Alert');
		$logger->critical('Critical');
		$logger->error('Error');

		$output = $this->getStreamContent($this->output_stream);
		$error = $this->getStreamContent($this->error_stream);

		$this->assertEmpty($output);
		$this->assertStringContainsString('Emergency', $error);
		$this->assertStringContainsString('Alert', $error);
		$this->assertStringContainsString('Critical', $error);
		$this->assertStringContainsString('Error', $error);
	}

	public function test_log_nonErrorLevels_goToStdout(): void
	{
		$logger = $this->createLogger(LogLevel::DEBUG, false, false, true);

		$logger->warning('Warning');
		$logger->notice('Notice');
		$logger->info('Info');
		$logger->debug('Debug');

		$output = $this->getStreamContent($this->output_stream);
		$error = $this->getStreamContent($this->error_stream);

		$this->assertEmpty($error);
		$this->assertStringContainsString('Warning', $output);
		$this->assertStringContainsString('Notice', $output);
		$this->assertStringContainsString('Info', $output);
		$this->assertStringContainsString('Debug', $output);
	}
}
