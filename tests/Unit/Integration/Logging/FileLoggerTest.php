<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Integration\Logging;

use Automattic\Automattic\WpAiAgent\Integration\Logging\FileLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

/**
 * Tests for FileLogger.
 *
 * @since n.e.x.t
 */
#[CoversClass(FileLogger::class)]
final class FileLoggerTest extends TestCase
{
	private string $test_dir;

	private string $log_file;

	protected function setUp(): void
	{
		parent::setUp();

		$this->test_dir = sys_get_temp_dir() . '/php-cli-agent-test-' . uniqid();
		$this->log_file = $this->test_dir . '/test.log';

		if (!is_dir($this->test_dir)) {
			mkdir($this->test_dir, 0755, true);
		}
	}

	protected function tearDown(): void
	{
		if (is_dir($this->test_dir)) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($this->test_dir, \RecursiveDirectoryIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::CHILD_FIRST
			);
			foreach ($iterator as $file) {
				$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
			}
			rmdir($this->test_dir);
		}

		parent::tearDown();
	}

	public function test_constructor_createsDirectory(): void
	{
		$nested_log = $this->test_dir . '/nested/deep/test.log';

		$logger = new FileLogger($nested_log);

		$this->assertDirectoryExists(dirname($nested_log));
	}

	public function test_getFilePath_returnsConfiguredPath(): void
	{
		$logger = new FileLogger($this->log_file);

		$this->assertEquals($this->log_file, $logger->getFilePath());
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
		$logger = new FileLogger($this->log_file);
		$message = 'Test message for ' . $level;

		$logger->log($level, $message);

		$this->assertFileExists($this->log_file);
		$content = file_get_contents($this->log_file);
		$this->assertIsString($content);
		$this->assertStringContainsString(strtoupper($level), $content);
		$this->assertStringContainsString($message, $content);
	}

	public function test_log_respectsMinLevel(): void
	{
		$logger = new FileLogger($this->log_file, LogLevel::WARNING);

		$logger->debug('Debug message');
		$logger->info('Info message');
		$logger->warning('Warning message');

		$content = file_get_contents($this->log_file);
		$this->assertIsString($content);
		$this->assertStringNotContainsString('Debug message', $content);
		$this->assertStringNotContainsString('Info message', $content);
		$this->assertStringContainsString('Warning message', $content);
	}

	public function test_setMinLevel_changesMinimum(): void
	{
		$logger = new FileLogger($this->log_file, LogLevel::ERROR);

		$logger->warning('Warning before');
		$logger->setMinLevel(LogLevel::DEBUG);
		$logger->warning('Warning after');

		$content = file_get_contents($this->log_file);
		$this->assertIsString($content);
		$this->assertStringNotContainsString('Warning before', $content);
		$this->assertStringContainsString('Warning after', $content);
	}

	public function test_log_interpolatesContext(): void
	{
		$logger = new FileLogger($this->log_file);

		$logger->info('User {name} logged in from {ip}', [
			'name' => 'John',
			'ip' => '127.0.0.1',
		]);

		$content = file_get_contents($this->log_file);
		$this->assertIsString($content);
		$this->assertStringContainsString('User John logged in from 127.0.0.1', $content);
	}

	public function test_log_addsContextAsJson(): void
	{
		$logger = new FileLogger($this->log_file);

		$logger->info('Test message', ['key' => 'value', 'number' => 42]);

		$content = file_get_contents($this->log_file);
		$this->assertIsString($content);
		$this->assertStringContainsString('"key":"value"', $content);
		$this->assertStringContainsString('"number":42', $content);
	}

	public function test_log_redactsSensitiveData(): void
	{
		$logger = new FileLogger($this->log_file);

		$logger->info('Auth attempt', [
			'user' => 'john',
			'password' => 'secret123',
			'api_key' => 'abc123',
			'token' => 'xyz789',
		]);

		$content = file_get_contents($this->log_file);
		$this->assertIsString($content);
		$this->assertStringContainsString('"user":"john"', $content);
		$this->assertStringContainsString('[REDACTED]', $content);
		$this->assertStringNotContainsString('secret123', $content);
		$this->assertStringNotContainsString('abc123', $content);
		$this->assertStringNotContainsString('xyz789', $content);
	}

	public function test_addRedactedKey_addsCustomKey(): void
	{
		$logger = new FileLogger($this->log_file);
		$logger->addRedactedKey('custom_secret');

		$logger->info('Custom data', ['custom_secret' => 'hidden', 'normal' => 'visible']);

		$content = file_get_contents($this->log_file);
		$this->assertIsString($content);
		$this->assertStringNotContainsString('hidden', $content);
		$this->assertStringContainsString('visible', $content);
	}

	public function test_log_handlesExceptionContext(): void
	{
		$logger = new FileLogger($this->log_file);
		$exception = new \RuntimeException('Test error', 123);

		$logger->error('Exception occurred', ['exception' => $exception]);

		$content = file_get_contents($this->log_file);
		$this->assertIsString($content);
		$this->assertStringContainsString('RuntimeException', $content);
		$this->assertStringContainsString('Test error', $content);
	}

	public function test_log_handlesNestedArrayContext(): void
	{
		$logger = new FileLogger($this->log_file);

		$logger->info('Nested data', [
			'outer' => [
				'inner' => 'value',
				'password' => 'secret',
			],
		]);

		$content = file_get_contents($this->log_file);
		$this->assertIsString($content);
		$this->assertStringContainsString('"inner":"value"', $content);
		$this->assertStringNotContainsString('secret', $content);
	}

	public function test_log_includesTimestamp(): void
	{
		$logger = new FileLogger($this->log_file);

		$logger->info('Timestamped message');

		$content = file_get_contents($this->log_file);
		$this->assertIsString($content);
		// Timestamp should be in brackets at the start.
		$this->assertMatchesRegularExpression('/^\[\d{4}-\d{2}-\d{2}/', $content);
	}

	public function test_log_rotatesFileWhenExceedsMaxSize(): void
	{
		// Create a logger with a very small max file size.
		$logger = new FileLogger(
			$this->log_file,
			LogLevel::DEBUG,
			100 // 100 bytes max
		);

		// Write enough to exceed the limit.
		for ($i = 0; $i < 5; $i++) {
			$logger->info('This is a long message to fill up the log file quickly: ' . $i);
		}

		// Check that rotation occurred.
		$this->assertFileExists($this->log_file . '.1');
	}

	public function test_log_handlesNullValue(): void
	{
		$logger = new FileLogger($this->log_file);

		$logger->info('Message with null: {value}', ['value' => null]);

		$content = file_get_contents($this->log_file);
		$this->assertIsString($content);
		$this->assertStringContainsString('Message with null: null', $content);
	}

	public function test_log_handlesBooleanValue(): void
	{
		$logger = new FileLogger($this->log_file);

		$logger->info('Bool: {yes} and {no}', ['yes' => true, 'no' => false]);

		$content = file_get_contents($this->log_file);
		$this->assertIsString($content);
		$this->assertStringContainsString('Bool: true and false', $content);
	}

	public function test_log_handlesObjectWithToString(): void
	{
		$logger = new FileLogger($this->log_file);
		$object = new class {
			public function __toString(): string
			{
				return 'StringableObject';
			}
		};

		$logger->info('Object: {obj}', ['obj' => $object]);

		$content = file_get_contents($this->log_file);
		$this->assertIsString($content);
		$this->assertStringContainsString('Object: StringableObject', $content);
	}

	public function test_convenienceMethods_work(): void
	{
		$logger = new FileLogger($this->log_file);

		$logger->emergency('Emergency message');
		$logger->alert('Alert message');
		$logger->critical('Critical message');
		$logger->error('Error message');
		$logger->warning('Warning message');
		$logger->notice('Notice message');
		$logger->info('Info message');
		$logger->debug('Debug message');

		$content = file_get_contents($this->log_file);
		$this->assertIsString($content);

		$this->assertStringContainsString('EMERGENCY', $content);
		$this->assertStringContainsString('ALERT', $content);
		$this->assertStringContainsString('CRITICAL', $content);
		$this->assertStringContainsString('ERROR', $content);
		$this->assertStringContainsString('WARNING', $content);
		$this->assertStringContainsString('NOTICE', $content);
		$this->assertStringContainsString('INFO', $content);
		$this->assertStringContainsString('DEBUG', $content);
	}
}
