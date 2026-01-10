<?php

declare(strict_types=1);

namespace PhpCliAgent\Integration\Tool\BuiltIn;

use PhpCliAgent\Core\Tool\AbstractTool;
use PhpCliAgent\Core\ValueObjects\ToolResult;

/**
 * Tool for executing bash/shell commands.
 *
 * Executes commands in a separate process with proper timeout handling.
 * Always requires user confirmation before execution for security.
 *
 * @since n.e.x.t
 */
class BashTool extends AbstractTool
{
	/**
	 * Default timeout in seconds.
	 */
	private const DEFAULT_TIMEOUT = 120;

	/**
	 * Returns the unique name of the tool.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return 'bash';
	}

	/**
	 * Returns a description of what the tool does.
	 *
	 * @return string
	 */
	public function getDescription(): string
	{
		return 'Execute a bash command in a separate shell process. '
			. 'Returns stdout, stderr, and exit code. '
			. 'Supports timeout and custom working directory.';
	}

	/**
	 * Returns the JSON Schema for the tool's parameters.
	 *
	 * @return array<string, mixed>
	 */
	public function getParametersSchema(): array
	{
		return [
			'type' => 'object',
			'properties' => [
				'command' => [
					'type' => 'string',
					'description' => 'The bash command to execute',
				],
				'timeout' => [
					'type' => 'integer',
					'description' => 'Timeout in seconds (default 120)',
				],
				'cwd' => [
					'type' => 'string',
					'description' => 'Working directory for command execution',
				],
			],
			'required' => ['command'],
		];
	}

	/**
	 * Bash commands always require confirmation.
	 *
	 * @return bool
	 */
	public function requiresConfirmation(): bool
	{
		return true;
	}

	/**
	 * Executes the bash command.
	 *
	 * @param array<string, mixed> $arguments The command arguments.
	 *
	 * @return ToolResult
	 */
	public function execute(array $arguments): ToolResult
	{
		$missing = $this->validateRequiredArguments($arguments, ['command']);
		if (count($missing) > 0) {
			return $this->failure('Missing required argument: command');
		}

		$command = $this->getStringArgument($arguments, 'command');
		$timeout = $this->getIntArgument($arguments, 'timeout', self::DEFAULT_TIMEOUT);
		$cwd = $this->getStringArgument($arguments, 'cwd', '');

		if ($command === '') {
			return $this->failure('Command cannot be empty');
		}

		if ($timeout <= 0) {
			$timeout = self::DEFAULT_TIMEOUT;
		}

		$working_directory = $this->resolveWorkingDirectory($cwd);
		if ($working_directory === null && $cwd !== '') {
			return $this->failure(
				sprintf('Working directory does not exist: %s', $cwd)
			);
		}

		return $this->executeCommand($command, $timeout, $working_directory);
	}

	/**
	 * Resolves and validates the working directory.
	 *
	 * @param string $cwd The requested working directory.
	 *
	 * @return string|null The resolved directory or null if invalid.
	 */
	private function resolveWorkingDirectory(string $cwd): ?string
	{
		if ($cwd === '') {
			return getcwd() ?: null;
		}

		$real_path = realpath($cwd);
		if ($real_path === false || !is_dir($real_path)) {
			return null;
		}

		return $real_path;
	}

	/**
	 * Executes the command using proc_open.
	 *
	 * @param string      $command           The command to execute.
	 * @param int         $timeout           Timeout in seconds.
	 * @param string|null $working_directory Working directory.
	 *
	 * @return ToolResult
	 */
	private function executeCommand(
		string $command,
		int $timeout,
		?string $working_directory
	): ToolResult {
		$descriptor_spec = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];

		$process = proc_open(
			$command,
			$descriptor_spec,
			$pipes,
			$working_directory,
			null
		);

		if (!is_resource($process)) {
			return $this->failure('Failed to start process');
		}

		fclose($pipes[0]);

		stream_set_blocking($pipes[1], false);
		stream_set_blocking($pipes[2], false);

		$stdout = '';
		$stderr = '';
		$start_time = time();
		$timed_out = false;

		while (true) {
			$status = proc_get_status($process);

			if (!$status['running']) {
				$stdout .= stream_get_contents($pipes[1]);
				$stderr .= stream_get_contents($pipes[2]);
				break;
			}

			if ((time() - $start_time) >= $timeout) {
				$timed_out = true;
				$this->terminateProcess($process);
				break;
			}

			$stdout .= fread($pipes[1], 8192) ?: '';
			$stderr .= fread($pipes[2], 8192) ?: '';

			usleep(10000);
		}

		fclose($pipes[1]);
		fclose($pipes[2]);

		if ($timed_out) {
			proc_close($process);
			return $this->failure(
				sprintf('Command timed out after %d seconds', $timeout),
				$this->formatOutput($stdout, $stderr, -1)
			);
		}

		$exit_code = $status['exitcode'];
		proc_close($process);

		$output = $this->formatOutput($stdout, $stderr, $exit_code);

		if ($exit_code !== 0) {
			return $this->failure(
				sprintf('Command exited with code %d', $exit_code),
				$output
			);
		}

		return $this->success($output, [
			'exit_code' => $exit_code,
			'stdout' => $stdout,
			'stderr' => $stderr,
		]);
	}

	/**
	 * Terminates a running process.
	 *
	 * @param resource $process The process resource.
	 *
	 * @return void
	 */
	private function terminateProcess($process): void
	{
		$status = proc_get_status($process);
		if ($status['running']) {
			if (PHP_OS_FAMILY === 'Windows') {
				exec(sprintf('taskkill /F /T /PID %d 2>&1', $status['pid']));
			} else {
				posix_kill($status['pid'], SIGTERM);
				usleep(100000);

				$status = proc_get_status($process);
				if ($status['running']) {
					posix_kill($status['pid'], SIGKILL);
				}
			}
		}
	}

	/**
	 * Formats the command output for display.
	 *
	 * @param string $stdout    Standard output.
	 * @param string $stderr    Standard error output.
	 * @param int    $exit_code The exit code.
	 *
	 * @return string
	 */
	private function formatOutput(string $stdout, string $stderr, int $exit_code): string
	{
		$parts = [];

		$stdout = trim($stdout);
		$stderr = trim($stderr);

		if ($stdout !== '') {
			$parts[] = $stdout;
		}

		if ($stderr !== '') {
			$parts[] = sprintf("[stderr]\n%s", $stderr);
		}

		if (count($parts) === 0) {
			return sprintf('[exit code: %d]', $exit_code);
		}

		return implode("\n\n", $parts);
	}
}
