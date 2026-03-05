<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Integration\Tool\BuiltIn;

use PHPUnit\Framework\TestCase;
use Automattic\Automattic\WpAiAgent\Integration\Tool\BuiltIn\BashTool;

/**
 * Tests for BashTool.
 *
 * @covers \Automattic\WpAiAgent\Integration\Tool\BuiltIn\BashTool
 */
final class BashToolTest extends TestCase
{
	private BashTool $tool;

	protected function setUp(): void
	{
		$this->tool = new BashTool();
	}

	public function test_getName_returnsBash(): void
	{
		$this->assertSame('bash', $this->tool->getName());
	}

	public function test_getDescription_returnsNonEmptyString(): void
	{
		$description = $this->tool->getDescription();

		$this->assertNotEmpty($description);
		$this->assertIsString($description);
	}

	public function test_getParametersSchema_returnsValidSchema(): void
	{
		$schema = $this->tool->getParametersSchema();

		$this->assertIsArray($schema);
		$this->assertSame('object', $schema['type']);
		$this->assertArrayHasKey('properties', $schema);
		$this->assertArrayHasKey('command', $schema['properties']);
		$this->assertArrayHasKey('timeout', $schema['properties']);
		$this->assertArrayHasKey('cwd', $schema['properties']);
		$this->assertSame(['command'], $schema['required']);
	}

	public function test_requiresConfirmation_returnsTrue(): void
	{
		$this->assertTrue($this->tool->requiresConfirmation());
	}

	public function test_execute_withEchoCommand_returnsSuccessWithOutput(): void
	{
		$result = $this->tool->execute(['command' => 'echo hello']);

		$this->assertTrue($result->isSuccess());
		$this->assertSame('hello', $result->getOutput());
		$this->assertSame(0, $result->getData()['exit_code']);
		$this->assertSame("hello\n", $result->getData()['stdout']);
	}

	public function test_execute_withMissingCommand_returnsFailure(): void
	{
		$result = $this->tool->execute([]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('Missing required argument', $result->getError());
	}

	public function test_execute_withEmptyCommand_returnsFailure(): void
	{
		$result = $this->tool->execute(['command' => '']);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('cannot be empty', $result->getError());
	}

	public function test_execute_withNonExistentDirectory_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'command' => 'echo test',
			'cwd' => '/nonexistent/directory/path',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('does not exist', $result->getError());
	}

	public function test_execute_withFailingCommand_returnsFailureWithExitCode(): void
	{
		$result = $this->tool->execute(['command' => 'ls /nonexistent_path_12345']);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('exited with code', $result->getError());
	}

	public function test_execute_withWorkingDirectory_executesInCorrectDirectory(): void
	{
		$result = $this->tool->execute([
			'command' => 'pwd',
			'cwd' => '/tmp',
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('/tmp', $result->getOutput());
	}

	public function test_execute_capturesStderr(): void
	{
		$result = $this->tool->execute(['command' => 'ls /nonexistent_path_12345 2>&1']);

		$this->assertFalse($result->isSuccess());
		$this->assertNotEmpty($result->getOutput());
	}

	public function test_execute_withTimeout_timesOutLongRunningCommand(): void
	{
		$start_time = time();

		$result = $this->tool->execute([
			'command' => 'sleep 10',
			'timeout' => 1,
		]);

		$elapsed = time() - $start_time;

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('timed out', $result->getError());
		$this->assertLessThan(5, $elapsed, 'Command should timeout quickly');
	}

	public function test_execute_withNegativeTimeout_usesDefault(): void
	{
		$result = $this->tool->execute([
			'command' => 'echo fast',
			'timeout' => -1,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('fast', $result->getOutput());
	}

	public function test_execute_withZeroTimeout_usesDefault(): void
	{
		$result = $this->tool->execute([
			'command' => 'echo fast',
			'timeout' => 0,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('fast', $result->getOutput());
	}

	public function test_execute_returnsExitCodeInData(): void
	{
		$result = $this->tool->execute(['command' => 'exit 0']);

		$this->assertTrue($result->isSuccess());
		$data = $result->getData();
		$this->assertArrayHasKey('exit_code', $data);
		$this->assertSame(0, $data['exit_code']);
	}

	public function test_execute_returnsStdoutInData(): void
	{
		$result = $this->tool->execute(['command' => 'echo "test output"']);

		$this->assertTrue($result->isSuccess());
		$data = $result->getData();
		$this->assertArrayHasKey('stdout', $data);
		$this->assertStringContainsString('test output', $data['stdout']);
	}

	public function test_execute_returnsStderrInData(): void
	{
		$result = $this->tool->execute(['command' => 'echo "error" >&2']);

		$this->assertTrue($result->isSuccess());
		$data = $result->getData();
		$this->assertArrayHasKey('stderr', $data);
		$this->assertStringContainsString('error', $data['stderr']);
	}

	public function test_execute_withMultilineOutput_preservesOutput(): void
	{
		$result = $this->tool->execute(['command' => 'printf "line1\nline2\nline3"']);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('line1', $result->getOutput());
		$this->assertStringContainsString('line2', $result->getOutput());
		$this->assertStringContainsString('line3', $result->getOutput());
	}

	public function test_execute_withSpecialCharacters_handlesCorrectly(): void
	{
		$result = $this->tool->execute(['command' => 'echo "hello world"']);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('hello world', $result->getOutput());
	}

	public function test_execute_withExitCode_returnsNonZeroExitCode(): void
	{
		$result = $this->tool->execute(['command' => 'exit 42']);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('exited with code 42', $result->getError());
	}

	public function test_execute_withValidCwd_executesSuccessfully(): void
	{
		$temp_dir = sys_get_temp_dir();

		$result = $this->tool->execute([
			'command' => 'pwd',
			'cwd' => $temp_dir,
		]);

		$this->assertTrue($result->isSuccess());
	}
}
