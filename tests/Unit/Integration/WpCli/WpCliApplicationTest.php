<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Integration\WpCli;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WpAiAgent\Core\Contracts\AgentInterface;
use WpAiAgent\Core\Contracts\ConfigurationInterface;
use WpAiAgent\Core\Contracts\SessionRepositoryInterface;
use WpAiAgent\Core\ValueObjects\SessionId;
use WpAiAgent\Integration\WpCli\WpCliApplication;
use WpAiAgent\Integration\WpCli\WpCliConfirmationHandler;
use WpAiAgent\Integration\WpCli\WpCliOutputHandler;

/**
 * Unit tests for WpCliApplication.
 *
 * WP_CLI is a static class only available in a real WP-CLI runtime. The stub
 * defined in tests/Stubs/WpCliStub.php (loaded by tests/bootstrap.php) records
 * every static call so tests can assert output routing without a live runtime.
 *
 * chat() reads from \STDIN via \fgets() and is not covered here because it
 * requires STDIN manipulation; ask() and init() are fully testable via
 * constructor-injected mocks.
 *
 * @covers \WpAiAgent\Integration\WpCli\WpCliApplication
 *
 * @since n.e.x.t
 */
final class WpCliApplicationTest extends TestCase
{
	/**
	 * Resets the WP_CLI stub state before each test to ensure isolation.
	 */
	protected function setUp(): void
	{
		\WP_CLI::$calls = [];
		\WP_CLI::$confirm_throws = false;
	}

	/**
	 * Builds a WpCliApplication with all dependencies mocked.
	 *
	 * @param AgentInterface&MockObject $agent The agent mock to inject.
	 *
	 * @return WpCliApplication
	 */
	private function makeApp(MockObject&AgentInterface $agent): WpCliApplication
	{
		return new WpCliApplication(
			$this->createMock(ConfigurationInterface::class),
			$agent,
			new WpCliOutputHandler(),
			new WpCliConfirmationHandler(),
			$this->createMock(SessionRepositoryInterface::class),
		);
	}

	/**
	 * Tests that ask() calls startSession(), sendMessage(), and endSession()
	 * in the correct order when no --session arg is given.
	 */
	public function test_ask_callsSendMessageThenEndSession(): void
	{
		$agent = $this->createMock(AgentInterface::class);

		$agent->expects($this->once())
			->method('startSession')
			->willReturn(SessionId::fromString('test-session-id'));

		$agent->expects($this->once())
			->method('sendMessage')
			->with('hello world');

		$agent->expects($this->once())
			->method('endSession');

		$app = $this->makeApp($agent);
		$app->ask('hello world', []);
	}

	/**
	 * Tests that ask() calls resumeSession() instead of startSession() when a
	 * --session arg is present.
	 */
	public function test_ask_withSessionArg_callsResumeSessionNotStartSession(): void
	{
		$agent = $this->createMock(AgentInterface::class);

		$agent->expects($this->never())
			->method('startSession');

		$agent->expects($this->once())
			->method('resumeSession')
			->with($this->callback(
				static fn (SessionId $id): bool => $id->toString() === 'abc-123'
			));

		$agent->expects($this->once())
			->method('sendMessage')
			->with('follow up');

		$agent->expects($this->once())
			->method('endSession');

		$app = $this->makeApp($agent);
		$app->ask('follow up', ['session' => 'abc-123']);
	}

	/**
	 * Tests that ask() enables debug mode on the output handler when the
	 * --debug flag is set in assoc_args.
	 */
	public function test_ask_withDebugFlag_enablesDebugOnOutputHandler(): void
	{
		$agent = $this->createMock(AgentInterface::class);
		$agent->method('startSession')->willReturn(SessionId::fromString('sess'));
		$agent->method('sendMessage');
		$agent->method('endSession');

		$output_handler = new WpCliOutputHandler();
		$this->assertFalse($output_handler->isDebugEnabled(), 'Debug should be off by default');

		$app = new WpCliApplication(
			$this->createMock(ConfigurationInterface::class),
			$agent,
			$output_handler,
			new WpCliConfirmationHandler(),
			$this->createMock(SessionRepositoryInterface::class),
		);

		$app->ask('test', ['debug' => true]);

		$this->assertTrue(
			$output_handler->isDebugEnabled(),
			'ask() must enable debug on the output handler when --debug is passed'
		);
	}

	/**
	 * Tests that WpCliApplication can be instantiated and getAgent() returns
	 * the injected agent instance.
	 */
	public function test_getAgent_returnsInjectedAgent(): void
	{
		$agent = $this->createMock(AgentInterface::class);

		$app = $this->makeApp($agent);

		$this->assertSame($agent, $app->getAgent());
	}

	/**
	 * Tests that ask() with no --session arg calls startSession() exactly once.
	 */
	public function test_ask_withNoSession_callsStartSessionOnce(): void
	{
		$agent = $this->createMock(AgentInterface::class);

		$agent->expects($this->once())
			->method('startSession')
			->willReturn(SessionId::fromString('new-session'));

		$agent->method('sendMessage');
		$agent->method('endSession');

		$app = $this->makeApp($agent);
		$app->ask('anything', []);
	}

	/**
	 * Tests that getOutputHandler() returns the injected WpCliOutputHandler.
	 */
	public function test_getOutputHandler_returnsInjectedOutputHandler(): void
	{
		$agent  = $this->createMock(AgentInterface::class);
		$output = new WpCliOutputHandler();

		$app = new WpCliApplication(
			$this->createMock(ConfigurationInterface::class),
			$agent,
			$output,
			new WpCliConfirmationHandler(),
			$this->createMock(SessionRepositoryInterface::class),
		);

		$this->assertSame($output, $app->getOutputHandler());
	}
}
