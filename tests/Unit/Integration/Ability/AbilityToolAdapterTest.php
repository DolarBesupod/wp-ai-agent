<?php

declare(strict_types=1);

namespace Automattic\Automattic\WpAiAgent\Tests\Unit\Integration\Ability;

use WP_Ability;
use WP_Error;
use Automattic\Automattic\WpAiAgent\Core\Contracts\ToolInterface;
use Automattic\Automattic\WpAiAgent\Integration\Ability\AbilityToolAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AbilityToolAdapter.
 *
 * @covers \Automattic\WpAiAgent\Integration\Ability\AbilityToolAdapter
 */
final class AbilityToolAdapterTest extends TestCase
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
	 * Creates an AbilityToolAdapter for the given ability configuration.
	 *
	 * @param string               $name The ability name.
	 * @param array<string, mixed> $args Configuration for the WP_Ability stub.
	 *
	 * @return AbilityToolAdapter
	 */
	private function createAdapter(string $name, array $args = []): AbilityToolAdapter
	{
		return new AbilityToolAdapter($this->createAbility($name, $args));
	}

	/**
	 * Tests that the adapter implements ToolInterface.
	 */
	public function test_implementsToolInterface(): void
	{
		$adapter = $this->createAdapter('core/get-site-info');

		$this->assertInstanceOf(ToolInterface::class, $adapter);
	}

	/**
	 * Tests that slashes in the ability name are normalized to underscores.
	 */
	public function test_getName_normalizesSlashesToUnderscores(): void
	{
		$adapter = $this->createAdapter('core/get-site-info');

		$this->assertSame('ability_core_get_site_info', $adapter->getName());
	}

	/**
	 * Tests that hyphens in the ability name are normalized to underscores.
	 */
	public function test_getName_normalizesHyphensToUnderscores(): void
	{
		$adapter = $this->createAdapter('my-plugin/my-tool');

		$this->assertSame('ability_my_plugin_my_tool', $adapter->getName());
	}

	/**
	 * Tests name normalization with three segments.
	 */
	public function test_getName_withThreeSegments(): void
	{
		$adapter = $this->createAdapter('ns/resource/action');

		$this->assertSame('ability_ns_resource_action', $adapter->getName());
	}

	/**
	 * Tests name normalization with four segments.
	 */
	public function test_getName_withFourSegments(): void
	{
		$adapter = $this->createAdapter('a/b/c/d');

		$this->assertSame('ability_a_b_c_d', $adapter->getName());
	}

	/**
	 * Tests that getOriginalName returns the original WP_Ability name.
	 */
	public function test_getOriginalName_returnsAbilityName(): void
	{
		$adapter = $this->createAdapter('core/get-site-info');

		$this->assertSame('core/get-site-info', $adapter->getOriginalName());
	}

	/**
	 * Tests description formatting when the ability has a label.
	 */
	public function test_getDescription_withLabel(): void
	{
		$adapter = $this->createAdapter('core/get-site-info', [
			'label' => 'Get Site Info',
			'description' => 'Returns site information',
		]);

		$this->assertSame('"Get Site Info" — Returns site information', $adapter->getDescription());
	}

	/**
	 * Tests description formatting when the ability has no label.
	 */
	public function test_getDescription_withoutLabel(): void
	{
		$adapter = $this->createAdapter('core/get-site-info', [
			'description' => 'Returns site information',
		]);

		$this->assertSame('Returns site information', $adapter->getDescription());
	}

	/**
	 * Tests description when the label is an empty string.
	 */
	public function test_getDescription_withEmptyLabel_returnsDescriptionOnly(): void
	{
		$adapter = $this->createAdapter('core/get-site-info', [
			'label' => '',
			'description' => 'Returns site information',
		]);

		$this->assertSame('Returns site information', $adapter->getDescription());
	}

	/**
	 * Tests that getParametersSchema passes through the input schema.
	 */
	public function test_getParametersSchema_passesThrough(): void
	{
		$schema = [
			'type' => 'object',
			'properties' => [
				'post_id' => [
					'type' => 'integer',
					'description' => 'The post ID',
				],
			],
			'required' => ['post_id'],
		];

		$adapter = $this->createAdapter('core/get-post', [
			'input_schema' => $schema,
		]);

		$this->assertSame($schema, $adapter->getParametersSchema());
	}

	/**
	 * Tests that getParametersSchema returns null for an empty schema.
	 */
	public function test_getParametersSchema_returnsNullForEmptySchema(): void
	{
		$adapter = $this->createAdapter('core/get-site-info', [
			'input_schema' => [],
		]);

		$this->assertNull($adapter->getParametersSchema());
	}

	/**
	 * Tests that getParametersSchema returns null when no schema is provided.
	 */
	public function test_getParametersSchema_returnsNullWhenNotProvided(): void
	{
		$adapter = $this->createAdapter('core/get-site-info');

		$this->assertNull($adapter->getParametersSchema());
	}

	/**
	 * Tests that a readonly ability does not require confirmation.
	 */
	public function test_requiresConfirmation_readonlyReturnsFalse(): void
	{
		$adapter = $this->createAdapter('core/get-site-info', [
			'meta' => [
				'annotations' => [
					'readonly' => true,
				],
			],
		]);

		$this->assertFalse($adapter->requiresConfirmation());
	}

	/**
	 * Tests that a destructive ability requires confirmation.
	 */
	public function test_requiresConfirmation_destructiveReturnsTrue(): void
	{
		$adapter = $this->createAdapter('core/delete-post', [
			'meta' => [
				'annotations' => [
					'destructive' => true,
				],
			],
		]);

		$this->assertTrue($adapter->requiresConfirmation());
	}

	/**
	 * Tests that an ability with no annotations requires confirmation.
	 */
	public function test_requiresConfirmation_noAnnotationsReturnsTrue(): void
	{
		$adapter = $this->createAdapter('core/unknown', [
			'meta' => [],
		]);

		$this->assertTrue($adapter->requiresConfirmation());
	}

	/**
	 * Tests that null readonly annotation requires confirmation.
	 */
	public function test_requiresConfirmation_nullReadonlyReturnsTrue(): void
	{
		$adapter = $this->createAdapter('core/some-action', [
			'meta' => [
				'annotations' => [
					'readonly' => null,
				],
			],
		]);

		$this->assertTrue($adapter->requiresConfirmation());
	}

	/**
	 * Tests that readonly=false requires confirmation.
	 */
	public function test_requiresConfirmation_readonlyFalseReturnsTrue(): void
	{
		$adapter = $this->createAdapter('core/some-action', [
			'meta' => [
				'annotations' => [
					'readonly' => false,
				],
			],
		]);

		$this->assertTrue($adapter->requiresConfirmation());
	}

	/**
	 * Tests that an ability with no meta at all requires confirmation.
	 */
	public function test_requiresConfirmation_noMetaReturnsTrue(): void
	{
		$adapter = $this->createAdapter('core/action');

		$this->assertTrue($adapter->requiresConfirmation());
	}

	/**
	 * Tests successful execution with an array result.
	 */
	public function test_execute_successWithArrayResult(): void
	{
		$adapter = $this->createAdapter('core/get-site-info', [
			'execute_result' => ['name' => 'My Site', 'url' => 'https://example.com'],
		]);

		$result = $adapter->execute([]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame('{"name":"My Site","url":"https:\/\/example.com"}', $result->getOutput());
	}

	/**
	 * Tests successful execution with a string result.
	 */
	public function test_execute_successWithStringResult(): void
	{
		$adapter = $this->createAdapter('core/get-version', [
			'execute_result' => '6.9.0',
		]);

		$result = $adapter->execute([]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame('6.9.0', $result->getOutput());
	}

	/**
	 * Tests successful execution with a null result.
	 */
	public function test_execute_successWithNullResult(): void
	{
		$null_ability = new class ('core/do-something') extends WP_Ability {
			public function execute(mixed $input = null): mixed
			{
				return null;
			}

			public function check_permissions(mixed $input = null): bool|\WP_Error
			{
				return true;
			}
		};

		$adapter = new AbilityToolAdapter($null_ability);
		$result = $adapter->execute([]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame('null', $result->getOutput());
	}

	/**
	 * Tests that permission denied (false) returns a failure result.
	 */
	public function test_execute_permissionDeniedReturnsFalse(): void
	{
		$adapter = $this->createAdapter('core/admin-action', [
			'permission_result' => false,
		]);

		$result = $adapter->execute([]);

		$this->assertFalse($result->isSuccess());
		$this->assertSame(
			'Permission denied for ability: core/admin-action',
			$result->getError()
		);
	}

	/**
	 * Tests that permission denied via WP_Error returns a failure with code.
	 */
	public function test_execute_permissionDeniedReturnsWpError(): void
	{
		$adapter = $this->createAdapter('core/admin-action', [
			'permission_result' => new WP_Error('rest_forbidden', 'You are not allowed to do this'),
		]);

		$result = $adapter->execute([]);

		$this->assertFalse($result->isSuccess());
		$this->assertSame(
			'rest_forbidden: You are not allowed to do this',
			$result->getError()
		);
	}

	/**
	 * Tests that a WP_Error execute result returns a failure.
	 */
	public function test_execute_wpErrorResult(): void
	{
		$adapter = $this->createAdapter('core/create-post', [
			'execute_result' => new WP_Error('invalid_input', 'Post title is required'),
		]);

		$result = $adapter->execute([]);

		$this->assertFalse($result->isSuccess());
		$this->assertSame(
			'invalid_input: Post title is required',
			$result->getError()
		);
	}

	/**
	 * Tests that an exception during execution is caught and returned as failure.
	 */
	public function test_execute_catchesException(): void
	{
		$throwing_ability = new class ('core/broken') extends WP_Ability {
			public function execute(mixed $input = null): mixed
			{
				throw new \RuntimeException('Something went wrong');
			}
		};

		$adapter = new AbilityToolAdapter($throwing_ability);
		$result = $adapter->execute(['some' => 'arg']);

		$this->assertFalse($result->isSuccess());
		$this->assertSame(
			'Ability execution failed: Something went wrong',
			$result->getError()
		);
	}

	/**
	 * Tests that empty arguments are passed as null to the ability.
	 */
	public function test_execute_emptyArgumentsPassedAsNull(): void
	{
		$captured_input = new \stdClass();
		$captured_input->value = 'not_set';

		$capturing_ability = new class ('core/capture', $captured_input) extends WP_Ability {
			private \stdClass $captured;

			public function __construct(string $name, \stdClass $captured)
			{
				parent::__construct($name);
				$this->captured = $captured;
			}

			public function execute(mixed $input = null): mixed
			{
				$this->captured->value = $input;

				return 'done';
			}

			public function check_permissions(mixed $input = null): bool|WP_Error
			{
				return true;
			}
		};

		$adapter = new AbilityToolAdapter($capturing_ability);
		$adapter->execute([]);

		$this->assertNull($captured_input->value);
	}

	/**
	 * Tests that non-empty arguments are passed through to the ability.
	 */
	public function test_execute_nonEmptyArgumentsPassedThrough(): void
	{
		$captured_input = new \stdClass();
		$captured_input->value = null;

		$capturing_ability = new class ('core/capture', $captured_input) extends WP_Ability {
			private \stdClass $captured;

			public function __construct(string $name, \stdClass $captured)
			{
				parent::__construct($name);
				$this->captured = $captured;
			}

			public function execute(mixed $input = null): mixed
			{
				$this->captured->value = $input;

				return 'done';
			}

			public function check_permissions(mixed $input = null): bool|WP_Error
			{
				return true;
			}
		};

		$adapter = new AbilityToolAdapter($capturing_ability);
		$adapter->execute(['post_id' => 42]);

		$this->assertSame(['post_id' => 42], $captured_input->value);
	}

	/**
	 * Tests the TOOL_PREFIX constant value.
	 */
	public function test_toolPrefixConstant(): void
	{
		$this->assertSame('ability_', AbilityToolAdapter::TOOL_PREFIX);
	}
}
