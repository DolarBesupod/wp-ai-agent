<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\AiClient;

use Automattic\WpAiAgent\Core\Exceptions\AiClientException;

/**
 * Parses Server-Sent Events (SSE) response bodies.
 *
 * Extracts typed events and their JSON data from SSE-formatted text
 * returned by streaming API endpoints such as the ChatGPT backend.
 *
 * @since n.e.x.t
 */
final class SseResponseParser
{
	/**
	 * Extracts and JSON-decodes the data from the last SSE event matching the given type.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $sse_body   The raw SSE response body.
	 * @param string $event_type The event type to search for (e.g. "response.completed").
	 *
	 * @return array<string, mixed> The decoded JSON data from the matching event.
	 *
	 * @throws AiClientException If the body is empty, the event type is not found,
	 *                           or the data is not valid JSON.
	 */
	public static function extractEventData(string $sse_body, string $event_type): array
	{
		$trimmed = trim($sse_body);
		if ($trimmed === '') {
			throw AiClientException::emptyResponse();
		}

		$events = self::parseEvents($trimmed);

		$matching_data = null;
		foreach ($events as $event) {
			if ($event['event'] === $event_type) {
				$matching_data = $event['data'];
			}
		}

		if ($matching_data === null) {
			throw AiClientException::sseEventNotFound($event_type);
		}

		$decoded = json_decode($matching_data, true);
		if (!is_array($decoded)) {
			throw AiClientException::streamingFailed(
				sprintf(
					'Invalid JSON in SSE event "%s" data: %s',
					$event_type,
					json_last_error_msg()
				)
			);
		}

		return $decoded;
	}

	/**
	 * Parses an SSE body into an array of event records.
	 *
	 * Each record contains:
	 * - "event": the event type (defaults to "message" if no event: line is present)
	 * - "data": the concatenated data lines (joined with newline)
	 *
	 * @since n.e.x.t
	 *
	 * @param string $sse_body The raw SSE response body.
	 *
	 * @return array<int, array{event: string, data: string}> The parsed events.
	 */
	public static function parseEvents(string $sse_body): array
	{
		$blocks = preg_split('/\n\n+/', $sse_body);
		if ($blocks === false) {
			return [];
		}

		$events = [];
		foreach ($blocks as $block) {
			$block = trim($block);
			if ($block === '') {
				continue;
			}

			$event_type = 'message';
			$data_lines = [];

			$lines = explode("\n", $block);
			foreach ($lines as $line) {
				if (str_starts_with($line, 'event:')) {
					$event_type = trim(substr($line, 6));
				} elseif (str_starts_with($line, 'data:')) {
					$data_lines[] = trim(substr($line, 5));
				}
			}

			if ($data_lines !== []) {
				$events[] = [
					'event' => $event_type,
					'data' => implode("\n", $data_lines),
				];
			}
		}

		return $events;
	}
}
