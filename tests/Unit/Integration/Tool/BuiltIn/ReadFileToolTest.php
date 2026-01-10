<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Integration\Tool\BuiltIn;

use PHPUnit\Framework\TestCase;
use PhpCliAgent\Integration\Tool\BuiltIn\ReadFileTool;

/**
 * Tests for ReadFileTool.
 *
 * @covers \PhpCliAgent\Integration\Tool\BuiltIn\ReadFileTool
 */
final class ReadFileToolTest extends TestCase
{
	private ReadFileTool $tool;
	private string $temp_dir;

	protected function setUp(): void
	{
		$this->tool = new ReadFileTool();
		$this->temp_dir = sys_get_temp_dir();
	}

	protected function tearDown(): void
	{
		$files = glob($this->temp_dir . '/readfiletool_test_*');
		if ($files !== false) {
			foreach ($files as $file) {
				if (is_file($file)) {
					unlink($file);
				}
			}
		}
	}

	public function test_getName_returnsReadFile(): void
	{
		$this->assertSame('read_file', $this->tool->getName());
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
		$this->assertArrayHasKey('file_path', $schema['properties']);
		$this->assertArrayHasKey('offset', $schema['properties']);
		$this->assertArrayHasKey('limit', $schema['properties']);
		$this->assertSame(['file_path'], $schema['required']);
	}

	public function test_requiresConfirmation_returnsFalse(): void
	{
		$this->assertFalse($this->tool->requiresConfirmation());
	}

	public function test_execute_withValidFile_returnsAllLinesNumbered(): void
	{
		$file_path = $this->createTestFile("line1\nline2\nline3\nline4\nline5\nline6\nline7\nline8\nline9\nline10");

		$result = $this->tool->execute(['file_path' => $file_path]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('1→line1', $result->getOutput());
		$this->assertStringContainsString('10→line10', $result->getOutput());
		$this->assertSame(10, $result->getData()['total_lines']);
		$this->assertSame(10, $result->getData()['lines_read']);
	}

	public function test_execute_withOffsetAndLimit_returnsCorrectLines(): void
	{
		$file_path = $this->createTestFile("line1\nline2\nline3\nline4\nline5\nline6\nline7\nline8\nline9\nline10");

		$result = $this->tool->execute([
			'file_path' => $file_path,
			'offset' => 5,
			'limit' => 3,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('5→line5', $result->getOutput());
		$this->assertStringContainsString('6→line6', $result->getOutput());
		$this->assertStringContainsString('7→line7', $result->getOutput());
		$this->assertStringNotContainsString('4→line4', $result->getOutput());
		$this->assertStringNotContainsString('8→line8', $result->getOutput());
		$this->assertSame(3, $result->getData()['lines_read']);
	}

	public function test_execute_withNonExistentFile_returnsFailure(): void
	{
		$result = $this->tool->execute(['file_path' => '/nonexistent/file/path/test.txt']);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('File not found', $result->getError());
	}

	public function test_execute_withBinaryFile_returnsFailure(): void
	{
		$file_path = $this->createBinaryFile();

		$result = $this->tool->execute(['file_path' => $file_path]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('binary file', $result->getError());
		$this->assertStringContainsString('cannot be displayed as text', $result->getOutput());
	}

	public function test_execute_withMissingFilePath_returnsFailure(): void
	{
		$result = $this->tool->execute([]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('Missing required argument', $result->getError());
	}

	public function test_execute_withEmptyFilePath_returnsFailure(): void
	{
		$result = $this->tool->execute(['file_path' => '']);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('cannot be empty', $result->getError());
	}

	public function test_execute_withDirectory_returnsFailure(): void
	{
		$result = $this->tool->execute(['file_path' => $this->temp_dir]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('not a file', $result->getError());
	}

	public function test_execute_withEmptyFile_returnsEmptyFileMessage(): void
	{
		$file_path = $this->createTestFile('');

		$result = $this->tool->execute(['file_path' => $file_path]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('Empty file', $result->getOutput());
		$this->assertSame(0, $result->getData()['total_lines']);
	}

	public function test_execute_withOffsetBeyondFileEnd_returnsFailure(): void
	{
		$file_path = $this->createTestFile("line1\nline2\nline3");

		$result = $this->tool->execute([
			'file_path' => $file_path,
			'offset' => 100,
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('beyond end of file', $result->getError());
	}

	public function test_execute_withNegativeOffset_usesDefaultOffset(): void
	{
		$file_path = $this->createTestFile("line1\nline2\nline3");

		$result = $this->tool->execute([
			'file_path' => $file_path,
			'offset' => -5,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('1→line1', $result->getOutput());
	}

	public function test_execute_withZeroOffset_usesDefaultOffset(): void
	{
		$file_path = $this->createTestFile("line1\nline2\nline3");

		$result = $this->tool->execute([
			'file_path' => $file_path,
			'offset' => 0,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('1→line1', $result->getOutput());
	}

	public function test_execute_withNegativeLimit_usesDefaultLimit(): void
	{
		$file_path = $this->createTestFile("line1\nline2\nline3");

		$result = $this->tool->execute([
			'file_path' => $file_path,
			'limit' => -1,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame(3, $result->getData()['lines_read']);
	}

	public function test_execute_withZeroLimit_usesDefaultLimit(): void
	{
		$file_path = $this->createTestFile("line1\nline2\nline3");

		$result = $this->tool->execute([
			'file_path' => $file_path,
			'limit' => 0,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame(3, $result->getData()['lines_read']);
	}

	public function test_execute_withLongLine_truncatesLine(): void
	{
		$long_line = str_repeat('a', 3000);
		$file_path = $this->createTestFile($long_line);

		$result = $this->tool->execute(['file_path' => $file_path]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('...', $result->getOutput());
		$this->assertLessThan(3000, strlen($result->getOutput()));
	}

	public function test_execute_preservesLineNumbers(): void
	{
		$file_path = $this->createTestFile("a\nb\nc\nd\ne");

		$result = $this->tool->execute([
			'file_path' => $file_path,
			'offset' => 3,
			'limit' => 2,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('3→c', $result->getOutput());
		$this->assertStringContainsString('4→d', $result->getOutput());
	}

	public function test_execute_returnsFilePathInData(): void
	{
		$file_path = $this->createTestFile("test content");

		$result = $this->tool->execute(['file_path' => $file_path]);

		$this->assertTrue($result->isSuccess());
		$this->assertArrayHasKey('file_path', $result->getData());
		$this->assertSame($file_path, $result->getData()['file_path']);
	}

	public function test_execute_handlesWindowsLineEndings(): void
	{
		$file_path = $this->createTestFile("line1\r\nline2\r\nline3");

		$result = $this->tool->execute(['file_path' => $file_path]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('1→line1', $result->getOutput());
		$this->assertStringNotContainsString("\r", $result->getOutput());
	}

	public function test_execute_handlesUtf8Content(): void
	{
		$file_path = $this->createTestFile("héllo wörld\n日本語\nемейл");

		$result = $this->tool->execute(['file_path' => $file_path]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('héllo wörld', $result->getOutput());
		$this->assertStringContainsString('日本語', $result->getOutput());
		$this->assertStringContainsString('емейл', $result->getOutput());
	}

	public function test_execute_withLimitExceedingFileLines_returnsAllAvailableLines(): void
	{
		$file_path = $this->createTestFile("line1\nline2\nline3");

		$result = $this->tool->execute([
			'file_path' => $file_path,
			'limit' => 1000,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame(3, $result->getData()['lines_read']);
	}

	public function test_execute_withTextFileContainingEscapeSequences_readsCorrectly(): void
	{
		$file_path = $this->createTestFile("line with\ttab\nline with\\backslash");

		$result = $this->tool->execute(['file_path' => $file_path]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString("line with\ttab", $result->getOutput());
		$this->assertStringContainsString('line with\\backslash', $result->getOutput());
	}

	/**
	 * Creates a temporary test file with the given content.
	 *
	 * @param string $content The file content.
	 *
	 * @return string The file path.
	 */
	private function createTestFile(string $content): string
	{
		$file_path = $this->temp_dir . '/readfiletool_test_' . uniqid() . '.txt';
		file_put_contents($file_path, $content);

		return $file_path;
	}

	/**
	 * Creates a binary file for testing binary detection.
	 *
	 * @return string The file path.
	 */
	private function createBinaryFile(): string
	{
		$file_path = $this->temp_dir . '/readfiletool_test_' . uniqid() . '.bin';

		$binary_content = '';
		for ($i = 0; $i < 100; $i++) {
			$binary_content .= chr(random_int(0, 255));
		}
		$binary_content .= "\0\0\0\0";

		file_put_contents($file_path, $binary_content);

		return $file_path;
	}
}
