<?php
/**
 * Abilities API registration for the Availability Board module.
 *
 * Registers a board-specific ability that returns the grouped,
 * enriched board data — the same shape the front-end block consumes.
 */

declare(strict_types=1);

namespace Leftfield\AvailabilityBoard\Abilities;

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_abilities_api_init',
	function (): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'producerkit/get-board',
			[
				'label'               => __( 'Get Availability Board', 'producerkit' ),
				'description'         => __( 'Retrieve the full availability board with products grouped by type, including thumbnails, prices, and status badges. Optionally filter by status, product type, or location.', 'producerkit' ),
				'category'            => 'farm-availability',
				'execute_callback'    => function ( array $input = [] ): array {
					// Reuse the REST callback by constructing a mock request.
					$request = new \WP_REST_Request( 'GET', '/lfuf/v1/board' );
					$request->set_param( 'status', $input['status'] ?? '' );
					$request->set_param( 'product_type', $input['product_type'] ?? '' );
					$request->set_param( 'location', (int) ( $input['location_id'] ?? 0 ) );

					$response = \Leftfield\AvailabilityBoard\REST\get_board( $request );
					return $response->get_data();
				},
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'status'       => [
							'type'        => 'string',
							'description' => 'Comma-separated status filter (e.g. "abundant,available,limited").',
						],
						'product_type' => [
							'type'        => 'string',
							'description' => 'Product type term slug filter.',
						],
						'location_id'  => [
							'type'        => 'integer',
							'description' => 'Location ID filter (0 = all).',
						],
					],
					'default'    => [],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'groups'       => [
							'type'  => 'array',
							'items' => [
								'type'       => 'object',
								'properties' => [
									'slug'  => [ 'type' => 'string' ],
									'label' => [ 'type' => 'string' ],
									'items' => [ 'type' => 'array' ],
								],
							],
						],
						'total_items'  => [ 'type' => 'integer' ],
						'filter_types' => [ 'type' => 'array' ],
						'statuses'     => [
							'type'  => 'array',
							'items' => [ 'type' => 'string' ],
						],
						'generated_at' => [ 'type' => 'string' ],
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
