<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Integration\Skill;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\WpOptionsStore;
use Automattic\Automattic\WpAiAgent\Core\Contracts\BashCommandExpanderInterface;
use Automattic\Automattic\WpAiAgent\Core\Contracts\FileReferenceExpanderInterface;
use Automattic\Automattic\WpAiAgent\Core\Contracts\ToolRegistryInterface;
use Automattic\Automattic\WpAiAgent\Core\Skill\Skill;
use Automattic\Automattic\WpAiAgent\Core\Skill\SkillConfig;
use Automattic\Automattic\WpAiAgent\Integration\Skill\SkillLoader;
use Automattic\Automattic\WpAiAgent\Integration\Skill\SkillRegistry;
use Automattic\Automattic\WpAiAgent\Integration\WpCli\WpOptionsSkillRepository;

/**
 * Unit tests for SkillRegistry.
 *
 * Uses WpOptionsStore for in-memory option simulation and temp directories
 * for bundled skill file tests. WP_CLI stubs record all static calls.
 *
 * @covers \Automattic\WpAiAgent\Integration\Skill\SkillRegistry
 *
 * @since n.e.x.t
 */
final class SkillRegistryTest extends TestCase
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
	 * Temp directories created during tests, cleaned up in tearDown().
	 *
	 * @var string[]
	 */
	private array $temp_dirs = [];

	/**
	 * Resets option store and WP_CLI calls, sets up pass-through expanders.
	 */
	protected function setUp(): void
	{
		parent::setUp();

		WpOptionsStore::reset();
		\WP_CLI::$calls = [];

		$this->file_expander = $this->createMock(FileReferenceExpanderInterface::class);
		$this->file_expander->method('expand')->willReturnArgument(0);

		$this->bash_expander = $this->createMock(BashCommandExpanderInterface::class);
		$this->bash_expander->method('expand')->willReturnArgument(0);
	}

	/**
	 * Removes all temporary directories created during tests.
	 */
	protected function tearDown(): void
	{
		foreach ($this->temp_dirs as $dir) {
			if (is_dir($dir)) {
				array_map('unlink', glob($dir . '/*') ?: []);
				rmdir($dir);
			}
		}

		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Creates a temporary skills directory with an optional .md file inside.
	 *
	 * @param string|null $skillName When provided, creates {skillName}.md with frontmatter.
	 *
	 * @return string The absolute path to the temp directory.
	 */
	private function makeTempSkillsDir(?string $skillName = null): string
	{
		$dir = sys_get_temp_dir() . '/skill_registry_test_' . uniqid();
		mkdir($dir, 0755, true);
		$this->temp_dirs[] = $dir;

		if (null !== $skillName) {
			$lines = [
				'---',
				"description: Bundled {$skillName} skill",
				'requires_confirmation: false',
				'---',
				"Body for {$skillName}",
			];
			file_put_contents($dir . '/' . $skillName . '.md', implode("\n", $lines));
		}

		return $dir;
	}

	/**
	 * Builds a concrete SkillLoader backed by the real MarkdownParser.
	 *
	 * @return SkillLoader
	 */
	private function makeLoader(): SkillLoader
	{
		return new SkillLoader(new \Automattic\WpAiAgent\Integration\Configuration\MarkdownParser());
	}

	/**
	 * Saves a skill to WpOptionsStore via the repository so the registry can find it.
	 *
	 * @param WpOptionsSkillRepository $repo The repository to save into.
	 * @param string                   $name The skill name.
	 *
	 * @return void
	 */
	private function saveSkillToRepo(WpOptionsSkillRepository $repo, string $name): void
	{
		$config = SkillConfig::fromFrontmatter(['requires_confirmation' => false]);
		$skill = new Skill($name, "Test {$name}", "body_{$name}", $config);
		$repo->save($skill);
	}

	/**
	 * Builds a SkillRegistry wired with real loader and repository.
	 *
	 * @param string $bundled_dir The bundled skills directory path.
	 *
	 * @return array{SkillRegistry, WpOptionsSkillRepository}
	 */
	private function makeRegistry(string $bundled_dir = ''): array
	{
		$repo = new WpOptionsSkillRepository();
		$loader = $this->makeLoader();

		$registry = new SkillRegistry(
			$repo,
			$loader,
			$this->file_expander,
			$this->bash_expander,
			$bundled_dir
		);

		return [$registry, $repo];
	}

	// -----------------------------------------------------------------------
	// Registers from options when index exists
	// -----------------------------------------------------------------------

	/**
	 * Tests that discoverAndRegister() loads skills from options and registers them
	 * as tools when the wp_ai_agent_skills index exists.
	 */
	public function test_discoverAndRegister_registersSkillsFromOptions(): void
	{
		[$registry, $repo] = $this->makeRegistry();

		$this->saveSkillToRepo($repo, 'summarize');
		$this->saveSkillToRepo($repo, 'translate');

		$tool_registry = $this->createMock(ToolRegistryInterface::class);
		$tool_registry->method('has')->willReturn(false);
		$tool_registry->expects($this->exactly(2))->method('register');

		$count = $registry->discoverAndRegister($tool_registry);

		$this->assertSame(2, $count);
	}

	// -----------------------------------------------------------------------
	// Falls back to bundled when index is null
	// -----------------------------------------------------------------------

	/**
	 * Tests that discoverAndRegister() falls back to the bundled skills directory
	 * when the wp_ai_agent_skills index option has never been set (null).
	 */
	public function test_discoverAndRegister_withNullIndex_fallsBackToBundled(): void
	{
		// Do NOT save any skills to options — index option stays null.
		$bundled_dir = $this->makeTempSkillsDir('summarize');
		[$registry] = $this->makeRegistry($bundled_dir);

		$tool_registry = $this->createMock(ToolRegistryInterface::class);
		$tool_registry->method('has')->willReturn(false);
		$tool_registry->expects($this->once())->method('register');

		$count = $registry->discoverAndRegister($tool_registry);

		$this->assertSame(1, $count);
	}

	/**
	 * Tests that discoverAndRegister() does NOT fall back to bundled skills when
	 * the index is an empty array (user intentionally cleared all skills).
	 */
	public function test_discoverAndRegister_withEmptyIndex_doesNotFallBackToBundled(): void
	{
		// Explicitly set index to empty array to signal "intentionally empty".
		WpOptionsStore::set('wp_ai_agent_skills', json_encode([]));

		$bundled_dir = $this->makeTempSkillsDir('summarize');
		[$registry] = $this->makeRegistry($bundled_dir);

		$tool_registry = $this->createMock(ToolRegistryInterface::class);
		$tool_registry->expects($this->never())->method('register');

		$count = $registry->discoverAndRegister($tool_registry);

		$this->assertSame(0, $count);
	}

	// -----------------------------------------------------------------------
	// Collision guard
	// -----------------------------------------------------------------------

	/**
	 * Tests that a skill whose name collides with an already-registered tool
	 * is skipped and a WP_CLI::warning() is emitted.
	 */
	public function test_discoverAndRegister_collidingNameIsSkipped(): void
	{
		[$registry, $repo] = $this->makeRegistry();
		$this->saveSkillToRepo($repo, 'bash');

		// Simulate that 'bash' is already registered as a built-in tool.
		$tool_registry = $this->createMock(ToolRegistryInterface::class);
		$tool_registry->method('has')->with('bash')->willReturn(true);
		$tool_registry->expects($this->never())->method('register');

		$count = $registry->discoverAndRegister($tool_registry);

		$this->assertSame(0, $count);

		$warning_calls = array_filter(
			\WP_CLI::$calls,
			static fn (array $c): bool => $c[0] === 'warning'
		);
		$this->assertNotEmpty($warning_calls);
	}

	// -----------------------------------------------------------------------
	// Missing directory
	// -----------------------------------------------------------------------

	/**
	 * Tests that discoverAndRegister() returns 0 without error when the bundled
	 * skills directory does not exist (and index is null — i.e., first run).
	 */
	public function test_discoverAndRegister_missingDirIsIgnored(): void
	{
		// Index option null (first run), but bundled dir does not exist.
		[$registry] = $this->makeRegistry('/nonexistent/path/skills');

		$tool_registry = $this->createMock(ToolRegistryInterface::class);
		$tool_registry->expects($this->never())->method('register');

		$count = $registry->discoverAndRegister($tool_registry);

		$this->assertSame(0, $count);
	}

	// -----------------------------------------------------------------------
	// Startup log
	// -----------------------------------------------------------------------

	/**
	 * Tests that discoverAndRegister() always emits a WP_CLI::log() call with
	 * the "[Skills] Discovered N skill(s)" message.
	 */
	public function test_discoverAndRegister_alwaysLogsDiscoveredCount(): void
	{
		[$registry] = $this->makeRegistry('/nonexistent/path');

		$tool_registry = $this->createMock(ToolRegistryInterface::class);
		$tool_registry->method('has')->willReturn(false);

		$registry->discoverAndRegister($tool_registry);

		$log_calls = array_filter(
			\WP_CLI::$calls,
			static fn (array $c): bool => $c[0] === 'log'
		);

		$this->assertNotEmpty($log_calls);

		$first_log = array_values($log_calls)[0];
		$this->assertStringContainsString('[Skills]', $first_log[1]);
		$this->assertStringContainsString('skill(s)', $first_log[1]);
	}
}
