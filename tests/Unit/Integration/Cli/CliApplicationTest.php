<?php

declare(strict_types=1);

namespace PhpCliAgent\Tests\Unit\Integration\Cli;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PhpCliAgent\Core\Contracts\AgentInterface;
use PhpCliAgent\Core\Contracts\ConfigurationInterface;
use PhpCliAgent\Core\Contracts\OutputHandlerInterface;
use PhpCliAgent\Core\Exceptions\ConfigurationException;
use PhpCliAgent\Core\Exceptions\SessionNotFoundException;
use PhpCliAgent\Core\ValueObjects\SessionId;
use PhpCliAgent\Integration\Cli\CliApplication;

/**
 * Tests for CliApplication.
 *
 * @covers \PhpCliAgent\Integration\Cli\CliApplication
 */
final class CliApplicationTest extends TestCase
{
	private ConfigurationInterface&MockObject $configuration;
	private AgentInterface&MockObject $agent;
	private OutputHandlerInterface&MockObject $output_handler;
	private CliApplication $app;

	protected function setUp(): void
	{
		$this->configuration = $this->createMock(ConfigurationInterface::class);
		$this->agent = $this->createMock(AgentInterface::class);
		$this->output_handler = $this->createMock(OutputHandlerInterface::class);

		$this->app = new CliApplication(
			$this->configuration,
			$this->agent,
			$this->output_handler
		);
	}

	public function test_parseArguments_withHelpFlag_setsHelpTrue(): void
	{
		$this->app->parseArguments(['agent', '--help']);

		$args = $this->app->getParsedArgs();
		$this->assertTrue($args['help']);
	}

	public function test_parseArguments_withShortHelpFlag_setsHelpTrue(): void
	{
		$this->app->parseArguments(['agent', '-h']);

		$args = $this->app->getParsedArgs();
		$this->assertTrue($args['help']);
	}

	public function test_parseArguments_withVersionFlag_setsVersionTrue(): void
	{
		$this->app->parseArguments(['agent', '--version']);

		$args = $this->app->getParsedArgs();
		$this->assertTrue($args['version']);
	}

	public function test_parseArguments_withShortVersionFlag_setsVersionTrue(): void
	{
		$this->app->parseArguments(['agent', '-v']);

		$args = $this->app->getParsedArgs();
		$this->assertTrue($args['version']);
	}

	public function test_parseArguments_withNoSaveFlag_setsNoSaveTrue(): void
	{
		$this->app->parseArguments(['agent', '--no-save']);

		$args = $this->app->getParsedArgs();
		$this->assertTrue($args['no_save']);
	}

	public function test_parseArguments_withDebugFlag_setsDebugTrue(): void
	{
		$this->app->parseArguments(['agent', '--debug']);

		$args = $this->app->getParsedArgs();
		$this->assertTrue($args['debug']);
	}

	public function test_parseArguments_withShortDebugFlag_setsDebugTrue(): void
	{
		$this->app->parseArguments(['agent', '-d']);

		$args = $this->app->getParsedArgs();
		$this->assertTrue($args['debug']);
	}

	public function test_parseArguments_withConfigPath_setsConfigPath(): void
	{
		$this->app->parseArguments(['agent', '--config=/path/to/config.yaml']);

		$args = $this->app->getParsedArgs();
		$this->assertSame('/path/to/config.yaml', $args['config']);
	}

	public function test_parseArguments_withShortConfigPath_setsConfigPath(): void
	{
		$this->app->parseArguments(['agent', '-c/path/to/config.yaml']);

		$args = $this->app->getParsedArgs();
		$this->assertSame('/path/to/config.yaml', $args['config']);
	}

	public function test_parseArguments_withSessionId_setsSessionId(): void
	{
		$this->app->parseArguments(['agent', '--session=abc123']);

		$args = $this->app->getParsedArgs();
		$this->assertSame('abc123', $args['session']);
	}

	public function test_parseArguments_withShortSessionId_setsSessionId(): void
	{
		$this->app->parseArguments(['agent', '-sabc123']);

		$args = $this->app->getParsedArgs();
		$this->assertSame('abc123', $args['session']);
	}

	public function test_parseArguments_withMultipleFlags_setsAllFlags(): void
	{
		$this->app->parseArguments(['agent', '--debug', '--no-save', '--config=/config.yaml']);

		$args = $this->app->getParsedArgs();
		$this->assertTrue($args['debug']);
		$this->assertTrue($args['no_save']);
		$this->assertSame('/config.yaml', $args['config']);
	}

	public function test_parseArguments_withUnknownOption_throwsException(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Unknown option: --unknown');

		$this->app->parseArguments(['agent', '--unknown']);
	}

	public function test_parseArguments_withShortConfigMissingValue_throwsException(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Option -c requires a value');

		$this->app->parseArguments(['agent', '-c']);
	}

	public function test_parseArguments_withShortSessionMissingValue_throwsException(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Option -s requires a value');

		$this->app->parseArguments(['agent', '-s']);
	}

	public function test_run_withHelpFlag_showsHelpAndReturnsSuccess(): void
	{
		$this->output_handler->expects($this->atLeastOnce())
			->method('writeLine');

		$exit_code = $this->app->run(['agent', '--help']);

		$this->assertSame(CliApplication::EXIT_SUCCESS, $exit_code);
	}

	public function test_run_withVersionFlag_showsVersionAndReturnsSuccess(): void
	{
		$this->output_handler->expects($this->once())
			->method('writeLine')
			->with($this->stringContains('PHP CLI Agent'));

		$exit_code = $this->app->run(['agent', '--version']);

		$this->assertSame(CliApplication::EXIT_SUCCESS, $exit_code);
	}

	public function test_run_withInvalidOption_returnsInvalidArgsExitCode(): void
	{
		$this->output_handler->expects($this->once())
			->method('writeError')
			->with($this->stringContains('Invalid argument'));

		$exit_code = $this->app->run(['agent', '--invalid-option']);

		$this->assertSame(CliApplication::EXIT_INVALID_ARGS, $exit_code);
	}

	public function test_run_withConfigError_returnsErrorExitCode(): void
	{
		$this->configuration->method('loadFromFile')
			->willThrowException(new ConfigurationException('Config file not found'));

		$this->output_handler->expects($this->once())
			->method('writeError')
			->with($this->stringContains('Configuration error'));

		$exit_code = $this->app->run(['agent', '--config=/nonexistent.yaml']);

		$this->assertSame(CliApplication::EXIT_ERROR, $exit_code);
	}

	public function test_run_withSessionNotFound_returnsErrorExitCode(): void
	{
		$session_id = SessionId::fromString('nonexistent');

		$this->agent->method('resumeSession')
			->with($this->callback(function ($id) {
				return $id instanceof SessionId && $id->toString() === 'nonexistent';
			}))
			->willThrowException(new SessionNotFoundException($session_id));

		$this->output_handler->expects($this->once())
			->method('writeError')
			->with($this->stringContains('Session not found'));

		$exit_code = $this->app->run(['agent', '--session=nonexistent']);

		$this->assertSame(CliApplication::EXIT_ERROR, $exit_code);
	}

	public function test_showHelp_outputsHelpText(): void
	{
		$expected_lines = [
			'PHP CLI Agent',
			'--config',
			'--session',
			'--no-save',
			'--debug',
			'--help',
			'--version',
		];

		$this->output_handler->expects($this->once())
			->method('writeLine')
			->with($this->callback(function (string $output) use ($expected_lines) {
				foreach ($expected_lines as $line) {
					if (strpos($output, $line) === false) {
						return false;
					}
				}
				return true;
			}));

		$this->app->showHelp();
	}

	public function test_showVersion_outputsVersionString(): void
	{
		$this->output_handler->expects($this->once())
			->method('writeLine')
			->with($this->stringContains(CliApplication::VERSION));

		$this->app->showVersion();
	}

	public function test_getFullVersion_returnsFormattedVersionString(): void
	{
		$version = $this->app->getFullVersion();

		$this->assertStringContainsString(CliApplication::NAME, $version);
		$this->assertStringContainsString(CliApplication::VERSION, $version);
	}

	public function test_getAgent_returnsInjectedAgent(): void
	{
		$this->assertSame($this->agent, $this->app->getAgent());
	}

	public function test_getConfiguration_returnsInjectedConfiguration(): void
	{
		$this->assertSame($this->configuration, $this->app->getConfiguration());
	}

	public function test_getOutputHandler_returnsInjectedOutputHandler(): void
	{
		$this->assertSame($this->output_handler, $this->app->getOutputHandler());
	}

	public function test_constants_haveExpectedValues(): void
	{
		$this->assertSame(0, CliApplication::EXIT_SUCCESS);
		$this->assertSame(1, CliApplication::EXIT_ERROR);
		$this->assertSame(2, CliApplication::EXIT_INVALID_ARGS);
		$this->assertSame('0.1.0', CliApplication::VERSION);
		$this->assertSame('PHP CLI Agent', CliApplication::NAME);
	}

	public function test_parseArguments_withNoArgs_hasDefaultValues(): void
	{
		$this->app->parseArguments(['agent']);

		$args = $this->app->getParsedArgs();
		$this->assertNull($args['config']);
		$this->assertNull($args['session']);
		$this->assertNull($args['subcommand']);
		$this->assertFalse($args['no_save']);
		$this->assertFalse($args['help']);
		$this->assertFalse($args['version']);
		$this->assertFalse($args['debug']);
		$this->assertFalse($args['force']);
	}

	public function test_parseArguments_skipsScriptNameCorrectly(): void
	{
		// Ensure the first argument (script name) is properly skipped.
		$this->app->parseArguments(['/usr/bin/agent', '--help', '--debug']);

		$args = $this->app->getParsedArgs();
		$this->assertTrue($args['help']);
		$this->assertTrue($args['debug']);
	}

	public function test_parseArguments_handlesEmptyArgv(): void
	{
		$this->app->parseArguments([]);

		$args = $this->app->getParsedArgs();
		$this->assertFalse($args['help']);
		$this->assertFalse($args['version']);
	}

	public function test_parseArguments_withInitSubcommand_setsSubcommand(): void
	{
		$this->app->parseArguments(['agent', 'init']);

		$args = $this->app->getParsedArgs();
		$this->assertSame('init', $args['subcommand']);
	}

	public function test_parseArguments_withInitAndForceFlag_setsSubcommandAndForce(): void
	{
		$this->app->parseArguments(['agent', 'init', '--force']);

		$args = $this->app->getParsedArgs();
		$this->assertSame('init', $args['subcommand']);
		$this->assertTrue($args['force']);
	}

	public function test_parseArguments_withInitAndShortForceFlag_setsSubcommandAndForce(): void
	{
		$this->app->parseArguments(['agent', 'init', '-f']);

		$args = $this->app->getParsedArgs();
		$this->assertSame('init', $args['subcommand']);
		$this->assertTrue($args['force']);
	}

	public function test_parseArguments_withNoSubcommand_hasNullSubcommand(): void
	{
		$this->app->parseArguments(['agent']);

		$args = $this->app->getParsedArgs();
		$this->assertNull($args['subcommand']);
	}

	public function test_run_earlyReturnsOnHelpBeforeLoadingConfig(): void
	{
		// Configuration should NOT be loaded when showing help.
		$this->configuration->expects($this->never())
			->method('loadFromFile');

		$this->output_handler->expects($this->atLeastOnce())
			->method('writeLine');

		$exit_code = $this->app->run(['agent', '--help', '--config=/path/to/config.yaml']);

		$this->assertSame(CliApplication::EXIT_SUCCESS, $exit_code);
	}

	public function test_run_earlyReturnsOnVersionBeforeLoadingConfig(): void
	{
		// Configuration should NOT be loaded when showing version.
		$this->configuration->expects($this->never())
			->method('loadFromFile');

		$this->output_handler->expects($this->once())
			->method('writeLine');

		$exit_code = $this->app->run(['agent', '--version', '--config=/path/to/config.yaml']);

		$this->assertSame(CliApplication::EXIT_SUCCESS, $exit_code);
	}

	public function test_run_withInitSubcommand_executesInitCommand(): void
	{
		// Agent should NOT be called when running init command.
		$this->agent->expects($this->never())
			->method('startSession');

		// Configuration should NOT be loaded when running init command.
		$this->configuration->expects($this->never())
			->method('loadFromFile');

		// Create a temporary directory for testing.
		$temp_dir = sys_get_temp_dir() . '/php-cli-agent-test-' . uniqid();
		mkdir($temp_dir, 0755, true);

		// Use reflection to create an app that initializes in the temp directory.
		$app = new CliApplication(
			$this->configuration,
			$this->agent,
			$this->output_handler
		);

		// Change to temp directory before running.
		$original_dir = getcwd();
		chdir($temp_dir);

		try {
			$exit_code = $app->run(['agent', 'init', '--force']);

			$this->assertSame(CliApplication::EXIT_SUCCESS, $exit_code);
			$this->assertDirectoryExists($temp_dir . '/.php-cli-agent');
			$this->assertFileExists($temp_dir . '/.php-cli-agent/settings.json');
			$this->assertFileExists($temp_dir . '/.php-cli-agent/mcp.json');
		} finally {
			chdir((string) $original_dir);
			// Clean up.
			if (file_exists($temp_dir . '/.php-cli-agent/settings.json')) {
				unlink($temp_dir . '/.php-cli-agent/settings.json');
			}
			if (file_exists($temp_dir . '/.php-cli-agent/mcp.json')) {
				unlink($temp_dir . '/.php-cli-agent/mcp.json');
			}
			if (is_dir($temp_dir . '/.php-cli-agent')) {
				rmdir($temp_dir . '/.php-cli-agent');
			}
			if (is_dir($temp_dir)) {
				rmdir($temp_dir);
			}
		}
	}

	public function test_run_withInitSubcommand_doesNotStartSession(): void
	{
		// Agent should NOT be called when running init command.
		$this->agent->expects($this->never())
			->method('startSession');

		$this->agent->expects($this->never())
			->method('resumeSession');

		// Create a temporary directory for testing.
		$temp_dir = sys_get_temp_dir() . '/php-cli-agent-test-' . uniqid();
		mkdir($temp_dir, 0755, true);

		$original_dir = getcwd();
		chdir($temp_dir);

		try {
			$exit_code = $this->app->run(['agent', 'init', '--force']);
			$this->assertSame(CliApplication::EXIT_SUCCESS, $exit_code);
		} finally {
			chdir((string) $original_dir);
			// Clean up.
			if (file_exists($temp_dir . '/.php-cli-agent/settings.json')) {
				unlink($temp_dir . '/.php-cli-agent/settings.json');
			}
			if (file_exists($temp_dir . '/.php-cli-agent/mcp.json')) {
				unlink($temp_dir . '/.php-cli-agent/mcp.json');
			}
			if (is_dir($temp_dir . '/.php-cli-agent')) {
				rmdir($temp_dir . '/.php-cli-agent');
			}
			if (is_dir($temp_dir)) {
				rmdir($temp_dir);
			}
		}
	}

	public function test_showHelp_includesInitCommand(): void
	{
		$this->output_handler->expects($this->once())
			->method('writeLine')
			->with($this->callback(function (string $output) {
				return strpos($output, 'init') !== false
					&& strpos($output, 'Initialize') !== false;
			}));

		$this->app->showHelp();
	}

	public function test_showHelp_includesForceOption(): void
	{
		$this->output_handler->expects($this->once())
			->method('writeLine')
			->with($this->callback(function (string $output) {
				return strpos($output, '--force') !== false
					&& strpos($output, '-f') !== false;
			}));

		$this->app->showHelp();
	}
}
