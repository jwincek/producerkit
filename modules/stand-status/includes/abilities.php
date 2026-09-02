<?php
/**
 * Abilities API registration for Stand Status.
 *
 * Extends the core farm-locations category with
 * stand-specific abilities for toggling and querying status.
 *
 * Requires WordPress 6.9+ (gracefully skips on older versions).
 */

declare(strict_types=1);

namespace ProducerKit\StandStatus\Abilities;

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_abilities_api_init',
	function (): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'producerkit/toggle-stand-status',
			[
				'label'               => __( 'Toggle Stand Status', 'producerkit' ),
				'description'         => __( 'Open or close the farm stand and optionally set a status message. Records a timestamp of the change.', 'producerkit' ),
				'category'            => 'farm-locations',
				'execute_callback'    => function ( array $input ): array {
					$location_id    = (int) $input['location_id'];
					$is_open        = (bool) $input['is_open'];
					$status_message = sanitize_text_field( $input['status_message'] ?? '' );

					$post = get_post( $location_id );
					if ( ! $post || $post->post_type !== 'pkit_location' ) {
						return [
							'success' => false,
							'message' => 'Location not found.',
						];
					}

					update_post_meta( $location_id, '_pkit_is_open', $is_open );
					update_post_meta( $location_id, '_pkit_ss_status_message', $status_message );
					update_post_meta( $location_id, '_pkit_ss_last_toggled', gmdate( 'c' ) );

					do_action( 'pkit_stand_status_changed', $location_id, $is_open, $status_message );

					return [
						'success'        => true,
						'id'             => $location_id,
						'is_open'        => $is_open,
						'status_message' => $status_message,
					];
				},
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'location_id'    => [
							'type'        => 'integer',
							'description' => 'The stand location post ID.',
						],
						'is_open'        => [
							'type'        => 'boolean',
							'description' => 'True to open, false to close.',
						],
						'status_message' => [
							'type'        => 'string',
							'description' => 'Optional message like "Back at 2 PM".',
						],
					],
					'required'   => [ 'location_id', 'is_open' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success'        => [ 'type' => 'boolean' ],
						'id'             => [ 'type' => 'integer' ],
						'is_open'        => [ 'type' => 'boolean' ],
						'status_message' => [ 'type' => 'string' ],
						'message'        => [ 'type' => 'string' ],
					],
				],
				'permission_callback' => fn () => current_user_can( 'edit_posts' ),
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [ 'idempotent' => true ],
				],
			]
		);

		wp_register_ability(
			'producerkit/get-stand-info',
			[
				'label'               => __( 'Get Stand Info', 'producerkit' ),
				'description'         => __( 'Retrieve the current status, schedule, season dates, address, hours, and Venmo handle for a stand location.', 'producerkit' ),
				'category'            => 'farm-locations',
				'execute_callback'    => function ( array $input ): array {
					$post = get_post( (int) $input['location_id'] );
					if ( ! $post || $post->post_type !== 'pkit_location' || $post->post_status !== 'publish' ) {
						return [ 'error' => 'Stand not found.' ];
					}
					return \ProducerKit\StandStatus\REST\build_stand_data( $post );
				},
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'location_id' => [
							'type'        => 'integer',
							'description' => 'The stand location post ID.',
						],
					],
					'required'   => [ 'location_id' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'id'              => [ 'type' => 'integer' ],
						'name'            => [ 'type' => 'string' ],
						'is_open'         => [ 'type' => 'boolean' ],
						'in_season'       => [ 'type' => 'boolean' ],
						'status_message'  => [ 'type' => 'string' ],
						'address'         => [ 'type' => 'string' ],
						'hours'           => [ 'type' => 'string' ],
						'venmo_handle'    => [
							'type'        => 'string',
							'description' => 'Legacy Venmo handle; prefer payment_methods.',
						],
						'payment_methods' => [
							'type'  => 'array',
							'items' => [
								'type'       => 'object',
								'properties' => [
									'type'    => [ 'type' => 'string' ],
									'label'   => [ 'type' => 'string' ],
									'value'   => [ 'type' => 'string' ],
									'url'     => [ 'type' => 'string' ],
									'is_link' => [ 'type' => 'boolean' ],
								],
							],
						],
						'season_start'    => [ 'type' => 'string' ],
						'season_end'      => [ 'type' => 'string' ],
					],
				],
				'permission_callback' => '__return_true',
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [ 'readonly' => true ],
				],
			]
		);
	}
);
