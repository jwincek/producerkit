<?php
/**
 * Producer Profiles module bootstrap.
 *
 * Re-labels the product taxonomies and seeds the vocabulary for the trade the
 * site actually practises, and switches on the optional material / finish /
 * component fields for the trades that need them.
 *
 * Core knows nothing about this module. It exposes two filters —
 * `pkit_taxonomy_names` and `pkit_taxonomy_default_terms` — and this module
 * answers them. Deactivate the module and core falls back to its own farm
 * vocabulary with no other change.
 */

declare(strict_types=1);

namespace ProducerKit\ProducerProfiles;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/profiles.php';
require_once __DIR__ . '/includes/taxonomies.php';

if ( is_admin() ) {
	require_once __DIR__ . '/includes/admin-settings.php';
}

// Registered now, at plugins_loaded, so both are in place before core's own
// init callback builds its labels and seeds its terms.
add_filter( 'pkit_taxonomy_names', __NAMESPACE__ . '\\Taxonomies\\filter_names', 10, 2 );
add_filter( 'pkit_taxonomy_default_terms', __NAMESPACE__ . '\\Taxonomies\\filter_default_terms', 10, 2 );
add_filter( 'pkit_post_type_names', __NAMESPACE__ . '\\Taxonomies\\filter_post_type_names', 10, 2 );
add_filter( 'pkit_commission_names', __NAMESPACE__ . '\\Taxonomies\\filter_commission_names' );

// Tells core which taxonomies are worth showing on a product. Without this
// module the list stays empty and templates render exactly as before.
add_filter(
	'pkit_detail_taxonomies',
	static fn ( array $taxonomies ): array => array_merge( $taxonomies, Profiles\active_taxonomies() )
);

// Core registers its three taxonomies at init/10; the optional ones follow.
add_action( 'init', __NAMESPACE__ . '\\Taxonomies\\register', 11 );

if ( is_admin() ) {
	// A profile change adds or removes taxonomy rewrite slugs, so permalinks
	// have to be rebuilt — but only once the new taxonomies have registered,
	// which is why this runs late on the request *after* the change.
	add_action( 'init', __NAMESPACE__ . '\\Admin\\maybe_flush', 99 );
}
