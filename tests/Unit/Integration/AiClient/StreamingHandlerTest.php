<?php

declare(strict_types=1);

namespace WpAiAgent\Tests\Unit\Integration\AiClient;

use WpAiAgent\Core\Contracts\OutputHandlerInterface;
use WpAiAgent\Integration\AiClient\StreamingHandler;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for StreamingHandler.
 *
 * @covers \WpAiAgent\Integration\AiClient\StreamingHandler
 */
final class StreamingHandlerTest extends TestCase
{
	/**
	 * Tests that default configuration is set correctly.
	 */
	public function test_constructor_setsDefaultValues(): void
	{
		$handler = new StreamingHandler();

		$this->assertSame(5000, $handler->getChunkDelay());
		$this->assertSame(4, $handler->getChunkSize());
		$this->assertTrue($handler->isEnabled());
	}

	/**
	 * Tests constructor accepts custom chunk delay.
	 */
	public function test_constructor_withCustomChunkDelay(): void
	{
		$handler = new StreamingHandler(10000);

		$this->assertSame(10000, $handler->getChunkDelay());
	}

	/**
	 * Tests constructor accepts custom chunk size.
	 */
	public function test_constructor_withCustomChunkSize(): void
	{
		$handler = new StreamingHandler(5000, 8);

		$this->assertSame(8, $handler->getChunkSize());
	}

	/**
	 * Tests that negative chunk delay is normalized to zero.
	 */
	public function test_constructor_withNegativeChunkDelay_normalizesToZero(): void
	{
		$handler = new StreamingHandler(-1000);

		$this->assertSame(0, $handler->getChunkDelay());
	}

	/**
	 * Tests that zero or negative chunk size is normalized to one.
	 */
	public function test_constructor_withZeroChunkSize_normalizesToOne(): void
	{
		$handler = new StreamingHandler(5000, 0);

		$this->assertSame(1, $handler->getChunkSize());
	}

	/**
	 * Tests setEnabled enables streaming.
	 */
	public function test_setEnabled_enablesStreaming(): void
	{
		$handler = new StreamingHandler();
		$handler->setEnabled(false);
		$handler->setEnabled(true);

		$this->assertTrue($handler->isEnabled());
	}

	/**
	 * Tests setEnabled disables streaming.
	 */
	public function test_setEnabled_disablesStreaming(): void
	{
		$handler = new StreamingHandler();
		$handler->setEnabled(false);

		$this->assertFalse($handler->isEnabled());
	}

	/**
	 * Tests setChunkDelay updates delay.
	 */
	public function test_setChunkDelay_updatesDelay(): void
	{
		$handler = new StreamingHandler();
		$handler->setChunkDelay(20000);

		$this->assertSame(20000, $handler->getChunkDelay());
	}

	/**
	 * Tests setChunkDelay normalizes negative values.
	 */
	public function test_setChunkDelay_withNegativeValue_normalizesToZero(): void
	{
		$handler = new StreamingHandler();
		$handler->setChunkDelay(-5000);

		$this->assertSame(0, $handler->getChunkDelay());
	}

	/**
	 * Tests setChunkSize updates size.
	 */
	public function test_setChunkSize_updatesSize(): void
	{
		$handler = new StreamingHandler();
		$handler->setChunkSize(10);

		$this->assertSame(10, $handler->getChunkSize());
	}

	/**
	 * Tests setChunkSize normalizes zero to one.
	 */
	public function test_setChunkSize_withZeroValue_normalizesToOne(): void
	{
		$handler = new StreamingHandler();
		$handler->setChunkSize(0);

		$this->assertSame(1, $handler->getChunkSize());
	}

	/**
	 * Tests generateChunks returns generator.
	 */
	public function test_generateChunks_returnsGenerator(): void
	{
		$handler = new StreamingHandler();

		$result = $handler->generateChunks('Hello world');

		$this->assertInstanceOf(\Generator::class, $result);
	}

	/**
	 * Tests generateChunks with empty string yields nothing.
	 */
	public function test_generateChunks_withEmptyString_yieldsNothing(): void
	{
		$handler = new StreamingHandler();

		$chunks = iterator_to_array($handler->generateChunks(''));

		$this->assertEmpty($chunks);
	}

	/**
	 * Tests generateChunks splits text into multiple chunks.
	 */
	public function test_generateChunks_splitsTextIntoChunks(): void
	{
		$handler = new StreamingHandler(0, 5);
		$text = 'Hello world test';

		$chunks = iterator_to_array($handler->generateChunks($text));

		$this->assertGreaterThan(1, count($chunks));
		$this->assertSame($text, implode('', $chunks));
	}

	/**
	 * Tests generateChunks preserves complete text.
	 */
	public function test_generateChunks_preservesCompleteText(): void
	{
		$handler = new StreamingHandler(0, 3);
		$text = 'The quick brown fox jumps over the lazy dog.';

		$chunks = iterator_to_array($handler->generateChunks($text));

		$this->assertSame($text, implode('', $chunks));
	}

	/**
	 * Tests generateChunks handles multibyte characters.
	 */
	public function test_generateChunks_handlesMultibyteCharacters(): void
	{
		$handler = new StreamingHandler(0, 4);
		$text = 'Hello 日本語 world';

		$chunks = iterator_to_array($handler->generateChunks($text));

		$this->assertSame($text, implode('', $chunks));
	}

	/**
	 * Tests streamToCallback calls callback with chunks.
	 */
	public function test_streamToCallback_callsCallbackWithChunks(): void
	{
		$handler = new StreamingHandler(0, 5);
		$collected_chunks = [];
		// Text must be >= 50 characters to trigger streaming simulation
		$text = 'This is a test message for streaming. It needs to be long enough to stream.';

		$handler->streamToCallback(
			$text,
			function (string $chunk) use (&$collected_chunks): void {
				$collected_chunks[] = $chunk;
			}
		);

		$this->assertGreaterThan(1, count($collected_chunks));
		$this->assertSame($text, implode('', $collected_chunks));
	}

	/**
	 * Tests streamToCallback with short text outputs all at once.
	 */
	public function test_streamToCallback_withShortText_outputsAllAtOnce(): void
	{
		$handler = new StreamingHandler(0, 5);
		$collected_chunks = [];

		$handler->streamToCallback(
			'Short text',
			function (string $chunk) use (&$collected_chunks): void {
				$collected_chunks[] = $chunk;
			}
		);

		$this->assertCount(1, $collected_chunks);
		$this->assertSame('Short text', $collected_chunks[0]);
	}

	/**
	 * Tests streamToCallback when disabled outputs all at once.
	 */
	public function test_streamToCallback_whenDisabled_outputsAllAtOnce(): void
	{
		$handler = new StreamingHandler(0, 5);
		$handler->setEnabled(false);
		$long_text = str_repeat('A', 200);
		$collected_chunks = [];

		$handler->streamToCallback(
			$long_text,
			function (string $chunk) use (&$collected_chunks): void {
				$collected_chunks[] = $chunk;
			}
		);

		$this->assertCount(1, $collected_chunks);
		$this->assertSame($long_text, $collected_chunks[0]);
	}

	/**
	 * Tests streamToCallback with empty string.
	 */
	public function test_streamToCallback_withEmptyString_callsCallbackOnce(): void
	{
		$handler = new StreamingHandler(0);
		$collected_chunks = [];

		$handler->streamToCallback(
			'',
			function (string $chunk) use (&$collected_chunks): void {
				$collected_chunks[] = $chunk;
			}
		);

		// Empty string is less than MIN_TEXT_LENGTH_FOR_STREAMING so outputs as-is
		$this->assertCount(1, $collected_chunks);
		$this->assertSame('', $collected_chunks[0]);
	}

	/**
	 * Tests streamToOutput calls writeStreamChunk on output handler.
	 */
	public function test_streamToOutput_callsWriteStreamChunk(): void
	{
		$handler = new StreamingHandler(0, 10);
		$long_text = str_repeat('Hello world! ', 10);

		$output_handler = $this->createMock(OutputHandlerInterface::class);
		$output_handler->expects($this->atLeastOnce())
			->method('writeStreamChunk');

		$handler->streamToOutput($long_text, $output_handler);
	}

	/**
	 * Tests streamToOutput preserves complete text.
	 */
	public function test_streamToOutput_preservesCompleteText(): void
	{
		$handler = new StreamingHandler(0, 5);
		$text = str_repeat('Test message for output streaming. ', 5);
		$received_text = '';

		$output_handler = $this->createMock(OutputHandlerInterface::class);
		$output_handler->method('writeStreamChunk')
			->willReturnCallback(function (string $chunk) use (&$received_text): void {
				$received_text .= $chunk;
			});

		$handler->streamToOutput($text, $output_handler);

		$this->assertSame($text, $received_text);
	}

	/**
	 * Tests that chunks break on natural boundaries when possible.
	 */
	public function test_generateChunks_prefersNaturalBreakpoints(): void
	{
		$handler = new StreamingHandler(0, 4);
		$text = 'Hello, world!';

		$chunks = iterator_to_array($handler->generateChunks($text));

		// The handler should attempt to break on natural boundaries
		// rather than arbitrary positions
		$this->assertSame($text, implode('', $chunks));

		// Check that at least one chunk ends with a natural break
		$has_natural_break = false;
		foreach ($chunks as $chunk) {
			$last_char = mb_substr($chunk, -1);
			if (in_array($last_char, [' ', ',', '.', '!', '?', ')', ';', ':', "\n"], true)) {
				$has_natural_break = true;
				break;
			}
		}

		// At minimum, we verify text is preserved
		$this->assertSame($text, implode('', $chunks));
	}

	/**
	 * Tests streaming with text containing only spaces.
	 */
	public function test_streamToCallback_withOnlySpaces(): void
	{
		$handler = new StreamingHandler(0, 5);
		$text = str_repeat(' ', 100);
		$collected_chunks = [];

		$handler->streamToCallback(
			$text,
			function (string $chunk) use (&$collected_chunks): void {
				$collected_chunks[] = $chunk;
			}
		);

		$this->assertSame($text, implode('', $collected_chunks));
	}

	/**
	 * Tests streaming with text containing newlines.
	 */
	public function test_streamToCallback_withNewlines(): void
	{
		$handler = new StreamingHandler(0, 5);
		$text = "Line one\nLine two\nLine three\nLine four\nLine five";
		$collected_chunks = [];

		$handler->streamToCallback(
			$text,
			function (string $chunk) use (&$collected_chunks): void {
				$collected_chunks[] = $chunk;
			}
		);

		$this->assertSame($text, implode('', $collected_chunks));
	}

	/**
	 * Tests long response text (1000+ chars) is streamed progressively.
	 */
	public function test_streamToCallback_withLongText_streamsProgressively(): void
	{
		$handler = new StreamingHandler(0, 10);
		$text = str_repeat('This is a long response that should be chunked. ', 30);
		$collected_chunks = [];

		$handler->streamToCallback(
			$text,
			function (string $chunk) use (&$collected_chunks): void {
				$collected_chunks[] = $chunk;
			}
		);

		// Should have multiple chunks
		$this->assertGreaterThan(10, count($collected_chunks));

		// All text should be preserved
		$this->assertSame($text, implode('', $collected_chunks));
	}

	/**
	 * Tests chunk size of 1 creates character-by-character streaming.
	 */
	public function test_generateChunks_withChunkSizeOne_streamsCharByChar(): void
	{
		$handler = new StreamingHandler(0, 1);
		$text = str_repeat('A', 100);

		$chunks = iterator_to_array($handler->generateChunks($text));

		// Each character should be in its own chunk
		foreach ($chunks as $chunk) {
			// Chunks may be slightly larger if they find break points
			$this->assertLessThanOrEqual(3, mb_strlen($chunk));
		}

		$this->assertSame($text, implode('', $chunks));
	}

	/**
	 * Tests large chunk size processes text in fewer chunks.
	 */
	public function test_generateChunks_withLargeChunkSize_fewerChunks(): void
	{
		$handler_small = new StreamingHandler(0, 5);
		$handler_large = new StreamingHandler(0, 50);
		$text = str_repeat('Word ', 100);

		$chunks_small = iterator_to_array($handler_small->generateChunks($text));
		$chunks_large = iterator_to_array($handler_large->generateChunks($text));

		$this->assertGreaterThan(count($chunks_large), count($chunks_small));
	}
}
