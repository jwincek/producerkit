<?php
/**
 * Meta field registration for all plugin CPTs.
 *
 * Every field is registered with show_in_rest so it's available
 * to Gutenberg and the REST API out of the box.
 */

declare(strict_types=1);

namespace ProducerKit\Core\Meta_Fields;

defined( 'ABSPATH' ) || exit;

function register(): void {
	register_product_meta();
	register_source_meta();
	register_location_meta();
	register_event_meta();
}

/* ───────────────────────────────────────────────
 * Product meta
 * ─────────────────────────────────────────────── */
function register_product_meta(): void {
	$fields = [
		'_pkit_source_ids'    => [
			'type'        => 'array',
			'description' => 'Related source (grain/farm) post IDs.',
			'default'     => [],
			'items'       => [ 'type' => 'integer' ],
		],
		'_pkit_unit'          => [
			'type'        => 'string',
			'description' => 'Unit of sale — bunch, loaf, pint, lb, each, etc.',
			'default'     => '',
		],
		'_pkit_price'         => [
			'type'        => 'string',
			'description' => 'Display price (free-text to allow "donation" or "$5/loaf").',
			'default'     => '',
		],
		'_pkit_growing_notes' => [
			'type'        => 'string',
			'description' => 'Brief growing / baking notes shown on front end.',
			'default'     => '',
		],
		'_pkit_payment_mode'  => [
			'type'        => 'string',
			'description' => 'What a pre-order collects up front: none (reserve only), deposit, or full.',
			'default'     => 'none',
			'sanitize'    => __NAMESPACE__ . '\\sanitize_payment_mode',
		],
		'_pkit_deposit_kind'  => [
			'type'        => 'string',
			'description' => 'Whether the deposit is a fixed amount per unit or a percentage of the line.',
			'default'     => 'fixed',
			'sanitize'    => __NAMESPACE__ . '\\sanitize_deposit_kind',
		],
		'_pkit_deposit_value' => [
			'type'        => 'number',
			'description' => 'Deposit amount per unit, or percent of the line when the kind is percent.',
			'default'     => 0,
			'sanitize'    => __NAMESPACE__ . '\\sanitize_deposit_value',
		],
	];

	foreach ( $fields as $key => $args ) {
		register_post_meta(
			'pkit_product',
			$key,
			[
				'show_in_rest'      => is_array( $args['default'] )
				? [
					'schema' => [
						'type'  => 'array',
						'items' => $args['items'],
					],
				]
				: true,
				'single'            => true,
				'type'              => $args['type'],
				'description'       => $args['description'],
				'default'           => $args['default'],
				'sanitize_callback' => $args['sanitize'] ?? ( 'array' === $args['type'] ? __NAMESPACE__ . '\\sanitize_int_array' : 'sanitize_text_field' ),
				'auth_callback'     => fn () => current_user_can( 'edit_posts' ),
			]
		);
	}
}

/* ───────────────────────────────────────────────
 * Recurrence
 * ─────────────────────────────────────────────── */

/**
 * Keep a rule the plugin cannot honour out of the database.
 *
 * Storing one is worse than refusing it: the field would look set, and the
 * series would expand to dates the rule does not describe — silently, because
 * an unsupported part is simply absent from the expansion.
 *
 * The event-manager module owns the parser, so without it there is nothing to
 * validate against. The value passes through in that case rather than being
 * discarded: a site that switches the module off should not lose the rules it
 * had, and switching it back on validates them again on the next save.
 */
function sanitize_recurrence_rule( mixed $value ): string {
	$value = trim( (string) $value );

	if ( '' === $value ) {
		return '';
	}

	if ( ! function_exists( '\\ProducerKit\\EventManager\\Recurrence\\validate' ) ) {
		return sanitize_text_field( $value );
	}

	$valid = \ProducerKit\EventManager\Recurrence\validate( $value );

	// A refused rule is dropped rather than stored. That keeps a rule the
	// plugin cannot honour out of the database, which is the important half
	// — but it is not the whole answer: a REST write still returns 200 and
	// the value comes back empty, which reads as a control that did nothing.
	//
	// A sanitize_callback cannot do better on its own. It is handed the value
	// with no object id, so it cannot leave the previous rule in place, and
	// register_post_meta() has no validate hook that could refuse the request
	// outright. The editor panel is where a person is told why, and until one
	// exists this is the safe failure rather than the clear one.
	return is_wp_error( $valid ) ? '' : sanitize_text_field( $value );
}

/* ───────────────────────────────────────────────
 * Deposit sanitisers
 * ─────────────────────────────────────────────── */

/**
 * What a pre-order for this product collects up front.
 *
 * Anything unrecognised falls back to 'none'. Getting this wrong in the
 * permissive direction would charge a customer for something the producer
 * never meant to charge for, so an unknown value must not be treated as an
 * instruction to take money.
 */
function sanitize_payment_mode( mixed $value ): string {
	$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';

	return in_array( $value, [ 'none', 'deposit', 'full' ], true ) ? $value : 'none';
}

/**
 * Fixed amount per unit, or a percentage of the line.
 */
function sanitize_deposit_kind( mixed $value ): string {
	$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';

	return 'percent' === $value ? 'percent' : 'fixed';
}

/**
 * The deposit figure itself.
 *
 * Never negative: absint() would turn -50 into 50 and quietly charge a
 * deposit the producer was trying to remove, so a negative is clamped to zero
 * rather than flipped. Not capped at 100 here even though a percentage above
 * that is meaningless — the cap belongs where the kind is known, and
 * Deposits\split_line() never lets a deposit exceed the line total anyway.
 */
function sanitize_deposit_value( mixed $value ): float {
	if ( is_string( $value ) ) {
		$value = str_replace( [ ',', '$', ' ' ], '', $value );
	}

	if ( ! is_numeric( $value ) ) {
		return 0.0;
	}

	return max( 0.0, round( (float) $value, 2 ) );
}

/* ───────────────────────────────────────────────
 * Source meta
 * ─────────────────────────────────────────────── */
function register_source_meta(): void {
	$fields = [
		'_pkit_source_farm_name' => [
			'type'        => 'string',
			'description' => 'Name of the partner farm or grain origin.',
			'default'     => '',
		],
		'_pkit_source_location'  => [
			'type'        => 'string',
			'description' => 'Geographic location of source (county, state).',
			'default'     => '',
		],
		'_pkit_source_history'   => [
			'type'        => 'string',
			'description' => 'Historical / heritage notes about the grain or ingredient.',
			'default'     => '',
		],
		'_pkit_milling_notes'    => [
			'type'        => 'string',
			'description' => 'Notes on milling process, grind, etc.',
			'default'     => '',
		],
	];

	foreach ( $fields as $key => $args ) {
		register_post_meta(
			'pkit_source',
			$key,
			[
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => $args['type'],
				'description'       => $args['description'],
				'default'           => $args['default'],
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => fn () => current_user_can( 'edit_posts' ),
			]
		);
	}
}

/* ───────────────────────────────────────────────
 * Location meta
 * ─────────────────────────────────────────────── */
function register_location_meta(): void {
	$fields = [
		'_pkit_address'          => [
			'type'        => 'string',
			'description' => 'Street address.',
			'default'     => '',
		],
		'_pkit_location_type'    => [
			'type'        => 'string',
			'description' => 'Type: stand, market, on-farm, retailer, other.',
			'default'     => 'stand',
		],
		'_pkit_venmo_handle'     => [
			'type'        => 'string',
			'description' => 'Venmo handle for payment at this location (legacy; merged into _pkit_payment_methods on read).',
			'default'     => '',
			'sanitize'    => __NAMESPACE__ . '\\sanitize_payment_handle',
		],
		'_pkit_payment_methods'  => [
			'type'        => 'string',
			'description' => 'JSON array of payment methods: [{type, value, label}].',
			'default'     => '',
			'sanitize'    => '\\ProducerKit\\Core\\Payments\\sanitize_payment_methods',
		],
		'_pkit_pickup_blackouts' => [
			'type'        => 'string',
			'description' => 'JSON array of dates (YYYY-MM-DD) when pickups are unavailable — holidays, closures.',
			'default'     => '',
			'sanitize'    => __NAMESPACE__ . '\\sanitize_date_json_array',
		],
		'_pkit_hours'            => [
			'type'        => 'string',
			'description' => 'Human-readable hours string.',
			'default'     => '',
		],
		'_pkit_is_open'          => [
			'type'        => 'boolean',
			'description' => 'Quick toggle — is this location currently open?',
			'default'     => false,
		],
		'_pkit_lat'              => [
			'type'        => 'number',
			'description' => 'Latitude.',
			'default'     => 0,
		],
		'_pkit_lng'              => [
			'type'        => 'number',
			'description' => 'Longitude.',
			'default'     => 0,
		],
	];

	foreach ( $fields as $key => $args ) {
		register_post_meta(
			'pkit_location',
			$key,
			[
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => $args['type'],
				'description'       => $args['description'],
				'default'           => $args['default'],
				'sanitize_callback' => $args['sanitize'] ?? match ( $args['type'] ) {
					'boolean' => 'rest_sanitize_boolean',
					'number'  => fn ( $v ) => (float) $v,
					default   => 'sanitize_text_field',
				},
				'auth_callback'     => fn () => current_user_can( 'edit_posts' ),
			]
		);
	}
}

/* ───────────────────────────────────────────────
 * Event meta
 * ─────────────────────────────────────────────── */
function register_event_meta(): void {
	$fields = [
		'_pkit_event_location_id'    => [
			'type'        => 'integer',
			'description' => 'Related pkit_location post ID.',
			'default'     => 0,
		],
		'_pkit_featured_product_ids' => [
			'type'        => 'array',
			'description' => 'Featured product post IDs for this event.',
			'default'     => [],
			'items'       => [ 'type' => 'integer' ],
		],
		'_pkit_start_datetime'       => [
			'type'        => 'string',
			'description' => 'ISO 8601 start date/time.',
			'default'     => '',
		],
		'_pkit_end_datetime'         => [
			'type'        => 'string',
			'description' => 'ISO 8601 end date/time.',
			'default'     => '',
		],
		'_pkit_recurrence_rule'      => [
			'type'        => 'string',
			'description' => 'iCal RRULE string for recurring events. A subset of RFC 5545; anything the plugin cannot honour is refused rather than stored.',
			'default'     => '',
			'sanitize'    => __NAMESPACE__ . '\\sanitize_recurrence_rule',
		],
		'_pkit_rsvp_cap'             => [
			'type'        => 'integer',
			'description' => 'Maximum attendees (0 = unlimited).',
			'default'     => 0,
		],
		'_pkit_donation_link'        => [
			'type'        => 'string',
			'description' => 'Venmo deeplink or external donation URL.',
			'default'     => '',
			'sanitize'    => __NAMESPACE__ . '\\sanitize_url_field',
		],
	];

	foreach ( $fields as $key => $args ) {
		$rest_schema = match ( true ) {
			$args['type'] === 'array' => [
				'schema' => [
					'type'  => 'array',
					'items' => $args['items'],
				],
			],
			default                   => true,
		};

		$sanitize = $args['sanitize'] ?? match ( $args['type'] ) {
			'integer' => fn ( $v ) => (int) $v,
			'array'   => __NAMESPACE__ . '\\sanitize_int_array',
			default   => 'sanitize_text_field',
		};

		register_post_meta(
			'pkit_event',
			$key,
			[
				'show_in_rest'      => $rest_schema,
				'single'            => true,
				'type'              => $args['type'],
				'description'       => $args['description'],
				'default'           => $args['default'],
				'sanitize_callback' => $sanitize,
				'auth_callback'     => fn () => current_user_can( 'edit_posts' ),
			]
		);
	}
}

/* ───────────────────────────────────────────────
 * Shared sanitizers
 * ─────────────────────────────────────────────── */
function sanitize_int_array( mixed $value ): array {
	if ( ! is_array( $value ) ) {
		return [];
	}
	return array_values( array_map( 'intval', $value ) );
}

/**
 * Sanitize an external payment/donation URL.
 *
 * Only http(s) URLs survive; anything else (javascript:, data:, mailto:)
 * is stored as ''. Output escaping alone isn't enough here because the
 * REST API and Abilities emit this meta raw to non-block consumers.
 */
function sanitize_url_field( mixed $value ): string {
	if ( ! is_string( $value ) || trim( $value ) === '' ) {
		return '';
	}
	return esc_url_raw( trim( $value ), [ 'http', 'https' ] );
}

/**
 * Sanitize a JSON array of YYYY-MM-DD dates (stored as a JSON string).
 * Invalid entries are dropped; capped at 50 dates.
 */
function sanitize_date_json_array( mixed $value ): string {
	if ( is_string( $value ) ) {
		$value = json_decode( $value, true );
	}
	if ( ! is_array( $value ) ) {
		return '';
	}

	$dates = [];
	foreach ( array_slice( $value, 0, 50 ) as $date ) {
		if ( is_string( $date ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$dates[] = $date;
		}
	}
	$dates = array_values( array_unique( $dates ) );
	sort( $dates );

	return $dates ? (string) wp_json_encode( $dates ) : '';
}

/**
 * Sanitize a Venmo-style payment handle.
 *
 * Strips a leading @ and anything outside Venmo's handle charset
 * ([A-Za-z0-9_-], max 30 chars) so the stored value can never smuggle
 * path or query segments into URLs built from it.
 */
function sanitize_payment_handle( mixed $value ): string {
	if ( ! is_string( $value ) ) {
		return '';
	}
	$handle = preg_replace( '/[^A-Za-z0-9_\-]/', '', ltrim( trim( $value ), '@' ) ) ?? '';
	return substr( $handle, 0, 30 );
}
