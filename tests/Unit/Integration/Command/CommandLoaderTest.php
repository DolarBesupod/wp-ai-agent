<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Integration\Command;

use Automattic\WpAiAgent\Core\Command\Command;
use Automattic\WpAiAgent\Core\Contracts\CommandLoaderInterface;
use Automattic\WpAiAgent\Core\Contracts\MarkdownParserInterface;
use Automattic\WpAiAgent\Core\Exceptions\ParseException;
use Automattic\WpAiAgent\Core\ValueObjects\ParsedMarkdown;
use Automattic\WpAiAgent\Integration\Command\CommandLoader;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CommandLoader.
 *
 * @covers \Automattic\WpAiAgent\Integration\Command\CommandLoader
 */
final class CommandLoaderTest extends TestCase
{
	/**
	 * The markdown parser mock.
	 *
	 * @var MarkdownParserInterface&MockObject
	 */
	private MarkdownParserInterface $markdown_parser;

	/**
	 * Sets up the test fixture.
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->markdown_parser = $this->createMock(MarkdownParserInterface::class);
	}

	/**
	 * Tests that constructor creates instance correctly.
	 */
	public function test_constructor_createsInstance(): void
	{
		$loader = new CommandLoader($this->markdown_parser);

		$this->assertInstanceOf(CommandLoaderInterface::class, $loader);
	}

	/**
	 * Tests that load extracts command name from filename.
	 */
	public function test_load_withSimpleFilename_extractsNameFromFilename(): void
	{
		$filepath = '/project/.wp-ai-agent/commands/review.md';
		$parsed = new ParsedMarkdown(
			['description' => 'Review code'],
			'Review the code for quality issues.'
		);

		$this->markdown_parser
			->expects($this->once())
			->method('parseFile')
			->with($filepath)
			->willReturn($parsed);

		$loader = new CommandLoader($this->markdown_parser);
		$command = $loader->load($filepath);

		$this->assertSame('review', $command->getName());
	}

	/**
	 * Tests that load sets description from frontmatter.
	 */
	public function test_load_withDescriptionInFrontmatter_setsDescription(): void
	{
		$filepath = '/project/.wp-ai-agent/commands/commit.md';
		$parsed = new ParsedMarkdown(
			['description' => 'Create a git commit'],
			'Commit changes with a message.'
		);

		$this->markdown_parser
			->method('parseFile')
			->willReturn($parsed);

		$loader = new CommandLoader($this->markdown_parser);
		$command = $loader->load($filepath);

		$this->assertSame('Create a git commit', $command->getDescription());
	}

	/**
	 * Tests that load uses empty string when no description in frontmatter.
	 */
	public function test_load_withNoDescription_usesEmptyString(): void
	{
		$filepath = '/project/.wp-ai-agent/commands/test.md';
		$parsed = new ParsedMarkdown([], 'Test content');

		$this->markdown_parser
			->method('parseFile')
			->willReturn($parsed);

		$loader = new CommandLoader($this->markdown_parser);
		$command = $loader->load($filepath);

		$this->assertSame('', $command->getDescription());
	}

	/**
	 * Tests that load sets body from parsed content.
	 */
	public function test_load_setsBodyFromParsedContent(): void
	{
		$filepath = '/project/.wp-ai-agent/commands/debug.md';
		$body = 'Debug the current issue step by step.';
		$parsed = new ParsedMarkdown(['description' => 'Debug tool'], $body);

		$this->markdown_parser
			->method('parseFile')
			->willReturn($parsed);

		$loader = new CommandLoader($this->markdown_parser);
		$command = $loader->load($filepath);

		$this->assertSame($body, $command->getBody());
	}

	/**
	 * Tests that load creates CommandConfig from frontmatter.
	 */
	public function test_load_createsCommandConfigFromFrontmatter(): void
	{
		$filepath = '/project/.wp-ai-agent/commands/analyze.md';
		$frontmatter = [
			'description' => 'Analyze code',
			'allowed_tools' => ['read_file', 'grep'],
			'model' => 'claude-3-opus',
		];
		$parsed = new ParsedMarkdown($frontmatter, 'Analyze the code.');

		$this->markdown_parser
			->method('parseFile')
			->willReturn($parsed);

		$loader = new CommandLoader($this->markdown_parser);
		$command = $loader->load($filepath);

		$config = $command->getConfig();
		$this->assertSame(['read_file', 'grep'], $config->getAllowedTools());
		$this->assertSame('claude-3-opus', $config->getModel());
	}

	/**
	 * Tests that load sets filepath on command.
	 */
	public function test_load_setsFilepath(): void
	{
		$filepath = '/project/.wp-ai-agent/commands/custom.md';
		$parsed = new ParsedMarkdown([], 'Custom command');

		$this->markdown_parser
			->method('parseFile')
			->willReturn($parsed);

		$loader = new CommandLoader($this->markdown_parser);
		$command = $loader->load($filepath);

		$this->assertSame($filepath, $command->getFilePath());
	}

	/**
	 * Tests that load extracts namespace from parent directory.
	 */
	public function test_load_withNamespacedPath_extractsNamespace(): void
	{
		$filepath = '/project/.wp-ai-agent/commands/frontend/review.md';
		$parsed = new ParsedMarkdown(['description' => 'Frontend review'], 'Review frontend code.');

		$this->markdown_parser
			->method('parseFile')
			->willReturn($parsed);

		$loader = new CommandLoader($this->markdown_parser);
		$command = $loader->load($filepath);

		$this->assertSame('frontend', $command->getNamespace());
		$this->assertSame('review', $command->getName());
	}

	/**
	 * Tests that load sets null namespace for root-level commands.
	 */
	public function test_load_withRootLevelCommand_setsNullNamespace(): void
	{
		$filepath = '/project/.wp-ai-agent/commands/simple.md';
		$parsed = new ParsedMarkdown([], 'Simple command');

		$this->markdown_parser
			->method('parseFile')
			->willReturn($parsed);

		$loader = new CommandLoader($this->markdown_parser);
		$command = $loader->load($filepath);

		$this->assertNull($command->getNamespace());
	}

	/**
	 * Tests that load handles nested namespace directories.
	 */
	public function test_load_withNestedNamespace_extractsFullNamespace(): void
	{
		$filepath = '/project/.wp-ai-agent/commands/frontend/components/button.md';
		$parsed = new ParsedMarkdown([], 'Button component review');

		$this->markdown_parser
			->method('parseFile')
			->willReturn($parsed);

		$loader = new CommandLoader($this->markdown_parser);
		$command = $loader->load($filepath);

		$this->assertSame('frontend/components', $command->getNamespace());
		$this->assertSame('button', $command->getName());
	}

	/**
	 * Tests that load handles filename with dots correctly.
	 */
	public function test_load_withDotsInFilename_extractsNameCorrectly(): void
	{
		$filepath = '/project/.wp-ai-agent/commands/code.review.md';
		$parsed = new ParsedMarkdown([], 'Code review');

		$this->markdown_parser
			->method('parseFile')
			->willReturn($parsed);

		$loader = new CommandLoader($this->markdown_parser);
		$command = $loader->load($filepath);

		$this->assertSame('code.review', $command->getName());
	}

	/**
	 * Tests that load throws ParseException when file cannot be parsed.
	 */
	public function test_load_withInvalidFile_throwsParseException(): void
	{
		$filepath = '/project/.wp-ai-agent/commands/invalid.md';

		$this->markdown_parser
			->method('parseFile')
			->willThrowException(ParseException::fileNotFound($filepath));

		$loader = new CommandLoader($this->markdown_parser);

		$this->expectException(ParseException::class);
		$loader->load($filepath);
	}

	/**
	 * Tests that load throws RuntimeException when file cannot be read.
	 */
	public function test_load_withUnreadableFile_throwsRuntimeException(): void
	{
		$filepath = '/project/.wp-ai-agent/commands/unreadable.md';

		$this->markdown_parser
			->method('parseFile')
			->willThrowException(new \RuntimeException('Cannot read file'));

		$loader = new CommandLoader($this->markdown_parser);

		$this->expectException(\RuntimeException::class);
		$loader->load($filepath);
	}

	/**
	 * Tests that loadFromContent creates command with given name.
	 */
	public function test_loadFromContent_usesProvidedName(): void
	{
		$name = 'custom-cmd';
		$content = "---\ndescription: Custom command\n---\nDo something custom.";
		$parsed = new ParsedMarkdown(['description' => 'Custom command'], 'Do something custom.');

		$this->markdown_parser
			->expects($this->once())
			->method('parse')
			->with($content)
			->willReturn($parsed);

		$loader = new CommandLoader($this->markdown_parser);
		$command = $loader->loadFromContent($name, $content);

		$this->assertSame($name, $command->getName());
	}

	/**
	 * Tests that loadFromContent sets body from parsed content.
	 */
	public function test_loadFromContent_setsBodyFromParsedContent(): void
	{
		$name = 'test-cmd';
		$content = 'Test content without frontmatter';
		$parsed = new ParsedMarkdown([], 'Test content without frontmatter');

		$this->markdown_parser
			->method('parse')
			->willReturn($parsed);

		$loader = new CommandLoader($this->markdown_parser);
		$command = $loader->loadFromContent($name, $content);

		$this->assertSame('Test content without frontmatter', $command->getBody());
	}

	/**
	 * Tests that loadFromContent sets null filepath (not from file).
	 */
	public function test_loadFromContent_setsNullFilepath(): void
	{
		$name = 'inline-cmd';
		$content = 'Inline command content';
		$parsed = new ParsedMarkdown([], 'Inline command content');

		$this->markdown_parser
			->method('parse')
			->willReturn($parsed);

		$loader = new CommandLoader($this->markdown_parser);
		$command = $loader->loadFromContent($name, $content);

		$this->assertNull($command->getFilePath());
	}

	/**
	 * Tests that loadFromContent sets null namespace.
	 */
	public function test_loadFromContent_setsNullNamespace(): void
	{
		$name = 'inline-cmd';
		$content = 'Inline command content';
		$parsed = new ParsedMarkdown([], 'Inline command content');

		$this->markdown_parser
			->method('parse')
			->willReturn($parsed);

		$loader = new CommandLoader($this->markdown_parser);
		$command = $loader->loadFromContent($name, $content);

		$this->assertNull($command->getNamespace());
	}

	/**
	 * Tests that loadFromContent creates CommandConfig from frontmatter.
	 */
	public function test_loadFromContent_createsCommandConfigFromFrontmatter(): void
	{
		$name = 'configured-cmd';
		$content = "---\nargument_hint: <file>\n---\nProcess file";
		$parsed = new ParsedMarkdown(['argument_hint' => '<file>'], 'Process file');

		$this->markdown_parser
			->method('parse')
			->willReturn($parsed);

		$loader = new CommandLoader($this->markdown_parser);
		$command = $loader->loadFromContent($name, $content);

		$config = $command->getConfig();
		$this->assertSame('<file>', $config->getArgumentHint());
	}

	/**
	 * Tests that loadFromContent throws ParseException for invalid content.
	 */
	public function test_loadFromContent_withInvalidYaml_throwsParseException(): void
	{
		$name = 'bad-cmd';
		$content = "---\ninvalid: yaml: content:\n---\nBody";

		$this->markdown_parser
			->method('parse')
			->willThrowException(ParseException::invalidFrontmatter('Invalid YAML'));

		$loader = new CommandLoader($this->markdown_parser);

		$this->expectException(ParseException::class);
		$loader->loadFromContent($name, $content);
	}

	/**
	 * Tests that loaded command returns correct isBuiltIn value.
	 */
	public function test_load_commandIsNotBuiltIn(): void
	{
		$filepath = '/project/.wp-ai-agent/commands/loaded.md';
		$parsed = new ParsedMarkdown([], 'Loaded command');

		$this->markdown_parser
			->method('parseFile')
			->willReturn($parsed);

		$loader = new CommandLoader($this->markdown_parser);
		$command = $loader->load($filepath);

		$this->assertFalse($command->isBuiltIn());
	}

	/**
	 * Tests that loadFromContent command is considered built-in (no filepath).
	 */
	public function test_loadFromContent_commandIsBuiltIn(): void
	{
		$name = 'builtin-cmd';
		$content = 'Built-in command content';
		$parsed = new ParsedMarkdown([], 'Built-in command content');

		$this->markdown_parser
			->method('parse')
			->willReturn($parsed);

		$loader = new CommandLoader($this->markdown_parser);
		$command = $loader->loadFromContent($name, $content);

		$this->assertTrue($command->isBuiltIn());
	}
}
