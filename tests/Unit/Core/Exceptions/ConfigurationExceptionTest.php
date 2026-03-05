<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Core\Exceptions;

use PHPUnit\Framework\TestCase;
use Automattic\WpAiAgent\Core\Exceptions\AgentException;
use Automattic\WpAiAgent\Core\Exceptions\ConfigurationException;

/**
 * Tests for ConfigurationException.
 *
 * @covers \Automattic\WpAiAgent\Core\Exceptions\ConfigurationException
 */
final class ConfigurationExceptionTest extends TestCase
{
	public function test_missingKey_createsException(): void
	{
		$exception = ConfigurationException::missingKey('api_key');

		$this->assertStringContainsString('api_key', $exception->getMessage());
		$this->assertStringContainsString('missing', $exception->getMessage());
	}

	public function test_invalidValue_createsException(): void
	{
		$exception = ConfigurationException::invalidValue('timeout', 'must be positive');

		$this->assertStringContainsString('timeout', $exception->getMessage());
		$this->assertStringContainsString('must be positive', $exception->getMessage());
	}

	public function test_fileLoadFailed_createsException(): void
	{
		$exception = ConfigurationException::fileLoadFailed('/path/to/config.json', 'File not found');

		$this->assertStringContainsString('/path/to/config.json', $exception->getMessage());
		$this->assertStringContainsString('File not found', $exception->getMessage());
	}

	public function test_fileLoadFailed_acceptsPreviousException(): void
	{
		$previous = new \RuntimeException('IO error');
		$exception = ConfigurationException::fileLoadFailed('/path', 'error', $previous);

		$this->assertSame($previous, $exception->getPrevious());
	}

	public function test_exception_extendsAgentException(): void
	{
		$exception = ConfigurationException::missingKey('test');

		$this->assertInstanceOf(AgentException::class, $exception);
	}

	public function test_exception_canBeCaughtAsAgentException(): void
	{
		$caught = false;

		try {
			throw ConfigurationException::invalidValue('key', 'reason');
		} catch (AgentException $e) {
			$caught = true;
		}

		$this->assertTrue($caught);
	}
}
