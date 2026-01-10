<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Integration\Tool\BuiltIn;

use PHPUnit\Framework\TestCase;
use PhpCliAgent\Integration\Tool\BuiltIn\GrepTool;

/**
 * Tests for GrepTool.
 *
 * @covers \PhpCliAgent\Integration\Tool\BuiltIn\GrepTool
 */
final class GrepToolTest extends TestCase
{
	private GrepTool $tool;
	private string $temp_dir;

	protected function setUp(): void
	{
		$this->tool = new GrepTool();
		$this->temp_dir = sys_get_temp_dir() . '/greptool_test_' . uniqid();
		mkdir($this->temp_dir, 0755, true);
	}

	protected function tearDown(): void
	{
		$this->removeDirectory($this->temp_dir);
	}

	public function test_getName_returnsGrep(): void
	{
		$this->assertSame('grep', $this->tool->getName());
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
		$this->assertArrayHasKey('pattern', $schema['properties']);
		$this->assertArrayHasKey('path', $schema['properties']);
		$this->assertArrayHasKey('case_insensitive', $schema['properties']);
		$this->assertSame(['pattern'], $schema['required']);
	}

	public function test_requiresConfirmation_returnsFalse(): void
	{
		$this->assertFalse($this->tool->requiresConfirmation());
	}

	public function test_execute_withSimplePattern_findsMatches(): void
	{
		$file_path = $this->createTestFile('test.php', "line one\nline two\nline three");

		$result = $this->tool->execute([
			'pattern' => 'two',
			'path' => $file_path,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString(':2:', $result->getOutput());
		$this->assertStringContainsString('line two', $result->getOutput());
		$this->assertSame(1, $result->getData()['match_count']);
	}

	public function test_execute_withRegexPattern_findsMatches(): void
	{
		$file_path = $this->createTestFile('test.php', "function test() {\n  return true;\n}");

		$result = $this->tool->execute([
			'pattern' => 'function\s+\w+',
			'path' => $file_path,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('function test()', $result->getOutput());
		$this->assertSame(1, $result->getData()['match_count']);
	}

	public function test_execute_withCaseInsensitive_findsMatches(): void
	{
		$file_path = $this->createTestFile('test.php', "Hello World\nhello world\nHELLO WORLD");

		$result = $this->tool->execute([
			'pattern' => 'hello',
			'path' => $file_path,
			'case_insensitive' => true,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame(3, $result->getData()['match_count']);
	}

	public function test_execute_withCaseSensitive_findsCaseSensitiveMatches(): void
	{
		$file_path = $this->createTestFile('test.php', "Hello World\nhello world\nHELLO WORLD");

		$result = $this->tool->execute([
			'pattern' => 'hello',
			'path' => $file_path,
			'case_insensitive' => false,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame(1, $result->getData()['match_count']);
		$this->assertStringContainsString('hello world', $result->getOutput());
	}

	public function test_execute_withDirectory_searchesAllFiles(): void
	{
		$this->createTestFile('file1.php', "function foo() {}");
		$this->createTestFile('file2.php', "function bar() {}");
		$this->createTestFile('file3.php', "class Baz {}");

		$result = $this->tool->execute([
			'pattern' => 'function',
			'path' => $this->temp_dir,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame(2, $result->getData()['match_count']);
		$this->assertStringContainsString('file1.php', $result->getOutput());
		$this->assertStringContainsString('file2.php', $result->getOutput());
	}

	public function test_execute_withNoMatches_returnsNoMatchesMessage(): void
	{
		$file_path = $this->createTestFile('test.php', "hello world");

		$result = $this->tool->execute([
			'pattern' => 'xyz123',
			'path' => $file_path,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('No matches found', $result->getOutput());
		$this->assertSame(0, $result->getData()['match_count']);
	}

	public function test_execute_withMissingPattern_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'path' => $this->temp_dir,
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('Missing required argument', $result->getError());
	}

	public function test_execute_withEmptyPattern_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'pattern' => '',
			'path' => $this->temp_dir,
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('cannot be empty', $result->getError());
	}

	public function test_execute_withInvalidRegex_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'pattern' => '[invalid',
			'path' => $this->temp_dir,
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('Invalid regex', $result->getError());
	}

	public function test_execute_withNonExistentPath_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'pattern' => 'test',
			'path' => '/nonexistent/path/to/file',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('does not exist', $result->getError());
	}

	public function test_execute_includesLineNumber(): void
	{
		$file_path = $this->createTestFile('test.php', "line one\nline two\nline three\nline two again");

		$result = $this->tool->execute([
			'pattern' => 'two',
			'path' => $file_path,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString(':2:', $result->getOutput());
		$this->assertStringContainsString(':4:', $result->getOutput());
	}

	public function test_execute_withBinaryFile_skipsFile(): void
	{
		$file_path = $this->createBinaryFile('test.bin');

		$result = $this->tool->execute([
			'pattern' => 'test',
			'path' => $file_path,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('No matches found', $result->getOutput());
	}

	public function test_execute_withSubdirectories_searchesRecursively(): void
	{
		mkdir($this->temp_dir . '/subdir', 0755, true);
		$this->createTestFile('root.php', 'target string');
		$this->createTestFile('subdir/nested.php', 'target string');

		$result = $this->tool->execute([
			'pattern' => 'target',
			'path' => $this->temp_dir,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame(2, $result->getData()['match_count']);
	}

	public function test_execute_returnsFilePathInOutput(): void
	{
		$file_path = $this->createTestFile('myfile.php', 'search term');

		$result = $this->tool->execute([
			'pattern' => 'search',
			'path' => $file_path,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('myfile.php', $result->getOutput());
	}

	public function test_execute_includesPatternInData(): void
	{
		$file_path = $this->createTestFile('test.php', 'content');

		$result = $this->tool->execute([
			'pattern' => 'content',
			'path' => $file_path,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame('content', $result->getData()['pattern']);
	}

	public function test_execute_includesFilesSearchedCount(): void
	{
		$this->createTestFile('file1.php', 'content');
		$this->createTestFile('file2.php', 'content');
		$this->createTestFile('file3.php', 'content');

		$result = $this->tool->execute([
			'pattern' => 'content',
			'path' => $this->temp_dir,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame(3, $result->getData()['files_searched']);
	}

	public function test_execute_truncatesLongLines(): void
	{
		$long_content = 'match' . str_repeat('x', 600);
		$file_path = $this->createTestFile('test.php', $long_content);

		$result = $this->tool->execute([
			'pattern' => 'match',
			'path' => $file_path,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('...', $result->getOutput());
		$this->assertLessThan(600, strlen($result->getOutput()));
	}

	public function test_execute_handlesMultipleMatchesInSameLine(): void
	{
		$file_path = $this->createTestFile('test.php', 'one two one three one');

		$result = $this->tool->execute([
			'pattern' => 'one',
			'path' => $file_path,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame(1, $result->getData()['match_count']);
	}

	public function test_execute_handlesSpecialRegexCharacters(): void
	{
		$file_path = $this->createTestFile('test.php', '$variable = "value";');

		$result = $this->tool->execute([
			'pattern' => '\$variable',
			'path' => $file_path,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame(1, $result->getData()['match_count']);
	}

	public function test_execute_withDefaultPath_usesCurrentDirectory(): void
	{
		$result = $this->tool->execute([
			'pattern' => 'composer',
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertArrayHasKey('path', $result->getData());
	}

	public function test_execute_handlesUtf8Content(): void
	{
		$file_path = $this->createTestFile('test.php', "héllo wörld\n日本語\nемейл");

		$result = $this->tool->execute([
			'pattern' => 'héllo',
			'path' => $file_path,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame(1, $result->getData()['match_count']);
		$this->assertStringContainsString('héllo wörld', $result->getOutput());
	}

	public function test_execute_handlesEmptyFile(): void
	{
		$file_path = $this->createTestFile('empty.php', '');

		$result = $this->tool->execute([
			'pattern' => 'test',
			'path' => $file_path,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('No matches found', $result->getOutput());
	}

	/**
	 * Creates a test file in the temporary directory.
	 *
	 * @param string $relative_path Relative path within temp directory.
	 * @param string $content       File content.
	 *
	 * @return string Full file path.
	 */
	private function createTestFile(string $relative_path, string $content): string
	{
		$file_path = $this->temp_dir . '/' . $relative_path;
		file_put_contents($file_path, $content);

		return $file_path;
	}

	/**
	 * Creates a binary file for testing.
	 *
	 * @param string $relative_path Relative path within temp directory.
	 *
	 * @return string Full file path.
	 */
	private function createBinaryFile(string $relative_path): string
	{
		$file_path = $this->temp_dir . '/' . $relative_path;

		$binary_content = '';
		for ($i = 0; $i < 100; $i++) {
			$binary_content .= chr(random_int(0, 255));
		}
		$binary_content .= "\0\0\0\0";

		file_put_contents($file_path, $binary_content);

		return $file_path;
	}

	/**
	 * Recursively removes a directory.
	 *
	 * @param string $path Directory path.
	 *
	 * @return void
	 */
	private function removeDirectory(string $path): void
	{
		if (!is_dir($path)) {
			return;
		}

		$items = scandir($path);
		if ($items === false) {
			return;
		}

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$full_path = $path . '/' . $item;

			if (is_dir($full_path)) {
				$this->removeDirectory($full_path);
			} else {
				unlink($full_path);
			}
		}

		rmdir($path);
	}
}
