<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Integration\Tool\BuiltIn;

use PHPUnit\Framework\TestCase;
use Automattic\WpAiAgent\Integration\Tool\BuiltIn\WriteFileTool;

/**
 * Tests for WriteFileTool.
 *
 * @covers \Automattic\WpAiAgent\Integration\Tool\BuiltIn\WriteFileTool
 */
final class WriteFileToolTest extends TestCase
{
	private WriteFileTool $tool;
	private string $temp_dir;

	protected function setUp(): void
	{
		$this->tool = new WriteFileTool();
		$this->temp_dir = sys_get_temp_dir() . '/writefiletool_test_' . uniqid();
		mkdir($this->temp_dir, 0755, true);
	}

	protected function tearDown(): void
	{
		$this->removeDirectory($this->temp_dir);
	}

	public function test_getName_returnsWriteFile(): void
	{
		$this->assertSame('write_file', $this->tool->getName());
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
		$this->assertArrayHasKey('content', $schema['properties']);
		$this->assertSame(['file_path', 'content'], $schema['required']);
	}

	public function test_requiresConfirmation_returnsTrue(): void
	{
		$this->assertTrue($this->tool->requiresConfirmation());
	}

	public function test_execute_withValidPath_createsFile(): void
	{
		$file_path = $this->temp_dir . '/new.txt';

		$result = $this->tool->execute([
			'file_path' => $file_path,
			'content' => 'hello',
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertFileExists($file_path);
		$this->assertSame('hello', file_get_contents($file_path));
		$this->assertSame(5, $result->getData()['bytes_written']);
		$this->assertFalse($result->getData()['file_existed']);
	}

	public function test_execute_withExistingFile_overwritesFile(): void
	{
		$file_path = $this->temp_dir . '/existing.txt';
		file_put_contents($file_path, 'old content');

		$result = $this->tool->execute([
			'file_path' => $file_path,
			'content' => 'new',
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame('new', file_get_contents($file_path));
		$this->assertTrue($result->getData()['file_existed']);
		$this->assertStringContainsString('Overwritten', $result->getOutput());
	}

	public function test_execute_withNestedPath_createsDirectories(): void
	{
		$file_path = $this->temp_dir . '/deep/nested/file.txt';

		$result = $this->tool->execute([
			'file_path' => $file_path,
			'content' => 'x',
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertFileExists($file_path);
		$this->assertSame('x', file_get_contents($file_path));
		$this->assertTrue($result->getData()['directories_created']);
		$this->assertStringContainsString('created parent directories', $result->getOutput());
	}

	public function test_execute_withMissingFilePath_returnsFailure(): void
	{
		$result = $this->tool->execute(['content' => 'test']);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('Missing required argument', $result->getError());
		$this->assertStringContainsString('file_path', $result->getError());
	}

	public function test_execute_withMissingContent_returnsFailure(): void
	{
		$result = $this->tool->execute(['file_path' => $this->temp_dir . '/test.txt']);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('Missing required argument', $result->getError());
		$this->assertStringContainsString('content', $result->getError());
	}

	public function test_execute_withEmptyFilePath_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'file_path' => '',
			'content' => 'test',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('cannot be empty', $result->getError());
	}

	public function test_execute_withRelativePath_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'file_path' => 'relative/path/file.txt',
			'content' => 'test',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('must be absolute', $result->getError());
	}

	public function test_execute_withProtectedSystemPath_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'file_path' => '/etc/passwd',
			'content' => 'test',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('protected system file', $result->getError());
	}

	public function test_execute_withProtectedDirectory_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'file_path' => '/etc/myconfig.conf',
			'content' => 'test',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('protected system directory', $result->getError());
	}

	public function test_execute_withBinPath_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'file_path' => '/bin/mycommand',
			'content' => 'test',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('protected', $result->getError());
	}

	public function test_execute_withUsrBinPath_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'file_path' => '/usr/bin/mycommand',
			'content' => 'test',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('protected', $result->getError());
	}

	public function test_execute_withPathTraversalAttempt_blocksAccess(): void
	{
		$result = $this->tool->execute([
			'file_path' => '/tmp/../etc/passwd',
			'content' => 'test',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('protected', $result->getError());
	}

	public function test_execute_withEmptyContent_createsEmptyFile(): void
	{
		$file_path = $this->temp_dir . '/empty.txt';

		$result = $this->tool->execute([
			'file_path' => $file_path,
			'content' => '',
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertFileExists($file_path);
		$this->assertSame('', file_get_contents($file_path));
		$this->assertSame(0, $result->getData()['bytes_written']);
	}

	public function test_execute_withMultilineContent_writesCorrectly(): void
	{
		$file_path = $this->temp_dir . '/multiline.txt';
		$content = "line1\nline2\nline3";

		$result = $this->tool->execute([
			'file_path' => $file_path,
			'content' => $content,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame($content, file_get_contents($file_path));
	}

	public function test_execute_withUtf8Content_writesCorrectly(): void
	{
		$file_path = $this->temp_dir . '/utf8.txt';
		$content = "héllo wörld\n日本語\nемейл";

		$result = $this->tool->execute([
			'file_path' => $file_path,
			'content' => $content,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame($content, file_get_contents($file_path));
	}

	public function test_execute_returnsFilePathInData(): void
	{
		$file_path = $this->temp_dir . '/test.txt';

		$result = $this->tool->execute([
			'file_path' => $file_path,
			'content' => 'test',
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertArrayHasKey('file_path', $result->getData());
		$this->assertSame($file_path, $result->getData()['file_path']);
	}

	public function test_execute_withDeeplyNestedPath_createsAllDirectories(): void
	{
		$file_path = $this->temp_dir . '/a/b/c/d/e/f/g/h/file.txt';

		$result = $this->tool->execute([
			'file_path' => $file_path,
			'content' => 'deep',
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertFileExists($file_path);
		$this->assertDirectoryExists($this->temp_dir . '/a/b/c/d/e/f/g/h');
	}

	public function test_execute_withTmpPath_succeeds(): void
	{
		$file_path = $this->temp_dir . '/allowed.txt';

		$result = $this->tool->execute([
			'file_path' => $file_path,
			'content' => 'allowed',
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertFileExists($file_path);
	}

	public function test_execute_withBootPath_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'file_path' => '/boot/test.txt',
			'content' => 'test',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('protected', $result->getError());
	}

	public function test_execute_withProcPath_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'file_path' => '/proc/test',
			'content' => 'test',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('protected', $result->getError());
	}

	public function test_execute_withSysPath_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'file_path' => '/sys/test',
			'content' => 'test',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('protected', $result->getError());
	}

	public function test_execute_withDevPath_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'file_path' => '/dev/test',
			'content' => 'test',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('protected', $result->getError());
	}

	public function test_execute_withProtectedSshConfig_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'file_path' => '/etc/ssh/sshd_config',
			'content' => 'test',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('protected', $result->getError());
	}

	public function test_execute_withEtcShadow_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'file_path' => '/etc/shadow',
			'content' => 'test',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('protected', $result->getError());
	}

	public function test_execute_withEtcGroup_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'file_path' => '/etc/group',
			'content' => 'test',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('protected', $result->getError());
	}

	public function test_execute_withEtcSudoers_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'file_path' => '/etc/sudoers',
			'content' => 'test',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('protected', $result->getError());
	}

	public function test_execute_withEtcHosts_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'file_path' => '/etc/hosts',
			'content' => 'test',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('protected', $result->getError());
	}

	public function test_execute_withEtcFstab_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'file_path' => '/etc/fstab',
			'content' => 'test',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('protected', $result->getError());
	}

	public function test_execute_withLibPath_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'file_path' => '/lib/test.so',
			'content' => 'test',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('protected', $result->getError());
	}

	public function test_execute_withLib64Path_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'file_path' => '/lib64/test.so',
			'content' => 'test',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('protected', $result->getError());
	}

	public function test_execute_withUsrLibPath_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'file_path' => '/usr/lib/test.so',
			'content' => 'test',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('protected', $result->getError());
	}

	public function test_execute_withSbinPath_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'file_path' => '/sbin/test',
			'content' => 'test',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('protected', $result->getError());
	}

	public function test_execute_withUsrSbinPath_returnsFailure(): void
	{
		$result = $this->tool->execute([
			'file_path' => '/usr/sbin/test',
			'content' => 'test',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('protected', $result->getError());
	}

	public function test_execute_outputMessageIncludesByteCount(): void
	{
		$file_path = $this->temp_dir . '/bytes.txt';
		$content = 'test content';

		$result = $this->tool->execute([
			'file_path' => $file_path,
			'content' => $content,
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('12 bytes', $result->getOutput());
	}

	/**
	 * Recursively removes a directory and its contents.
	 *
	 * @param string $directory The directory to remove.
	 *
	 * @return void
	 */
	private function removeDirectory(string $directory): void
	{
		if (!is_dir($directory)) {
			return;
		}

		$items = scandir($directory);
		if ($items === false) {
			return;
		}

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$path = $directory . '/' . $item;

			if (is_dir($path)) {
				$this->removeDirectory($path);
			} else {
				unlink($path);
			}
		}

		rmdir($directory);
	}
}
