<?php
/**
 * Custom Post Types: lfuf_product, lfuf_source, lfuf_location, lfuf_event
 */

declare(strict_types=1);

namespace ProducerKit\Core\Post_Types;

defined( 'ABSPATH' ) || exit;

function register(): void {
	register_product();
	register_source();
	register_location();
	register_event();
}

/* ───────────────────────────────────────────────
 * Product — anything grown, baked, or sold.
 * ─────────────────────────────────────────────── */
function register_product(): void {
	$labels = [
		'name'               => __( 'Products', 'producerkit' ),
		'singular_name'      => __( 'Product', 'producerkit' ),
		'add_new_item'       => __( 'Add New Product', 'producerkit' ),
		'edit_item'          => __( 'Edit Product', 'producerkit' ),
		'new_item'           => __( 'New Product', 'producerkit' ),
		'view_item'          => __( 'View Product', 'producerkit' ),
		'search_items'       => __( 'Search Products', 'producerkit' ),
		'not_found'          => __( 'No products found.', 'producerkit' ),
		'not_found_in_trash' => __( 'No products found in Trash.', 'producerkit' ),
		'all_items'          => __( 'All Products', 'producerkit' ),
		'menu_name'          => __( 'Products', 'producerkit' ),
	];

	register_post_type(
		'lfuf_product',
		[
			'labels'         => $labels,
			'public'         => true,
			'has_archive'    => false,
			'rewrite'        => [
				'slug'       => 'products',
				'with_front' => false,
			],
			'menu_icon'      => 'dashicons-carrot',
			'menu_position'  => 26,
			'supports'       => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
			'show_in_rest'   => true,
			'rest_base'      => 'products',
			'rest_namespace' => 'lfuf/v1',
			'template'       => [],
			'template_lock'  => false,
		]
	);
}

/* ───────────────────────────────────────────────
 * Source — a grain origin, partner farm, etc.
 * ─────────────────────────────────────────────── */
function register_source(): void {
	$labels = [
		'name'          => __( 'Sources', 'producerkit' ),
		'singular_name' => __( 'Source', 'producerkit' ),
		'add_new_item'  => __( 'Add New Source', 'producerkit' ),
		'edit_item'     => __( 'Edit Source', 'producerkit' ),
		'new_item'      => __( 'New Source', 'producerkit' ),
		'view_item'     => __( 'View Source', 'producerkit' ),
		'search_items'  => __( 'Search Sources', 'producerkit' ),
		'not_found'     => __( 'No sources found.', 'producerkit' ),
		'all_items'     => __( 'All Sources', 'producerkit' ),
		'menu_name'     => __( 'Sources', 'producerkit' ),
	];

	register_post_type(
		'lfuf_source',
		[
			'labels'         => $labels,
			'public'         => true,
			'has_archive'    => false,
			'rewrite'        => [
				'slug'       => 'sources',
				'with_front' => false,
			],
			'menu_icon'      => 'dashicons-location-alt',
			'menu_position'  => 27,
			'supports'       => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
			'show_in_rest'   => true,
			'rest_base'      => 'sources',
			'rest_namespace' => 'lfuf/v1',
		]
	);
}

/* ───────────────────────────────────────────────
 * Location — sales channels (stand, market, farm).
 * ─────────────────────────────────────────────── */
function register_location(): void {
	$labels = [
		'name'          => __( 'Locations', 'producerkit' ),
		'singular_name' => __( 'Location', 'producerkit' ),
		'add_new_item'  => __( 'Add New Location', 'producerkit' ),
		'edit_item'     => __( 'Edit Location', 'producerkit' ),
		'new_item'      => __( 'New Location', 'producerkit' ),
		'view_item'     => __( 'View Location', 'producerkit' ),
		'search_items'  => __( 'Search Locations', 'producerkit' ),
		'not_found'     => __( 'No locations found.', 'producerkit' ),
		'all_items'     => __( 'All Locations', 'producerkit' ),
		'menu_name'     => __( 'Locations', 'producerkit' ),
	];

	register_post_type(
		'lfuf_location',
		[
			'labels'         => $labels,
			'public'         => true,
			'has_archive'    => false,
			'rewrite'        => [
				'slug'       => 'locations',
				'with_front' => false,
			],
			'menu_icon'      => 'dashicons-store',
			'menu_position'  => 28,
			'supports'       => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
			'show_in_rest'   => true,
			'rest_base'      => 'locations',
			'rest_namespace' => 'lfuf/v1',
		]
	);
}

/* ───────────────────────────────────────────────
 * Event — pizza nights, potlucks, farm dinners.
 * ─────────────────────────────────────────────── */
function register_event(): void {
	$labels = [
		'name'          => __( 'Events', 'producerkit' ),
		'singular_name' => __( 'Event', 'producerkit' ),
		'add_new_item'  => __( 'Add New Event', 'producerkit' ),
		'edit_item'     => __( 'Edit Event', 'producerkit' ),
		'new_item'      => __( 'New Event', 'producerkit' ),
		'view_item'     => __( 'View Event', 'producerkit' ),
		'search_items'  => __( 'Search Events', 'producerkit' ),
		'not_found'     => __( 'No events found.', 'producerkit' ),
		'all_items'     => __( 'All Events', 'producerkit' ),
		'menu_name'     => __( 'Events', 'producerkit' ),
	];

	register_post_type(
		'lfuf_event',
		[
			'labels'         => $labels,
			'public'         => true,
			'has_archive'    => false,
			'rewrite'        => [
				'slug'       => 'events',
				'with_front' => false,
			],
			'menu_icon'      => 'dashicons-calendar-alt',
			'menu_position'  => 29,
			'supports'       => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
			'show_in_rest'   => true,
			'rest_base'      => 'events',
			'rest_namespace' => 'lfuf/v1',
		]
	);
}
