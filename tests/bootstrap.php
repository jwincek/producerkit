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

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, $value, ...$args ) {
		return $value;
	}
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Files under unit test (pure helpers only are exercised).
require_once dirname( __DIR__ ) . '/modules/core/includes/payments.php';
require_once dirname( __DIR__ ) . '/modules/core/includes/structured-data.php';
