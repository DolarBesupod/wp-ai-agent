<?php

declare(strict_types=1);

namespace WpAiAgent\Integration\AiClient;

use WpAiAgent\Core\Contracts\OutputHandlerInterface;

/**
 * Handles streaming responses from the AI client.
 *
 * Since the php-ai-client library does not currently support native streaming
 * for Anthropic models, this handler provides simulated streaming by splitting
 * the complete response text into chunks and outputting them progressively.
 *
 * The handler supports two modes:
 * - Callback mode: Chunks are passed to a provided callback function
 * - Output handler mode: Chunks are written via OutputHandlerInterface
 *
 * @since n.e.x.t
 */
final class StreamingHandler
{
	/**
	 * Default delay between chunks in microseconds.
	 *
	 * @var int
	 */
	private const DEFAULT_CHUNK_DELAY_MICROSECONDS = 5000;

	/**
	 * Default chunk size in characters.
	 *
	 * @var int
	 */
	private const DEFAULT_CHUNK_SIZE = 4;

	/**
	 * Minimum text length to enable streaming simulation.
	 *
	 * Texts shorter than this will be output all at once.
	 *
	 * @var int
	 */
	private const MIN_TEXT_LENGTH_FOR_STREAMING = 50;

	/**
	 * Delay between chunks in microseconds.
	 *
	 * @var int
	 */
	private int $chunk_delay;

	/**
	 * Size of each chunk in characters.
	 *
	 * @var int
	 */
	private int $chunk_size;

	/**
	 * Whether streaming simulation is enabled.
	 *
	 * @var bool
	 */
	private bool $enabled = true;

	/**
	 * Creates a new StreamingHandler instance.
	 *
	 * @param int $chunk_delay Delay between chunks in microseconds (default: 5000).
	 * @param int $chunk_size  Size of each chunk in characters (default: 4).
	 */
	public function __construct(
		int $chunk_delay = self::DEFAULT_CHUNK_DELAY_MICROSECONDS,
		int $chunk_size = self::DEFAULT_CHUNK_SIZE
	) {
		$this->chunk_delay = max(0, $chunk_delay);
		$this->chunk_size = max(1, $chunk_size);
	}

	/**
	 * Streams text to the provided callback function.
	 *
	 * Splits the text into chunks and calls the callback with each chunk,
	 * simulating streaming output from the AI.
	 *
	 * @param string              $text     The complete text to stream.
	 * @param callable(string): void $callback The callback to receive each chunk.
	 */
	public function streamToCallback(string $text, callable $callback): void
	{
		if (!$this->shouldStream($text)) {
			$callback($text);
			return;
		}

		foreach ($this->generateChunks($text) as $chunk) {
			$callback($chunk);

			if ($this->chunk_delay > 0) {
				usleep($this->chunk_delay);
			}
		}
	}

	/**
	 * Streams text to an OutputHandler.
	 *
	 * Writes chunks to the output handler using writeStreamChunk(),
	 * which is designed for real-time streaming display.
	 *
	 * @param string                 $text           The complete text to stream.
	 * @param OutputHandlerInterface $output_handler The output handler for display.
	 */
	public function streamToOutput(string $text, OutputHandlerInterface $output_handler): void
	{
		$this->streamToCallback(
			$text,
			static function (string $chunk) use ($output_handler): void {
				$output_handler->writeStreamChunk($chunk);
			}
		);
	}

	/**
	 * Generates chunks from the text.
	 *
	 * Attempts to split on natural boundaries (words, punctuation) when possible,
	 * falling back to fixed-size chunks for long words or non-space text.
	 *
	 * @param string $text The text to chunk.
	 *
	 * @return \Generator<int, string, null, void> Generator yielding text chunks.
	 */
	public function generateChunks(string $text): \Generator
	{
		if ($text === '') {
			return;
		}

		$length = mb_strlen($text);
		$position = 0;

		while ($position < $length) {
			$chunk = $this->extractNextChunk($text, $position, $length);
			$chunk_length = mb_strlen($chunk);

			if ($chunk_length === 0) {
				break;
			}

			yield $chunk;
			$position += $chunk_length;
		}
	}

	/**
	 * Enables or disables streaming simulation.
	 *
	 * When disabled, text is output all at once without chunking.
	 *
	 * @param bool $enabled Whether to enable streaming.
	 */
	public function setEnabled(bool $enabled): void
	{
		$this->enabled = $enabled;
	}

	/**
	 * Checks if streaming simulation is enabled.
	 *
	 * @return bool True if enabled.
	 */
	public function isEnabled(): bool
	{
		return $this->enabled;
	}

	/**
	 * Sets the delay between chunks.
	 *
	 * @param int $microseconds Delay in microseconds.
	 */
	public function setChunkDelay(int $microseconds): void
	{
		$this->chunk_delay = max(0, $microseconds);
	}

	/**
	 * Gets the current chunk delay.
	 *
	 * @return int Delay in microseconds.
	 */
	public function getChunkDelay(): int
	{
		return $this->chunk_delay;
	}

	/**
	 * Sets the chunk size.
	 *
	 * @param int $characters Number of characters per chunk.
	 */
	public function setChunkSize(int $characters): void
	{
		$this->chunk_size = max(1, $characters);
	}

	/**
	 * Gets the current chunk size.
	 *
	 * @return int Characters per chunk.
	 */
	public function getChunkSize(): int
	{
		return $this->chunk_size;
	}

	/**
	 * Determines if text should be streamed.
	 *
	 * @param string $text The text to check.
	 *
	 * @return bool True if text should be streamed.
	 */
	private function shouldStream(string $text): bool
	{
		if (!$this->enabled) {
			return false;
		}

		return mb_strlen($text) >= self::MIN_TEXT_LENGTH_FOR_STREAMING;
	}

	/**
	 * Extracts the next chunk from the text at the given position.
	 *
	 * Attempts to find natural break points (spaces, punctuation) to create
	 * more readable chunks. Falls back to fixed-size chunks when necessary.
	 *
	 * @param string $text     The full text.
	 * @param int    $position Current position in the text.
	 * @param int    $length   Total length of the text.
	 *
	 * @return string The extracted chunk.
	 */
	private function extractNextChunk(string $text, int $position, int $length): string
	{
		$remaining = $length - $position;

		if ($remaining <= $this->chunk_size) {
			return mb_substr($text, $position);
		}

		$chunk = mb_substr($text, $position, $this->chunk_size);

		$break_position = $this->findBreakPosition($text, $position, $this->chunk_size * 2);

		if ($break_position !== null && $break_position > $position) {
			$extended_length = $break_position - $position + 1;

			if ($extended_length <= $this->chunk_size * 2) {
				return mb_substr($text, $position, $extended_length);
			}
		}

		return $chunk;
	}

	/**
	 * Finds a natural break position (space, newline, punctuation) within range.
	 *
	 * @param string $text      The full text.
	 * @param int    $start     Start position to search from.
	 * @param int    $max_range Maximum range to search within.
	 *
	 * @return int|null The break position, or null if not found.
	 */
	private function findBreakPosition(string $text, int $start, int $max_range): ?int
	{
		$search_start = $start + $this->chunk_size - 1;
		$search_end = min($start + $max_range, mb_strlen($text) - 1);

		for ($i = $search_start; $i <= $search_end; $i++) {
			$char = mb_substr($text, $i, 1);

			if ($this->isBreakCharacter($char)) {
				return $i;
			}
		}

		return null;
	}

	/**
	 * Checks if a character is a natural break point.
	 *
	 * @param string $char The character to check.
	 *
	 * @return bool True if the character is a break point.
	 */
	private function isBreakCharacter(string $char): bool
	{
		return in_array($char, [' ', "\n", "\r", '.', ',', ';', ':', '!', '?', ')'], true);
	}
}
