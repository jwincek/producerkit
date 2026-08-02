<?php
/**
 * Event Manager REST extensions.
 *
 * Adds endpoints for:
 *  - Listing upcoming/past events with enriched data
 *  - Submitting and cancelling RSVPs (public, with rate limiting)
 *  - Viewing RSVP list (editor+ only)
 */

declare(strict_types=1);

namespace Leftfield\EventManager\REST;

use Leftfield\EventManager\RSVP;

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', __NAMESPACE__ . '\\register_routes' );

function register_routes(): void {
	$ns = 'lfuf/v1';

	register_rest_route(
		$ns,
		'/events/upcoming',
		[
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\get_upcoming_events',
			'permission_callback' => '__return_true',
			'args'                => [
				'per_page'   => [
					'type'              => 'integer',
					'default'           => 10,
					'sanitize_callback' => 'absint',
				],
				'event_type' => [
					'type'    => 'string',
					'default' => '',
				],
			],
		]
	);

	register_rest_route(
		$ns,
		'/events/past',
		[
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\get_past_events',
			'permission_callback' => '__return_true',
			'args'                => [
				'per_page' => [
					'type'              => 'integer',
					'default'           => 10,
					'sanitize_callback' => 'absint',
				],
			],
		]
	);

	/**
	 * POST /lfuf/v1/events/{id}/rsvp
	 *
	 * Public RSVP submission with:
	 *   - Honeypot field check (silent rejection)
	 *   - IP-based rate limiting
	 *   - Duplicate name detection
	 *   - Atomic cap enforcement
	 */
	register_rest_route(
		$ns,
		'/events/(?P<id>\d+)/rsvp',
		[
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => __NAMESPACE__ . '\\submit_rsvp',
			'permission_callback' => '__return_true',
			'args'                => [
				'id'         => [
					'type'              => 'integer',
					'required'          => true,
					'sanitize_callback' => 'absint',
				],
				'name'       => [
					'type'     => 'string',
					'required' => true,
				],
				'email'      => [
					'type'    => 'string',
					'default' => '',
				],
				'party_size' => [
					'type'              => 'integer',
					'default'           => 1,
					'sanitize_callback' => 'absint',
				],
				'note'       => [
					'type'    => 'string',
					'default' => '',
				],
				'website'    => [
					'type'    => 'string',
					'default' => '',
				], // Honeypot field.
			],
		]
	);

	register_rest_route(
		$ns,
		'/rsvp/(?P<token>[a-zA-Z0-9]+)',
		[
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => __NAMESPACE__ . '\\cancel_rsvp',
			'permission_callback' => '__return_true',
			'args'                => [
				'token' => [
					'type'     => 'string',
					'required' => true,
				],
			],
		]
	);

	register_rest_route(
		$ns,
		'/events/(?P<id>\d+)/rsvps',
		[
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\get_event_rsvps',
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
}

/* ───────────────────────────────────────────────
 * Upcoming events
 * ─────────────────────────────────────────────── */

function get_upcoming_events( \WP_REST_Request $request ): \WP_REST_Response {
	$now      = current_time( 'c' );
	$per_page = min( 50, $request->get_param( 'per_page' ) );
	$type     = sanitize_text_field( $request->get_param( 'event_type' ) );

	$meta_query = [
		'relation' => 'AND',
		[
			'key'     => '_lfuf_start_datetime',
			'value'   => $now,
			'compare' => '>=',
			'type'    => 'DATETIME',
		],
	];

	$tax_query = [];
	if ( $type ) {
		$tax_query[] = [
			'taxonomy' => 'lfuf_event_type',
			'field'    => 'slug',
			'terms'    => $type,
		];
	}

	$events = get_posts(
		[
			'post_type'   => 'lfuf_event',
			'post_status' => 'publish',
			'numberposts' => $per_page,
			'meta_key'    => '_lfuf_start_datetime',
			'orderby'     => 'meta_value',
			'order'       => 'ASC',
			'meta_query'  => $meta_query,
			'tax_query'   => $tax_query ?: [],
		]
	);

	return new \WP_REST_Response(
		array_map( __NAMESPACE__ . '\\build_event_data', $events ),
		200,
	);
}

/* ───────────────────────────────────────────────
 * Past events
 * ─────────────────────────────────────────────── */

function get_past_events( \WP_REST_Request $request ): \WP_REST_Response {
	$now      = current_time( 'c' );
	$per_page = min( 50, $request->get_param( 'per_page' ) );

	$events = get_posts(
		[
			'post_type'   => 'lfuf_event',
			'post_status' => 'publish',
			'numberposts' => $per_page,
			'meta_key'    => '_lfuf_start_datetime',
			'orderby'     => 'meta_value',
			'order'       => 'DESC',
			'meta_query'  => [
				[
					'key'     => '_lfuf_start_datetime',
					'value'   => $now,
					'compare' => '<',
					'type'    => 'DATETIME',
				],
			],
		]
	);

	return new \WP_REST_Response(
		array_map( __NAMESPACE__ . '\\build_event_data', $events ),
		200,
	);
}

/* ───────────────────────────────────────────────
 * RSVP submission
 * ─────────────────────────────────────────────── */

function submit_rsvp( \WP_REST_Request $request ): \WP_REST_Response {
	$name = (string) $request->get_param( 'name' );
	$note = (string) $request->get_param( 'note' );

	// Optional content screening via Simple Spam Shield (keyword, link, and
	// duplicate guards), complementing this module's own honeypot, rate
	// limiting, and duplicate detection in add_rsvp(). Skipped when the
	// honeypot is tripped so those bots still get add_rsvp()'s silent
	// fake-success rather than a visible rejection. Degrades gracefully when
	// Simple Spam Shield is not installed.
	if ( function_exists( 'simple_spam_shield_check' ) && '' === trim( (string) $request->get_param( 'website' ) ) ) {
		$check = simple_spam_shield_check(
			[
				'content' => trim( $note . ' ' . $name ),
				'author'  => $name,
				'email'   => (string) $request->get_param( 'email' ),
			],
			'rsvp_form',
		);

		if ( is_wp_error( $check ) ) {
			return new \WP_REST_Response(
				[
					'message' => $check->get_error_message(),
					'code'    => $check->get_error_code(),
				],
				400,
			);
		}
	}

	$result = RSVP\add_rsvp(
		[
			'event_id'   => $request->get_param( 'id' ),
			'name'       => $name,
			'email'      => $request->get_param( 'email' ),
			'party_size' => $request->get_param( 'party_size' ),
			'note'       => $note,
			'honeypot'   => $request->get_param( 'website' ), // Honeypot field.
		]
	);

	if ( is_wp_error( $result ) ) {
		$status_code = match ( $result->get_error_code() ) {
			'rate_limited'   => 429,
			'duplicate_rsvp' => 409,
			'rsvp_full'      => 409,
			default          => 400,
		};

		return new \WP_REST_Response(
			[
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			],
			$status_code,
		);
	}

	$summary = RSVP\get_event_rsvp_summary( $request->get_param( 'id' ) );

	return new \WP_REST_Response(
		[
			'rsvp'    => [
				'id'         => $result['id'],
				'name'       => $result['name'],
				'party_size' => $result['party_size'],
				'token'      => $result['token'],
			],
			'summary' => $summary,
			'message' => __( 'You\'re on the list! See you there.', 'farm-stand-manager' ),
		],
		201
	);
}

/* ───────────────────────────────────────────────
 * RSVP cancellation
 * ─────────────────────────────────────────────── */

function cancel_rsvp( \WP_REST_Request $request ): \WP_REST_Response {
	$token   = $request->get_param( 'token' );
	$deleted = RSVP\cancel_rsvp( $token );

	if ( ! $deleted ) {
		return new \WP_REST_Response(
			[ 'message' => __( 'RSVP not found or already cancelled.', 'farm-stand-manager' ) ],
			404,
		);
	}

	return new \WP_REST_Response(
		[
			'deleted' => true,
			'message' => __( 'Your RSVP has been cancelled.', 'farm-stand-manager' ),
		],
		200
	);
}

/* ───────────────────────────────────────────────
 * Admin RSVP list
 * ─────────────────────────────────────────────── */

function get_event_rsvps( \WP_REST_Request $request ): \WP_REST_Response {
	$event_id = $request->get_param( 'id' );

	$rsvps   = RSVP\get_event_rsvps( $event_id );
	$summary = RSVP\get_event_rsvp_summary( $event_id );

	return new \WP_REST_Response(
		[
			'rsvps'   => $rsvps,
			'summary' => $summary,
		],
		200
	);
}

/* ───────────────────────────────────────────────
 * Event data builder
 * ─────────────────────────────────────────────── */

function build_event_data( \WP_Post $event ): array {
	$id = $event->ID;

	// Location.
	$location_id = (int) get_post_meta( $id, '_lfuf_event_location_id', true );
	$location    = null;
	if ( $location_id > 0 ) {
		$loc = get_post( $location_id );
		if ( $loc ) {
			$location = [
				'id'      => $loc->ID,
				'title'   => $loc->post_title,
				'address' => get_post_meta( $loc->ID, '_lfuf_address', true ),
				'venmo'   => get_post_meta( $loc->ID, '_lfuf_venmo_handle', true ),
			];
		}
	}

	// Event type terms.
	$types      = get_the_terms( $id, 'lfuf_event_type' );
	$type_names = ( $types && ! is_wp_error( $types ) ) ? wp_list_pluck( $types, 'name' ) : [];
	$type_slugs = ( $types && ! is_wp_error( $types ) ) ? wp_list_pluck( $types, 'slug' ) : [];

	// Thumbnail.
	$thumb_id  = get_post_thumbnail_id( $id );
	$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';

	// RSVP summary.
	$rsvp_enabled = (bool) get_post_meta( $id, '_lfuf_em_rsvp_enabled', true );
	$rsvp_summary = $rsvp_enabled ? RSVP\get_event_rsvp_summary( $id ) : null;

	// Featured products.
	$product_ids = get_post_meta( $id, '_lfuf_featured_product_ids', true ) ?: [];
	$products    = [];
	if ( ! empty( $product_ids ) ) {
		$posts    = get_posts(
			[
				'post_type'   => 'lfuf_product',
				'post__in'    => $product_ids,
				'numberposts' => 10,
				'post_status' => 'publish',
			]
		);
		$products = array_map(
			fn ( \WP_Post $p ) => [
				'id'    => $p->ID,
				'title' => $p->post_title,
			],
			$posts
		);
	}

	return [
		'id'              => $id,
		'title'           => $event->post_title,
		'excerpt'         => $event->post_excerpt,
		'permalink'       => get_permalink( $id ),
		'thumbnail_url'   => $thumb_url ?: '',
		'start'           => get_post_meta( $id, '_lfuf_start_datetime', true ),
		'end'             => get_post_meta( $id, '_lfuf_end_datetime', true ),
		'recurrence_rule' => get_post_meta( $id, '_lfuf_recurrence_rule', true ),
		'event_types'     => array_values( $type_names ),
		'event_slugs'     => array_values( $type_slugs ),
		'location'        => $location,
		'donation_link'   => get_post_meta( $id, '_lfuf_donation_link', true ),
		'cost_note'       => get_post_meta( $id, '_lfuf_em_cost_note', true ),
		'what_to_bring'   => get_post_meta( $id, '_lfuf_em_what_to_bring', true ),
		'cancelled'       => (bool) get_post_meta( $id, '_lfuf_em_cancelled', true ),
		'rsvp'            => $rsvp_summary,
		'products'        => $products,
	];
}
