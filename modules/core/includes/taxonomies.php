<?php
/**
 * Shared Taxonomies.
 *
 * lfuf_product_type — Produce, Bread, Pantry Good, Seedling, etc.
 * lfuf_season       — Spring, Summer, Fall, Winter (shared across products & events).
 * lfuf_event_type   — Pizza Night, Potluck, Farm Dinner, Workshop, Tour, Market, etc.
 */

declare(strict_types=1);

namespace ProducerKit\Core\Taxonomies;

defined( 'ABSPATH' ) || exit;

function register(): void {
	register_product_type();
	register_season();
	register_event_type();
}

/* ───────────────────────────────────────────────
 * Product Type
 * ─────────────────────────────────────────────── */
function register_product_type(): void {
	$labels = [
		'name'              => __( 'Product Types', 'producerkit' ),
		'singular_name'     => __( 'Product Type', 'producerkit' ),
		'search_items'      => __( 'Search Product Types', 'producerkit' ),
		'all_items'         => __( 'All Product Types', 'producerkit' ),
		'parent_item'       => __( 'Parent Product Type', 'producerkit' ),
		'parent_item_colon' => __( 'Parent Product Type:', 'producerkit' ),
		'edit_item'         => __( 'Edit Product Type', 'producerkit' ),
		'update_item'       => __( 'Update Product Type', 'producerkit' ),
		'add_new_item'      => __( 'Add New Product Type', 'producerkit' ),
		'new_item_name'     => __( 'New Product Type Name', 'producerkit' ),
		'menu_name'         => __( 'Product Types', 'producerkit' ),
	];

	register_taxonomy(
		'lfuf_product_type',
		[ 'lfuf_product' ],
		[
			'labels'            => $labels,
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'rest_base'         => 'product-types',
			'rest_namespace'    => 'lfuf/v1',
			'rewrite'           => [
				'slug'       => 'product-type',
				'with_front' => false,
			],
			'show_admin_column' => true,
		]
	);

	// Seed default terms (self-healing, admin only).
	if ( is_admin() ) {
		$defaults = [ 'Produce', 'Bread', 'Baked Good', 'Pantry Good', 'Seedling' ];
		foreach ( $defaults as $term ) {
			if ( ! term_exists( $term, 'lfuf_product_type' ) ) {
				wp_insert_term( $term, 'lfuf_product_type' );
			}
		}
	}
}

/* ───────────────────────────────────────────────
 * Season (shared: products + events)
 * ─────────────────────────────────────────────── */
function register_season(): void {
	$labels = [
		'name'          => __( 'Seasons', 'producerkit' ),
		'singular_name' => __( 'Season', 'producerkit' ),
		'search_items'  => __( 'Search Seasons', 'producerkit' ),
		'all_items'     => __( 'All Seasons', 'producerkit' ),
		'edit_item'     => __( 'Edit Season', 'producerkit' ),
		'update_item'   => __( 'Update Season', 'producerkit' ),
		'add_new_item'  => __( 'Add New Season', 'producerkit' ),
		'new_item_name' => __( 'New Season Name', 'producerkit' ),
		'menu_name'     => __( 'Seasons', 'producerkit' ),
	];

	register_taxonomy(
		'lfuf_season',
		[ 'lfuf_product', 'lfuf_event' ],
		[
			'labels'            => $labels,
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'rest_base'         => 'seasons',
			'rest_namespace'    => 'lfuf/v1',
			'rewrite'           => [
				'slug'       => 'season',
				'with_front' => false,
			],
			'show_admin_column' => true,
		]
	);

	if ( is_admin() ) {
		$defaults = [ 'Spring', 'Summer', 'Fall', 'Winter' ];
		foreach ( $defaults as $term ) {
			if ( ! term_exists( $term, 'lfuf_season' ) ) {
				wp_insert_term( $term, 'lfuf_season' );
			}
		}
	}
}

/* ───────────────────────────────────────────────
 * Event Type
 * ─────────────────────────────────────────────── */
function register_event_type(): void {
	$labels = [
		'name'          => __( 'Event Types', 'producerkit' ),
		'singular_name' => __( 'Event Type', 'producerkit' ),
		'search_items'  => __( 'Search Event Types', 'producerkit' ),
		'all_items'     => __( 'All Event Types', 'producerkit' ),
		'edit_item'     => __( 'Edit Event Type', 'producerkit' ),
		'update_item'   => __( 'Update Event Type', 'producerkit' ),
		'add_new_item'  => __( 'Add New Event Type', 'producerkit' ),
		'new_item_name' => __( 'New Event Type Name', 'producerkit' ),
		'menu_name'     => __( 'Event Types', 'producerkit' ),
	];

	register_taxonomy(
		'lfuf_event_type',
		[ 'lfuf_event' ],
		[
			'labels'            => $labels,
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'rest_base'         => 'event-types',
			'rest_namespace'    => 'lfuf/v1',
			'rewrite'           => [
				'slug'       => 'event-type',
				'with_front' => false,
			],
			'show_admin_column' => true,
		]
	);

	if ( is_admin() ) {
		$defaults = [
			'Pizza Night',
			'Potluck',
			'Farm Dinner',
			'Workshop',
			'Farm Tour',
			'Seed Exchange',
			'Mini Market',
		];
		foreach ( $defaults as $term ) {
			if ( ! term_exists( $term, 'lfuf_event_type' ) ) {
				wp_insert_term( $term, 'lfuf_event_type' );
			}
		}
	}
}
