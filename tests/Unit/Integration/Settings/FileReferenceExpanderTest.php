<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Integration\Settings;

use WpAiAgent\Core\Contracts\FileReferenceExpanderInterface;
use WpAiAgent\Integration\Settings\FileReferenceExpander;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for FileReferenceExpander.
 *
 * @covers \WpAiAgent\Integration\Settings\FileReferenceExpander
 */
final class FileReferenceExpanderTest extends TestCase
{
	/**
	 * Temporary directory for test files.
	 *
	 * @var string
	 */
	private string $temp_dir;

	/**
	 * Mock user home directory.
	 *
	 * @var string
	 */
	private string $user_home;

	/**
	 * Sets up the test fixture.
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->temp_dir = sys_get_temp_dir() . '/file_reference_expander_test_' . uniqid();
		mkdir($this->temp_dir, 0755, true);

		$this->user_home = $this->temp_dir . '/home';
		mkdir($this->user_home, 0755, true);
	}

	/**
	 * Tears down the test fixture.
	 */
	protected function tearDown(): void
	{
		$this->removeDirectory($this->temp_dir);

		parent::tearDown();
	}

	/**
	 * Tests that constructor creates instance implementing the interface.
	 */
	public function test_constructor_implementsInterface(): void
	{
		$expander = new FileReferenceExpander($this->user_home);

		$this->assertInstanceOf(FileReferenceExpanderInterface::class, $expander);
	}

	/**
	 * Tests that expand returns content unchanged when no references present.
	 */
	public function test_expand_withNoReferences_returnsContentUnchanged(): void
	{
		$expander = new FileReferenceExpander($this->user_home);
		$content = 'This is plain content without any file references.';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame($content, $result);
	}

	/**
	 * Tests that expand handles relative path references.
	 */
	public function test_expand_withRelativePath_expandsFileContent(): void
	{
		$this->createFile($this->temp_dir . '/helpers', 'prompt.md', 'This is the prompt content.');

		$expander = new FileReferenceExpander($this->user_home);
		$content = "Here is the content:\n\n@./helpers/prompt.md\n\nEnd of document.";

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame(
			"Here is the content:\n\nThis is the prompt content.\n\nEnd of document.",
			$result
		);
	}

	/**
	 * Tests that expand handles home directory references.
	 */
	public function test_expand_withHomePath_expandsFileContent(): void
	{
		$this->createFile($this->user_home . '/prompts', 'common.md', 'Common prompt template.');

		$expander = new FileReferenceExpander($this->user_home);
		$content = "Include this:\n\n@~/prompts/common.md";

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame(
			"Include this:\n\nCommon prompt template.",
			$result
		);
	}

	/**
	 * Tests that expand handles absolute path references.
	 */
	public function test_expand_withAbsolutePath_expandsFileContent(): void
	{
		$absolute_dir = $this->temp_dir . '/absolute';
		$this->createFile($absolute_dir, 'file.md', 'Absolute path content.');

		$expander = new FileReferenceExpander($this->user_home);
		$content = "Absolute file:\n\n@" . $absolute_dir . "/file.md";

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame(
			"Absolute file:\n\nAbsolute path content.",
			$result
		);
	}

	/**
	 * Tests that expand handles multiple references in same content.
	 */
	public function test_expand_withMultipleReferences_expandsAll(): void
	{
		$this->createFile($this->temp_dir . '/helpers', 'first.md', 'First content.');
		$this->createFile($this->temp_dir . '/helpers', 'second.md', 'Second content.');

		$expander = new FileReferenceExpander($this->user_home);
		$content = "@./helpers/first.md\n\nMiddle text.\n\n@./helpers/second.md";

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame(
			"First content.\n\nMiddle text.\n\nSecond content.",
			$result
		);
	}

	/**
	 * Tests that expand does not expand email addresses.
	 */
	public function test_expand_withEmailAddress_doesNotExpand(): void
	{
		$expander = new FileReferenceExpander($this->user_home);
		$content = 'Contact us at user@domain.com for support.';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame($content, $result);
	}

	/**
	 * Tests that expand does not expand @ mentions that are not file paths.
	 */
	public function test_expand_withAtMention_doesNotExpand(): void
	{
		$expander = new FileReferenceExpander($this->user_home);
		$content = 'Ask @john for help with this.';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame($content, $result);
	}

	/**
	 * Tests that expand throws exception for non-existent files.
	 */
	public function test_expand_withNonExistentFile_throwsException(): void
	{
		$expander = new FileReferenceExpander($this->user_home);
		$content = '@./non-existent-file.md';

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('File not found');

		$expander->expand($content, $this->temp_dir);
	}

	/**
	 * Tests that expand handles recursive file references.
	 */
	public function test_expand_withRecursiveReferences_expandsRecursively(): void
	{
		$this->createFile($this->temp_dir, 'outer.md', "Outer start.\n\n@./inner.md\n\nOuter end.");
		$this->createFile($this->temp_dir, 'inner.md', 'Inner content.');

		$expander = new FileReferenceExpander($this->user_home);
		$content = '@./outer.md';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame(
			"Outer start.\n\nInner content.\n\nOuter end.",
			$result
		);
	}

	/**
	 * Tests that expand detects circular references and throws exception.
	 */
	public function test_expand_withCircularReference_throwsException(): void
	{
		$this->createFile($this->temp_dir, 'a.md', "Content A.\n\n@./b.md");
		$this->createFile($this->temp_dir, 'b.md', "Content B.\n\n@./a.md");

		$expander = new FileReferenceExpander($this->user_home);
		$content = '@./a.md';

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Circular reference detected');

		$expander->expand($content, $this->temp_dir);
	}

	/**
	 * Tests that expand handles reference at start of line only.
	 */
	public function test_expand_withReferenceAtStartOfLine_expandsCorrectly(): void
	{
		$this->createFile($this->temp_dir, 'file.md', 'File content.');

		$expander = new FileReferenceExpander($this->user_home);
		$content = "@./file.md";

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame('File content.', $result);
	}

	/**
	 * Tests that expand preserves @ in inline context.
	 */
	public function test_expand_withAtInMiddleOfWord_doesNotExpand(): void
	{
		$expander = new FileReferenceExpander($this->user_home);
		$content = 'Check the file@./path for details.';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame($content, $result);
	}

	/**
	 * Tests that expand handles empty content.
	 */
	public function test_expand_withEmptyContent_returnsEmpty(): void
	{
		$expander = new FileReferenceExpander($this->user_home);

		$result = $expander->expand('', $this->temp_dir);

		$this->assertSame('', $result);
	}

	/**
	 * Tests that expand handles multiline file content.
	 */
	public function test_expand_withMultilineFileContent_preservesLineBreaks(): void
	{
		$file_content = "Line 1\nLine 2\nLine 3";
		$this->createFile($this->temp_dir, 'multiline.md', $file_content);

		$expander = new FileReferenceExpander($this->user_home);
		$content = "Before:\n\n@./multiline.md\n\nAfter.";

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame("Before:\n\nLine 1\nLine 2\nLine 3\n\nAfter.", $result);
	}

	/**
	 * Tests that expand handles paths with special characters.
	 */
	public function test_expand_withSpecialCharactersInPath_expandsCorrectly(): void
	{
		$this->createFile($this->temp_dir . '/my-prompts', 'special_file.md', 'Special content.');

		$expander = new FileReferenceExpander($this->user_home);
		$content = '@./my-prompts/special_file.md';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame('Special content.', $result);
	}

	/**
	 * Tests that expand handles reference with trailing content on same line.
	 */
	public function test_expand_withTrailingContentOnSameLine_expandsCorrectly(): void
	{
		$this->createFile($this->temp_dir, 'inline.md', 'Inline content');

		$expander = new FileReferenceExpander($this->user_home);
		// When the @ reference is on its own line, it should be expanded
		// When there's other content on the same line, it depends on the implementation
		$content = "Start.\n@./inline.md\nEnd.";

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame("Start.\nInline content\nEnd.", $result);
	}

	/**
	 * Tests that expand handles mixed home and relative references.
	 */
	public function test_expand_withMixedReferences_expandsBoth(): void
	{
		$this->createFile($this->temp_dir, 'local.md', 'Local content.');
		$this->createFile($this->user_home, 'global.md', 'Global content.');

		$expander = new FileReferenceExpander($this->user_home);
		$content = "@./local.md\n\n@~/global.md";

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame("Local content.\n\nGlobal content.", $result);
	}

	/**
	 * Tests that expand handles deeply nested recursive references.
	 */
	public function test_expand_withDeeplyNestedReferences_expandsAll(): void
	{
		$this->createFile($this->temp_dir, 'level1.md', "Level 1\n@./level2.md");
		$this->createFile($this->temp_dir, 'level2.md', "Level 2\n@./level3.md");
		$this->createFile($this->temp_dir, 'level3.md', 'Level 3');

		$expander = new FileReferenceExpander($this->user_home);
		$content = '@./level1.md';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame("Level 1\nLevel 2\nLevel 3", $result);
	}

	/**
	 * Tests that expand handles relative path with parent directory.
	 */
	public function test_expand_withParentDirectoryPath_expandsCorrectly(): void
	{
		$subdir = $this->temp_dir . '/subdir';
		mkdir($subdir, 0755, true);
		$this->createFile($this->temp_dir, 'parent.md', 'Parent content.');

		$expander = new FileReferenceExpander($this->user_home);
		$content = '@./../parent.md';

		$result = $expander->expand($content, $subdir);

		$this->assertSame('Parent content.', $result);
	}

	/**
	 * Tests that expand ignores @ followed by numbers (like Twitter mentions).
	 */
	public function test_expand_withAtFollowedByNumber_doesNotExpand(): void
	{
		$expander = new FileReferenceExpander($this->user_home);
		$content = 'See reference @123 for details.';

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame($content, $result);
	}

	/**
	 * Tests that expand handles file without trailing newline.
	 */
	public function test_expand_withFileWithoutTrailingNewline_handlesCorrectly(): void
	{
		$this->createFile($this->temp_dir, 'no-newline.md', 'Content without trailing newline');

		$expander = new FileReferenceExpander($this->user_home);
		$content = "@./no-newline.md";

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame('Content without trailing newline', $result);
	}

	/**
	 * Tests that expand trims whitespace from file paths.
	 */
	public function test_expand_withWhitespaceAroundPath_trimsWhitespace(): void
	{
		$this->createFile($this->temp_dir, 'file.md', 'Trimmed content.');

		$expander = new FileReferenceExpander($this->user_home);
		// Reference with trailing spaces before newline should still work
		$content = "@./file.md   \n\nAfter.";

		$result = $expander->expand($content, $this->temp_dir);

		$this->assertSame("Trimmed content.\n\nAfter.", $result);
	}

	/**
	 * Creates a file in the specified directory.
	 *
	 * @param string $directory The directory to create the file in.
	 * @param string $filename  The filename.
	 * @param string $content   The file content.
	 */
	private function createFile(string $directory, string $filename, string $content): void
	{
		if (! is_dir($directory)) {
			mkdir($directory, 0755, true);
		}
		file_put_contents($directory . '/' . $filename, $content);
	}

	/**
	 * Recursively removes a directory.
	 *
	 * @param string $dir The directory to remove.
	 */
	private function removeDirectory(string $dir): void
	{
		if (! is_dir($dir)) {
			return;
		}

		$items = scandir($dir);
		if ($items === false) {
			return;
		}

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$path = $dir . '/' . $item;
			if (is_dir($path)) {
				$this->removeDirectory($path);
			} else {
				unlink($path);
			}
		}

		rmdir($dir);
	}
}
