<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Tests\Unit\Integration\User;

use Automattic\WpAiAgent\Integration\User\UserContextTool;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UserContextTool.
 *
 * @covers \Automattic\WpAiAgent\Integration\User\UserContextTool
 */
final class UserContextToolTest extends TestCase
{
	/**
	 * Creates a UserContextTool with injectable callables.
	 *
	 * @param callable|null $list_users_fn       Callable for listing users.
	 * @param callable|null $set_user_fn         Callable for setting the active user.
	 * @param callable|null $get_current_user_fn Callable for getting the current user.
	 *
	 * @return UserContextTool
	 */
	private function createTool(
		?callable $list_users_fn = null,
		?callable $set_user_fn = null,
		?callable $get_current_user_fn = null,
	): UserContextTool {
		return new UserContextTool(
			$list_users_fn ?? fn () => [],
			$set_user_fn ?? fn () => false,
			$get_current_user_fn ?? fn () => [
				'id' => 0,
				'login' => '',
				'display_name' => '',
				'email' => '',
				'roles' => [],
				'logged_in' => false,
			],
		);
	}

	/**
	 * Creates a sample user array for testing.
	 *
	 * @param int $id The user ID.
	 * @param string $login The user login.
	 * @param string   $display_name The display name.
	 * @param string $email The user email.
	 * @param string[] $roles        The user roles.
	 *
	 * @return array{id: int, login: string, display_name: string, email: string, roles: string[]}
	 */
	private function createUserData(
		int $id = 1,
		string $login = 'admin',
		string $display_name = 'Admin',
		string $email = 'admin@example.com',
		array $roles = ['administrator'],
	): array {
		return [
			'id' => $id,
			'login' => $login,
			'display_name' => $display_name,
			'email' => $email,
			'roles' => $roles,
		];
	}

	// ---------------------------------------------------------------
	// Metadata
	// ---------------------------------------------------------------

	/**
	 * Tests that getName returns 'wordpress_users'.
	 */
	public function test_getName_returnsWordpressUsers(): void
	{
		$tool = $this->createTool();

		$this->assertSame('wordpress_users', $tool->getName());
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
	 * Tests that getParametersSchema includes action enum, user, role, and search properties.
	 */
	public function test_getParametersSchema_includesAllProperties(): void
	{
		$tool = $this->createTool();
		$schema = $tool->getParametersSchema();

		$this->assertSame('object', $schema['type']);
		$this->assertSame(['action'], $schema['required']);
		$this->assertSame(
			['list', 'set', 'current'],
			$schema['properties']['action']['enum']
		);
		$this->assertArrayHasKey('user', $schema['properties']);
		$this->assertArrayHasKey('role', $schema['properties']);
		$this->assertArrayHasKey('search', $schema['properties']);
	}

	// ---------------------------------------------------------------
	// List action
	// ---------------------------------------------------------------

	/**
	 * Tests that list action with default (admins) returns users array and count.
	 */
	public function test_execute_withListAction_returnsUsersAndCount(): void
	{
		$admin = $this->createUserData();
		$tool = $this->createTool(
			list_users_fn: fn (string $role, string $search) => [$admin],
		);

		$result = $tool->execute(['action' => 'list']);

		$this->assertTrue($result->isSuccess());

		$data = json_decode($result->getOutput(), true);
		$this->assertCount(1, $data['users']);
		$this->assertSame(1, $data['count']);
		$this->assertSame('admin', $data['users'][0]['login']);
		$this->assertSame(['administrator'], $data['users'][0]['roles']);
	}

	/**
	 * Tests that list action passes the role parameter to the callable.
	 */
	public function test_execute_withListAction_withRoleFilter_passesRoleToCallable(): void
	{
		$captured_role = '';
		$editor = $this->createUserData(2, 'editor', 'Editor User', 'editor@example.com', ['editor']);
		$tool = $this->createTool(
			list_users_fn: function (string $role, string $search) use (&$captured_role, $editor): array {
				$captured_role = $role;

				return [$editor];
			},
		);

		$result = $tool->execute(['action' => 'list', 'role' => 'editor']);

		$this->assertTrue($result->isSuccess());
		$this->assertSame('editor', $captured_role);

		$data = json_decode($result->getOutput(), true);
		$this->assertCount(1, $data['users']);
		$this->assertSame('editor', $data['users'][0]['login']);
	}

	/**
	 * Tests that list action passes search parameter to the callable.
	 */
	public function test_execute_withListAction_withSearch_passesSearchToCallable(): void
	{
		$captured_search = '';
		$john = $this->createUserData(5, 'john', 'John Doe', 'john@example.com', ['subscriber']);
		$tool = $this->createTool(
			list_users_fn: function (string $role, string $search) use (&$captured_search, $john): array {
				$captured_search = $search;

				return [$john];
			},
		);

		$result = $tool->execute(['action' => 'list', 'search' => 'john']);

		$this->assertTrue($result->isSuccess());
		$this->assertSame('john', $captured_search);

		$data = json_decode($result->getOutput(), true);
		$this->assertCount(1, $data['users']);
		$this->assertSame('john', $data['users'][0]['login']);
		$this->assertSame(['subscriber'], $data['users'][0]['roles']);
	}

	/**
	 * Tests that list action with empty users returns empty array and zero count.
	 */
	public function test_execute_withListAction_withNoUsers_returnsEmptyArrayAndZeroCount(): void
	{
		$tool = $this->createTool(
			list_users_fn: fn (string $role, string $search) => [],
		);

		$result = $tool->execute(['action' => 'list']);

		$this->assertTrue($result->isSuccess());

		$data = json_decode($result->getOutput(), true);
		$this->assertSame([], $data['users']);
		$this->assertSame(0, $data['count']);
		$this->assertArrayNotHasKey('has_more', $data);
	}

	/**
	 * Tests that list action caps at 25 results and sets has_more with hint.
	 */
	public function test_execute_withListAction_withMoreThan25Users_capsAndSetsHasMore(): void
	{
		$users = [];
		for ($i = 1; $i <= 30; $i++) {
			$users[] = $this->createUserData($i, "user{$i}", "User {$i}", "user{$i}@example.com", ['subscriber']);
		}

		$tool = $this->createTool(
			list_users_fn: fn (string $role, string $search) => $users,
		);

		$result = $tool->execute(['action' => 'list']);

		$this->assertTrue($result->isSuccess());

		$data = json_decode($result->getOutput(), true);
		$this->assertCount(25, $data['users']);
		$this->assertSame(25, $data['count']);
		$this->assertTrue($data['has_more']);
		$this->assertStringContainsString('search', $data['hint']);
	}

	/**
	 * Tests that list action with empty search string falls back to role filter.
	 */
	public function test_execute_withListAction_withEmptySearch_fallsBackToRoleFilter(): void
	{
		$captured_role = '';
		$captured_search = '';
		$tool = $this->createTool(
			list_users_fn: function (string $role, string $search) use (&$captured_role, &$captured_search): array {
				$captured_role = $role;
				$captured_search = $search;

				return [];
			},
		);

		$tool->execute(['action' => 'list', 'search' => '', 'role' => 'editor']);

		$this->assertSame('editor', $captured_role);
		$this->assertSame('', $captured_search);
	}

	/**
	 * Tests that list action handles non-array return from callable gracefully.
	 */
	public function test_execute_withListAction_withNonArrayReturn_returnsEmptyList(): void
	{
		$tool = $this->createTool(
			list_users_fn: fn (string $role, string $search) => 'not an array',
		);

		$result = $tool->execute(['action' => 'list']);

		$this->assertTrue($result->isSuccess());

		$data = json_decode($result->getOutput(), true);
		$this->assertSame([], $data['users']);
		$this->assertSame(0, $data['count']);
	}

	/**
	 * Tests that list action defaults role to 'administrator' when not provided.
	 */
	public function test_execute_withListAction_withoutRole_defaultsToAdministrator(): void
	{
		$captured_role = '';
		$tool = $this->createTool(
			list_users_fn: function (string $role, string $search) use (&$captured_role): array {
				$captured_role = $role;

				return [];
			},
		);

		$tool->execute(['action' => 'list']);

		$this->assertSame('administrator', $captured_role);
	}

	// ---------------------------------------------------------------
	// Set action
	// ---------------------------------------------------------------

	/**
	 * Tests that set action by numeric ID resolves correctly.
	 */
	public function test_execute_withSetAction_byNumericId_resolvesCorrectly(): void
	{
		$admin = $this->createUserData();
		$tool = $this->createTool(
			set_user_fn: function (string $identifier) use ($admin): array|false {
				if ($identifier === '1') {
					return $admin;
				}

				return false;
			},
		);

		$result = $tool->execute(['action' => 'set', 'user' => '1']);

		$this->assertTrue($result->isSuccess());

		$data = json_decode($result->getOutput(), true);
		$this->assertSame(1, $data['id']);
		$this->assertSame('admin', $data['login']);
	}

	/**
	 * Tests that set action by login resolves correctly.
	 */
	public function test_execute_withSetAction_byLogin_resolvesCorrectly(): void
	{
		$editor = $this->createUserData(2, 'editor', 'Editor User', 'editor@example.com', ['editor']);
		$tool = $this->createTool(
			set_user_fn: function (string $identifier) use ($editor): array|false {
				if ($identifier === 'editor') {
					return $editor;
				}

				return false;
			},
		);

		$result = $tool->execute(['action' => 'set', 'user' => 'editor']);

		$this->assertTrue($result->isSuccess());

		$data = json_decode($result->getOutput(), true);
		$this->assertSame(2, $data['id']);
		$this->assertSame('editor', $data['login']);
	}

	/**
	 * Tests that set action by email resolves correctly.
	 */
	public function test_execute_withSetAction_byEmail_resolvesCorrectly(): void
	{
		$admin = $this->createUserData();
		$tool = $this->createTool(
			set_user_fn: function (string $identifier) use ($admin): array|false {
				if ($identifier === 'admin@example.com') {
					return $admin;
				}

				return false;
			},
		);

		$result = $tool->execute(['action' => 'set', 'user' => 'admin@example.com']);

		$this->assertTrue($result->isSuccess());

		$data = json_decode($result->getOutput(), true);
		$this->assertSame(1, $data['id']);
		$this->assertSame('admin@example.com', $data['email']);
	}

	/**
	 * Tests that set action with unknown user returns failure.
	 */
	public function test_execute_withSetAction_withUnknownUser_returnsFailure(): void
	{
		$tool = $this->createTool(
			set_user_fn: fn (string $identifier) => false,
		);

		$result = $tool->execute(['action' => 'set', 'user' => 'nonexistent']);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString('User not found: nonexistent', $result->getError());
		$this->assertStringContainsString("action 'list'", $result->getError());
	}

	/**
	 * Tests that set action with missing user param returns failure.
	 */
	public function test_execute_withSetAction_withMissingUser_returnsFailure(): void
	{
		$tool = $this->createTool();

		$result = $tool->execute(['action' => 'set']);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString("user parameter is required for 'set' action", $result->getError());
	}

	/**
	 * Tests that set action with empty user string returns failure.
	 */
	public function test_execute_withSetAction_withEmptyUser_returnsFailure(): void
	{
		$tool = $this->createTool();

		$result = $tool->execute(['action' => 'set', 'user' => '']);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString("user parameter is required for 'set' action", $result->getError());
	}

	// ---------------------------------------------------------------
	// Current action
	// ---------------------------------------------------------------

	/**
	 * Tests that current action returns user data with logged_in true.
	 */
	public function test_execute_withCurrentAction_withLoggedInUser_returnsUserData(): void
	{
		$tool = $this->createTool(
			get_current_user_fn: fn () => [
				'id' => 1,
				'login' => 'admin',
				'display_name' => 'Admin',
				'email' => 'admin@example.com',
				'roles' => ['administrator'],
				'logged_in' => true,
			],
		);

		$result = $tool->execute(['action' => 'current']);

		$this->assertTrue($result->isSuccess());

		$data = json_decode($result->getOutput(), true);
		$this->assertSame(1, $data['id']);
		$this->assertSame('admin', $data['login']);
		$this->assertSame('Admin', $data['display_name']);
		$this->assertSame('admin@example.com', $data['email']);
		$this->assertSame(['administrator'], $data['roles']);
		$this->assertTrue($data['logged_in']);
	}

	/**
	 * Tests that current action with no user set returns logged_in false.
	 */
	public function test_execute_withCurrentAction_withNoUser_returnsLoggedInFalse(): void
	{
		$tool = $this->createTool(
			get_current_user_fn: fn () => [
				'id' => 0,
				'login' => '',
				'display_name' => '',
				'email' => '',
				'roles' => [],
				'logged_in' => false,
			],
		);

		$result = $tool->execute(['action' => 'current']);

		$this->assertTrue($result->isSuccess());

		$data = json_decode($result->getOutput(), true);
		$this->assertSame(0, $data['id']);
		$this->assertSame('', $data['login']);
		$this->assertSame([], $data['roles']);
		$this->assertFalse($data['logged_in']);
	}

	/**
	 * Tests that current action with non-array return falls back to default data.
	 */
	public function test_execute_withCurrentAction_withNonArrayReturn_returnsDefaultData(): void
	{
		$tool = $this->createTool(
			get_current_user_fn: fn () => null,
		);

		$result = $tool->execute(['action' => 'current']);

		$this->assertTrue($result->isSuccess());

		$data = json_decode($result->getOutput(), true);
		$this->assertSame(0, $data['id']);
		$this->assertSame('', $data['login']);
		$this->assertSame([], $data['roles']);
		$this->assertFalse($data['logged_in']);
	}

	// ---------------------------------------------------------------
	// Invalid / missing action
	// ---------------------------------------------------------------

	/**
	 * Tests that an invalid action returns failure with enum hint.
	 */
	public function test_execute_withInvalidAction_returnsFailure(): void
	{
		$tool = $this->createTool();

		$result = $tool->execute(['action' => 'invalid']);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString("Invalid action 'invalid'", $result->getError());
		$this->assertStringContainsString('list, set, current', $result->getError());
	}

	/**
	 * Tests that a missing action returns failure.
	 */
	public function test_execute_withMissingAction_returnsFailure(): void
	{
		$tool = $this->createTool();

		$result = $tool->execute([]);

		$this->assertFalse($result->isSuccess());
		$this->assertStringContainsString("Invalid action ''", $result->getError());
	}
}
