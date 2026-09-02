<?php
/**
 * REST routes for commissions, under producerkit/v1.
 *
 * Public:
 *   POST   /commissions                          submit a request
 *   GET    /commissions/token/{token}            the customer's own view
 *   POST   /commissions/quote/{token}/accept     accept a quote
 *   POST   /commissions/quote/{token}/decline    decline a quote
 *
 * Staff (edit_posts):
 *   GET    /commissions                          list, optionally by status
 *   POST   /commissions/{id}/quote               send a quote
 *   POST   /commissions/{id}/status              move the status on
 *
 * The public accept/decline routes authenticate with the quote token alone —
 * the guest has no account, and the token is the capability. They are POST
 * rather than GET so a link preview, a scanner, or a mail client prefetching
 * the URL cannot accept a quote on the customer's behalf.
 */

declare(strict_types=1);

namespace ProducerKit\Commissions\REST;

use ProducerKit\Commissions\Store;

defined( 'ABSPATH' ) || exit;

const NAMESPACE_V1 = 'producerkit/v1';

add_action( 'rest_api_init', __NAMESPACE__ . '\\register_routes' );

function register_routes(): void {
	register_rest_route(
		NAMESPACE_V1,
		'/commissions',
		[
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => __NAMESPACE__ . '\\create',
				'permission_callback' => '__return_true',
				'args'                => create_args(),
			],
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => __NAMESPACE__ . '\\index',
				'permission_callback' => fn () => current_user_can( 'edit_posts' ),
			],
		]
	);

	register_rest_route(
		NAMESPACE_V1,
		'/commissions/token/(?P<token>[A-Za-z0-9]{16,64})',
		[
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\show_by_token',
			'permission_callback' => '__return_true',
		]
	);

	register_rest_route(
		NAMESPACE_V1,
		'/commissions/quote/(?P<token>[A-Za-z0-9]{16,64})/(?P<decision>accept|decline)',
		[
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => __NAMESPACE__ . '\\decide',
			'permission_callback' => '__return_true',
		]
	);

	register_rest_route(
		NAMESPACE_V1,
		'/commissions/(?P<id>\d+)/quote',
		[
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => __NAMESPACE__ . '\\quote',
			'permission_callback' => fn () => current_user_can( 'edit_posts' ),
		]
	);

	register_rest_route(
		NAMESPACE_V1,
		'/commissions/(?P<id>\d+)/status',
		[
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => __NAMESPACE__ . '\\status',
			'permission_callback' => fn () => current_user_can( 'edit_posts' ),
		]
	);
}

/**
 * @return array<string, array<string, mixed>>
 */
function create_args(): array {
	return [
		'name'         => [
			'required' => true,
			'type'     => 'string',
		],
		'email'        => [
			'required' => true,
			'type'     => 'string',
		],
		'description'  => [
			'required' => true,
			'type'     => 'string',
		],
		'phone'        => [ 'type' => 'string' ],
		'product_type' => [ 'type' => 'string' ],
		'material'     => [ 'type' => 'string' ],
		'budget_range' => [ 'type' => 'string' ],
		'deadline'     => [ 'type' => 'string' ],
		// Honeypot. Named for what a bot expects to see, not what it does.
		'website'      => [ 'type' => 'string' ],
	] + array_fill_keys(
		// Onsite Spam Guard's hidden fields. Declared so they survive into the
		// handler: the guard falls back to $_POST, which is empty for a JSON
		// body, so a REST submission has to carry them itself.
		\ProducerKit\Core\Requests\spam_guard_fields(),
		[ 'type' => 'string' ]
	);
}

function error_status( \WP_Error $error ): int {
	return [
		'rate_limited'       => 429,
		'spam_rejected'      => 429,
		'not_found'          => 404,
		'invalid_transition' => 409,
		'db_error'           => 500,
	][ $error->get_error_code() ] ?? 400;
}

function create( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
	$data = [
		'name'         => (string) $request->get_param( 'name' ),
		'email'        => (string) $request->get_param( 'email' ),
		'description'  => (string) $request->get_param( 'description' ),
		'phone'        => (string) $request->get_param( 'phone' ),
		'product_type' => (string) $request->get_param( 'product_type' ),
		'material'     => (string) $request->get_param( 'material' ),
		'budget_range' => (string) $request->get_param( 'budget_range' ),
		'deadline'     => (string) $request->get_param( 'deadline' ),
		'honeypot'     => (string) $request->get_param( 'website' ),
	];

	foreach ( \ProducerKit\Core\Requests\spam_guard_fields() as $field ) {
		$value = $request->get_param( $field );
		if ( null !== $value ) {
			$data[ $field ] = (string) $value;
		}
	}

	$result = Store\create( $data );

	if ( is_wp_error( $result ) ) {
		$result->add_data( [ 'status' => error_status( $result ) ] );
		return $result;
	}

	return new \WP_REST_Response( $result, 201 );
}

function index( \WP_REST_Request $request ): \WP_REST_Response {
	return new \WP_REST_Response(
		Store\list_commissions(
			[
				'status' => (string) $request->get_param( 'status' ),
				'limit'  => (int) ( $request->get_param( 'limit' ) ?: 50 ),
				'offset' => (int) $request->get_param( 'offset' ),
			]
		)
	);
}

function show_by_token( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
	$commission = Store\find_by_token( (string) $request->get_param( 'token' ) );

	if ( null === $commission ) {
		return new \WP_Error( 'not_found', __( 'Commission not found.', 'producerkit' ), [ 'status' => 404 ] );
	}

	return new \WP_REST_Response( Store\to_public( $commission ) );
}

/**
 * Accept or decline a quote using the quote token.
 */
function decide( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
	$commission = Store\find_by_quote_token( (string) $request->get_param( 'token' ) );

	if ( null === $commission ) {
		// Covers both "no such token" and "expired": a guest cannot tell the
		// difference, and neither answer helps someone guessing.
		return new \WP_Error(
			'not_found',
			__( 'That quote link is no longer valid. Please ask for a new one.', 'producerkit' ),
			[ 'status' => 404 ]
		);
	}

	$decision = 'accept' === $request->get_param( 'decision' ) ? 'accepted' : 'declined';
	$result   = Store\set_status( (int) $commission['id'], $decision );

	if ( is_wp_error( $result ) ) {
		$result->add_data( [ 'status' => error_status( $result ) ] );
		return $result;
	}

	return new \WP_REST_Response( $result );
}

function quote( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
	$result = Store\send_quote(
		(int) $request->get_param( 'id' ),
		(float) $request->get_param( 'price' ),
		(string) $request->get_param( 'estimated_date' ),
		(string) $request->get_param( 'maker_note' )
	);

	if ( is_wp_error( $result ) ) {
		$result->add_data( [ 'status' => error_status( $result ) ] );
		return $result;
	}

	return new \WP_REST_Response( $result );
}

function status( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
	$result = Store\set_status(
		(int) $request->get_param( 'id' ),
		(string) $request->get_param( 'status' )
	);

	if ( is_wp_error( $result ) ) {
		$result->add_data( [ 'status' => error_status( $result ) ] );
		return $result;
	}

	return new \WP_REST_Response( $result );
}
