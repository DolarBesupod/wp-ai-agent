<?php

// phpcs:disable
// Minimal WP_Error stub for unit tests.
//
// The real WP_Error class ships with WordPress core and is available in the
// php-stubs/wordpress-stubs package (loaded by PHPStan). This lightweight
// stub provides just enough behaviour for tests that exercise code paths
// which create or inspect WP_Error instances without loading WordPress.

declare(strict_types=1);

if (!class_exists('WP_Error')) {
	/**
	 * Minimal WP_Error stub for unit tests.
	 *
	 * Stores a single error code, message, and data value. This covers the
	 * common case where production code calls get_error_code(),
	 * get_error_message(), or get_error_data() on a WP_Error returned by
	 * a WordPress API.
	 *
	 * @since n.e.x.t
	 */
	class WP_Error
	{
		/**
		 * The error code.
		 *
		 * @var string|int
		 */
		private string|int $code;

		/**
		 * The error message.
		 *
		 * @var string
		 */
		private string $message;

		/**
		 * Optional error data.
		 *
		 * @var mixed
		 */
		private mixed $data;

		/**
		 * Initializes the error.
		 *
		 * @since n.e.x.t
		 *
		 * @param string|int $code    Error code.
		 * @param string     $message Error message.
		 * @param mixed      $data    Optional. Error data. Default empty string.
		 */
		public function __construct(string|int $code = '', string $message = '', mixed $data = '')
		{
			$this->code = $code;
			$this->message = $message;
			$this->data = $data;
		}

		/**
		 * Retrieves the first error code.
		 *
		 * @since n.e.x.t
		 *
		 * @return string|int The error code, or empty string if none.
		 */
		public function get_error_code(): string|int
		{
			return $this->code;
		}

		/**
		 * Gets the error message for the first error code.
		 *
		 * @since n.e.x.t
		 *
		 * @return string The error message.
		 */
		public function get_error_message(): string
		{
			return $this->message;
		}

		/**
		 * Retrieves the most recently added error data.
		 *
		 * @since n.e.x.t
		 *
		 * @return mixed Error data, if it exists.
		 */
		public function get_error_data(): mixed
		{
			return $this->data;
		}
	}
}
// phpcs:enable
