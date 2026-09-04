<?php
/**
 * Abilities API registration for the Commissions module.
 *
 * Pre-orders have been addressable by an agent since they were written;
 * commissions arrived from a merge that predated that work and had none at
 * all. So a maker could ask "which pre-orders are due for pickup?" and get an
 * answer, and "which commissions are waiting on a quote?" and get nothing.
 *
 * Every ability here is gated on Store\manage_cap() rather than edit_posts.
 * These rows hold a customer's name, email, phone and the text of what they
 * asked for, and quoting sets a price the business is then held to — an
 * ability looser than the admin screen guarding the same data would be the
 * same leak with a wider reach.
 */

declare(strict_types=1);

namespace ProducerKit\Commissions\Abilities;

use ProducerKit\Commissions\Store;

defined( 'ABSPATH' ) || exit;

/**
 * A commission as an agent sees it.
 *
 * Mirrors Store\to_public(), which is an allowlist — so the settlement columns
 * the WooCommerce module adds by ALTER TABLE do not leak through here either.
 */
const COMMISSION_SCHEMA = [
	'type'       => 'object',
	'properties' => [
		'id'             => [ 'type' => 'integer' ],
		'name'           => [ 'type' => 'string' ],
		'email'          => [ 'type' => 'string' ],
		'phone'          => [ 'type' => 'string' ],
		'description'    => [ 'type' => 'string' ],
		'product_type'   => [ 'type' => 'string' ],
		'material'       => [ 'type' => 'string' ],
		'budget_range'   => [ 'type' => 'string' ],
		'deadline'       => [ 'type' => 'string' ],
		'status'         => [ 'type' => 'string' ],
		'quoted_price'   => [ 'type' => [ 'number', 'null' ] ],
		'estimated_date' => [ 'type' => [ 'string', 'null' ] ],
		'maker_note'     => [ 'type' => 'string' ],
		'product_id'     => [ 'type' => 'integer' ],
		'created_at'     => [ 'type' => 'string' ],
	],
];

add_action(
	'wp_abilities_api_categories_init',
	function (): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			'producerkit-commissions',
			[
				'label'       => __( 'Commissions', 'producerkit' ),
				'description' => __( 'Abilities for reading commission requests and moving them through quoting and making.', 'producerkit' ),
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

		$may_manage = static fn (): bool => current_user_can( Store\manage_cap() );

		wp_register_ability(
			'producerkit/list-commissions',
			[
				'label'               => __( 'List Commissions', 'producerkit' ),
				'description'         => __( 'Retrieve commission requests, optionally filtered by status (new, quoted, accepted, in_progress, complete, declined, cancelled). Use this to answer questions like which requests are still waiting on a quote. Staff only.', 'producerkit' ),
				'category'            => 'producerkit-commissions',
				'execute_callback'    => static function ( array $input = [] ): array {
					return Store\list_commissions( $input );
				},
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'status' => [
							'type'        => 'string',
							'description' => 'Filter by status.',
							'enum'        => array_merge( [ '' ], Store\valid_statuses() ),
						],
						'limit'  => [
							'type'        => 'integer',
							'description' => 'Max commissions to return (default 50).',
						],
						'offset' => [
							'type'        => 'integer',
							'description' => 'Pagination offset.',
						],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'commissions' => [
							'type'  => 'array',
							'items' => COMMISSION_SCHEMA,
						],
						'total'       => [ 'type' => 'integer' ],
					],
				],
				'permission_callback' => $may_manage,
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [ 'readonly' => true ],
				],
			]
		);

		wp_register_ability(
			'producerkit/count-commissions-by-status',
			[
				'label'               => __( 'Count Commissions by Status', 'producerkit' ),
				'description'         => __( 'How many commission requests sit at each status. One grouped query, so this is the cheap way to ask how much work is waiting rather than listing everything. Staff only.', 'producerkit' ),
				'category'            => 'producerkit-commissions',
				'execute_callback'    => static function (): array {
					return [ 'counts' => Store\count_by_status() ];
				},
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'counts' => [
							'type'                 => 'object',
							'description'          => 'Status => count. Statuses with none are omitted.',
							'additionalProperties' => [ 'type' => 'integer' ],
						],
					],
				],
				'permission_callback' => $may_manage,
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [ 'readonly' => true ],
				],
			]
		);

		wp_register_ability(
			'producerkit/send-commission-quote',
			[
				'label'               => __( 'Send Commission Quote', 'producerkit' ),
				'description'         => __( 'Quote a price for a commission and email the customer a link to accept or decline. Also used to revise a quote, or reissue one after the 30-day link has expired. Staff only.', 'producerkit' ),
				'category'            => 'producerkit-commissions',
				'execute_callback'    => static function ( array $input ): array {
					$result = Store\send_quote(
						(int) $input['id'],
						(float) $input['price'],
						(string) ( $input['estimated_date'] ?? '' ),
						(string) ( $input['maker_note'] ?? '' )
					);

					if ( is_wp_error( $result ) ) {
						return [
							'success' => false,
							'message' => $result->get_error_message(),
						];
					}

					// Deliberately not returned: the quote token. It is the
					// customer's capability to accept a binding price, and it
					// reaches them by email rather than through this response.
					unset( $result['quote_token'] );

					return [
						'success'    => true,
						'commission' => $result,
					];
				},
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'id'             => [
							'type'        => 'integer',
							'description' => 'Commission ID.',
						],
						'price'          => [
							'type'             => 'number',
							'description'      => 'Quoted price. Must be greater than zero.',
							'exclusiveMinimum' => 0,
						],
						'estimated_date' => [
							'type'        => 'string',
							'description' => 'Optional estimated ready date (YYYY-MM-DD).',
						],
						'maker_note'     => [
							'type'        => 'string',
							'description' => 'Optional note to the customer, sent with the quote.',
						],
					],
					'required'   => [ 'id', 'price' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success'    => [ 'type' => 'boolean' ],
						'message'    => [ 'type' => 'string' ],
						'commission' => COMMISSION_SCHEMA,
					],
				],
				'permission_callback' => $may_manage,
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [ 'idempotent' => false ],
				],
			]
		);

		wp_register_ability(
			'producerkit/update-commission-status',
			[
				'label'               => __( 'Update Commission Status', 'producerkit' ),
				'description'         => __( 'Move a commission along: accepted, in_progress, complete, declined or cancelled. The transition table is enforced, so an illegal move is refused with a reason rather than applied. Quoting is not available here — it needs a price, so use Send Commission Quote. Staff only.', 'producerkit' ),
				'category'            => 'producerkit-commissions',
				'execute_callback'    => static function ( array $input ): array {
					$result = Store\set_status( (int) $input['id'], (string) $input['status'] );

					if ( is_wp_error( $result ) ) {
						return [
							'success' => false,
							'message' => $result->get_error_message(),
						];
					}

					return [
						'success'    => true,
						'commission' => $result,
					];
				},
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'id'     => [
							'type'        => 'integer',
							'description' => 'Commission ID.',
						],
						'status' => [
							'type'        => 'string',
							'description' => 'Target status. "quoted" is excluded on purpose: it requires a price and a fresh token, which only Send Commission Quote produces.',
							'enum'        => array_values(
								array_diff( Store\valid_statuses(), [ 'quoted' ] )
							),
						],
					],
					'required'   => [ 'id', 'status' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success'    => [ 'type' => 'boolean' ],
						'message'    => [ 'type' => 'string' ],
						'commission' => COMMISSION_SCHEMA,
					],
				],
				'permission_callback' => $may_manage,
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [ 'idempotent' => false ],
				],
			]
		);
	}
);
