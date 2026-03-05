<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Contracts;

/**
 * Interface for session metadata.
 *
 * Session metadata stores information about the session such as creation time,
 * last update time, working directory, and other contextual information that
 * persists across session resumption.
 *
 * @since n.e.x.t
 */
interface SessionMetadataInterface
{
	/**
	 * Returns the session creation timestamp.
	 *
	 * @return \DateTimeImmutable
	 */
	public function getCreatedAt(): \DateTimeImmutable;

	/**
	 * Returns the last update timestamp.
	 *
	 * @return \DateTimeImmutable
	 */
	public function getUpdatedAt(): \DateTimeImmutable;

	/**
	 * Sets the last update timestamp.
	 *
	 * @param \DateTimeImmutable $timestamp The new update timestamp.
	 *
	 * @return void
	 */
	public function setUpdatedAt(\DateTimeImmutable $timestamp): void;

	/**
	 * Returns the working directory for the session.
	 *
	 * @return string
	 */
	public function getWorkingDirectory(): string;

	/**
	 * Sets the working directory.
	 *
	 * @param string $directory The working directory path.
	 *
	 * @return void
	 */
	public function setWorkingDirectory(string $directory): void;

	/**
	 * Returns a custom metadata value.
	 *
	 * @param string $key     The metadata key.
	 * @param mixed  $default The default value if key doesn't exist.
	 *
	 * @return mixed
	 */
	public function get(string $key, mixed $default = null): mixed;

	/**
	 * Sets a custom metadata value.
	 *
	 * @param string $key   The metadata key.
	 * @param mixed  $value The value to store.
	 *
	 * @return void
	 */
	public function set(string $key, mixed $value): void;

	/**
	 * Checks if a metadata key exists.
	 *
	 * @param string $key The metadata key.
	 *
	 * @return bool
	 */
	public function has(string $key): bool;

	/**
	 * Removes a metadata key.
	 *
	 * @param string $key The metadata key.
	 *
	 * @return void
	 */
	public function remove(string $key): void;

	/**
	 * Returns all custom metadata.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array;

	/**
	 * Converts the metadata to an array for serialization.
	 *
	 * @return array{
	 *     created_at: string,
	 *     updated_at: string,
	 *     working_directory: string,
	 *     custom: array<string, mixed>
	 * }
	 */
	public function toArray(): array;

	/**
	 * Returns the session title if set.
	 *
	 * The title is typically derived from the first user message
	 * or can be explicitly set.
	 *
	 * @return string|null The session title or null if not set.
	 */
	public function getTitle(): ?string;

	/**
	 * Sets the session title.
	 *
	 * @param string $title The session title.
	 *
	 * @return void
	 */
	public function setTitle(string $title): void;
}
