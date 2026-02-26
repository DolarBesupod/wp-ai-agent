<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Integration\WpCli;

use PHPUnit\Framework\TestCase;
use Tests\Stubs\WpOptionsStore;
use WpAiAgent\Core\Credential\AuthMode;
use WpAiAgent\Integration\WpCli\CredentialResolver;
use WpAiAgent\Integration\WpCli\WpCliAuthCommand;
use WpAiAgent\Integration\WpCli\WpOptionsCredentialRepository;

/**
 * Unit tests for WpCliAuthCommand.
 *
 * Tests cover all four subcommands: set, get, delete, and status. WP_CLI
 * calls are captured by the stub in tests/Stubs/WpCliStub.php. WordPress
 * option functions use the in-memory WpOptionsStore.
 *
 * @covers \WpAiAgent\Integration\WpCli\WpCliAuthCommand
 *
 * @since n.e.x.t
 */
final class WpCliAuthCommandTest extends TestCase
{
	/**
	 * Resets all shared stub state before each test.
	 */
	protected function setUp(): void
	{
		\WP_CLI::$calls = [];
		\WP_CLI::$confirm_throws = false;
		WpOptionsStore::reset();
	}

	/**
	 * Tests that set() prompts for a secret and stores it via the repository.
	 */
	public function test_set_withValidProvider_promptsAndStoresCredential(): void
	{
		$repository = new WpOptionsCredentialRepository();
		$resolver = new CredentialResolver($repository, $this->noEnvGetter(), $this->noConstantChecker());
		$command = new WpCliAuthCommand(
			$repository,
			$resolver,
			static fn(string $message): string => 'sk-ant-test-secret-1234'
		);

		$command->set([], ['provider' => 'anthropic']);

		$this->assertTrue($repository->hasCredential('anthropic'), 'Credential must be stored');

		$credential = $repository->getCredential('anthropic');
		$this->assertSame('sk-ant-test-secret-1234', $credential->getSecret());
		$this->assertSame(AuthMode::API_KEY, $credential->getAuthMode());

		$success_calls = $this->filterCalls('success');
		$this->assertNotEmpty($success_calls, 'set() must call WP_CLI::success()');
		$this->assertStringContainsString('anthropic', $success_calls[0][1]);
	}

	/**
	 * Tests that set() stores a subscription credential when mode=subscription is provided.
	 */
	public function test_set_withSubscriptionMode_storesSubscriptionCredential(): void
	{
		$repository = new WpOptionsCredentialRepository();
		$resolver = new CredentialResolver($repository, $this->noEnvGetter(), $this->noConstantChecker());
		$command = new WpCliAuthCommand(
			$repository,
			$resolver,
			static fn(string $message): string => 'sk-ant-oat01-' . str_repeat('a', 90)
		);

		$command->set([], ['provider' => 'anthropic', 'mode' => 'subscription']);

		$credential = $repository->getCredential('anthropic');
		$this->assertSame(AuthMode::SUBSCRIPTION, $credential->getAuthMode());

		$log_calls = $this->filterCalls('log');
		$log_messages = array_map(static fn(array $c): string => $c[1], $log_calls);
		$this->assertStringContainsString(
			'claude setup-token',
			implode("\n", $log_messages),
			'Subscription mode should instruct user about setup-token workflow'
		);
	}

	/**
	 * Tests that invalid subscription token is rejected and not stored.
	 */
	public function test_set_withInvalidSubscriptionToken_callsWpCliError(): void
	{
		$repository = new WpOptionsCredentialRepository();
		$resolver = new CredentialResolver($repository, $this->noEnvGetter(), $this->noConstantChecker());
		$command = new WpCliAuthCommand(
			$repository,
			$resolver,
			static fn(string $message): string => 'bad-token'
		);

		$command->set([], ['provider' => 'anthropic', 'mode' => 'subscription']);

		$error_calls = $this->filterCalls('error');
		$this->assertNotEmpty($error_calls);
		$this->assertStringContainsString('setup-token', $error_calls[0][1]);
		$this->assertFalse($repository->hasCredential('anthropic'));
	}

	/**
	 * Tests that set() with an empty secret calls WP_CLI::error().
	 */
	public function test_set_withEmptySecret_callsWpCliError(): void
	{
		$repository = new WpOptionsCredentialRepository();
		$resolver = new CredentialResolver($repository, $this->noEnvGetter(), $this->noConstantChecker());
		$command = new WpCliAuthCommand(
			$repository,
			$resolver,
			static fn(string $message): string => ''
		);

		$command->set([], ['provider' => 'anthropic']);

		$error_calls = $this->filterCalls('error');
		$this->assertNotEmpty($error_calls, 'set() with empty secret must call WP_CLI::error()');
		$this->assertStringContainsString('empty', $error_calls[0][1]);

		$this->assertFalse($repository->hasCredential('anthropic'), 'No credential should be stored');
	}

	/**
	 * Tests that set() without --provider calls WP_CLI::error().
	 */
	public function test_set_withoutProvider_callsWpCliError(): void
	{
		$repository = new WpOptionsCredentialRepository();
		$resolver = new CredentialResolver($repository, $this->noEnvGetter(), $this->noConstantChecker());
		$command = new WpCliAuthCommand($repository, $resolver);

		$command->set([], []);

		$error_calls = $this->filterCalls('error');
		$this->assertNotEmpty($error_calls, 'set() without --provider must call WP_CLI::error()');
		$this->assertStringContainsString('--provider', $error_calls[0][1]);
	}

	/**
	 * Tests that get() displays masked credential info via WP_CLI::log().
	 */
	public function test_get_withExistingCredential_displaysMaskedInfo(): void
	{
		$repository = new WpOptionsCredentialRepository();
		$repository->setCredential('anthropic', AuthMode::API_KEY, 'sk-ant-test-secret-1234');

		$resolver = new CredentialResolver($repository, $this->noEnvGetter(), $this->noConstantChecker());
		$command = new WpCliAuthCommand($repository, $resolver);

		$command->get([], ['provider' => 'anthropic']);

		$log_calls = $this->filterCalls('log');
		$this->assertNotEmpty($log_calls, 'get() must call WP_CLI::log()');

		$log_messages = array_map(static fn(array $c): string => $c[1], $log_calls);
		$all_output = implode("\n", $log_messages);

		$this->assertStringContainsString('anthropic', $all_output, 'Output must contain provider name');
		$this->assertStringContainsString('api_key', $all_output, 'Output must contain auth mode');
		$this->assertStringContainsString('sk-ant-t****', $all_output, 'Output must contain masked secret');
		$this->assertStringNotContainsString(
			'sk-ant-test-secret-1234',
			$all_output,
			'Output must never contain raw secret'
		);
	}

	/**
	 * Tests that get() with a nonexistent provider calls WP_CLI::error().
	 */
	public function test_get_withNonexistentProvider_callsWpCliError(): void
	{
		$repository = new WpOptionsCredentialRepository();
		$resolver = new CredentialResolver($repository, $this->noEnvGetter(), $this->noConstantChecker());
		$command = new WpCliAuthCommand($repository, $resolver);

		$command->get([], ['provider' => 'nonexistent']);

		$error_calls = $this->filterCalls('error');
		$this->assertNotEmpty($error_calls, 'get() with nonexistent provider must call WP_CLI::error()');
		$this->assertStringContainsString('nonexistent', $error_calls[0][1]);
	}

	/**
	 * Tests that delete() removes the credential and calls WP_CLI::success().
	 */
	public function test_delete_withExistingCredential_removesAndShowsSuccess(): void
	{
		$repository = new WpOptionsCredentialRepository();
		$repository->setCredential('anthropic', AuthMode::API_KEY, 'sk-ant-test-secret');

		$resolver = new CredentialResolver($repository, $this->noEnvGetter(), $this->noConstantChecker());
		$command = new WpCliAuthCommand($repository, $resolver);

		$command->delete([], ['provider' => 'anthropic']);

		$this->assertFalse($repository->hasCredential('anthropic'), 'Credential must be deleted');

		$success_calls = $this->filterCalls('success');
		$this->assertNotEmpty($success_calls, 'delete() must call WP_CLI::success()');
		$this->assertStringContainsString('anthropic', $success_calls[0][1]);
	}

	/**
	 * Tests that delete() with a nonexistent provider calls WP_CLI::error().
	 */
	public function test_delete_withNonexistentProvider_callsWpCliError(): void
	{
		$repository = new WpOptionsCredentialRepository();
		$resolver = new CredentialResolver($repository, $this->noEnvGetter(), $this->noConstantChecker());
		$command = new WpCliAuthCommand($repository, $resolver);

		$command->delete([], ['provider' => 'nonexistent']);

		$error_calls = $this->filterCalls('error');
		$this->assertNotEmpty($error_calls, 'delete() with nonexistent provider must call WP_CLI::error()');
		$this->assertStringContainsString('nonexistent', $error_calls[0][1]);
	}

	/**
	 * Tests that status() shows a table of all credentials via format_items().
	 */
	public function test_status_withCredentials_showsFormattedTable(): void
	{
		$repository = new WpOptionsCredentialRepository();
		$repository->setCredential('anthropic', AuthMode::API_KEY, 'sk-ant-constant-key');

		$resolver = new CredentialResolver(
			$repository,
			$this->noEnvGetter(),
			static fn(string $name): string|false => $name === 'ANTHROPIC_API_KEY' ? 'sk-ant-constant-key' : false
		);
		$command = new WpCliAuthCommand($repository, $resolver);

		$command->status([], []);

		$format_calls = $this->filterCalls('format_items');
		$this->assertNotEmpty($format_calls, 'status() must call WP_CLI\\Utils\\format_items()');

		$call = $format_calls[0];
		$this->assertSame('table', $call[1], 'status() must request table format');
		$this->assertSame(
			['provider', 'auth_mode', 'source', 'secret'],
			$call[3],
			'status() must pass the expected columns'
		);

		/** @var array<int, array<string, string>> $rows */
		$rows = $call[2];
		$this->assertNotEmpty($rows, 'Table must have at least one row');

		$anthropic_row = null;
		foreach ($rows as $row) {
			if ($row['provider'] === 'anthropic') {
				$anthropic_row = $row;
				break;
			}
		}

		$this->assertNotNull($anthropic_row, 'Table must contain an anthropic row');
		$this->assertSame('constant', $anthropic_row['source'], 'Constant source takes priority');
		$this->assertSame('sk-ant-c****', $anthropic_row['secret'], 'Secret must be masked');
	}

	/**
	 * Returns a callable that filters WP_CLI::$calls by method name.
	 *
	 * @param string $method The WP_CLI method name to filter by.
	 *
	 * @return array<int, array<int, mixed>> The filtered calls, re-indexed.
	 */
	private function filterCalls(string $method): array
	{
		return array_values(array_filter(
			\WP_CLI::$calls,
			static fn(array $c): bool => $c[0] === $method
		));
	}

	/**
	 * Returns an env getter callable that always returns false (no env vars).
	 *
	 * @return callable
	 */
	private function noEnvGetter(): callable
	{
		return static fn(string $name): string|false => false;
	}

	/**
	 * Returns a constant checker callable that always returns false (no constants).
	 *
	 * @return callable
	 */
	private function noConstantChecker(): callable
	{
		return static fn(string $name): string|false => false;
	}
}
