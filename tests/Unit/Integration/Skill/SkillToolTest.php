<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Integration\Skill;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Automattic\WpAiAgent\Core\Contracts\BashCommandExpanderInterface;
use Automattic\WpAiAgent\Core\Contracts\FileReferenceExpanderInterface;
use Automattic\WpAiAgent\Core\Skill\Skill;
use Automattic\WpAiAgent\Core\Skill\SkillConfig;
use Automattic\WpAiAgent\Integration\Skill\SkillTool;

/**
 * Unit tests for SkillTool.
 *
 * FileReferenceExpanderInterface and BashCommandExpanderInterface are mocked.
 * By default both expanders return their input unchanged (pass-through).
 *
 * @covers \Automattic\WpAiAgent\Integration\Skill\SkillTool
 *
 * @since n.e.x.t
 */
final class SkillToolTest extends TestCase
{
	/**
	 * @var FileReferenceExpanderInterface&MockObject
	 */
	private FileReferenceExpanderInterface $file_expander;

	/**
	 * @var BashCommandExpanderInterface&MockObject
	 */
	private BashCommandExpanderInterface $bash_expander;

	/**
	 * Sets up pass-through expander mocks before each test.
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->file_expander = $this->createMock(FileReferenceExpanderInterface::class);
		$this->file_expander->method('expand')->willReturnArgument(0);

		$this->bash_expander = $this->createMock(BashCommandExpanderInterface::class);
		$this->bash_expander->method('expand')->willReturnArgument(0);
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Builds a Skill with the given config and body.
	 *
	 * @param SkillConfig $config The skill config.
	 * @param string $body The body template.
	 * @param string $name The skill name.
	 *
	 * @return Skill
	 */
	private function makeSkill(SkillConfig $config, string $body, string $name = 'test_skill'): Skill
	{
		return new Skill($name, 'Test description', $body, $config);
	}

	/**
	 * Builds a SkillTool wrapping the given skill.
	 *
	 * @param Skill $skill The skill to wrap.
	 *
	 * @return SkillTool
	 */
	private function makeTool(Skill $skill): SkillTool
	{
		return new SkillTool($skill, $this->file_expander, $this->bash_expander);
	}

	// -----------------------------------------------------------------------
	// getName / getDescription / requiresConfirmation
	// -----------------------------------------------------------------------

	/**
	 * Tests that getName() returns the skill name.
	 */
	public function test_getName_returnsSkillName(): void
	{
		$config = SkillConfig::fromFrontmatter([]);
		$tool = $this->makeTool($this->makeSkill($config, 'body', 'my_skill'));

		$this->assertSame('my_skill', $tool->getName());
	}

	/**
	 * Tests that requiresConfirmation() reflects the skill config's value.
	 */
	public function test_requiresConfirmation_reflectsConfig(): void
	{
		$config_off = SkillConfig::fromFrontmatter(['requires_confirmation' => false]);
		$tool_off = $this->makeTool($this->makeSkill($config_off, ''));

		$this->assertFalse($tool_off->requiresConfirmation());

		$config_on = SkillConfig::fromFrontmatter(['requires_confirmation' => true]);
		$tool_on = $this->makeTool($this->makeSkill($config_on, ''));

		$this->assertTrue($tool_on->requiresConfirmation());
	}

	// -----------------------------------------------------------------------
	// getParametersSchema
	// -----------------------------------------------------------------------

	/**
	 * Tests that getParametersSchema() builds a valid JSON Schema object
	 * from the skill config's parameter definitions.
	 */
	public function test_getParametersSchema_buildsJsonSchema(): void
	{
		$config = SkillConfig::fromFrontmatter([
			'parameters' => [
				'content' => ['type' => 'string', 'description' => 'Text', 'required' => true],
				'style'   => ['type' => 'string', 'enum' => ['a', 'b']],
			],
		]);

		$tool = $this->makeTool($this->makeSkill($config, 'body'));
		$schema = $tool->getParametersSchema();

		$this->assertSame('object', $schema['type']);
		$this->assertArrayHasKey('content', $schema['properties']);
		$this->assertArrayHasKey('style', $schema['properties']);
		$this->assertSame(['content'], $schema['required']);
		$this->assertSame(['a', 'b'], $schema['properties']['style']['enum']);
	}

	// -----------------------------------------------------------------------
	// execute
	// -----------------------------------------------------------------------

	/**
	 * Tests that execute() substitutes named $param placeholders and returns
	 * the expanded body in a successful ToolResult.
	 */
	public function test_execute_withAllRequiredParams_returnsExpandedBody(): void
	{
		$config = SkillConfig::fromFrontmatter([
			'parameters' => [
				'content' => ['type' => 'string', 'required' => true],
				'style'   => ['type' => 'string', 'default' => 'brief'],
			],
		]);
		$tool = $this->makeTool($this->makeSkill($config, 'Style: $style, Content: $content'));

		$result = $tool->execute(['content' => 'Hello world', 'style' => 'detailed']);

		$this->assertTrue($result->isSuccess());
		$this->assertSame('Style: detailed, Content: Hello world', $result->getOutput());
	}

	/**
	 * Tests that execute() returns a failure ToolResult when a required
	 * parameter is absent from the arguments.
	 */
	public function test_execute_withMissingRequiredParam_returnsFailure(): void
	{
		$config = SkillConfig::fromFrontmatter([
			'parameters' => [
				'content' => ['type' => 'string', 'required' => true],
			],
		]);
		$tool = $this->makeTool($this->makeSkill($config, 'Content: $content'));

		$result = $tool->execute([]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('content', (string) $result->getError());
	}

	/**
	 * Tests that execute() applies the configured default value for an optional
	 * parameter that was not provided.
	 */
	public function test_execute_withOptionalParamDefault_substitutesDefault(): void
	{
		$config = SkillConfig::fromFrontmatter([
			'parameters' => [
				'style' => ['type' => 'string', 'default' => 'brief'],
			],
		]);
		$tool = $this->makeTool($this->makeSkill($config, 'Style: $style'));

		$result = $tool->execute([]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame('Style: brief', $result->getOutput());
	}

	/**
	 * Tests that a FileReferenceExpander exception is captured inline as
	 * an [Error: …] prefix rather than propagating.
	 */
	public function test_execute_withFileExpansionError_capturesErrorInline(): void
	{
		$this->file_expander = $this->createMock(FileReferenceExpanderInterface::class);
		$this->file_expander
			->method('expand')
			->willThrowException(new RuntimeException('file not found'));

		// Also reset bash expander to pass-through for this test.
		$this->bash_expander = $this->createMock(BashCommandExpanderInterface::class);
		$this->bash_expander->method('expand')->willReturnArgument(0);

		$config = SkillConfig::fromFrontmatter([]);
		$tool = new SkillTool(
			$this->makeSkill($config, '@missing-file'),
			$this->file_expander,
			$this->bash_expander
		);

		$result = $tool->execute([]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('[Error:', $result->getOutput());
	}
}
