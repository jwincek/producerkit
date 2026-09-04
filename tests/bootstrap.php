<?php
/**
 * PHPUnit bootstrap for the pure-function unit suite.
 *
 * These tests deliberately do NOT load WordPress. They cover helpers
 * whose logic is self-contained (price parsing, schema mapping, payment
 * URL building), so we stub the handful of WP functions those files
 * reference and load just the source under test. Everything touching
 * the database, hooks, or sanitization runs in the integration suite.
 */

declare(strict_types=1);

// Source files carry a `defined('ABSPATH') || exit;` guard; satisfy it.
defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__ ) . '/' );

// ─── Minimal WP function stubs ─────────────────────────────────────
// Unqualified calls inside the plugin's namespaces fall back to these
// globals when WordPress isn't loaded.

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$args ): bool {
		return true;
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( float $number, int $decimals = 0 ): string {
		return number_format( $number, $decimals );
	}
}

// The recurrence parser reports refusals as WP_Error, which is the plugin's
// convention everywhere else and worth keeping rather than inventing a second
// error shape for the one file the unit suite happens to reach.
// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- A test
// bootstrap is a pile of stubs by nature; splitting this one class into its
// own file to satisfy the sniff would make the stubs harder to find, not
// easier.
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public string $code;
		public string $message;

		public function __construct( string $code = '', string $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, $value, ...$args ) {
		return $value;
	}
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Files under unit test (pure helpers only are exercised).
require_once dirname( __DIR__ ) . '/modules/core/includes/payments.php';
require_once dirname( __DIR__ ) . '/modules/core/includes/structured-data.php';
require_once dirname( __DIR__ ) . '/modules/core/includes/deposits.php';
require_once dirname( __DIR__ ) . '/modules/event-manager/includes/recurrence.php';
