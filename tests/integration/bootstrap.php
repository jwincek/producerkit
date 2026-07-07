<?php
/**
 * Bootstrap for the WordPress integration test suite.
 *
 * Loads the WordPress PHPUnit test library (installed by
 * bin/install-wp-tests.sh), then loads this plugin on `muplugins_loaded`
 * so its real modules — CPTs, meta, custom tables, REST routes, and
 * abilities — are wired up against a real WordPress + database.
 */

declare(strict_types=1);

$_tests_dir = getenv('WP_TESTS_DIR');
if (! $_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (! file_exists($_tests_dir . '/includes/functions.php')) {
    fwrite(
        STDERR,
        "WordPress test library not found at {$_tests_dir}.\n" .
        "Run bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] first,\n" .
        "or set WP_TESTS_DIR to its location.\n"
    );
    exit(1);
}

// Composer autoload first so the PHPUnit Polyfills the WP suite requires
// are available, then point the suite at them explicitly.
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
if (! defined('WP_TESTS_PHPUNIT_POLYFILLS_PATH')) {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname(__DIR__, 2) . '/vendor/yoast/phpunit-polyfills');
}

require_once $_tests_dir . '/includes/functions.php';

// Load the plugin into the test WordPress instance.
tests_add_filter('muplugins_loaded', function (): void {
    require dirname(__DIR__, 2) . '/farm-stand-manager.php';
});

// The RSVP and pre-order tables self-heal on plugins_loaded, but the
// availability table is only created on activation — which never fires
// here. Create all three explicitly before the suite starts.
tests_add_filter('plugins_loaded', function (): void {
    \Leftfield\Core\Availability\create_table();
    \Leftfield\EventManager\RSVP\create_table();
    \Leftfield\PreOrder\Orders\create_table();
}, 25);

require $_tests_dir . '/includes/bootstrap.php';
