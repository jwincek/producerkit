<?php
/**
 * Availability Board REST extensions.
 *
 * Adds a purpose-built endpoint for the front-end board block
 * that returns availability grouped by product type, with
 * product thumbnails, prices, and filter support.
 */

declare(strict_types=1);

namespace ProducerKit\AvailabilityBoard\REST;

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', __NAMESPACE__ . '\\register_routes' );

function register_routes(): void {
	$ns = 'producerkit/v1';

	/**
	 * GET /producerkit/v1/board
	 *
	 * Public endpoint returning the full board data structure.
	 * Optimized for a single fetch from the front-end block.
	 *
	 * Query params:
	 *   status       — filter by status (e.g. "abundant,available,limited")
	 *   product_type — filter by product type term slug
	 *   location     — filter by location ID
	 */
	register_rest_route(
		$ns,
		'/board',
		[
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\get_board',
			'permission_callback' => '__return_true',
			'args'                => [
				'status'       => [
					'type'        => 'string',
					'default'     => '',
					'description' => 'Comma-separated status filter.',
				],
				'product_type' => [
					'type'        => 'string',
					'default'     => '',
					'description' => 'Product type term slug filter.',
				],
				'location'     => [
					'type'              => 'integer',
					'default'           => 0,
					'sanitize_callback' => 'absint',
					'description'       => 'Location ID filter.',
				],
			],
		]
	);

	/**
	 * GET /producerkit/v1/board/last-updated
	 *
	 * Returns the timestamp of the most recent availability change.
	 * Used by the front-end polling to skip full refetches when nothing changed.
	 */
	register_rest_route(
		$ns,
		'/board/last-updated',
		[
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\get_last_updated',
			'permission_callback' => '__return_true',
		]
	);
}

/* ───────────────────────────────────────────────
 * GET /board
 * ─────────────────────────────────────────────── */

function get_board( \WP_REST_Request $request ): \WP_REST_Response {
	global $wpdb;

	$table = $wpdb->prefix . 'pkit_availability';
	$today = current_time( 'Y-m-d' );

	// Build WHERE clause.
	$where_parts = [
		$wpdb->prepare( 'a.effective_date <= %s', $today ),
		$wpdb->prepare( '(a.expires_date IS NULL OR a.expires_date >= %s)', $today ),
		"p.post_status = 'publish'",
	];

	// Status filter.
	$status_filter = $request->get_param( 'status' );
	if ( $status_filter ) {
		$statuses = array_filter( array_map( 'sanitize_text_field', explode( ',', $status_filter ) ) );
		$valid    = \ProducerKit\Core\Availability\valid_statuses();
		$statuses = array_intersect( $statuses, $valid );
		if ( $statuses ) {
			$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders are generated from a count, and the values are intersected against valid_statuses() before binding.
			$where_parts[] = $wpdb->prepare( "a.status IN ({$placeholders})", ...$statuses );
		}
	}

	// Location filter.
	$location_id = $request->get_param( 'location' );
	if ( $location_id > 0 ) {
		$where_parts[] = $wpdb->prepare( '(a.location_id = %d OR a.location_id = 0)', $location_id );
	}

	// Product type filter (join to taxonomy).
	$product_type_slug = sanitize_text_field( $request->get_param( 'product_type' ) );
	$type_join         = '';
	if ( $product_type_slug ) {
		$type_join     = "
            INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
            INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'pkit_product_type'
            INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
        ";
		$where_parts[] = $wpdb->prepare( 't.slug = %s', $product_type_slug );
	}

	$where = implode( ' AND ', $where_parts );

	// Query: one row per product (most recent availability per product).
	$sql = "
        SELECT
            a.id AS availability_id,
            a.product_id,
            a.location_id,
            a.status,
            a.quantity_note,
            a.effective_date,
            a.notes,
            a.updated_at,
            p.post_title AS product_name,
            p.post_excerpt AS product_excerpt
        FROM {$table} a
        INNER JOIN {$wpdb->posts} p ON p.ID = a.product_id
        {$type_join}
        WHERE {$where}
        ORDER BY
            FIELD(a.status, 'abundant', 'available', 'limited', 'sold_out', 'unavailable'),
            p.post_title ASC
    ";

	$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- every variable fragment in $sql is built with $wpdb->prepare() above; remaining interpolations are wpdb table names.

	// Enrich with product meta and taxonomy terms.
	$items         = [];
	$seen_products = [];

	foreach ( $rows as $row ) {
		// Deduplicate: one entry per product (latest status wins).
		if ( isset( $seen_products[ $row->product_id ] ) ) {
			continue;
		}
		$seen_products[ $row->product_id ] = true;

		$pid = (int) $row->product_id;

		// Product type terms.
		$types      = get_the_terms( $pid, 'pkit_product_type' );
		$type_names = ( $types && ! is_wp_error( $types ) )
			? wp_list_pluck( $types, 'name' )
			: [];
		$type_slugs = ( $types && ! is_wp_error( $types ) )
			? wp_list_pluck( $types, 'slug' )
			: [];

		// Season terms.
		$seasons      = get_the_terms( $pid, 'pkit_season' );
		$season_names = ( $seasons && ! is_wp_error( $seasons ) )
			? wp_list_pluck( $seasons, 'name' )
			: [];

		// Thumbnail.
		// Featured image if the product has one, otherwise the placeholder for
		// its product type. Resolved here rather than in the block so the
		// server render, the editor preview and any REST consumer all agree.
		$thumb_url = \ProducerKit\Core\Product_Images\thumbnail_url( $pid, 'thumbnail' );

		$items[] = [
			'availability_id' => (int) $row->availability_id,
			'product_id'      => $pid,
			'product_name'    => $row->product_name,
			'product_excerpt' => $row->product_excerpt ?: '',
			'thumbnail_url'   => $thumb_url ?: '',
			'status'          => $row->status,
			'quantity_note'   => $row->quantity_note,
			'effective_date'  => $row->effective_date,
			'notes'           => $row->notes,
			'price'           => get_post_meta( $pid, '_pkit_price', true ) ?: '',
			'unit'            => get_post_meta( $pid, '_pkit_unit', true ) ?: '',
			'product_types'   => array_values( $type_names ),
			'product_slugs'   => array_values( $type_slugs ),
			'seasons'         => array_values( $season_names ),
			// Trade fields the active producer profile switched on — a
			// potter's Clay Body, a printer's Ink. Keyed by taxonomy so the
			// board can offer one filter row per field without knowing which
			// fields exist.
			'traits'          => trait_slugs( $pid ),
			'permalink'       => get_permalink( $pid ),
		];
	}

	// Filter rows for the trade fields, built from what is actually on the
	// board rather than the whole taxonomy: offering "Cone 10 Gas" when
	// nothing on the board is fired that way is a dead end.
	$filter_traits = collect_trait_filters( $items );

	// Group by primary product type for the board layout.
	$grouped = [];
	foreach ( $items as $item ) {
		$group_key   = $item['product_slugs'][0] ?? 'other';
		$group_label = $item['product_types'][0] ?? __( 'Other', 'producerkit' );

		if ( ! isset( $grouped[ $group_key ] ) ) {
			$grouped[ $group_key ] = [
				'slug'  => $group_key,
				'label' => $group_label,
				'items' => [],
			];
		}

		$grouped[ $group_key ]['items'][] = $item;
	}

	// Get available product type terms for filter UI.
	$all_types    = get_terms(
		[
			'taxonomy'   => 'pkit_product_type',
			'hide_empty' => true,
		]
	);
	$filter_types = [];
	if ( $all_types && ! is_wp_error( $all_types ) ) {
		foreach ( $all_types as $term ) {
			$filter_types[] = [
				'slug'  => $term->slug,
				'label' => $term->name,
				'count' => $term->count,
			];
		}
	}

	return new \WP_REST_Response(
		[
			'groups'        => array_values( $grouped ),
			'total_items'   => count( $items ),
			'filter_types'  => $filter_types,
			'filter_traits' => $filter_traits,
			'statuses'      => \ProducerKit\Core\Availability\valid_statuses(),
			'generated_at'  => current_time( 'c' ),
		],
		200
	);
}

/* ───────────────────────────────────────────────
 * GET /board/last-updated
 * ─────────────────────────────────────────────── */

function get_last_updated( \WP_REST_Request $request ): \WP_REST_Response {
	global $wpdb;

	$table = $wpdb->prefix . 'pkit_availability';

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized.
	$last = $wpdb->get_var( "SELECT MAX(updated_at) FROM {$table}" );

	return new \WP_REST_Response(
		[
			'last_updated' => $last ?: null,
		],
		200
	);
}

/**
 * The trade-field terms on one product, keyed by taxonomy.
 *
 * Empty when the producer-profiles module is off, or when no active profile
 * asks for any of these fields — which is how a farm sees no extra filters.
 *
 * @return array<string, string[]> Taxonomy => term slugs.
 */
function trait_slugs( int $product_id ): array {
	$out = [];

	// The same filter core uses to avoid knowing that profiles exist.
	foreach ( (array) apply_filters( 'pkit_detail_taxonomies', [], $product_id ) as $taxonomy ) {
		if ( ! is_string( $taxonomy ) || ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}

		$terms = get_the_terms( $product_id, $taxonomy );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$out[ $taxonomy ] = array_values( wp_list_pluck( $terms, 'slug' ) );
		}
	}

	return $out;
}

/**
 * Build one filter row per trade field, from the terms actually on the board.
 *
 * Not from the whole taxonomy: offering "Cone 10 Gas" when nothing on the
 * board is fired that way is a dead end, and a row with a single option
 * filters nothing, so both are dropped.
 *
 * Labelled as the *current viewer* names them, which under multi-profile is
 * not the same for everyone — the grower reads Material where the baker reads
 * Flour, over the same field.
 *
 * @param array[] $items
 * @return array<int, array{taxonomy: string, label: string, terms: array<int, array{slug: string, label: string}>}>
 */
function collect_trait_filters( array $items ): array {
	$present = [];

	foreach ( $items as $item ) {
		foreach ( (array) ( $item['traits'] ?? [] ) as $taxonomy => $slugs ) {
			foreach ( $slugs as $slug ) {
				$present[ $taxonomy ][ $slug ] = true;
			}
		}
	}

	$out = [];

	foreach ( $present as $taxonomy => $slugs ) {
		if ( ! taxonomy_exists( $taxonomy ) || count( $slugs ) < 2 ) {
			continue;
		}

		$terms = [];
		foreach ( array_keys( $slugs ) as $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term instanceof \WP_Term ) {
				$terms[] = [
					'slug'  => $term->slug,
					'label' => $term->name,
				];
			}
		}

		usort( $terms, static fn ( array $a, array $b ): int => strcasecmp( $a['label'], $b['label'] ) );

		$out[] = [
			'taxonomy' => $taxonomy,
			'label'    => get_taxonomy( $taxonomy )->labels->singular_name,
			'terms'    => $terms,
		];
	}

	return $out;
}
