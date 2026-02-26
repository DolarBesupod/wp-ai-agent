<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Integration\Skill;

use PHPUnit\Framework\TestCase;
use Tests\Stubs\WpOptionsStore;
use WpAiAgent\Core\Contracts\SkillRepositoryInterface;
use WpAiAgent\Core\Exceptions\SkillNotFoundException;
use WpAiAgent\Core\Skill\Skill;
use WpAiAgent\Core\Skill\SkillConfig;
use WpAiAgent\Integration\WpCli\WpOptionsSkillRepository;

/**
 * Unit tests for WpOptionsSkillRepository.
 *
 * WordPress functions (get_option, update_option, delete_option, wp_json_encode)
 * are provided by tests/Stubs/WpFunctionsStub.php, loaded by tests/bootstrap.php.
 * WpOptionsStore::reset() ensures complete isolation between test cases.
 *
 * @covers \WpAiAgent\Integration\WpCli\WpOptionsSkillRepository
 *
 * @since n.e.x.t
 */
final class WpOptionsSkillRepositoryTest extends TestCase
{
	/**
	 * The repository under test.
	 *
	 * @var WpOptionsSkillRepository
	 */
	private WpOptionsSkillRepository $repository;

	/**
	 * Resets the in-memory option store and creates a fresh repository.
	 */
	protected function setUp(): void
	{
		parent::setUp();

		WpOptionsStore::reset();
		$this->repository = new WpOptionsSkillRepository();
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Creates a minimal test skill with a known name.
	 *
	 * @param string $name The skill name.
	 *
	 * @return Skill
	 */
	private function makeSkill(string $name): Skill
	{
		$config = SkillConfig::fromFrontmatter([
			'requires_confirmation' => false,
			'parameters'            => [
				'content' => ['type' => 'string', 'required' => true],
			],
		]);

		return new Skill($name, 'Test skill', 'body: $content', $config);
	}

	// -----------------------------------------------------------------------
	// save()
	// -----------------------------------------------------------------------

	/**
	 * Tests that save() stores the skill JSON with autoload=false under the
	 * expected option key.
	 */
	public function test_save_storesSkillOptionWithAutoloadFalse(): void
	{
		$this->repository->save($this->makeSkill('summarize'));

		$stored = WpOptionsStore::get('wp_ai_agent_skill_summarize', false);

		$this->assertIsString($stored);
		$data = json_decode($stored, true);
		$this->assertSame('summarize', $data['name']);
		$this->assertSame('Test skill', $data['description']);
	}

	/**
	 * Tests that save() adds the skill name to the index option.
	 */
	public function test_save_addsNameToIndex(): void
	{
		$this->repository->save($this->makeSkill('summarize'));

		$names = $this->repository->listNames();

		$this->assertContains('summarize', $names);
	}

	/**
	 * Tests that saving the same skill twice results in the name appearing
	 * only once in the index.
	 */
	public function test_save_twice_nameAppearsOnceInIndex(): void
	{
		$this->repository->save($this->makeSkill('dup'));
		$this->repository->save($this->makeSkill('dup'));

		$names = $this->repository->listNames();
		$this->assertCount(1, array_filter($names, static fn (string $n): bool => $n === 'dup'));
	}

	// -----------------------------------------------------------------------
	// load()
	// -----------------------------------------------------------------------

	/**
	 * Tests that load() returns a Skill with the correct name, description, and body.
	 */
	public function test_load_returnsSkill(): void
	{
		$this->repository->save($this->makeSkill('summarize'));

		$loaded = $this->repository->load('summarize');

		$this->assertSame('summarize', $loaded->getName());
		$this->assertSame('Test skill', $loaded->getDescription());
		$this->assertSame('body: $content', $loaded->getBody());
		$this->assertFalse($loaded->getConfig()->requiresConfirmation());
	}

	/**
	 * Tests that load() throws SkillNotFoundException when the skill does not exist.
	 */
	public function test_load_whenSkillMissing_throwsSkillNotFoundException(): void
	{
		$this->expectException(SkillNotFoundException::class);

		$this->repository->load('nonexistent');
	}

	// -----------------------------------------------------------------------
	// delete()
	// -----------------------------------------------------------------------

	/**
	 * Tests that delete() removes the skill option and its index entry.
	 */
	public function test_delete_removesOptionAndIndexEntry(): void
	{
		$this->repository->save($this->makeSkill('remove-me'));
		$this->repository->delete('remove-me');

		$this->assertFalse($this->repository->exists('remove-me'));
		$this->assertNotContains('remove-me', $this->repository->listNames());
	}

	/**
	 * Tests that delete() returns true when the skill existed.
	 */
	public function test_delete_returnsTrueIfExisted(): void
	{
		$this->repository->save($this->makeSkill('exists'));

		$this->assertTrue($this->repository->delete('exists'));
	}

	/**
	 * Tests that delete() returns false when the skill did not exist.
	 */
	public function test_delete_returnsFalseIfNotExisted(): void
	{
		$this->assertFalse($this->repository->delete('ghost'));
	}

	// -----------------------------------------------------------------------
	// listNames()
	// -----------------------------------------------------------------------

	/**
	 * Tests that listNames() returns an empty array when the index option has
	 * never been set.
	 */
	public function test_listNames_returnsEmptyArrayWhenIndexMissing(): void
	{
		// Store reset in setUp ensures the index option is absent.
		$names = $this->repository->listNames();

		$this->assertSame([], $names);
	}

	/**
	 * Tests that listNames() returns all saved skill names.
	 */
	public function test_listNames_returnsAllSavedNames(): void
	{
		$this->repository->save($this->makeSkill('alpha'));
		$this->repository->save($this->makeSkill('beta'));
		$this->repository->save($this->makeSkill('gamma'));

		$names = $this->repository->listNames();

		$this->assertCount(3, $names);
		$this->assertContains('alpha', $names);
		$this->assertContains('beta', $names);
		$this->assertContains('gamma', $names);
	}

	// -----------------------------------------------------------------------
	// Interface contract
	// -----------------------------------------------------------------------

	/**
	 * Tests that WpOptionsSkillRepository implements SkillRepositoryInterface.
	 */
	public function test_implementsSkillRepositoryInterface(): void
	{
		$this->assertInstanceOf(SkillRepositoryInterface::class, $this->repository);
	}
}
