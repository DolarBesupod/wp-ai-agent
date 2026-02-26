<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Integration\Configuration;

use WpAiAgent\Core\Contracts\MarkdownParserInterface;
use WpAiAgent\Core\Exceptions\ParseException;
use WpAiAgent\Core\ValueObjects\ParsedMarkdown;
use WpAiAgent\Integration\Configuration\MarkdownParser;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MarkdownParser.
 *
 * @covers \WpAiAgent\Integration\Configuration\MarkdownParser
 */
final class MarkdownParserTest extends TestCase
{
	/**
	 * The parser instance under test.
	 *
	 * @var MarkdownParser
	 */
	private MarkdownParser $parser;

	/**
	 * Sets up the test fixture.
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->parser = new MarkdownParser();
	}

	/**
	 * Tests that the parser implements the interface.
	 */
	public function test_implementsInterface(): void
	{
		$this->assertInstanceOf(MarkdownParserInterface::class, $this->parser);
	}

	/**
	 * Tests that parse returns ParsedMarkdown for valid content with frontmatter.
	 */
	public function test_parse_withValidFrontmatter_returnsParsedMarkdown(): void
	{
		$content = <<<'MARKDOWN'
---
name: example-skill
description: An example skill
---

# Skill Content

This is the body of the markdown.
MARKDOWN;

		$result = $this->parser->parse($content);

		$this->assertInstanceOf(ParsedMarkdown::class, $result);
		$this->assertSame('example-skill', $result->get('name'));
		$this->assertSame('An example skill', $result->get('description'));
		$this->assertStringContainsString('# Skill Content', $result->getBody());
		$this->assertStringContainsString('This is the body of the markdown.', $result->getBody());
	}

	/**
	 * Tests that parse handles content without frontmatter.
	 */
	public function test_parse_withoutFrontmatter_returnsEmptyFrontmatter(): void
	{
		$content = <<<'MARKDOWN'
# Just a Heading

This is markdown without frontmatter.
MARKDOWN;

		$result = $this->parser->parse($content);

		$this->assertInstanceOf(ParsedMarkdown::class, $result);
		$this->assertFalse($result->hasFrontmatter());
		$this->assertSame([], $result->getFrontmatter());
		$this->assertStringContainsString('# Just a Heading', $result->getBody());
	}

	/**
	 * Tests that parse handles empty content.
	 */
	public function test_parse_withEmptyContent_returnsEmptyResult(): void
	{
		$result = $this->parser->parse('');

		$this->assertInstanceOf(ParsedMarkdown::class, $result);
		$this->assertFalse($result->hasFrontmatter());
		$this->assertFalse($result->hasBody());
	}

	/**
	 * Tests that parse handles only frontmatter with no body.
	 */
	public function test_parse_withOnlyFrontmatter_returnsEmptyBody(): void
	{
		$content = <<<'MARKDOWN'
---
name: frontmatter-only
---
MARKDOWN;

		$result = $this->parser->parse($content);

		$this->assertSame('frontmatter-only', $result->get('name'));
		$this->assertFalse($result->hasBody());
	}

	/**
	 * Tests that parse handles complex YAML frontmatter.
	 */
	public function test_parse_withComplexYaml_parsesNestedData(): void
	{
		$content = <<<'MARKDOWN'
---
name: complex-skill
allowed_tools:
  - Read
  - Write
  - Bash
config:
  timeout: 30
  retries: 3
---

# Content
MARKDOWN;

		$result = $this->parser->parse($content);

		$this->assertSame('complex-skill', $result->get('name'));
		$this->assertSame(['Read', 'Write', 'Bash'], $result->get('allowed_tools'));
		$this->assertSame(['timeout' => 30, 'retries' => 3], $result->get('config'));
	}

	/**
	 * Tests that parse throws ParseException for invalid YAML.
	 */
	public function test_parse_withInvalidYaml_throwsParseException(): void
	{
		$content = <<<'MARKDOWN'
---
name: test
  invalid: indentation
items:
  - item1
 - invalid
---

# Content
MARKDOWN;

		$this->expectException(ParseException::class);
		$this->expectExceptionMessage('frontmatter');

		$this->parser->parse($content);
	}

	/**
	 * Tests that parse handles frontmatter with only opening delimiter.
	 */
	public function test_parse_withOnlyOpeningDelimiter_treatsAsContent(): void
	{
		$content = <<<'MARKDOWN'
---
This is not frontmatter
It's just content starting with dashes
MARKDOWN;

		$result = $this->parser->parse($content);

		// Without a closing ---, this should be treated as regular content
		$this->assertFalse($result->hasFrontmatter());
		$this->assertStringContainsString('This is not frontmatter', $result->getBody());
	}

	/**
	 * Tests that parse handles empty frontmatter block.
	 */
	public function test_parse_withEmptyFrontmatterBlock_returnsEmptyFrontmatter(): void
	{
		$content = <<<'MARKDOWN'
---
---

# Content here
MARKDOWN;

		$result = $this->parser->parse($content);

		$this->assertFalse($result->hasFrontmatter());
		$this->assertStringContainsString('# Content here', $result->getBody());
	}

	/**
	 * Tests that parse handles Windows line endings.
	 */
	public function test_parse_withWindowsLineEndings_parsesCorrectly(): void
	{
		$content = "---\r\nname: windows-test\r\n---\r\n\r\n# Content";

		$result = $this->parser->parse($content);

		$this->assertSame('windows-test', $result->get('name'));
		$this->assertStringContainsString('# Content', $result->getBody());
	}

	/**
	 * Tests that parse handles frontmatter with special characters.
	 */
	public function test_parse_withSpecialCharacters_parsesCorrectly(): void
	{
		$content = <<<'MARKDOWN'
---
name: "skill with spaces"
description: "Contains: colons and special chars!"
path: "/some/path/to/file"
---

# Content
MARKDOWN;

		$result = $this->parser->parse($content);

		$this->assertSame('skill with spaces', $result->get('name'));
		$this->assertSame('Contains: colons and special chars!', $result->get('description'));
		$this->assertSame('/some/path/to/file', $result->get('path'));
	}

	/**
	 * Tests that parse handles multi-line strings in YAML.
	 */
	public function test_parse_withMultiLineYamlString_parsesCorrectly(): void
	{
		$content = <<<'MARKDOWN'
---
name: multiline-test
description: |
  This is a multi-line
  description that spans
  several lines.
---

# Content
MARKDOWN;

		$result = $this->parser->parse($content);

		$description = $result->get('description');
		$this->assertIsString($description);
		$this->assertStringContainsString('multi-line', $description);
		$this->assertStringContainsString('several lines', $description);
	}

	/**
	 * Tests that parse preserves body content exactly.
	 */
	public function test_parse_preservesBodyFormatting(): void
	{
		$content = <<<'MARKDOWN'
---
name: test
---

# Heading

Some paragraph.

```php
<?php
echo "code block";
```

- List item 1
- List item 2
MARKDOWN;

		$result = $this->parser->parse($content);

		$body = $result->getBody();
		$this->assertStringContainsString('# Heading', $body);
		$this->assertStringContainsString('```php', $body);
		$this->assertStringContainsString('echo "code block"', $body);
		$this->assertStringContainsString('- List item 1', $body);
	}

	/**
	 * Tests that parse handles boolean values in YAML.
	 */
	public function test_parse_withBooleanValues_parsesCorrectly(): void
	{
		$content = <<<'MARKDOWN'
---
enabled: true
disabled: false
---

# Content
MARKDOWN;

		$result = $this->parser->parse($content);

		$this->assertTrue($result->get('enabled'));
		$this->assertFalse($result->get('disabled'));
	}

	/**
	 * Tests that parse handles numeric values in YAML.
	 */
	public function test_parse_withNumericValues_parsesCorrectly(): void
	{
		$content = <<<'MARKDOWN'
---
count: 42
price: 19.99
---

# Content
MARKDOWN;

		$result = $this->parser->parse($content);

		$this->assertSame(42, $result->get('count'));
		$this->assertSame(19.99, $result->get('price'));
	}

	/**
	 * Tests that parse trims leading/trailing whitespace from body.
	 */
	public function test_parse_trimsBodyWhitespace(): void
	{
		$content = <<<'MARKDOWN'
---
name: test
---


# Content


MARKDOWN;

		$result = $this->parser->parse($content);

		$body = $result->getBody();
		$this->assertSame('# Content', $body);
	}

	/**
	 * Tests that parse handles --- within body content correctly.
	 */
	public function test_parse_withDashesInBody_parsesCorrectly(): void
	{
		$content = <<<'MARKDOWN'
---
name: test
---

# Content

---

This is a horizontal rule in markdown.

---

More content after the rule.
MARKDOWN;

		$result = $this->parser->parse($content);

		$this->assertSame('test', $result->get('name'));
		$body = $result->getBody();
		$this->assertStringContainsString('horizontal rule', $body);
		$this->assertStringContainsString('More content after the rule', $body);
	}

	/**
	 * Tests parseFile reads and parses a file.
	 */
	public function test_parseFile_withValidFile_returnsParsedMarkdown(): void
	{
		$temp_dir = sys_get_temp_dir() . '/markdown_parser_test_' . uniqid();
		mkdir($temp_dir, 0755, true);

		$file_path = $temp_dir . '/test.md';
		$content = <<<'MARKDOWN'
---
name: file-test
---

# File Content
MARKDOWN;
		file_put_contents($file_path, $content);

		try {
			$result = $this->parser->parseFile($file_path);

			$this->assertSame('file-test', $result->get('name'));
			$this->assertStringContainsString('# File Content', $result->getBody());
		} finally {
			unlink($file_path);
			rmdir($temp_dir);
		}
	}

	/**
	 * Tests parseFile throws ParseException for non-existent file.
	 */
	public function test_parseFile_withNonExistentFile_throwsParseException(): void
	{
		$this->expectException(ParseException::class);
		$this->expectExceptionMessage('not found');

		$this->parser->parseFile('/non/existent/file.md');
	}

	/**
	 * Tests parseFile throws ParseException for unreadable file.
	 */
	public function test_parseFile_withUnreadableFile_throwsParseException(): void
	{
		$temp_dir = sys_get_temp_dir() . '/markdown_parser_test_' . uniqid();
		mkdir($temp_dir, 0755, true);

		$file_path = $temp_dir . '/unreadable.md';
		file_put_contents($file_path, 'content');
		chmod($file_path, 0000);

		try {
			$this->expectException(ParseException::class);
			$this->expectExceptionMessage('read');

			$this->parser->parseFile($file_path);
		} finally {
			chmod($file_path, 0644);
			unlink($file_path);
			rmdir($temp_dir);
		}
	}
}
