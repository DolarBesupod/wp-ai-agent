<?php

// phpcs:disable
// Minimal WP_Ability stub for unit tests.
//
// The real WP_Ability class ships with WordPress 6.9+ core and is available
// in the php-stubs/wordpress-stubs package (loaded by PHPStan). This
// lightweight stub provides just enough behaviour for tests that exercise
// AbilityToolAdapter and AbilityToolRegistry without loading WordPress.
//
// Tests configure behaviour by passing an associative $args array to the
// constructor. The stub stores those values and returns them from the
// corresponding getter methods.

declare(strict_types=1);

if (!class_exists('WP_Ability')) {
	/**
	 * Minimal WP_Ability stub for unit tests.
	 *
	 * Configurable via the constructor $args array. Each getter returns the
	 * value provided in $args, or a sensible default.
	 *
	 * @since n.e.x.t
	 */
	class WP_Ability
	{
		/**
		 * The ability name (e.g. 'core/get-site-info').
		 *
		 * @var string
		 */
		private string $name;

		/**
		 * The human-readable label.
		 *
		 * @var string
		 */
		private string $label;

		/**
		 * The detailed description.
		 *
		 * @var string
		 */
		private string $description;

		/**
		 * The input schema array.
		 *
		 * @var array<string, mixed>
		 */
		private array $input_schema;

		/**
		 * The metadata array.
		 *
		 * @var array<string, mixed>
		 */
		private array $meta;

		/**
		 * The value returned by execute().
		 *
		 * @var mixed
		 */
		private mixed $execute_result;

		/**
		 * The value returned by check_permissions().
		 *
		 * @var bool|\WP_Error
		 */
		private bool|\WP_Error $permission_result;

		/**
		 * Constructs the stub.
		 *
		 * Matches the real WP_Ability constructor signature (name + args).
		 * Tests pass the desired return values in the $args array.
		 *
		 * @since n.e.x.t
		 *
		 * @param string               $name The ability name (e.g. 'core/get-site-info').
		 * @param array<string, mixed> $args {
		 *     Configuration for the stub.
		 *
		 *     @type string               $label             The human-readable label. Default ''.
		 *     @type string               $description       The detailed description. Default ''.
		 *     @type array<string, mixed>  $input_schema      The input schema. Default [].
		 *     @type array<string, mixed>  $meta              The metadata. Default [].
		 *     @type mixed                 $execute_result    The value returned by execute(). Default [].
		 *     @type bool|\WP_Error        $permission_result The value returned by check_permissions(). Default true.
		 * }
		 */
		public function __construct(string $name, array $args = [])
		{
			$this->name = $name;
			$this->label = (string) ($args['label'] ?? '');
			$this->description = (string) ($args['description'] ?? '');
			$this->input_schema = (array) ($args['input_schema'] ?? []);
			$this->meta = (array) ($args['meta'] ?? []);
			$this->execute_result = $args['execute_result'] ?? [];
			$this->permission_result = $args['permission_result'] ?? true;
		}

		/**
		 * Retrieves the name of the ability.
		 *
		 * @since n.e.x.t
		 *
		 * @return string The ability name.
		 */
		public function get_name(): string
		{
			return $this->name;
		}

		/**
		 * Retrieves the human-readable label.
		 *
		 * @since n.e.x.t
		 *
		 * @return string The label, or empty string if not set.
		 */
		public function get_label(): string
		{
			return $this->label;
		}

		/**
		 * Retrieves the detailed description.
		 *
		 * @since n.e.x.t
		 *
		 * @return string The description.
		 */
		public function get_description(): string
		{
			return $this->description;
		}

		/**
		 * Retrieves the input schema.
		 *
		 * @since n.e.x.t
		 *
		 * @return array<string, mixed> The input schema array.
		 */
		public function get_input_schema(): array
		{
			return $this->input_schema;
		}

		/**
		 * Retrieves the metadata.
		 *
		 * @since n.e.x.t
		 *
		 * @return array<string, mixed> The metadata array.
		 */
		public function get_meta(): array
		{
			return $this->meta;
		}

		/**
		 * Checks whether the ability has the necessary permissions.
		 *
		 * Returns the value configured via the constructor's $args['permission_result'].
		 *
		 * @since n.e.x.t
		 *
		 * @param mixed $input Optional. The input data. Default null.
		 *
		 * @return bool|\WP_Error Whether the ability has permission.
		 */
		public function check_permissions(mixed $input = null): bool|\WP_Error
		{
			return $this->permission_result;
		}

		/**
		 * Executes the ability.
		 *
		 * Returns the value configured via the constructor's $args['execute_result'].
		 *
		 * @since n.e.x.t
		 *
		 * @param mixed $input Optional. The input data. Default null.
		 *
		 * @return mixed The execution result, or WP_Error on failure.
		 */
		public function execute(mixed $input = null): mixed
		{
			return $this->execute_result;
		}
	}
}
// phpcs:enable
