<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Core\Tool;

use PHPUnit\Framework\TestCase;
use Automattic\Automattic\WpAiAgent\Core\Tool\AbstractTool;
use Automattic\Automattic\WpAiAgent\Core\ValueObjects\ToolResult;

/**
 * Tests for AbstractTool.
 *
 * @covers \Automattic\WpAiAgent\Core\Tool\AbstractTool
 */
final class AbstractToolTest extends TestCase
{
	public function test_requiresConfirmation_returnsTrueByDefault(): void
	{
		$tool = new ConcreteTestTool();

		$this->assertTrue($tool->requiresConfirmation());
	}

	public function test_success_createsSuccessfulResult(): void
	{
		$tool = new ConcreteTestTool();

		$result = $tool->publicSuccess('Output text', ['key' => 'value']);

		$this->assertTrue($result->isSuccess());
		$this->assertSame('Output text', $result->getOutput());
		$this->assertSame(['key' => 'value'], $result->getData());
	}

	public function test_failure_createsFailedResult(): void
	{
		$tool = new ConcreteTestTool();

		$result = $tool->publicFailure('Error message', 'Some output');

		$this->assertFalse($result->isSuccess());
		$this->assertSame('Error message', $result->getError());
		$this->assertSame('Some output', $result->getOutput());
	}

	public function test_validateRequiredArguments_returnsEmptyWhenAllPresent(): void
	{
		$tool = new ConcreteTestTool();

		$missing = $tool->publicValidateRequiredArguments(
			['name' => 'value', 'other' => 'data'],
			['name', 'other']
		);

		$this->assertSame([], $missing);
	}

	public function test_validateRequiredArguments_returnsMissingNames(): void
	{
		$tool = new ConcreteTestTool();

		$missing = $tool->publicValidateRequiredArguments(
			['name' => 'value'],
			['name', 'required_field', 'another']
		);

		$this->assertSame(['required_field', 'another'], $missing);
	}

	public function test_getArgument_returnsValueWhenPresent(): void
	{
		$tool = new ConcreteTestTool();

		$value = $tool->publicGetArgument(['key' => 'value'], 'key', 'default');

		$this->assertSame('value', $value);
	}

	public function test_getArgument_returnsDefaultWhenMissing(): void
	{
		$tool = new ConcreteTestTool();

		$value = $tool->publicGetArgument(['other' => 'value'], 'key', 'default');

		$this->assertSame('default', $value);
	}

	public function test_getStringArgument_returnsStringValue(): void
	{
		$tool = new ConcreteTestTool();

		$value = $tool->publicGetStringArgument(['text' => 'hello'], 'text', '');

		$this->assertSame('hello', $value);
	}

	public function test_getStringArgument_returnsDefaultForNonString(): void
	{
		$tool = new ConcreteTestTool();

		$value = $tool->publicGetStringArgument(['num' => 123], 'num', 'default');

		$this->assertSame('default', $value);
	}

	public function test_getIntArgument_returnsIntValue(): void
	{
		$tool = new ConcreteTestTool();

		$value = $tool->publicGetIntArgument(['count' => 42], 'count', 0);

		$this->assertSame(42, $value);
	}

	public function test_getIntArgument_convertsNumericString(): void
	{
		$tool = new ConcreteTestTool();

		$value = $tool->publicGetIntArgument(['count' => '123'], 'count', 0);

		$this->assertSame(123, $value);
	}

	public function test_getIntArgument_returnsDefaultForNonNumeric(): void
	{
		$tool = new ConcreteTestTool();

		$value = $tool->publicGetIntArgument(['text' => 'not a number'], 'text', 99);

		$this->assertSame(99, $value);
	}

	public function test_getBoolArgument_returnsBoolValue(): void
	{
		$tool = new ConcreteTestTool();

		$this->assertTrue($tool->publicGetBoolArgument(['flag' => true], 'flag', false));
		$this->assertFalse($tool->publicGetBoolArgument(['flag' => false], 'flag', true));
	}

	public function test_getBoolArgument_convertsTruthyStrings(): void
	{
		$tool = new ConcreteTestTool();

		$this->assertTrue($tool->publicGetBoolArgument(['flag' => 'true'], 'flag', false));
		$this->assertTrue($tool->publicGetBoolArgument(['flag' => '1'], 'flag', false));
		$this->assertTrue($tool->publicGetBoolArgument(['flag' => 'yes'], 'flag', false));
		$this->assertTrue($tool->publicGetBoolArgument(['flag' => 'on'], 'flag', false));
	}

	public function test_getBoolArgument_convertsFalsyStrings(): void
	{
		$tool = new ConcreteTestTool();

		$this->assertFalse($tool->publicGetBoolArgument(['flag' => 'false'], 'flag', true));
		$this->assertFalse($tool->publicGetBoolArgument(['flag' => '0'], 'flag', true));
		$this->assertFalse($tool->publicGetBoolArgument(['flag' => 'no'], 'flag', true));
		$this->assertFalse($tool->publicGetBoolArgument(['flag' => 'off'], 'flag', true));
	}

	public function test_getArrayArgument_returnsArrayValue(): void
	{
		$tool = new ConcreteTestTool();

		$value = $tool->publicGetArrayArgument(['items' => ['a', 'b', 'c']], 'items', []);

		$this->assertSame(['a', 'b', 'c'], $value);
	}

	public function test_getArrayArgument_returnsDefaultForNonArray(): void
	{
		$tool = new ConcreteTestTool();

		$value = $tool->publicGetArrayArgument(['items' => 'not array'], 'items', ['default']);

		$this->assertSame(['default'], $value);
	}
}

/**
 * Concrete implementation for testing AbstractTool.
 */
class ConcreteTestTool extends AbstractTool
{
	public function getName(): string
	{
		return 'test_tool';
	}

	public function getDescription(): string
	{
		return 'A test tool for unit testing';
	}

	public function getParametersSchema(): ?array
	{
		return null;
	}

	public function execute(array $arguments): ToolResult
	{
		return ToolResult::success('executed');
	}

	/**
	 * Exposes protected success method for testing.
	 *
	 * @param string               $output The output text.
	 * @param array<string, mixed> $data   Optional data.
	 *
	 * @return ToolResult
	 */
	public function publicSuccess(string $output, array $data = []): ToolResult
	{
		return $this->success($output, $data);
	}

	/**
	 * Exposes protected failure method for testing.
	 *
	 * @param string $error  The error message.
	 * @param string $output Optional output.
	 *
	 * @return ToolResult
	 */
	public function publicFailure(string $error, string $output = ''): ToolResult
	{
		return $this->failure($error, $output);
	}

	/**
	 * Exposes protected validateRequiredArguments method for testing.
	 *
	 * @param array<string, mixed> $arguments The arguments.
	 * @param array<int, string>   $required  Required names.
	 *
	 * @return array<int, string>
	 */
	public function publicValidateRequiredArguments(array $arguments, array $required): array
	{
		return $this->validateRequiredArguments($arguments, $required);
	}

	/**
	 * Exposes protected getArgument method for testing.
	 *
	 * @param array<string, mixed> $arguments The arguments.
	 * @param string               $name      Argument name.
	 * @param mixed                $default   Default value.
	 *
	 * @return mixed
	 */
	public function publicGetArgument(array $arguments, string $name, mixed $default = null): mixed
	{
		return $this->getArgument($arguments, $name, $default);
	}

	/**
	 * Exposes protected getStringArgument method for testing.
	 *
	 * @param array<string, mixed> $arguments The arguments.
	 * @param string               $name      Argument name.
	 * @param string               $default   Default value.
	 *
	 * @return string
	 */
	public function publicGetStringArgument(array $arguments, string $name, string $default = ''): string
	{
		return $this->getStringArgument($arguments, $name, $default);
	}

	/**
	 * Exposes protected getIntArgument method for testing.
	 *
	 * @param array<string, mixed> $arguments The arguments.
	 * @param string               $name      Argument name.
	 * @param int                  $default   Default value.
	 *
	 * @return int
	 */
	public function publicGetIntArgument(array $arguments, string $name, int $default = 0): int
	{
		return $this->getIntArgument($arguments, $name, $default);
	}

	/**
	 * Exposes protected getBoolArgument method for testing.
	 *
	 * @param array<string, mixed> $arguments The arguments.
	 * @param string               $name      Argument name.
	 * @param bool                 $default   Default value.
	 *
	 * @return bool
	 */
	public function publicGetBoolArgument(array $arguments, string $name, bool $default = false): bool
	{
		return $this->getBoolArgument($arguments, $name, $default);
	}

	/**
	 * Exposes protected getArrayArgument method for testing.
	 *
	 * @param array<string, mixed> $arguments The arguments.
	 * @param string               $name      Argument name.
	 * @param array<mixed>         $default   Default value.
	 *
	 * @return array<mixed>
	 */
	public function publicGetArrayArgument(array $arguments, string $name, array $default = []): array
	{
		return $this->getArrayArgument($arguments, $name, $default);
	}
}
