<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Integration\Ability;

use WP_Ability;
use WP_Error;
use WpAiAgent\Integration\Ability\AbilityStrapTool;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AbilityStrapTool.
 *
 * @covers \WpAiAgent\Integration\Ability\AbilityStrapTool
 */
final class AbilityStrapToolTest extends TestCase
{
	/**
	 * Creates a WP_Ability stub with the given configuration.
	 *
	 * @param string               $name             The ability name (e.g. 'core/get-site-info').
	 * @param string               $label            The human-readable label.
	 * @param string               $description      The description.
	 * @param array<string, mixed> $meta             The metadata including annotations.
	 * @param array<string, mixed>|null $input_schema The input schema or null.
	 * @param mixed                $execute_result   The value returned by execute().
	 * @param bool|\WP_Error       $permission_result The value returned by check_permissions().
	 *
	 * @return WP_Ability
	 */
	private function createStubAbility(
		string $name,
		string $label = '',
		string $description = '',
		array $meta = [],
		?array $input_schema = null,
		mixed $execute_result = null,
		bool|\WP_Error $permission_result = true,
	): WP_Ability {
		$args = [
			'label' => $label,
			'description' => $description,
			'meta' => $meta,
			'permission_result' => $permission_result,
		];

		if ($input_schema !== null) {
			$args['input_schema'] = $input_schema;
		}

		if ($execute_result !== null) {
			$args['execute_result'] = $execute_result;
		}

		return new WP_Ability($name, $args);
	}

	/**
	 * Creates an AbilityStrapTool with the given abilities.
	 *
	 * @param array<int, WP_Ability> $abilities The abilities to provide.
	 *
	 * @return AbilityStrapTool
	 */
	private function createTool(array $abilities = []): AbilityStrapTool
	{
		return new AbilityStrapTool(fn () => $abilities);
	}

	/**
	 * Tests that getName returns 'wordpress_abilities'.
	 */
	public function test_getName_returnsWordpressAbilities(): void
	{
		$tool = $this->createTool();

		$this->assertSame('wordpress_abilities', $tool->getName());
	}

	/**
	 * Tests that requiresConfirmation returns false.
	 */
	public function test_requiresConfirmation_returnsFalse(): void
	{
		$tool = $this->createTool();

		$this->assertFalse($tool->requiresConfirmation());
	}

	/**
	 * Tests that getParametersSchema returns a schema with the action enum.
	 */
	public function test_getParametersSchema_returnsSchemaWithActionEnum(): void
	{
		$tool = $this->createTool();
		$schema = $tool->getParametersSchema();

		$this->assertSame('object', $schema['type']);
		$this->assertSame(['action'], $schema['required']);
		$this->assertSame(
			['list', 'describe', 'execute'],
			$schema['properties']['action']['enum']
		);
		$this->assertArrayHasKey('ability_name', $schema['properties']);
		$this->assertArrayHasKey('params', $schema['properties']);
	}

	// ---------------------------------------------------------------
	// List action
	// ---------------------------------------------------------------

	/**
	 * Tests that the list action returns all abilities.
	 */
	public function test_execute_withListAction_returnsAllAbilities(): void
	{
		$tool = $this->createTool([
			$this->createStubAbility(
				'core/get-site-info',
				'Get Site Info',
				'Returns site information',
				['annotations' => ['readonly' => true]],
			),
			$this->createStubAbility(
				'mcp/create-post',
				'Create Post',
				'Create a post',
				['annotations' => ['readonly' => false, 'destructive' => false]],
				[
					'type' => 'object',
					'properties' => [
						'title' => ['type' => 'string'],
						'content' => ['type' => 'string'],
					],
				],
			),
		]);

		$result = $tool->execute(['action' => 'list']);

		$this->assertTrue($result->isSuccess());

		$data = json_decode($result->getOutput(), true);
		$this->assertCount(2, $data['abilities']);
		$this->assertSame('core/get-site-info', $data['abilities'][0]['name']);
		$this->assertSame('mcp/create-post', $data['abilities'][1]['name']);
	}

	/**
	 * Tests that the list action includes key_params from the input schema.
	 */
	public function test_execute_withListAction_includesKeyParamsFromSchema(): void
	{
		$tool = $this->createTool([
			$this->createStubAbility(
				'mcp/create-post',
				'Create Post',
				'Create a post',
				[],
				[
					'type' => 'object',
					'properties' => [
						'title' => ['type' => 'string'],
						'content' => ['type' => 'string'],
						'status' => ['type' => 'string'],
					],
				],
			),
		]);

		$result = $tool->execute(['action' => 'list']);
		$data = json_decode($result->getOutput(), true);

		$this->assertSame(['title', 'content', 'status'], $data['abilities'][0]['key_params']);
	}

	/**
	 * Tests that the list action includes annotations when present.
	 */
	public function test_execute_withListAction_includesAnnotations(): void
	{
		$tool = $this->createTool([
			$this->createStubAbility(
				'core/get-site-info',
				'Get Site Info',
				'Returns site information',
				['annotations' => ['readonly' => true]],
			),
		]);

		$result = $tool->execute(['action' => 'list']);
		$data = json_decode($result->getOutput(), true);

		$this->assertSame(['readonly' => true], $data['abilities'][0]['annotations']);
	}

	/**
	 * Tests that the list action omits annotations when empty.
	 */
	public function test_execute_withListAction_omitsAnnotationsWhenEmpty(): void
	{
		$tool = $this->createTool([
			$this->createStubAbility(
				'core/get-site-info',
				'Get Site Info',
				'Returns site information',
			),
		]);

		$result = $tool->execute(['action' => 'list']);
		$data = json_decode($result->getOutput(), true);

		$this->assertArrayNotHasKey('annotations', $data['abilities'][0]);
	}

	/**
	 * Tests that the list action returns an empty array when no abilities exist.
	 */
	public function test_execute_withListAction_withNoAbilities_returnsEmptyArray(): void
	{
		$tool = $this->createTool([]);

		$result = $tool->execute(['action' => 'list']);

		$this->assertTrue($result->isSuccess());

		$data = json_decode($result->getOutput(), true);
		$this->assertSame([], $data['abilities']);
		$this->assertSame(0, $data['count']);
	}

	/**
	 * Tests that the list action includes count and usage_hint.
	 */
	public function test_execute_withListAction_includesCountAndUsageHint(): void
	{
		$tool = $this->createTool([
			$this->createStubAbility('core/get-site-info', 'Get Site Info', 'Returns site information'),
			$this->createStubAbility('core/get-version', 'Get Version', 'Returns WP version'),
		]);

		$result = $tool->execute(['action' => 'list']);
		$data = json_decode($result->getOutput(), true);

		$this->assertSame(2, $data['count']);
		$this->assertStringContainsString('describe', $data['usage_hint']);
		$this->assertStringContainsString('execute', $data['usage_hint']);
	}

	// ---------------------------------------------------------------
	// Describe action
	// ---------------------------------------------------------------

	/**
	 * Tests that the describe action returns the full schema.
	 */
	public function test_execute_withDescribeAction_returnsFullSchema(): void
	{
		$schema = [
			'type' => 'object',
			'properties' => [
				'title' => ['type' => 'string'],
				'content' => ['type' => 'string'],
			],
			'required' => ['title'],
		];

		$tool = $this->createTool([
			$this->createStubAbility(
				'mcp/create-post',
				'Create Post',
				'Create a post',
				['annotations' => ['readonly' => false]],
				$schema,
			),
		]);

		$result = $tool->execute(['action' => 'describe', 'ability_name' => 'mcp/create-post']);

		$this->assertTrue($result->isSuccess());

		$data = json_decode($result->getOutput(), true);
		$this->assertSame('mcp/create-post', $data['name']);
		$this->assertStringContainsString('Create a post', $data['description']);
		$this->assertSame($schema, $data['input_schema']);
	}

	/**
	 * Tests that describing an unknown ability returns failure.
	 */
	public function test_execute_withDescribeAction_withUnknownAbility_returnsFailure(): void
	{
		$tool = $this->createTool([]);

		$result = $tool->execute(['action' => 'describe', 'ability_name' => 'nonexistent/tool']);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('Unknown ability: nonexistent/tool', $result->getError());
		$this->assertStringContainsString("Use action 'list'", $result->getError());
	}

	/**
	 * Tests that describing without ability_name returns failure.
	 */
	public function test_execute_withDescribeAction_withMissingAbilityName_returnsFailure(): void
	{
		$tool = $this->createTool([]);

		$result = $tool->execute(['action' => 'describe']);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString("ability_name is required for 'describe' action", $result->getError());
	}

	/**
	 * Tests that describing an ability with no input schema returns null input_schema.
	 */
	public function test_execute_withDescribeAction_withNullSchema_returnsNullInputSchema(): void
	{
		$tool = $this->createTool([
			$this->createStubAbility('core/get-site-info', 'Get Site Info', 'Returns site information'),
		]);

		$result = $tool->execute(['action' => 'describe', 'ability_name' => 'core/get-site-info']);

		$this->assertTrue($result->isSuccess());

		$data = json_decode($result->getOutput(), true);
		$this->assertArrayHasKey('input_schema', $data);
		$this->assertNull($data['input_schema']);
	}

	// ---------------------------------------------------------------
	// Execute action
	// ---------------------------------------------------------------

	/**
	 * Tests that a readonly ability executes without confirmation.
	 */
	public function test_execute_withExecuteAction_readonlyAbility_executesWithoutConfirmation(): void
	{
		$tool = $this->createTool([
			$this->createStubAbility(
				'core/get-site-info',
				'Get Site Info',
				'Returns site information',
				['annotations' => ['readonly' => true]],
				null,
				['name' => 'My Site', 'url' => 'https://example.com'],
			),
		]);

		$result = $tool->execute([
			'action' => 'execute',
			'ability_name' => 'core/get-site-info',
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('My Site', $result->getOutput());
	}

	/**
	 * Tests that a mutative ability without confirmed returns confirmation_required.
	 */
	public function test_execute_withExecuteAction_mutativeAbility_withoutConfirmed_returnsConfirmationRequired(): void
	{
		$tool = $this->createTool([
			$this->createStubAbility(
				'mcp/create-post',
				'Create Post',
				'Create a post',
				['annotations' => ['readonly' => false]],
			),
		]);

		$result = $tool->execute([
			'action' => 'execute',
			'ability_name' => 'mcp/create-post',
			'params' => ['title' => 'Hello'],
		]);

		$this->assertFalse($result->isSuccess());

		$error_data = json_decode($result->getError(), true);
		$this->assertSame('confirmation_required', $error_data['error_code']);
		$this->assertSame('mcp/create-post', $error_data['ability_name']);
		$this->assertStringContainsString('modify data', $error_data['message']);
	}

	/**
	 * Tests that a mutative ability with confirmed executes.
	 */
	public function test_execute_withExecuteAction_mutativeAbility_withConfirmed_executes(): void
	{
		$tool = $this->createTool([
			$this->createStubAbility(
				'mcp/create-post',
				'Create Post',
				'Create a post',
				['annotations' => ['readonly' => false]],
				null,
				['id' => 42, 'title' => 'Hello'],
			),
		]);

		$result = $tool->execute([
			'action' => 'execute',
			'ability_name' => 'mcp/create-post',
			'params' => ['title' => 'Hello', 'confirmed' => true],
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('42', $result->getOutput());
	}

	/**
	 * Tests that the confirmed param is stripped before passing to the ability.
	 */
	public function test_execute_withExecuteAction_stripsConfirmedFromParams(): void
	{
		$captured_input = new \stdClass();
		$captured_input->value = 'not_set';

		$capturing_ability = new class ('mcp/create-post', $captured_input) extends WP_Ability {
			private \stdClass $captured;

			public function __construct(string $name, \stdClass $captured)
			{
				parent::__construct($name, [
					'label' => 'Create Post',
					'description' => 'Create a post',
					'meta' => ['annotations' => ['readonly' => false]],
				]);
				$this->captured = $captured;
			}

			public function execute(mixed $input = null): mixed
			{
				$this->captured->value = $input;

				return ['ok' => true];
			}

			public function check_permissions(mixed $input = null): bool|\WP_Error
			{
				return true;
			}
		};

		$tool = new AbilityStrapTool(fn () => [$capturing_ability]);

		$tool->execute([
			'action' => 'execute',
			'ability_name' => 'mcp/create-post',
			'params' => ['title' => 'Hello', 'confirmed' => true],
		]);

		$this->assertIsArray($captured_input->value);
		$this->assertArrayHasKey('title', $captured_input->value);
		$this->assertArrayNotHasKey('confirmed', $captured_input->value);
	}

	/**
	 * Tests that permission denied returns failure.
	 */
	public function test_execute_withExecuteAction_permissionDenied_returnsFailure(): void
	{
		$tool = $this->createTool([
			$this->createStubAbility(
				'admin/delete-user',
				'Delete User',
				'Delete a user',
				['annotations' => ['readonly' => true]],
				null,
				null,
				false,
			),
		]);

		$result = $tool->execute([
			'action' => 'execute',
			'ability_name' => 'admin/delete-user',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('Permission denied', $result->getError());
		$this->assertStringContainsString('admin/delete-user', $result->getError());
	}

	/**
	 * Tests that a WP_Error from execute returns failure.
	 */
	public function test_execute_withExecuteAction_wpError_returnsFailure(): void
	{
		$tool = $this->createTool([
			$this->createStubAbility(
				'mcp/create-post',
				'Create Post',
				'Create a post',
				['annotations' => ['readonly' => true]],
				null,
				new WP_Error('empty_title', 'Title cannot be empty'),
			),
		]);

		$result = $tool->execute([
			'action' => 'execute',
			'ability_name' => 'mcp/create-post',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('empty_title', $result->getError());
		$this->assertStringContainsString('Title cannot be empty', $result->getError());
	}

	/**
	 * Tests that executing an unknown ability returns failure.
	 */
	public function test_execute_withExecuteAction_withUnknownAbility_returnsFailure(): void
	{
		$tool = $this->createTool([]);

		$result = $tool->execute([
			'action' => 'execute',
			'ability_name' => 'nonexistent/tool',
		]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('Unknown ability: nonexistent/tool', $result->getError());
	}

	/**
	 * Tests that executing without ability_name returns failure.
	 */
	public function test_execute_withExecuteAction_withMissingAbilityName_returnsFailure(): void
	{
		$tool = $this->createTool([]);

		$result = $tool->execute(['action' => 'execute']);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString("ability_name is required for 'execute' action", $result->getError());
	}

	/**
	 * Tests that missing params defaults to an empty array.
	 */
	public function test_execute_withExecuteAction_withMissingParams_defaultsToEmptyArray(): void
	{
		$tool = $this->createTool([
			$this->createStubAbility(
				'core/get-site-info',
				'Get Site Info',
				'Returns site information',
				['annotations' => ['readonly' => true]],
				null,
				['name' => 'My Site'],
			),
		]);

		$result = $tool->execute([
			'action' => 'execute',
			'ability_name' => 'core/get-site-info',
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertStringContainsString('My Site', $result->getOutput());
	}

	/**
	 * Tests that confirmed on a readonly ability is silently ignored.
	 */
	public function test_execute_withExecuteAction_confirmedOnReadonly_silentlyIgnored(): void
	{
		$captured_input = new \stdClass();
		$captured_input->value = 'not_set';

		$capturing_ability = new class ('core/get-site-info', $captured_input) extends WP_Ability {
			private \stdClass $captured;

			public function __construct(string $name, \stdClass $captured)
			{
				parent::__construct($name, [
					'label' => 'Get Site Info',
					'description' => 'Returns site information',
					'meta' => ['annotations' => ['readonly' => true]],
				]);
				$this->captured = $captured;
			}

			public function execute(mixed $input = null): mixed
			{
				$this->captured->value = $input;

				return ['name' => 'My Site'];
			}

			public function check_permissions(mixed $input = null): bool|\WP_Error
			{
				return true;
			}
		};

		$tool = new AbilityStrapTool(fn () => [$capturing_ability]);

		$result = $tool->execute([
			'action' => 'execute',
			'ability_name' => 'core/get-site-info',
			'params' => ['confirmed' => true],
		]);

		$this->assertTrue($result->isSuccess());

		// confirmed should be stripped, leaving empty params which becomes null
		$this->assertNull($captured_input->value);
	}

	// ---------------------------------------------------------------
	// Invalid action
	// ---------------------------------------------------------------

	/**
	 * Tests that an invalid action returns failure.
	 */
	public function test_execute_withInvalidAction_returnsFailure(): void
	{
		$tool = $this->createTool([]);

		$result = $tool->execute(['action' => 'invalid']);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString("Invalid action 'invalid'", $result->getError());
		$this->assertStringContainsString('list, describe, execute', $result->getError());
	}

	/**
	 * Tests that a missing action returns failure.
	 */
	public function test_execute_withMissingAction_returnsFailure(): void
	{
		$tool = $this->createTool([]);

		$result = $tool->execute([]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString("Invalid action ''", $result->getError());
	}

	// ---------------------------------------------------------------
	// Lazy discovery
	// ---------------------------------------------------------------

	/**
	 * Tests that the abilities provider is called only once across multiple execute calls.
	 */
	public function test_execute_callsAbilitiesProviderOnlyOnce(): void
	{
		$call_count = 0;
		$abilities = [
			$this->createStubAbility('core/get-site-info', 'Get Site Info', 'Returns site information'),
		];

		$tool = new AbilityStrapTool(function () use (&$call_count, $abilities) {
			$call_count++;

			return $abilities;
		});

		$tool->execute(['action' => 'list']);
		$tool->execute(['action' => 'list']);
		$tool->execute(['action' => 'describe', 'ability_name' => 'core/get-site-info']);

		$this->assertSame(1, $call_count);
	}

	/**
	 * Tests that an uncallable provider results in an empty list.
	 *
	 * When no provider is injected, the default 'wp_get_abilities' string is
	 * used. Since that function doesn't exist in tests, is_callable() returns
	 * false and the catalog stays empty.
	 */
	public function test_execute_withUncallableProvider_returnsEmptyList(): void
	{
		$tool = new AbilityStrapTool();

		$result = $tool->execute(['action' => 'list']);

		$this->assertTrue($result->isSuccess());

		$data = json_decode($result->getOutput(), true);
		$this->assertSame([], $data['abilities']);
		$this->assertSame(0, $data['count']);
	}
}
