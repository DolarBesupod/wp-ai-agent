<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Core\Exceptions;

use PHPUnit\Framework\TestCase;
use Automattic\WpAiAgent\Core\Exceptions\AgentException;

/**
 * Tests for AgentException.
 *
 * @covers \Automattic\WpAiAgent\Core\Exceptions\AgentException
 */
final class AgentExceptionTest extends TestCase
{
	public function test_constructor_setsMessageAndCode(): void
	{
		$exception = new AgentException('Test message', 42);

		$this->assertSame('Test message', $exception->getMessage());
		$this->assertSame(42, $exception->getCode());
	}

	public function test_constructor_setsPreviousException(): void
	{
		$previous = new \RuntimeException('Previous error');
		$exception = new AgentException('Test', 0, $previous);

		$this->assertSame($previous, $exception->getPrevious());
	}

	public function test_constructor_setsContext(): void
	{
		$context = ['tool' => 'bash', 'command' => 'ls -la'];
		$exception = new AgentException('Test', 0, null, $context);

		$this->assertSame($context, $exception->getContext());
	}

	public function test_getContext_returnsEmptyArrayByDefault(): void
	{
		$exception = new AgentException('Test');

		$this->assertSame([], $exception->getContext());
	}

	public function test_getContextValue_returnsValueForExistingKey(): void
	{
		$exception = new AgentException('Test', 0, null, ['key' => 'value']);

		$this->assertSame('value', $exception->getContextValue('key'));
	}

	public function test_getContextValue_returnsDefaultForMissingKey(): void
	{
		$exception = new AgentException('Test', 0, null, ['key' => 'value']);

		$this->assertSame('default', $exception->getContextValue('missing', 'default'));
	}

	public function test_getContextValue_returnsNullByDefaultForMissingKey(): void
	{
		$exception = new AgentException('Test');

		$this->assertNull($exception->getContextValue('missing'));
	}

	public function test_withContext_mergesAdditionalContext(): void
	{
		$original = new AgentException('Test', 0, null, ['a' => 1, 'b' => 2]);

		$new = $original->withContext(['b' => 3, 'c' => 4]);

		$this->assertSame(['a' => 1, 'b' => 3, 'c' => 4], $new->getContext());
		$this->assertSame(['a' => 1, 'b' => 2], $original->getContext());
	}

	public function test_withContext_preservesMessage(): void
	{
		$original = new AgentException('Original message');

		$new = $original->withContext(['key' => 'value']);

		$this->assertSame('Original message', $new->getMessage());
	}

	public function test_withContext_preservesPreviousException(): void
	{
		$previous = new \RuntimeException('Previous');
		$original = new AgentException('Test', 0, $previous);

		$new = $original->withContext(['key' => 'value']);

		$this->assertSame($previous, $new->getPrevious());
	}

	public function test_exception_isInstanceOfRuntimeException(): void
	{
		$exception = new AgentException('Test');

		$this->assertInstanceOf(\RuntimeException::class, $exception);
	}
}
