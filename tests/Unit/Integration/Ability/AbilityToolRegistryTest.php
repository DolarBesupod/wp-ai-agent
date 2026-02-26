<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Integration\Ability;

use WP_Ability;
use WpAiAgent\Core\Tool\ToolRegistry;
use WpAiAgent\Integration\Ability\AbilityToolAdapter;
use WpAiAgent\Integration\Ability\AbilityToolRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AbilityToolRegistry.
 *
 * @covers \WpAiAgent\Integration\Ability\AbilityToolRegistry
 */
final class AbilityToolRegistryTest extends TestCase
{
	/**
	 * Creates a WP_Ability stub with the given configuration.
	 *
	 * @param string               $name The ability name (e.g. 'core/get-site-info').
	 * @param array<string, mixed> $args Configuration for the WP_Ability stub.
	 *
	 * @return WP_Ability
	 */
	private function createAbility(string $name, array $args = []): WP_Ability
	{
		return new WP_Ability($name, $args);
	}

	/**
	 * Tests that discoverAndRegister registers all abilities from the provider.
	 */
	public function test_discoverAndRegister_registersAllAbilities(): void
	{
		$abilities = [
			$this->createAbility('core/get-site-info', ['description' => 'Returns site info']),
			$this->createAbility('core/list-posts', ['description' => 'Lists posts']),
			$this->createAbility('core/get-users', ['description' => 'Returns users']),
		];

		$registry = new AbilityToolRegistry(fn (): array => $abilities);
		$tool_registry = new ToolRegistry();

		$count = $registry->discoverAndRegister($tool_registry);

		$this->assertSame(3, $count);
		$this->assertSame(3, $tool_registry->count());
	}

	/**
	 * Tests that discoverAndRegister returns zero when the provider yields no abilities.
	 */
	public function test_discoverAndRegister_withEmptyAbilities_returnsZero(): void
	{
		$registry = new AbilityToolRegistry(fn (): array => []);
		$tool_registry = new ToolRegistry();

		$count = $registry->discoverAndRegister($tool_registry);

		$this->assertSame(0, $count);
		$this->assertSame(0, $tool_registry->count());
	}

	/**
	 * Tests that a pre-registered tool with the same name is skipped (not overwritten).
	 */
	public function test_discoverAndRegister_skipsDuplicateToolNames(): void
	{
		$abilities = [
			$this->createAbility('core/get-site-info', ['description' => 'Returns site info']),
			$this->createAbility('core/list-posts', ['description' => 'Lists posts']),
		];

		$tool_registry = new ToolRegistry();

		// Pre-register a tool with the same normalized name as the first ability.
		$pre_existing = new AbilityToolAdapter(
			$this->createAbility('core/get-site-info', ['description' => 'Already registered'])
		);
		$tool_registry->register($pre_existing);

		$registry = new AbilityToolRegistry(fn (): array => $abilities);
		$count = $registry->discoverAndRegister($tool_registry);

		// Only the second ability should be registered; the first was a duplicate.
		$this->assertSame(1, $count);
		$this->assertSame(2, $tool_registry->count());

		// Verify the pre-existing tool was not replaced.
		$tool = $tool_registry->get('ability_core_get_site_info');
		$this->assertNotNull($tool);
		$this->assertSame('Already registered', $tool->getDescription());
	}

	/**
	 * Tests that tool names in the registry match the expected normalization.
	 */
	public function test_discoverAndRegister_toolNamesAreCorrect(): void
	{
		$abilities = [
			$this->createAbility('core/get-site-info'),
			$this->createAbility('my-plugin/do-something'),
			$this->createAbility('ns/resource/action'),
		];

		$registry = new AbilityToolRegistry(fn (): array => $abilities);
		$tool_registry = new ToolRegistry();

		$registry->discoverAndRegister($tool_registry);

		$names = $tool_registry->getToolNames();
		sort($names);

		$expected = [
			'ability_core_get_site_info',
			'ability_my_plugin_do_something',
			'ability_ns_resource_action',
		];
		sort($expected);

		$this->assertSame($expected, $names);
	}

	/**
	 * Tests that the default constructor (uncallable provider) returns zero.
	 *
	 * When wp_get_abilities does not exist, the provider is the string
	 * 'wp_get_abilities' which is not callable, so discovery should return 0.
	 */
	public function test_discoverAndRegister_withUncallableProvider_returnsZero(): void
	{
		// Default constructor: provider defaults to 'wp_get_abilities' which
		// does not exist in the test environment.
		$registry = new AbilityToolRegistry();
		$tool_registry = new ToolRegistry();

		$count = $registry->discoverAndRegister($tool_registry);

		$this->assertSame(0, $count);
		$this->assertSame(0, $tool_registry->count());
	}

	/**
	 * Tests that a provider returning a non-array value results in zero registrations.
	 */
	public function test_discoverAndRegister_withNonArrayProviderResult_returnsZero(): void
	{
		$registry = new AbilityToolRegistry(fn (): mixed => null);
		$tool_registry = new ToolRegistry();

		$count = $registry->discoverAndRegister($tool_registry);

		$this->assertSame(0, $count);
		$this->assertSame(0, $tool_registry->count());
	}

	/**
	 * Tests that registered tools are AbilityToolAdapter instances.
	 */
	public function test_discoverAndRegister_registersAbilityToolAdapters(): void
	{
		$abilities = [
			$this->createAbility('core/get-site-info', ['description' => 'Returns site info']),
		];

		$registry = new AbilityToolRegistry(fn (): array => $abilities);
		$tool_registry = new ToolRegistry();

		$registry->discoverAndRegister($tool_registry);

		$tool = $tool_registry->get('ability_core_get_site_info');

		$this->assertInstanceOf(AbilityToolAdapter::class, $tool);
	}
}
