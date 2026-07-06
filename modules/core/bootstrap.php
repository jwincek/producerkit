<?php
/**
 * Core module bootstrap.
 *
 * Registers CPTs, taxonomies, meta fields, availability table,
 * REST API routes, and Abilities API abilities.
 */

declare(strict_types=1);

namespace Leftfield\Core;

defined('ABSPATH') || exit;

$module_dir = __DIR__;

require_once $module_dir . '/includes/post-types.php';
require_once $module_dir . '/includes/taxonomies.php';
require_once $module_dir . '/includes/meta-fields.php';
require_once $module_dir . '/includes/payments.php';
require_once $module_dir . '/includes/availability-table.php';
require_once $module_dir . '/includes/rest-api.php';
require_once $module_dir . '/includes/abilities.php';
require_once $module_dir . '/includes/single-content.php';
require_once $module_dir . '/includes/single-styles.php';
require_once $module_dir . '/includes/admin-columns.php';
require_once $module_dir . '/includes/product-import-export.php';

/**
 * Init hook: register all data structures.
 */
add_action('init', function (): void {
    Post_Types\register();
    Taxonomies\register();
    Meta_Fields\register();

    // Self-healing: ensure the availability cleanup cron is scheduled.
    // Handles the case where the plugin was updated without reactivation.
    Availability\schedule_cleanup();
});