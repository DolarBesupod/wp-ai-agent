<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Core\Session;

use DateTimeImmutable;
use Automattic\WpAiAgent\Core\Contracts\SessionMetadataInterface;

/**
 * Session metadata implementation.
 *
 * Stores contextual information about a session including timestamps,
 * working directory, and custom key-value pairs for extensibility.
 *
 * @since 0.1.0
 */
final class SessionMetadata implements SessionMetadataInterface
{
	private DateTimeImmutable $created_at;
	private DateTimeImmutable $updated_at;
	private string $working_directory;

	/**
	 * Custom metadata storage.
	 *
	 * @var array<string, mixed>
	 */
	private array $custom = [];

	/**
	 * Creates a new SessionMetadata instance.
	 *
	 * @param DateTimeImmutable|null $created_at        The creation timestamp.
	 * @param DateTimeImmutable|null $updated_at        The last update timestamp.
	 * @param string|null            $working_directory The working directory path.
	 * @param array<string, mixed>   $custom            Custom metadata values.
	 */
	public function __construct(
		?DateTimeImmutable $created_at = null,
		?DateTimeImmutable $updated_at = null,
		?string $working_directory = null,
		array $custom = []
	) {
		$now = new DateTimeImmutable();

		$this->created_at = $created_at ?? $now;
		$this->updated_at = $updated_at ?? $now;
		$this->working_directory = $working_directory ?? getcwd() ?: '';
		$this->custom = $custom;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getCreatedAt(): DateTimeImmutable
	{
		return $this->created_at;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getUpdatedAt(): DateTimeImmutable
	{
		return $this->updated_at;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setUpdatedAt(DateTimeImmutable $timestamp): void
	{
		$this->updated_at = $timestamp;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getWorkingDirectory(): string
	{
		return $this->working_directory;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setWorkingDirectory(string $directory): void
	{
		$this->working_directory = $directory;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get(string $key, mixed $default = null): mixed
	{
		return $this->custom[$key] ?? $default;
	}

	/**
	 * {@inheritDoc}
	 */
	public function set(string $key, mixed $value): void
	{
		$this->custom[$key] = $value;
		$this->updated_at = new DateTimeImmutable();
	}

	/**
	 * {@inheritDoc}
	 */
	public function has(string $key): bool
	{
		return array_key_exists($key, $this->custom);
	}

	/**
	 * {@inheritDoc}
	 */
	public function remove(string $key): void
	{
		unset($this->custom[$key]);
		$this->updated_at = new DateTimeImmutable();
	}

	/**
	 * {@inheritDoc}
	 */
	public function all(): array
	{
		return $this->custom;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'created_at' => $this->created_at->format(DateTimeImmutable::ATOM),
			'updated_at' => $this->updated_at->format(DateTimeImmutable::ATOM),
			'working_directory' => $this->working_directory,
			'custom' => $this->custom,
		];
	}

	/**
	 * Creates a SessionMetadata instance from an array.
	 *
	 * @param array{
	 *     created_at?: string,
	 *     updated_at?: string,
	 *     working_directory?: string,
	 *     custom?: array<string, mixed>
	 * } $data The serialized metadata array.
	 *
	 * @return self
	 *
	 * @throws \Exception If date parsing fails.
	 */
	public static function fromArray(array $data): self
	{
		$created_at = isset($data['created_at'])
			? new DateTimeImmutable($data['created_at'])
			: null;

		$updated_at = isset($data['updated_at'])
			? new DateTimeImmutable($data['updated_at'])
			: null;

		return new self(
			$created_at,
			$updated_at,
			$data['working_directory'] ?? null,
			$data['custom'] ?? []
		);
	}

	/**
	 * Returns the session title if set.
	 *
	 * The title is derived from the first message or can be set explicitly.
	 *
	 * @return string|null
	 */
	public function getTitle(): ?string
	{
		$title = $this->get('title');

		return is_string($title) ? $title : null;
	}

	/**
	 * Sets the session title.
	 *
	 * @param string $title The session title.
	 *
	 * @return void
	 */
	public function setTitle(string $title): void
	{
		$this->set('title', $title);
	}
}
