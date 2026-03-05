<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Integration\Tool\BuiltIn;

use PHPUnit\Framework\TestCase;
use Automattic\Automattic\WpAiAgent\Integration\Tool\BuiltIn\GlobTool;

/**
 * Tests for GlobTool.
 *
 * @covers \Automattic\WpAiAgent\Integration\Tool\BuiltIn\GlobTool
 */
final class GlobToolTest extends TestCase
{
	private GlobTool $tool;
	private string $temp_dir;

	protected function setUp(): void
	{
		$this->tool = new GlobTool();
		$this->temp_dir = sys_get_temp_dir() . '/globtool_test_' . uniqid();
		mkdir($this->temp_dir, 0755, true);
	}

	protected function tearDown(): void
	{
		$this->removeDirectory($this->temp_dir);
	}

	public function test_getName_returnsGlob(): void
	{
		$this->assertSame('glob', $this->tool->getName());
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
		$this->assertSame(['pattern'], $schema['required']);
	}

	public function test_requiresConfirmation_returnsFalse(): void
	{
		$this->assertFalse($this->tool->requiresConfirmation());
	}

	public function test_execute_withSimplePattern_returnsMatchingFiles(): void
	{
		$this->createTestFile('file1.php', 'content');
		$this->createTestFile('file2.php', 'content');
		$this->createTestFile('file3.txt', 'content');

		$result = $this->tool->execute([
			'pattern' => '*.php',
			'path' => $this->temp_dir,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('file1.php', $result->getOutput());
		$this->assertStringContainsString('file2.php', $result->getOutput());
		$this->assertStringNotContainsString('file3.txt', $result->getOutput());
		$this->assertSame(2, $result->getData()['count']);
	}

	public function test_execute_withRecursivePattern_findsFilesInSubdirectories(): void
	{
		mkdir($this->temp_dir . '/subdir', 0755, true);
		$this->createTestFile('subdir/nested.php', 'content');
		mkdir($this->temp_dir . '/subdir/deep', 0755, true);
		$this->createTestFile('subdir/deep/deep.php', 'content');

		$result = $this->tool->execute([
			'pattern' => '**/*.php',
			'path' => $this->temp_dir,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('nested.php', $result->getOutput());
		$this->assertStringContainsString('deep.php', $result->getOutput());
		$this->assertSame(2, $result->getData()['count']);
	}

	public function test_execute_withNoMatches_returnsEmptyResult(): void
	{
		$this->createTestFile('file.txt', 'content');

		$result = $this->tool->execute([
			'pattern' => '*.xyz',
			'path' => $this->temp_dir,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('No files found', $result->getOutput());
		$this->assertSame(0, $result->getData()['count']);
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

	public function test_execute_withInvalidPath_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'pattern' => '*.php',
			'path' => '/nonexistent/path/to/directory',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('does not exist', $result->getError());
	}

	public function test_execute_withDefaultPath_usesCurrentDirectory(): void
	{
		$cwd = getcwd();
		if ($cwd === false) {
			$this->markTestSkipped('Unable to get current working directory');
		}

		$result = $this->tool->execute([
			'pattern' => 'composer.json',
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertArrayHasKey('base_path', $result->getData());
	}

	public function test_execute_sortsByModificationTime(): void
	{
		$this->createTestFile('old.php', 'content');
		sleep(1);
		$this->createTestFile('new.php', 'content');

		$result = $this->tool->execute([
			'pattern' => '*.php',
			'path' => $this->temp_dir,
		]);

		$this->assertTrue($result->isSuccess());

		$output = $result->getOutput();
		$new_pos = strpos($output, 'new.php');
		$old_pos = strpos($output, 'old.php');

		$this->assertLessThan($old_pos, $new_pos, 'Newer file should appear first');
	}

	public function test_execute_returnsFullPaths(): void
	{
		$this->createTestFile('test.php', 'content');

		$result = $this->tool->execute([
			'pattern' => '*.php',
			'path' => $this->temp_dir,
		]);

		$this->assertTrue($result->isSuccess());
		$files = $result->getData()['files'];
		$this->assertCount(1, $files);

		$real_temp_dir = realpath($this->temp_dir);
		$this->assertNotFalse($real_temp_dir);
		$this->assertStringStartsWith($real_temp_dir, $files[0]);
	}

	public function test_execute_withQuestionMarkPattern_matchesSingleCharacter(): void
	{
		$this->createTestFile('file1.php', 'content');
		$this->createTestFile('file2.php', 'content');
		$this->createTestFile('file12.php', 'content');

		$result = $this->tool->execute([
			'pattern' => 'file?.php',
			'path' => $this->temp_dir,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('file1.php', $result->getOutput());
		$this->assertStringContainsString('file2.php', $result->getOutput());
		$this->assertStringNotContainsString('file12.php', $result->getOutput());
	}

	public function test_execute_includesPatternInData(): void
	{
		$this->createTestFile('test.php', 'content');

		$result = $this->tool->execute([
			'pattern' => '*.php',
			'path' => $this->temp_dir,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame('*.php', $result->getData()['pattern']);
	}

	public function test_execute_includesBasePathInData(): void
	{
		$this->createTestFile('test.php', 'content');

		$result = $this->tool->execute([
			'pattern' => '*.php',
			'path' => $this->temp_dir,
		]);

		$this->assertTrue($result->isSuccess());
		$real_path = realpath($this->temp_dir);
		$this->assertSame($real_path, $result->getData()['base_path']);
	}

	public function test_execute_ignoresDirectories(): void
	{
		mkdir($this->temp_dir . '/subdir.php', 0755, true);
		$this->createTestFile('file.php', 'content');

		$result = $this->tool->execute([
			'pattern' => '*.php',
			'path' => $this->temp_dir,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame(1, $result->getData()['count']);
	}

	public function test_execute_withDoubleStarAtStart_matchesFromRoot(): void
	{
		mkdir($this->temp_dir . '/src', 0755, true);
		mkdir($this->temp_dir . '/src/Models', 0755, true);
		$this->createTestFile('src/Controller.php', 'content');
		$this->createTestFile('src/Models/User.php', 'content');

		$result = $this->tool->execute([
			'pattern' => '**/User.php',
			'path' => $this->temp_dir,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('User.php', $result->getOutput());
		$this->assertSame(1, $result->getData()['count']);
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
