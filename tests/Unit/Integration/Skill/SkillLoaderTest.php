<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Integration\Skill;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Automattic\WpAiAgent\Core\Contracts\MarkdownParserInterface;
use Automattic\WpAiAgent\Core\Exceptions\ParseException;
use Automattic\WpAiAgent\Core\ValueObjects\ParsedMarkdown;
use Automattic\WpAiAgent\Integration\Skill\SkillLoader;

/**
 * Unit tests for SkillLoader.
 *
 * MarkdownParserInterface is mocked so no real filesystem or YAML parsing
 * is needed. All tests exercise the SkillLoader's orchestration logic only.
 *
 * @covers \Automattic\WpAiAgent\Integration\Skill\SkillLoader
 *
 * @since n.e.x.t
 */
final class SkillLoaderTest extends TestCase
{
	/**
	 * The mocked markdown parser.
	 *
	 * @var MarkdownParserInterface&MockObject
	 */
	private MarkdownParserInterface $parser;

	/**
	 * The loader under test.
	 *
	 * @var SkillLoader
	 */
	private SkillLoader $loader;

	/**
	 * Creates a fresh loader with a mock parser before each test.
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->parser = $this->createMock(MarkdownParserInterface::class);
		$this->loader = new SkillLoader($this->parser);
	}

	/**
	 * Tests that load() with a valid file returns a Skill with the correct name,
	 * description, body, and config populated from the parsed frontmatter.
	 */
	public function test_load_withValidFile_returnsSkill(): void
	{
		$frontmatter = [
			'description'          => 'Summarize content',
			'requires_confirmation' => false,
			'parameters'           => [
				'content' => ['type' => 'string', 'required' => true],
			],
		];

		$this->parser
			->expects($this->once())
			->method('parseFile')
			->with('/skills/summarize.md')
			->willReturn(new ParsedMarkdown($frontmatter, 'Please summarize: $content'));

		$skill = $this->loader->load('/skills/summarize.md');

		$this->assertSame('summarize', $skill->getName());
		$this->assertSame('Summarize content', $skill->getDescription());
		$this->assertSame('Please summarize: $content', $skill->getBody());
		$this->assertFalse($skill->getConfig()->requiresConfirmation());
		$this->assertSame('/skills/summarize.md', $skill->getFilePath());
	}

	/**
	 * Tests that when the frontmatter has no 'description' key, the skill
	 * description defaults to "Skill: {name}".
	 */
	public function test_load_withMissingDescription_defaultsToSkillName(): void
	{
		$this->parser
			->method('parseFile')
			->willReturn(new ParsedMarkdown([], 'body text'));

		$skill = $this->loader->load('/skills/myskill.md');

		$this->assertSame('Skill: myskill', $skill->getDescription());
	}

	/**
	 * Tests that load() propagates a ParseException thrown by the parser.
	 */
	public function test_load_withInvalidFile_throwsParseException(): void
	{
		$this->parser
			->method('parseFile')
			->willThrowException(ParseException::invalidFrontmatter('invalid yaml'));

		$this->expectException(ParseException::class);

		$this->loader->load('/skills/broken.md');
	}

	/**
	 * Tests that loadFromMarkdown() uses the provided name (not the filepath)
	 * and correctly populates the Skill body.
	 */
	public function test_loadFromMarkdown_withValidContent_returnsSkillWithProvidedName(): void
	{
		$frontmatter = ['description' => 'Inline skill'];

		$this->parser
			->expects($this->once())
			->method('parse')
			->willReturn(new ParsedMarkdown($frontmatter, 'inline body'));

		$skill = $this->loader->loadFromMarkdown('my-inline', '---\ndescription: Inline skill\n---\ninline body');

		$this->assertSame('my-inline', $skill->getName());
		$this->assertSame('Inline skill', $skill->getDescription());
		$this->assertSame('inline body', $skill->getBody());
		$this->assertNull($skill->getFilePath());
	}
}
