<?php
/**
 * Core module bootstrap.
 *
 * Registers CPTs, taxonomies, meta fields, availability table,
 * REST API routes, and Abilities API abilities.
 */

declare(strict_types=1);

namespace ProducerKit\Core;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/post-types.php';
require_once __DIR__ . '/includes/taxonomies.php';
require_once __DIR__ . '/includes/meta-fields.php';
require_once __DIR__ . '/includes/requests.php';
require_once __DIR__ . '/includes/payments.php';
require_once __DIR__ . '/includes/product-images.php';
require_once __DIR__ . '/includes/availability-table.php';
require_once __DIR__ . '/includes/rest-api.php';
require_once __DIR__ . '/includes/abilities.php';
require_once __DIR__ . '/includes/structured-data.php';
require_once __DIR__ . '/includes/single-content.php';
require_once __DIR__ . '/includes/single-styles.php';
require_once __DIR__ . '/includes/admin-columns.php';
require_once __DIR__ . '/includes/product-import-export.php';

/**
 * Init hook: register all data structures.
 */
add_action(
	'init',
	function (): void {
		Post_Types\register();
		Taxonomies\register();
		Meta_Fields\register();

		// Self-healing: ensure the availability cleanup cron is scheduled.
		// Handles the case where the plugin was updated without reactivation.
		Availability\schedule_cleanup();
	}
);
