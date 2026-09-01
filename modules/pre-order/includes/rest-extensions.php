<?php
/**
 * REST API for pre-orders (lfuf/v1).
 *
 *   POST   /preorders            — create (public, rate-limited)
 *   GET    /preorders/{token}    — look up by token (public; token is the secret)
 *   DELETE /preorders/{token}    — customer cancel (public; token is the secret)
 *   GET    /preorders            — list (staff, edit_posts)
 *   POST   /preorders/{id}/status — update status (staff, edit_posts)
 */

declare(strict_types=1);

namespace ProducerKit\PreOrder\REST;

use ProducerKit\PreOrder\Orders;

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', __NAMESPACE__ . '\\register_routes' );

function register_routes(): void {
	$ns = 'lfuf/v1';

	register_rest_route(
		$ns,
		'/preorders',
		[
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => __NAMESPACE__ . '\\create',
				'permission_callback' => '__return_true',
				'args'                => [
					'name'        => [
						'type'     => 'string',
						'required' => true,
					],
					'items'       => [
						'type'     => 'array',
						'required' => true,
					],
					'pickup_date' => [
						'type'     => 'string',
						'required' => true,
					],
					'location_id' => [
						'type'    => 'integer',
						'default' => 0,
					],
					'email'       => [
						'type'    => 'string',
						'default' => '',
					],
					'phone'       => [
						'type'    => 'string',
						'default' => '',
					],
					'note'        => [
						'type'    => 'string',
						'default' => '',
					],
					'website'     => [
						'type'    => 'string',
						'default' => '',
					], // honeypot
				],
			],
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => __NAMESPACE__ . '\\index',
				'permission_callback' => fn () => current_user_can( 'edit_posts' ),
				'args'                => [
					'status' => [
						'type'    => 'string',
						'default' => '',
					],
					'limit'  => [
						'type'    => 'integer',
						'default' => 50,
					],
					'offset' => [
						'type'    => 'integer',
						'default' => 0,
					],
				],
			],
		]
	);

	register_rest_route(
		$ns,
		'/preorders/harvest',
		[
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\harvest',
			'permission_callback' => fn () => current_user_can( 'edit_posts' ),
			'args'                => [
				'date_from'   => [
					'type'    => 'string',
					'default' => '',
				],
				'date_to'     => [
					'type'    => 'string',
					'default' => '',
				],
				'location_id' => [
					'type'    => 'integer',
					'default' => 0,
				],
			],
		]
	);

	register_rest_route(
		$ns,
		'/preorders/(?P<token>[a-zA-Z0-9]{20,64})',
		[
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => __NAMESPACE__ . '\\show',
				'permission_callback' => '__return_true',
			],
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => __NAMESPACE__ . '\\cancel',
				'permission_callback' => '__return_true',
			],
		]
	);

	register_rest_route(
		$ns,
		'/preorders/(?P<id>\d+)/status',
		[
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => __NAMESPACE__ . '\\update_status',
			'permission_callback' => fn () => current_user_can( 'edit_posts' ),
			'args'                => [
				'status' => [
					'type'     => 'string',
					'required' => true,
					'enum'     => Orders\valid_statuses(),
				],
			],
		]
	);
}

function create( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
	$order = Orders\create_order(
		[
			'name'        => (string) $request->get_param( 'name' ),
			'items'       => (array) $request->get_param( 'items' ),
			'pickup_date' => (string) $request->get_param( 'pickup_date' ),
			'location_id' => (int) $request->get_param( 'location_id' ),
			'email'       => (string) $request->get_param( 'email' ),
			'phone'       => (string) $request->get_param( 'phone' ),
			'note'        => (string) $request->get_param( 'note' ),
			'honeypot'    => (string) $request->get_param( 'website' ),
		]
	);

	if ( is_wp_error( $order ) ) {
		$order->add_data( [ 'status' => 400 ] );
		return $order;
	}

	return new \WP_REST_Response(
		[
			'message' => __( 'Pre-order received! We\'ll have it ready for pickup.', 'producerkit' ),
			'order'   => $order,
		],
		201
	);
}

function show( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
	$order = Orders\get_order_by_token( (string) $request['token'] );
	if ( ! $order ) {
		return new \WP_Error( 'not_found', __( 'Pre-order not found.', 'producerkit' ), [ 'status' => 404 ] );
	}
	return new \WP_REST_Response( $order );
}

function cancel( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
	$result = Orders\cancel_order_by_token( (string) $request['token'] );
	if ( is_wp_error( $result ) ) {
		$result->add_data( [ 'status' => $result->get_error_code() === 'not_found' ? 404 : 409 ] );
		return $result;
	}
	return new \WP_REST_Response( [ 'message' => __( 'Pre-order cancelled.', 'producerkit' ) ] );
}

function harvest( \WP_REST_Request $request ): \WP_REST_Response {
	$args = [ 'location_id' => (int) $request->get_param( 'location_id' ) ];
	if ( $request->get_param( 'date_from' ) ) {
		$args['date_from'] = (string) $request->get_param( 'date_from' );
	}
	if ( $request->get_param( 'date_to' ) ) {
		$args['date_to'] = (string) $request->get_param( 'date_to' );
	}
	return new \WP_REST_Response( Orders\get_harvest_list( $args ) );
}

function index( \WP_REST_Request $request ): \WP_REST_Response {
	return new \WP_REST_Response(
		Orders\get_orders(
			[
				'status' => (string) $request->get_param( 'status' ),
				'limit'  => (int) $request->get_param( 'limit' ),
				'offset' => (int) $request->get_param( 'offset' ),
			]
		)
	);
}

function update_status( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
	$result = Orders\update_status( (int) $request['id'], (string) $request->get_param( 'status' ) );
	if ( is_wp_error( $result ) ) {
		$result->add_data( [ 'status' => $result->get_error_code() === 'not_found' ? 404 : 400 ] );
		return $result;
	}
	return new \WP_REST_Response( [ 'message' => __( 'Status updated.', 'producerkit' ) ] );
}
