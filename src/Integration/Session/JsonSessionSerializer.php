<?php

declare(strict_types=1);

namespace PhpCliAgent\Integration\Session;

use PhpCliAgent\Core\Contracts\SessionInterface;
use PhpCliAgent\Core\Exceptions\SessionPersistenceException;
use PhpCliAgent\Core\Session\Session;

/**
 * Serializer for converting sessions to/from JSON format.
 *
 * Handles the transformation of Session objects to JSON strings and back,
 * providing proper error handling for malformed data.
 *
 * @since n.e.x.t
 */
final class JsonSessionSerializer
{
	/**
	 * JSON encoding flags for session serialization.
	 */
	private const JSON_ENCODE_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

	/**
	 * Serializes a session to a JSON string.
	 *
	 * @param SessionInterface $session The session to serialize.
	 *
	 * @return string The JSON representation of the session.
	 *
	 * @throws SessionPersistenceException If serialization fails.
	 */
	public function serialize(SessionInterface $session): string
	{
		$data = $session->toArray();

		$json = json_encode($data, self::JSON_ENCODE_FLAGS);

		if (false === $json) {
			throw SessionPersistenceException::saveFailed(
				sprintf('JSON encoding failed: %s', json_last_error_msg())
			);
		}

		return $json;
	}

	/**
	 * Deserializes a JSON string back to a Session object.
	 *
	 * @param string $json The JSON string to deserialize.
	 *
	 * @return Session The reconstructed session.
	 *
	 * @throws SessionPersistenceException If deserialization fails.
	 */
	public function deserialize(string $json): Session
	{
		if ('' === trim($json)) {
			throw SessionPersistenceException::loadFailed('Empty JSON data');
		}

		$data = json_decode($json, true);

		if (null === $data && JSON_ERROR_NONE !== json_last_error()) {
			throw SessionPersistenceException::loadFailed(
				sprintf('JSON decoding failed: %s', json_last_error_msg())
			);
		}

		if (!is_array($data)) {
			throw SessionPersistenceException::loadFailed('Invalid JSON structure: expected object');
		}

		$this->validateSessionData($data);

		try {
			/** @var array{id: string, system_prompt: string, messages: array<int, array<string, mixed>>, metadata: array<string, mixed>, token_usage?: array{input: int, output: int}} $data */
			return Session::fromArray($data);
		} catch (\Throwable $exception) {
			throw SessionPersistenceException::loadFailed(
				sprintf('Failed to reconstruct session: %s', $exception->getMessage()),
				$exception
			);
		}
	}

	/**
	 * Validates that the decoded JSON data contains required session fields.
	 *
	 * @param array<string, mixed> $data The decoded JSON data.
	 *
	 * @return void
	 *
	 * @throws SessionPersistenceException If required fields are missing.
	 */
	private function validateSessionData(array $data): void
	{
		$required_fields = ['id', 'system_prompt', 'messages', 'metadata'];
		$missing_fields = [];

		foreach ($required_fields as $field) {
			if (!array_key_exists($field, $data)) {
				$missing_fields[] = $field;
			}
		}

		if (0 !== count($missing_fields)) {
			throw SessionPersistenceException::loadFailed(
				sprintf('Missing required fields: %s', implode(', ', $missing_fields))
			);
		}

		if (!is_string($data['id'])) {
			throw SessionPersistenceException::loadFailed('Field "id" must be a string');
		}

		if (!is_string($data['system_prompt'])) {
			throw SessionPersistenceException::loadFailed('Field "system_prompt" must be a string');
		}

		if (!is_array($data['messages'])) {
			throw SessionPersistenceException::loadFailed('Field "messages" must be an array');
		}

		if (!is_array($data['metadata'])) {
			throw SessionPersistenceException::loadFailed('Field "metadata" must be an array');
		}
	}
}
