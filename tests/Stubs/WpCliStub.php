<?php

// phpcs:disable
// This file intentionally uses multi-namespace block syntax (namespace { } braces)
// to define both the global WP_CLI class and the WP_CLI\ExitException sub-namespace
// class in a single file. This pattern is the only valid PHP approach for mixing
// global and named namespaces in one file and is required for test stubs.

namespace {
	/**
	 * Minimal WP_CLI stub for unit tests.
	 *
	 * Records every static call in `$calls` so tests can assert output routing
	 * without a real WP-CLI runtime. The `$confirm_throws` flag lets tests
	 * simulate the user declining a WP_CLI::confirm() prompt (WP-CLI throws
	 * WP_CLI\ExitException when the user answers "no").
	 */
	class WP_CLI
	{
		/**
		 * All calls recorded by the stub, in order.
		 *
		 * Each entry is an indexed array: [ method_name, ...args ].
		 *
		 * @var list<array<int, mixed>>
		 */
		public static array $calls = [];

		/**
		 * When true, WP_CLI::confirm() throws ExitException to simulate "no".
		 *
		 * @var bool
		 */
		public static bool $confirm_throws = false;

		public static function line(string $msg): void
		{
			self::$calls[] = ['line', $msg];
		}

		public static function error(string $msg, bool $exit = true): void
		{
			self::$calls[] = ['error', $msg, $exit];
		}

		public static function success(string $msg): void
		{
			self::$calls[] = ['success', $msg];
		}

		public static function warning(string $msg): void
		{
			self::$calls[] = ['warning', $msg];
		}

		public static function log(string $msg): void
		{
			self::$calls[] = ['log', $msg];
		}

		public static function debug(string $msg, string $group = ''): void
		{
			self::$calls[] = ['debug', $msg, $group];
		}

		public static function out(string $msg): void
		{
			self::$calls[] = ['out', $msg];
		}

		public static function confirm(string $msg): void
		{
			self::$calls[] = ['confirm', $msg];

			if (self::$confirm_throws) {
				throw new \WP_CLI\ExitException('Cancelled');
			}
		}
	}
}

namespace WP_CLI {
	/**
	 * WP_CLI\ExitException stub.
	 *
	 * WP-CLI throws this exception when the user declines a confirm() prompt.
	 * WpCliConfirmationHandler catches it and returns false.
	 */
	class ExitException extends \RuntimeException {}
}
// phpcs:enable
