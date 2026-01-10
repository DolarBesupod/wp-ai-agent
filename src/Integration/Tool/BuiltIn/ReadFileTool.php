<?php

declare(strict_types=1);

namespace PhpCliAgent\Integration\Tool\BuiltIn;

use PhpCliAgent\Core\Tool\AbstractTool;
use PhpCliAgent\Core\ValueObjects\ToolResult;

/**
 * Tool for reading file contents safely.
 *
 * Reads file contents with support for line offset and limit.
 * Detects binary files and prevents reading them as text.
 * Returns line numbers with content for easy reference.
 *
 * @since n.e.x.t
 */
class ReadFileTool extends AbstractTool
{
	/**
	 * Default number of lines to read.
	 */
	private const DEFAULT_LIMIT = 2000;

	/**
	 * Maximum line length before truncation.
	 */
	private const MAX_LINE_LENGTH = 2000;

	/**
	 * Number of bytes to sample for binary detection.
	 */
	private const BINARY_CHECK_BYTES = 8192;

	/**
	 * Returns the unique name of the tool.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return 'read_file';
	}

	/**
	 * Returns a description of what the tool does.
	 *
	 * @return string
	 */
	public function getDescription(): string
	{
		return 'Read the contents of a file. '
			. 'Returns line numbers with content. '
			. 'Supports reading a subset of lines with offset and limit. '
			. 'Detects and rejects binary files.';
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
				'file_path' => [
					'type' => 'string',
					'description' => 'Absolute path to the file',
				],
				'offset' => [
					'type' => 'integer',
					'description' => 'Line number to start from (1-based)',
				],
				'limit' => [
					'type' => 'integer',
					'description' => 'Number of lines to read',
				],
			],
			'required' => ['file_path'],
		];
	}

	/**
	 * Reading files is a safe operation that does not require confirmation.
	 *
	 * @return bool
	 */
	public function requiresConfirmation(): bool
	{
		return false;
	}

	/**
	 * Executes the file read operation.
	 *
	 * @param array<string, mixed> $arguments The tool arguments.
	 *
	 * @return ToolResult
	 */
	public function execute(array $arguments): ToolResult
	{
		$missing = $this->validateRequiredArguments($arguments, ['file_path']);
		if (count($missing) > 0) {
			return $this->failure('Missing required argument: file_path');
		}

		$file_path = $this->getStringArgument($arguments, 'file_path');
		$offset = $this->getIntArgument($arguments, 'offset', 1);
		$limit = $this->getIntArgument($arguments, 'limit', self::DEFAULT_LIMIT);

		if ($file_path === '') {
			return $this->failure('File path cannot be empty');
		}

		if ($offset < 1) {
			$offset = 1;
		}

		if ($limit < 1) {
			$limit = self::DEFAULT_LIMIT;
		}

		return $this->readFile($file_path, $offset, $limit);
	}

	/**
	 * Reads the file content with offset and limit.
	 *
	 * @param string $file_path The file path.
	 * @param int    $offset    Starting line number (1-based).
	 * @param int    $limit     Number of lines to read.
	 *
	 * @return ToolResult
	 */
	private function readFile(string $file_path, int $offset, int $limit): ToolResult
	{
		$real_path = realpath($file_path);
		if ($real_path === false) {
			return $this->failure(
				sprintf('File not found: %s', $file_path)
			);
		}

		if (!is_file($real_path)) {
			return $this->failure(
				sprintf('Path is not a file: %s', $file_path)
			);
		}

		if (!is_readable($real_path)) {
			return $this->failure(
				sprintf('File is not readable: %s', $file_path)
			);
		}

		if ($this->isBinaryFile($real_path)) {
			return $this->failure(
				sprintf('Cannot read binary file as text: %s', $file_path),
				'This file appears to be a binary file (image, executable, etc.) and cannot be displayed as text.'
			);
		}

		return $this->readLines($real_path, $file_path, $offset, $limit);
	}

	/**
	 * Reads lines from the file with numbering.
	 *
	 * @param string $real_path    The resolved file path.
	 * @param string $display_path The original path for display.
	 * @param int    $offset       Starting line number (1-based).
	 * @param int    $limit        Number of lines to read.
	 *
	 * @return ToolResult
	 */
	private function readLines(
		string $real_path,
		string $display_path,
		int $offset,
		int $limit
	): ToolResult {
		$handle = fopen($real_path, 'r');
		if ($handle === false) {
			return $this->failure(
				sprintf('Failed to open file: %s', $display_path)
			);
		}

		$lines = [];
		$current_line = 0;
		$total_lines = 0;
		$lines_read = 0;
		$end_line = $offset + $limit - 1;

		while (($line = fgets($handle)) !== false) {
			$current_line++;
			$total_lines++;

			if ($current_line < $offset) {
				continue;
			}

			if ($current_line > $end_line) {
				while (fgets($handle) !== false) {
					$total_lines++;
				}
				break;
			}

			$line = rtrim($line, "\r\n");

			if (strlen($line) > self::MAX_LINE_LENGTH) {
				$line = substr($line, 0, self::MAX_LINE_LENGTH) . '...';
			}

			$line_number_width = strlen((string) $end_line);
			$lines[] = sprintf(
				'%' . $line_number_width . 'd→%s',
				$current_line,
				$line
			);
			$lines_read++;
		}

		fclose($handle);

		if ($lines_read === 0) {
			if ($total_lines === 0) {
				return $this->success('[Empty file]', [
					'total_lines' => 0,
					'lines_read' => 0,
					'offset' => $offset,
					'limit' => $limit,
				]);
			}

			return $this->failure(
				sprintf(
					'Offset %d is beyond end of file (file has %d lines)',
					$offset,
					$total_lines
				)
			);
		}

		$output = implode("\n", $lines);

		return $this->success($output, [
			'total_lines' => $total_lines,
			'lines_read' => $lines_read,
			'offset' => $offset,
			'limit' => $limit,
			'file_path' => $display_path,
		]);
	}

	/**
	 * Detects if a file is binary by checking for null bytes.
	 *
	 * @param string $file_path The file path to check.
	 *
	 * @return bool True if the file appears to be binary.
	 */
	private function isBinaryFile(string $file_path): bool
	{
		$file_size = filesize($file_path);
		if ($file_size === false || $file_size === 0) {
			return false;
		}

		$handle = fopen($file_path, 'rb');
		if ($handle === false) {
			return false;
		}

		$bytes_to_read = min($file_size, self::BINARY_CHECK_BYTES);
		$sample = fread($handle, $bytes_to_read);
		fclose($handle);

		if ($sample === false || $sample === '') {
			return false;
		}

		if (strpos($sample, "\0") !== false) {
			return true;
		}

		$non_text_bytes = 0;
		$sample_length = strlen($sample);

		for ($i = 0; $i < $sample_length; $i++) {
			$byte = ord($sample[$i]);

			if ($byte < 8) {
				$non_text_bytes++;
			} elseif ($byte > 13 && $byte < 32 && $byte !== 27) {
				$non_text_bytes++;
			}
		}

		$threshold = $sample_length * 0.3;

		return $non_text_bytes > $threshold;
	}
}
