<?php
/**
 * Abilities API registration for the Event Manager module.
 */

declare(strict_types=1);

namespace ProducerKit\EventManager\Abilities;

use ProducerKit\EventManager\RSVP;

defined( 'ABSPATH' ) || exit;

/**
 * One booking as an agent sees it.
 *
 * No token. It is the guest's capability to cancel their own booking, and it
 * reaches them by email; an agent that can already cancel on their behalf has
 * no use for it, and handing it out would let it travel further.
 */
const RSVP_SCHEMA = [
	'type'       => 'object',
	'properties' => [
		'id'         => [ 'type' => 'integer' ],
		'event_id'   => [ 'type' => 'integer' ],
		'name'       => [ 'type' => 'string' ],
		'email'      => [ 'type' => 'string' ],
		'party_size' => [ 'type' => 'integer' ],
		'note'       => [ 'type' => 'string' ],
		'created_at' => [ 'type' => 'string' ],
	],
];

add_action(
	'wp_abilities_api_categories_init',
	function (): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			'producerkit-events',
			[
				'label'       => __( 'Farm Events', 'producerkit' ),
				'description' => __( 'Abilities for farm events — pizza nights, potlucks, workshops, and tours.', 'producerkit' ),
			]
		);
	}
);

add_action(
	'wp_abilities_api_init',
	function (): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'producerkit/list-upcoming-events',
			[
				'label'               => __( 'List Upcoming Events', 'producerkit' ),
				'description'         => __( 'Retrieve upcoming farm events with location, RSVP status, and event type.', 'producerkit' ),
				'category'            => 'producerkit-events',
				'execute_callback'    => function ( array $input = [] ): array {
					$request = new \WP_REST_Request( 'GET', '/producerkit/v1/events/upcoming' );
					$request->set_param( 'per_page', (int) ( $input['per_page'] ?? 10 ) );
					$request->set_param( 'event_type', $input['event_type'] ?? '' );

					$response = \ProducerKit\EventManager\REST\get_upcoming_events( $request );
					return $response->get_data();
				},
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'per_page'   => [
							'type'        => 'integer',
							'description' => 'Max events to return (default 10).',
						],
						'event_type' => [
							'type'        => 'string',
							'description' => 'Filter by event type slug.',
						],
					],
					'default'    => [],
				],
				'output_schema'       => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'id'        => [ 'type' => 'integer' ],
							'title'     => [ 'type' => 'string' ],
							'start'     => [ 'type' => 'string' ],
							'end'       => [ 'type' => 'string' ],
							'cancelled' => [ 'type' => 'boolean' ],
							'permalink' => [ 'type' => 'string' ],
						],
					],
				],
				'permission_callback' => '__return_true',
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [ 'readonly' => true ],
				],
			]
		);

		wp_register_ability(
			'producerkit/rsvp-to-event',
			[
				'label'               => __( 'RSVP to Event', 'producerkit' ),
				'description'         => __( 'Submit an RSVP to a farm event. Returns a cancellation token.', 'producerkit' ),
				'category'            => 'producerkit-events',
				'execute_callback'    => function ( array $input ): array {
					$result = \ProducerKit\EventManager\RSVP\add_rsvp( $input );
					if ( is_wp_error( $result ) ) {
						return [
							'success' => false,
							'message' => $result->get_error_message(),
						];
					}
					return [
						'success' => true,
						'rsvp_id' => $result['id'],
						'token'   => $result['token'],
					];
				},
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'event_id'   => [
							'type'        => 'integer',
							'description' => 'Event post ID.',
						],
						'name'       => [
							'type'        => 'string',
							'description' => 'Attendee name.',
							'minLength'   => 1,
						],
						'email'      => [
							'type'        => 'string',
							'description' => 'Optional email.',
						],
						'party_size' => [
							'type'        => 'integer',
							'description' => 'Number of people (default 1).',
						],
						'note'       => [
							'type'        => 'string',
							'description' => 'Optional note.',
						],
					],
					'required'   => [ 'event_id', 'name' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success' => [ 'type' => 'boolean' ],
						'rsvp_id' => [ 'type' => 'integer' ],
						'token'   => [ 'type' => 'string' ],
						'message' => [ 'type' => 'string' ],
					],
				],
				'permission_callback' => '__return_true',
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [ 'idempotent' => false ],
				],
			]
		);
		wp_register_ability(
			'producerkit/list-event-rsvps',
			[
				'label'               => __( 'List Event RSVPs', 'producerkit' ),
				'description'         => __( 'Who is coming to an event: names, party sizes, notes and the headcount, with spots remaining when the event has a cap. This is how to answer "who is coming on Saturday?". Staff only.', 'producerkit' ),
				'category'            => 'producerkit-events',
				'execute_callback'    => static function ( array $input ): array {
					$event_id = (int) $input['event_id'];

					$rsvps = array_map(
						static function ( $row ): array {
							$row = (array) $row;
							// The token is the guest's own capability; it does
							// not belong in a list an agent can read.
							unset( $row['token'], $row['ip_hash'] );
							return $row;
						},
						RSVP\get_event_rsvps( $event_id )
					);

					return [
						'rsvps'   => $rsvps,
						'summary' => RSVP\get_event_rsvp_summary( $event_id ),
					];
				},
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'event_id' => [
							'type'        => 'integer',
							'description' => 'Event post ID.',
						],
					],
					'required'   => [ 'event_id' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'rsvps'   => [
							'type'  => 'array',
							'items' => RSVP_SCHEMA,
						],
						'summary' => [
							'type'       => 'object',
							'properties' => [
								'headcount'  => [ 'type' => 'integer' ],
								'spots_left' => [ 'type' => [ 'integer', 'null' ] ],
								'is_full'    => [ 'type' => 'boolean' ],
							],
						],
					],
				],
				'permission_callback' => static fn (): bool => current_user_can( RSVP\manage_cap() ),
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [ 'readonly' => true ],
				],
			]
		);

		wp_register_ability(
			'producerkit/cancel-rsvp',
			[
				'label'               => __( 'Cancel an RSVP', 'producerkit' ),
				'description'         => __( 'Cancel a booking on a guest\'s behalf, freeing their place in the headcount. Takes the RSVP id from List Event RSVPs. Staff only — a guest cancels their own from the link in their confirmation email.', 'producerkit' ),
				'category'            => 'producerkit-events',
				'execute_callback'    => static function ( array $input ): array {
					$rsvp = RSVP\find_by_id( (int) $input['id'] );

					if ( null === $rsvp ) {
						return [
							'success' => false,
							'message' => __( 'That booking no longer exists.', 'producerkit' ),
						];
					}

					// Through the same function the guest's own link uses, so
					// the cap arithmetic and pkit_rsvp_cancelled fire once, in
					// one place, however the cancellation was made.
					$cancelled = RSVP\cancel_rsvp( (string) $rsvp['token'] );

					return [
						'success'   => $cancelled,
						'message'   => $cancelled ? '' : __( 'Could not cancel that booking.', 'producerkit' ),
						'headcount' => RSVP\get_event_rsvp_summary( (int) $rsvp['event_id'] )['headcount'] ?? 0,
					];
				},
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'id' => [
							'type'        => 'integer',
							'description' => 'RSVP id, as returned by List Event RSVPs.',
						],
					],
					'required'   => [ 'id' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success'   => [ 'type' => 'boolean' ],
						'message'   => [ 'type' => 'string' ],
						'headcount' => [
							'type'        => 'integer',
							'description' => 'The event headcount after cancelling.',
						],
					],
				],
				'permission_callback' => static fn (): bool => current_user_can( RSVP\manage_cap() ),
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [ 'idempotent' => false ],
				],
			]
		);
	}
);
