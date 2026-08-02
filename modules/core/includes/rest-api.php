<?php
/**
 * REST API routes under the lfuf/v1 namespace.
 *
 * CPTs already expose themselves via show_in_rest + rest_namespace,
 * so these routes cover the custom availability table and any
 * cross-entity queries that feature plugins will need.
 */

declare(strict_types=1);

namespace Leftfield\Core\REST;

use Leftfield\Core\Availability;

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', __NAMESPACE__ . '\\register_routes' );

function register_routes(): void {
	$namespace = 'lfuf/v1';

	/* ── Availability ────────────────────────── */

	// GET  /lfuf/v1/availability          — all current rows
	// GET  /lfuf/v1/availability?product=1 — filtered by product
	register_rest_route(
		$namespace,
		'/availability',
		[
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\get_availability',
			'permission_callback' => '__return_true',
			'args'                => [
				'product'  => [
					'type'              => 'integer',
					'default'           => 0,
					'sanitize_callback' => 'absint',
				],
				'location' => [
					'type'              => 'integer',
					'default'           => 0,
					'sanitize_callback' => 'absint',
				],
			],
		]
	);

	// POST /lfuf/v1/availability          — upsert a row
	register_rest_route(
		$namespace,
		'/availability',
		[
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => __NAMESPACE__ . '\\upsert_availability',
			'permission_callback' => fn () => current_user_can( 'edit_posts' ),
			'args'                => [
				'product_id'     => [
					'type'              => 'integer',
					'required'          => true,
					'sanitize_callback' => 'absint',
				],
				'location_id'    => [
					'type'              => 'integer',
					'default'           => 0,
					'sanitize_callback' => 'absint',
				],
				'status'         => [
					'type'     => 'string',
					'required' => true,
				],
				'quantity_note'  => [
					'type'    => 'string',
					'default' => '',
				],
				'effective_date' => [
					'type'     => 'string',
					'required' => true,
				],
				'expires_date'   => [
					'type'    => 'string',
					'default' => '',
				],
				'notes'          => [
					'type'    => 'string',
					'default' => '',
				],
			],
		]
	);

	// DELETE /lfuf/v1/availability/(?P<id>\d+)
	register_rest_route(
		$namespace,
		'/availability/(?P<id>\d+)',
		[
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => __NAMESPACE__ . '\\delete_availability',
			'permission_callback' => fn () => current_user_can( 'edit_posts' ),
			'args'                => [
				'id' => [
					'type'              => 'integer',
					'required'          => true,
					'sanitize_callback' => 'absint',
				],
			],
		]
	);

	/* ── Cross-entity: product → sources ─── */

	// GET /lfuf/v1/products/(?P<id>\d+)/sources
	register_rest_route(
		$namespace,
		'/products/(?P<id>\d+)/sources',
		[
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\get_product_sources',
			'permission_callback' => '__return_true',
			'args'                => [
				'id' => [
					'type'              => 'integer',
					'required'          => true,
					'sanitize_callback' => 'absint',
				],
			],
		]
	);

	/* ── Cross-entity: event → location + products */

	// GET /lfuf/v1/events/(?P<id>\d+)/details
	register_rest_route(
		$namespace,
		'/events/(?P<id>\d+)/details',
		[
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\get_event_details',
			'permission_callback' => '__return_true',
			'args'                => [
				'id' => [
					'type'              => 'integer',
					'required'          => true,
					'sanitize_callback' => 'absint',
				],
			],
		]
	);

	/* ── Stand status (quick toggle) ─────── */

	// PATCH /lfuf/v1/locations/(?P<id>\d+)/toggle
	register_rest_route(
		$namespace,
		'/locations/(?P<id>\d+)/toggle',
		[
			'methods'             => \WP_REST_Server::EDITABLE,
			'callback'            => __NAMESPACE__ . '\\toggle_location_open',
			'permission_callback' => fn () => current_user_can( 'edit_posts' ),
			'args'                => [
				'id'      => [
					'type'              => 'integer',
					'required'          => true,
					'sanitize_callback' => 'absint',
				],
				'is_open' => [
					'type'     => 'boolean',
					'required' => true,
				],
			],
		]
	);
}

/* ───────────────────────────────────────────────
 * Callbacks
 * ─────────────────────────────────────────────── */

function get_availability( \WP_REST_Request $request ): \WP_REST_Response {
	$product  = $request->get_param( 'product' );
	$location = $request->get_param( 'location' );

	if ( $product > 0 ) {
		$rows = Availability\get_current( $product, $location );
	} else {
		$rows = Availability\get_all_current();
	}

	return new \WP_REST_Response( $rows, 200 );
}

function upsert_availability( \WP_REST_Request $request ): \WP_REST_Response {
	$data = [
		'product_id'     => $request->get_param( 'product_id' ),
		'location_id'    => $request->get_param( 'location_id' ),
		'status'         => $request->get_param( 'status' ),
		'quantity_note'  => $request->get_param( 'quantity_note' ),
		'effective_date' => $request->get_param( 'effective_date' ),
		'expires_date'   => $request->get_param( 'expires_date' ) ?: null,
		'notes'          => $request->get_param( 'notes' ),
	];

	$id = Availability\upsert( $data );

	if ( $id === false ) {
		return new \WP_REST_Response(
			[ 'message' => 'Invalid data. Check status value.' ],
			400,
		);
	}

	return new \WP_REST_Response( [ 'id' => $id ], $id ? 200 : 201 );
}

function delete_availability( \WP_REST_Request $request ): \WP_REST_Response {
	$deleted = Availability\delete_row( $request->get_param( 'id' ) );
	return new \WP_REST_Response( [ 'deleted' => $deleted ], $deleted ? 200 : 404 );
}

function get_product_sources( \WP_REST_Request $request ): \WP_REST_Response {
	$product_id = $request->get_param( 'id' );
	$source_ids = get_post_meta( $product_id, '_lfuf_source_ids', true );

	if ( empty( $source_ids ) || ! is_array( $source_ids ) ) {
		return new \WP_REST_Response( [], 200 );
	}

	$sources = get_posts(
		[
			'post_type'   => 'lfuf_source',
			'post__in'    => $source_ids,
			'numberposts' => 20,
			'post_status' => 'publish',
		]
	);

	$result = array_map(
		fn ( \WP_Post $p ) => [
			'id'            => $p->ID,
			'title'         => $p->post_title,
			'excerpt'       => $p->post_excerpt,
			'farm_name'     => get_post_meta( $p->ID, '_lfuf_source_farm_name', true ),
			'location'      => get_post_meta( $p->ID, '_lfuf_source_location', true ),
			'history'       => get_post_meta( $p->ID, '_lfuf_source_history', true ),
			'milling_notes' => get_post_meta( $p->ID, '_lfuf_milling_notes', true ),
		],
		$sources
	);

	return new \WP_REST_Response( $result, 200 );
}

function get_event_details( \WP_REST_Request $request ): \WP_REST_Response {
	$event_id = $request->get_param( 'id' );
	$event    = get_post( $event_id );

	if ( ! $event || $event->post_type !== 'lfuf_event' ) {
		return new \WP_REST_Response( [ 'message' => 'Event not found.' ], 404 );
	}

	$location_id = (int) get_post_meta( $event_id, '_lfuf_event_location_id', true );
	$product_ids = get_post_meta( $event_id, '_lfuf_featured_product_ids', true ) ?: [];

	$location = null;
	if ( $location_id > 0 ) {
		$loc_post = get_post( $location_id );
		if ( $loc_post ) {
			$location = [
				'id'      => $loc_post->ID,
				'title'   => $loc_post->post_title,
				'address' => get_post_meta( $loc_post->ID, '_lfuf_address', true ),
				'type'    => get_post_meta( $loc_post->ID, '_lfuf_location_type', true ),
				'venmo'   => get_post_meta( $loc_post->ID, '_lfuf_venmo_handle', true ),
			];
		}
	}

	$products = [];
	if ( ! empty( $product_ids ) ) {
		$posts    = get_posts(
			[
				'post_type'   => 'lfuf_product',
				'post__in'    => $product_ids,
				'numberposts' => 20,
				'post_status' => 'publish',
			]
		);
		$products = array_map(
			fn ( \WP_Post $p ) => [
				'id'    => $p->ID,
				'title' => $p->post_title,
				'price' => get_post_meta( $p->ID, '_lfuf_price', true ),
			],
			$posts
		);
	}

	return new \WP_REST_Response(
		[
			'event'    => [
				'id'              => $event->ID,
				'title'           => $event->post_title,
				'content'         => apply_filters( 'the_content', $event->post_content ),
				'start'           => get_post_meta( $event_id, '_lfuf_start_datetime', true ),
				'end'             => get_post_meta( $event_id, '_lfuf_end_datetime', true ),
				'recurrence_rule' => get_post_meta( $event_id, '_lfuf_recurrence_rule', true ),
				'rsvp_cap'        => (int) get_post_meta( $event_id, '_lfuf_rsvp_cap', true ),
				'donation_link'   => get_post_meta( $event_id, '_lfuf_donation_link', true ),
			],
			'location' => $location,
			'products' => $products,
		],
		200
	);
}

function toggle_location_open( \WP_REST_Request $request ): \WP_REST_Response {
	$location_id = $request->get_param( 'id' );
	$is_open     = $request->get_param( 'is_open' );

	$post = get_post( $location_id );
	if ( ! $post || $post->post_type !== 'lfuf_location' ) {
		return new \WP_REST_Response( [ 'message' => 'Location not found.' ], 404 );
	}

	update_post_meta( $location_id, '_lfuf_is_open', $is_open );

	return new \WP_REST_Response(
		[
			'id'      => $location_id,
			'is_open' => $is_open,
		],
		200
	);
}
