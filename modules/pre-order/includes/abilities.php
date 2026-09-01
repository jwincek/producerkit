<?php
/**
 * Abilities API registration for the Pre-Order module.
 */

declare(strict_types=1);

namespace Leftfield\PreOrder\Abilities;

use Leftfield\PreOrder\Orders;

defined( 'ABSPATH' ) || exit;

const ITEM_SCHEMA = [
	'type'       => 'object',
	'properties' => [
		'product_id' => [ 'type' => 'integer' ],
		'qty'        => [ 'type' => 'integer' ],
		'title'      => [ 'type' => 'string' ],
		'unit'       => [ 'type' => 'string' ],
		'price'      => [ 'type' => 'string' ],
	],
];

const ORDER_SCHEMA = [
	'type'       => 'object',
	'properties' => [
		'id'            => [ 'type' => 'integer' ],
		'token'         => [ 'type' => 'string' ],
		'location_id'   => [ 'type' => 'integer' ],
		'location_name' => [ 'type' => 'string' ],
		'name'          => [ 'type' => 'string' ],
		'email'         => [ 'type' => 'string' ],
		'phone'         => [ 'type' => 'string' ],
		'pickup_date'   => [ 'type' => 'string' ],
		'status'        => [ 'type' => 'string' ],
		'items'         => [
			'type'  => 'array',
			'items' => ITEM_SCHEMA,
		],
		'note'          => [ 'type' => 'string' ],
		'created_at'    => [ 'type' => 'string' ],
	],
];

add_action(
	'wp_abilities_api_categories_init',
	function (): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			'farm-preorders',
			[
				'label'       => __( 'Farm Pre-Orders', 'producerkit' ),
				'description' => __( 'Abilities for creating and managing pay-at-pickup pre-orders.', 'producerkit' ),
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
			'producerkit/create-preorder',
			[
				'label'               => __( 'Create Pre-Order', 'producerkit' ),
				'description'         => __( 'Place a pay-at-pickup pre-order for farm products: product lines with quantities, a pickup date within the next month, and contact details. Returns a cancellation token.', 'producerkit' ),
				'category'            => 'farm-preorders',
				'execute_callback'    => function ( array $input ): array {
					$result = Orders\create_order( $input );
					if ( is_wp_error( $result ) ) {
						return [
							'success' => false,
							'message' => $result->get_error_message(),
						];
					}
					return [
						'success' => true,
						'order'   => $result,
					];
				},
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'name'        => [
							'type'        => 'string',
							'description' => 'Customer name.',
							'minLength'   => 1,
						],
						'items'       => [
							'type'        => 'array',
							'description' => 'Product lines: [{product_id, qty}].',
							'items'       => [
								'type'       => 'object',
								'properties' => [
									'product_id' => [ 'type' => 'integer' ],
									'qty'        => [
										'type'    => 'integer',
										'minimum' => 1,
									],
								],
								'required'   => [ 'product_id', 'qty' ],
							],
						],
						'pickup_date' => [
							'type'        => 'string',
							'description' => 'Pickup date (YYYY-MM-DD), today through +30 days.',
						],
						'location_id' => [
							'type'        => 'integer',
							'description' => 'Optional pickup location post ID.',
						],
						'email'       => [
							'type'        => 'string',
							'description' => 'Optional email for confirmation.',
						],
						'phone'       => [
							'type'        => 'string',
							'description' => 'Optional phone number.',
						],
						'note'        => [
							'type'        => 'string',
							'description' => 'Optional note to the farm.',
						],
					],
					'required'   => [ 'name', 'items', 'pickup_date' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success' => [ 'type' => 'boolean' ],
						'message' => [ 'type' => 'string' ],
						'order'   => ORDER_SCHEMA,
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
			'producerkit/list-preorders',
			[
				'label'               => __( 'List Pre-Orders', 'producerkit' ),
				'description'         => __( 'Retrieve pre-orders, optionally filtered by status (pending, confirmed, ready, picked_up, cancelled). Staff only.', 'producerkit' ),
				'category'            => 'farm-preorders',
				'execute_callback'    => function ( array $input = [] ): array {
					return Orders\get_orders( $input );
				},
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'status' => [
							'type'        => 'string',
							'description' => 'Filter by status.',
							'enum'        => array_merge( [ '' ], Orders\valid_statuses() ),
						],
						'limit'  => [
							'type'        => 'integer',
							'description' => 'Max orders to return (default 50).',
						],
						'offset' => [
							'type'        => 'integer',
							'description' => 'Pagination offset.',
						],
					],
					'default'    => [],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'orders' => [
							'type'  => 'array',
							'items' => ORDER_SCHEMA,
						],
						'total'  => [ 'type' => 'integer' ],
					],
				],
				'permission_callback' => fn () => current_user_can( 'edit_posts' ),
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [ 'readonly' => true ],
				],
			]
		);

		wp_register_ability(
			'producerkit/get-harvest-list',
			[
				'label'               => __( 'Get Harvest List', 'producerkit' ),
				'description'         => __( 'Aggregate active pre-orders (pending, confirmed, ready) into per-pickup-date totals of each product to have ready — the sheet a farmer takes to the field. Staff only.', 'producerkit' ),
				'category'            => 'farm-preorders',
				'execute_callback'    => function ( array $input = [] ): array {
					return Orders\get_harvest_list( $input );
				},
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'date_from'   => [
							'type'        => 'string',
							'description' => 'Start date (YYYY-MM-DD, default today).',
						],
						'date_to'     => [
							'type'        => 'string',
							'description' => 'End date (YYYY-MM-DD, default +30 days).',
						],
						'location_id' => [
							'type'        => 'integer',
							'description' => 'Filter by pickup location (0 = all).',
						],
					],
					'default'    => [],
				],
				'output_schema'       => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'pickup_date'   => [ 'type' => 'string' ],
							'location_id'   => [ 'type' => 'integer' ],
							'location_name' => [ 'type' => 'string' ],
							'order_count'   => [ 'type' => 'integer' ],
							'items'         => [
								'type'  => 'array',
								'items' => [
									'type'       => 'object',
									'properties' => [
										'product_id'  => [ 'type' => 'integer' ],
										'title'       => [ 'type' => 'string' ],
										'unit'        => [ 'type' => 'string' ],
										'total_qty'   => [ 'type' => 'integer' ],
										'order_count' => [ 'type' => 'integer' ],
									],
								],
							],
						],
					],
				],
				'permission_callback' => fn () => current_user_can( 'edit_posts' ),
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [ 'readonly' => true ],
				],
			]
		);

		wp_register_ability(
			'producerkit/update-preorder-status',
			[
				'label'               => __( 'Update Pre-Order Status', 'producerkit' ),
				'description'         => __( 'Move a pre-order through its lifecycle: pending → confirmed → ready → picked_up, or cancelled. Staff only.', 'producerkit' ),
				'category'            => 'farm-preorders',
				'execute_callback'    => function ( array $input ): array {
					$result = Orders\update_status( (int) $input['id'], (string) $input['status'] );
					if ( is_wp_error( $result ) ) {
						return [
							'success' => false,
							'message' => $result->get_error_message(),
						];
					}
					return [ 'success' => true ];
				},
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'id'     => [
							'type'        => 'integer',
							'description' => 'Pre-order ID.',
						],
						'status' => [
							'type'        => 'string',
							'description' => 'New status.',
							'enum'        => Orders\valid_statuses(),
						],
					],
					'required'   => [ 'id', 'status' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success' => [ 'type' => 'boolean' ],
						'message' => [ 'type' => 'string' ],
					],
				],
				'permission_callback' => fn () => current_user_can( 'edit_posts' ),
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [ 'idempotent' => true ],
				],
			]
		);
	}
);
