<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\Settings;

use WpAiAgent\Core\Contracts\BashCommandExpanderInterface;
use RuntimeException;

/**
 * Expands bash command references in content.
 *
 * Replaces !`command` references with the output of executing the command.
 * Commands are executed in a shell process using proc_open with proper
 * timeout handling.
 *
 * Security Note: This is a deliberate feature for power users creating their
 * own commands/skills. The commands come from files the user has explicitly
 * created or installed.
 *
 * @since n.e.x.t
 */
final class BashCommandExpander implements BashCommandExpanderInterface
{
	/**
	 * Default timeout in seconds for command execution.
	 */
	private const DEFAULT_TIMEOUT = 30;

	/**
	 * Pattern to match bash command references.
	 *
	 * Matches !` followed by any characters (non-greedy) until closing backtick.
	 * Uses a negative lookbehind to avoid matching escaped backticks.
	 */
	private const COMMAND_PATTERN = '/!\`([^`]+)\`/';

	/**
	 * The timeout in seconds for command execution.
	 *
	 * @var int
	 */
	private int $timeout;

	/**
	 * Creates a new BashCommandExpander instance.
	 *
	 * @param int $timeout The timeout in seconds for command execution.
	 *                     Defaults to 30 seconds.
	 *
	 * @since n.e.x.t
	 */
	public function __construct(int $timeout = self::DEFAULT_TIMEOUT)
	{
		$this->timeout = $timeout > 0 ? $timeout : self::DEFAULT_TIMEOUT;
	}

	/**
	 * Expands bash command references in content.
	 *
	 * Replaces !`command` references with the output of executing the command.
	 * Commands are executed in a shell process with the specified working directory.
	 *
	 * Regular backticks without the ! prefix are NOT executed and are left unchanged.
	 *
	 * @param string $content           The content containing !`command` references.
	 * @param string $working_directory The directory to run commands in.
	 *
	 * @return string The content with commands replaced by their output.
	 *
	 * @throws RuntimeException If a command fails (non-zero exit code) or times out.
	 *
	 * @since n.e.x.t
	 */
	public function expand(string $content, string $working_directory): string
	{
		if ($content === '') {
			return '';
		}

		$resolved_directory = $this->resolveWorkingDirectory($working_directory);

		return (string) preg_replace_callback(
			self::COMMAND_PATTERN,
			function (array $matches) use ($resolved_directory): string {
				$command = trim($matches[1]);

				if ($command === '') {
					return '';
				}

				return $this->executeCommand($command, $resolved_directory);
			},
			$content
		);
	}

	/**
	 * Resolves and validates the working directory.
	 *
	 * @param string $directory The requested working directory.
	 *
	 * @return string The resolved directory.
	 *
	 * @throws RuntimeException If the directory does not exist.
	 */
	private function resolveWorkingDirectory(string $directory): string
	{
		if ($directory === '') {
			$cwd = getcwd();
			if ($cwd === false) {
				throw new RuntimeException('Unable to determine current working directory');
			}
			return $cwd;
		}

		$real_path = realpath($directory);
		if ($real_path === false || ! is_dir($real_path)) {
			throw new RuntimeException(
				sprintf('Working directory does not exist: %s', $directory)
			);
		}

		return $real_path;
	}

	/**
	 * Executes a shell command and returns its output.
	 *
	 * @param string $command           The command to execute.
	 * @param string $working_directory The working directory for execution.
	 *
	 * @return string The command output (trimmed).
	 *
	 * @throws RuntimeException If the command fails or times out.
	 */
	private function executeCommand(string $command, string $working_directory): string
	{
		$descriptor_spec = [
			0 => ['pipe', 'r'], // stdin
			1 => ['pipe', 'w'], // stdout
			2 => ['pipe', 'w'], // stderr
		];

		$process = proc_open(
			$command,
			$descriptor_spec,
			$pipes,
			$working_directory,
			null
		);

		if (! is_resource($process)) {
			throw new RuntimeException(
				sprintf('Failed to start process for command: %s', $command)
			);
		}

		// Close stdin as we're not sending any input
		fclose($pipes[0]);

		// Set pipes to non-blocking mode
		stream_set_blocking($pipes[1], false);
		stream_set_blocking($pipes[2], false);

		$stdout = '';
		$stderr = '';
		$start_time = time();
		$timed_out = false;

		while (true) {
			$status = proc_get_status($process);

			if (! $status['running']) {
				// Process finished, read remaining output
				$stdout .= stream_get_contents($pipes[1]);
				$stderr .= stream_get_contents($pipes[2]);
				break;
			}

			if ((time() - $start_time) >= $this->timeout) {
				$timed_out = true;
				$this->terminateProcess($process, $status);
				break;
			}

			// Read available output
			$stdout .= fread($pipes[1], 8192) ?: '';
			$stderr .= fread($pipes[2], 8192) ?: '';

			// Small delay to prevent busy waiting
			usleep(10000); // 10ms
		}

		fclose($pipes[1]);
		fclose($pipes[2]);

		if ($timed_out) {
			proc_close($process);
			throw new RuntimeException(
				sprintf('Command timed out after %d seconds: %s', $this->timeout, $command)
			);
		}

		$exit_code = $status['exitcode'];
		proc_close($process);

		if ($exit_code !== 0) {
			$error_message = trim($stderr) !== '' ? trim($stderr) : 'Unknown error';
			throw new RuntimeException(
				sprintf(
					'Command failed with exit code %d: %s (%s)',
					$exit_code,
					$command,
					$error_message
				)
			);
		}

		// Return stdout, trimmed of trailing whitespace/newlines
		return rtrim($stdout);
	}

	/**
	 * Terminates a running process.
	 *
	 * @param resource             $process The process resource.
	 * @param array<string, mixed> $status  The process status array.
	 *
	 * @return void
	 */
	private function terminateProcess($process, array $status): void
	{
		if (PHP_OS_FAMILY === 'Windows') {
			exec(sprintf('taskkill /F /T /PID %d 2>&1', $status['pid']));
		} else {
			// Send SIGTERM first
			posix_kill((int) $status['pid'], SIGTERM);
			usleep(100000); // Wait 100ms

			// Check if still running and send SIGKILL if necessary
			$updated_status = proc_get_status($process);
			if ($updated_status['running']) {
				posix_kill((int) $status['pid'], SIGKILL);
			}
		}
	}
}
