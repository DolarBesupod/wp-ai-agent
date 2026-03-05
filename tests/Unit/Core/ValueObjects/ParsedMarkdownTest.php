<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Core\ValueObjects;

use PHPUnit\Framework\TestCase;
use Automattic\Automattic\WpAiAgent\Core\ValueObjects\ParsedMarkdown;

/**
 * Tests for ParsedMarkdown value object.
 *
 * @covers \Automattic\WpAiAgent\Core\ValueObjects\ParsedMarkdown
 */
final class ParsedMarkdownTest extends TestCase
{
	public function test_constructor_acceptsValidData(): void
	{
		$frontmatter = ['name' => 'test', 'description' => 'Test description'];
		$body = '# Test Content';

		$parsed = new ParsedMarkdown($frontmatter, $body);

		$this->assertSame($frontmatter, $parsed->getFrontmatter());
		$this->assertSame($body, $parsed->getBody());
	}

	public function test_constructor_acceptsEmptyFrontmatter(): void
	{
		$parsed = new ParsedMarkdown([], '# Content');

		$this->assertSame([], $parsed->getFrontmatter());
		$this->assertSame('# Content', $parsed->getBody());
	}

	public function test_constructor_acceptsEmptyBody(): void
	{
		$frontmatter = ['name' => 'test'];

		$parsed = new ParsedMarkdown($frontmatter, '');

		$this->assertSame('', $parsed->getBody());
	}

	public function test_hasFrontmatter_returnsTrueWhenFrontmatterExists(): void
	{
		$parsed = new ParsedMarkdown(['name' => 'test'], '# Content');

		$this->assertTrue($parsed->hasFrontmatter());
	}

	public function test_hasFrontmatter_returnsFalseWhenEmpty(): void
	{
		$parsed = new ParsedMarkdown([], '# Content');

		$this->assertFalse($parsed->hasFrontmatter());
	}

	public function test_hasBody_returnsTrueWhenBodyExists(): void
	{
		$parsed = new ParsedMarkdown([], '# Content');

		$this->assertTrue($parsed->hasBody());
	}

	public function test_hasBody_returnsFalseWhenEmpty(): void
	{
		$parsed = new ParsedMarkdown(['name' => 'test'], '');

		$this->assertFalse($parsed->hasBody());
	}

	public function test_hasBody_returnsFalseWhenWhitespaceOnly(): void
	{
		$parsed = new ParsedMarkdown(['name' => 'test'], '   ');

		$this->assertFalse($parsed->hasBody());
	}

	public function test_get_returnsValueForExistingKey(): void
	{
		$parsed = new ParsedMarkdown(['name' => 'my-skill', 'version' => 1], '');

		$this->assertSame('my-skill', $parsed->get('name'));
		$this->assertSame(1, $parsed->get('version'));
	}

	public function test_get_returnsNullForMissingKey(): void
	{
		$parsed = new ParsedMarkdown(['name' => 'test'], '');

		$this->assertNull($parsed->get('missing'));
	}

	public function test_get_returnsDefaultForMissingKey(): void
	{
		$parsed = new ParsedMarkdown(['name' => 'test'], '');

		$this->assertSame('default-value', $parsed->get('missing', 'default-value'));
	}

	public function test_has_returnsTrueForExistingKey(): void
	{
		$parsed = new ParsedMarkdown(['name' => 'test'], '');

		$this->assertTrue($parsed->has('name'));
	}

	public function test_has_returnsFalseForMissingKey(): void
	{
		$parsed = new ParsedMarkdown(['name' => 'test'], '');

		$this->assertFalse($parsed->has('missing'));
	}

	public function test_immutability_frontmatterCannotBeModifiedExternally(): void
	{
		$frontmatter = ['name' => 'original'];
		$parsed = new ParsedMarkdown($frontmatter, '');

		// Attempt to modify the original array
		$frontmatter['name'] = 'modified';

		// The value object should retain the original value
		$this->assertSame('original', $parsed->get('name'));
	}

	public function test_withComplexFrontmatter(): void
	{
		$frontmatter = [
			'name' => 'complex-skill',
			'allowed_tools' => ['Read', 'Write', 'Bash'],
			'config' => [
				'timeout' => 30,
				'retries' => 3,
			],
		];

		$parsed = new ParsedMarkdown($frontmatter, '# Complex');

		$this->assertSame(['Read', 'Write', 'Bash'], $parsed->get('allowed_tools'));
		$this->assertSame(['timeout' => 30, 'retries' => 3], $parsed->get('config'));
	}
}
