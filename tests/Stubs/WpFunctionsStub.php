<?php

// phpcs:disable
// This file intentionally uses multi-namespace block syntax (namespace { } braces)
// to define WordPress global functions and the supporting WpOptionsStore class
// in a single file. This pattern is the only valid PHP approach for mixing
// global and named namespaces in one file and is required for test stubs.

namespace {
	if (!function_exists('get_option')) {
		/**
		 * Stub for WordPress get_option().
		 *
		 * Retrieves the value from the in-memory WpOptionsStore.
		 *
		 * @param string $option  The option name.
		 * @param mixed  $default The default value if the option does not exist.
		 *
		 * @return mixed
		 */
		function get_option(string $option, mixed $default = false): mixed
		{
			return \Tests\Stubs\WpOptionsStore::get($option, $default);
		}
	}

	if (!function_exists('update_option')) {
		/**
		 * Stub for WordPress update_option().
		 *
		 * Stores the value in the in-memory WpOptionsStore.
		 *
		 * @param string          $option   The option name.
		 * @param mixed           $value    The value to store.
		 * @param bool|string     $autoload Whether to autoload. Ignored by the stub.
		 *
		 * @return bool Always returns true.
		 */
		function update_option(string $option, mixed $value, bool|string $autoload = true): bool
		{
			\Tests\Stubs\WpOptionsStore::set($option, $value);

			return true;
		}
	}

	if (!function_exists('delete_option')) {
		/**
		 * Stub for WordPress delete_option().
		 *
		 * Removes the value from the in-memory WpOptionsStore.
		 *
		 * @param string $option The option name.
		 *
		 * @return bool True if the option existed, false otherwise.
		 */
		function delete_option(string $option): bool
		{
			return \Tests\Stubs\WpOptionsStore::delete($option);
		}
	}
}

namespace Tests\Stubs {
	/**
	 * In-memory store backing the WordPress option function stubs.
	 *
	 * Tests call WpOptionsStore::reset() in setUp() to ensure complete
	 * isolation between test cases.
	 */
	class WpOptionsStore
	{
		/**
		 * The in-memory option store.
		 *
		 * @var array<string, mixed>
		 */
		private static array $store = [];

		/**
		 * Returns the value for the given option key, or the default if absent.
		 *
		 * @param string $key     The option name.
		 * @param mixed  $default The default value.
		 *
		 * @return mixed
		 */
		public static function get(string $key, mixed $default): mixed
		{
			if (array_key_exists($key, self::$store)) {
				return self::$store[$key];
			}

			return $default;
		}

		/**
		 * Stores a value under the given option key.
		 *
		 * @param string $key   The option name.
		 * @param mixed  $value The value to store.
		 *
		 * @return void
		 */
		public static function set(string $key, mixed $value): void
		{
			self::$store[$key] = $value;
		}

		/**
		 * Deletes the option with the given key.
		 *
		 * @param string $key The option name.
		 *
		 * @return bool True if the key existed and was removed, false otherwise.
		 */
		public static function delete(string $key): bool
		{
			$existed = array_key_exists($key, self::$store);
			unset(self::$store[$key]);

			return $existed;
		}

		/**
		 * Clears all stored options.
		 *
		 * Must be called in PHPUnit setUp() to isolate tests.
		 *
		 * @return void
		 */
		public static function reset(): void
		{
			self::$store = [];
		}
	}
}
// phpcs:enable
