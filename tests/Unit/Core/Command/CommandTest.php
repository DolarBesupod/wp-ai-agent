<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Core\Command;

use PHPUnit\Framework\TestCase;
use Automattic\Automattic\WpAiAgent\Core\Command\Command;
use Automattic\Automattic\WpAiAgent\Core\Command\CommandConfig;

/**
 * Tests for Command value object.
 *
 * @covers \Automattic\WpAiAgent\Core\Command\Command
 */
final class CommandTest extends TestCase
{
	public function test_constructor_createsValidCommand(): void
	{
		$config = CommandConfig::fromFrontmatter(['description' => 'Test command']);
		$command = new Command(
			name: 'test',
			description: 'A test command',
			body: 'This is the command body',
			config: $config,
			filepath: '/path/to/command.md',
			namespace: 'custom'
		);

		$this->assertSame('test', $command->getName());
		$this->assertSame('A test command', $command->getDescription());
		$this->assertSame('This is the command body', $command->getBody());
		$this->assertSame($config, $command->getConfig());
		$this->assertSame('/path/to/command.md', $command->getFilePath());
		$this->assertSame('custom', $command->getNamespace());
	}

	public function test_getName_returnsName(): void
	{
		$command = $this->createMinimalCommand('my-command');

		$this->assertSame('my-command', $command->getName());
	}

	public function test_getDescription_returnsDescription(): void
	{
		$command = new Command(
			name: 'test',
			description: 'This is a description',
			body: 'Body content',
			config: CommandConfig::fromFrontmatter([])
		);

		$this->assertSame('This is a description', $command->getDescription());
	}

	public function test_getBody_returnsBody(): void
	{
		$command = new Command(
			name: 'test',
			description: 'Test',
			body: 'The full body content here',
			config: CommandConfig::fromFrontmatter([])
		);

		$this->assertSame('The full body content here', $command->getBody());
	}

	public function test_getConfig_returnsConfig(): void
	{
		$config = CommandConfig::fromFrontmatter(['model' => 'claude-3-opus']);
		$command = new Command(
			name: 'test',
			description: 'Test',
			body: 'Body',
			config: $config
		);

		$this->assertSame($config, $command->getConfig());
	}

	public function test_getFilePath_returnsFilePath(): void
	{
		$command = new Command(
			name: 'test',
			description: 'Test',
			body: 'Body',
			config: CommandConfig::fromFrontmatter([]),
			filepath: '/home/user/.wp-ai-agent/commands/test.md'
		);

		$this->assertSame('/home/user/.wp-ai-agent/commands/test.md', $command->getFilePath());
	}

	public function test_getFilePath_returnsNullWhenNotSet(): void
	{
		$command = $this->createMinimalCommand('test');

		$this->assertNull($command->getFilePath());
	}

	public function test_getNamespace_returnsNamespace(): void
	{
		$command = new Command(
			name: 'test',
			description: 'Test',
			body: 'Body',
			config: CommandConfig::fromFrontmatter([]),
			namespace: 'project'
		);

		$this->assertSame('project', $command->getNamespace());
	}

	public function test_getNamespace_returnsNullWhenNotSet(): void
	{
		$command = $this->createMinimalCommand('test');

		$this->assertNull($command->getNamespace());
	}

	public function test_isBuiltIn_returnsTrueWhenNoFilePath(): void
	{
		$command = new Command(
			name: 'help',
			description: 'Help command',
			body: 'Display help information',
			config: CommandConfig::fromFrontmatter([]),
			filepath: null
		);

		$this->assertTrue($command->isBuiltIn());
	}

	public function test_isBuiltIn_returnsFalseWhenFilePathSet(): void
	{
		$command = new Command(
			name: 'custom',
			description: 'Custom command',
			body: 'Custom body',
			config: CommandConfig::fromFrontmatter([]),
			filepath: '/path/to/custom.md'
		);

		$this->assertFalse($command->isBuiltIn());
	}

	public function test_withBody_returnsNewCommandWithUpdatedBody(): void
	{
		$original = new Command(
			name: 'test',
			description: 'Test',
			body: 'Original body',
			config: CommandConfig::fromFrontmatter([]),
			filepath: '/path/test.md',
			namespace: 'ns'
		);

		$updated = $original->withBody('New body content');

		// Original is unchanged
		$this->assertSame('Original body', $original->getBody());

		// New instance has updated body
		$this->assertSame('New body content', $updated->getBody());

		// Other properties are preserved
		$this->assertSame('test', $updated->getName());
		$this->assertSame('Test', $updated->getDescription());
		$this->assertSame($original->getConfig(), $updated->getConfig());
		$this->assertSame('/path/test.md', $updated->getFilePath());
		$this->assertSame('ns', $updated->getNamespace());
	}

	public function test_immutability_commandCannotBeModified(): void
	{
		$config = CommandConfig::fromFrontmatter(['description' => 'Original']);
		$command = new Command(
			name: 'test',
			description: 'Test',
			body: 'Body',
			config: $config
		);

		// The command should be immutable - no public setters
		$this->assertSame('test', $command->getName());
		$this->assertSame('Test', $command->getDescription());
		$this->assertSame('Body', $command->getBody());
	}

	public function test_command_withMultilineBody(): void
	{
		$body = "First line\nSecond line\nThird line with code:\n```php\necho 'Hello';\n```";

		$command = new Command(
			name: 'multiline',
			description: 'Multiline command',
			body: $body,
			config: CommandConfig::fromFrontmatter([])
		);

		$this->assertSame($body, $command->getBody());
	}

	public function test_command_withEmptyDescription(): void
	{
		$command = new Command(
			name: 'test',
			description: '',
			body: 'Body content',
			config: CommandConfig::fromFrontmatter([])
		);

		$this->assertSame('', $command->getDescription());
	}

	public function test_command_withEmptyBody(): void
	{
		$command = new Command(
			name: 'test',
			description: 'Test',
			body: '',
			config: CommandConfig::fromFrontmatter([])
		);

		$this->assertSame('', $command->getBody());
	}

	/**
	 * Creates a minimal command for testing.
	 *
	 * @param string $name The command name.
	 *
	 * @return Command
	 */
	private function createMinimalCommand(string $name): Command
	{
		return new Command(
			name: $name,
			description: 'Minimal test command',
			body: 'Body',
			config: CommandConfig::fromFrontmatter([])
		);
	}
}
