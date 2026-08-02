<?php
/**
 * Custom table: {prefix}_lfuf_preorders
 *
 * Cartless pre-orders: a visitor picks products and quantities, chooses a
 * pickup date, and pays at pickup (or via the location's payment links).
 * No money moves through the plugin.
 *
 * Security hardening mirrors the RSVP table:
 *   - Rate limiting per IP (transient-based)
 *   - Honeypot field check
 *   - Server-side line/quantity caps
 *   - Cancellation via unguessable token
 */

declare(strict_types=1);

namespace Leftfield\PreOrder\Orders;

defined( 'ABSPATH' ) || exit;

/** Max pre-orders from one IP per hour. */
const RATE_LIMIT_PER_IP = 3;

/** Max distinct product lines per order. */
const MAX_LINES = 20;

/** Max quantity per line. */
const MAX_QTY = 99;

/** How far ahead a pickup date may be (days). */
const MAX_PICKUP_DAYS = 30;

add_action(
	'plugins_loaded',
	function (): void {
		if ( get_option( 'lfuf_preorder_db_version' ) !== '1.0.0' ) {
			create_table();
		}
	},
	20
);

function table_name(): string {
	global $wpdb;
	return $wpdb->prefix . 'lfuf_preorders';
}

function create_table(): void {
	global $wpdb;

	$table   = table_name();
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
        id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        token        VARCHAR(64)     NOT NULL,
        location_id  BIGINT UNSIGNED NOT NULL DEFAULT 0,
        name         VARCHAR(200)    NOT NULL,
        email        VARCHAR(200)    NOT NULL DEFAULT '',
        phone        VARCHAR(50)     NOT NULL DEFAULT '',
        pickup_date  DATE            NOT NULL,
        status       VARCHAR(20)     NOT NULL DEFAULT 'pending',
        items        LONGTEXT        NOT NULL,
        note         VARCHAR(500)    NOT NULL DEFAULT '',
        ip_hash      VARCHAR(64)     NOT NULL DEFAULT '',
        created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY idx_token (token),
        KEY idx_status (status),
        KEY idx_pickup (pickup_date)
    ) {$charset};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	update_option( 'lfuf_preorder_db_version', '1.0.0' );
}

/**
 * Hash an IP address for storage (privacy-preserving, salted).
 * Local copy so this module stands alone when event-manager is disabled.
 */
function hash_ip( string $ip ): string {
	return hash( 'sha256', $ip . wp_salt( 'auth' ) );
}

/**
 * Get the client IP (REMOTE_ADDR is sufficient for a small farm site).
 */
function get_client_ip(): string {
	return sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
}

/**
 * Order lifecycle. Staff move orders forward; customers may cancel while
 * the order is still pending or confirmed.
 *
 * @return string[]
 */
function valid_statuses(): array {
	return [ 'pending', 'confirmed', 'ready', 'picked_up', 'cancelled' ];
}

/**
 * Validate and normalize the items payload.
 *
 * Each line must reference a published lfuf_product. A title snapshot is
 * stored so the order stays readable if the product is later deleted.
 *
 * @return array|\WP_Error
 */
function normalize_items( mixed $items ): array|\WP_Error {
	if ( is_string( $items ) ) {
		$items = json_decode( $items, true );
	}
	if ( ! is_array( $items ) || $items === [] ) {
		return new \WP_Error( 'items_required', __( 'Please choose at least one product.', 'farm-stand-manager' ) );
	}
	if ( count( $items ) > MAX_LINES ) {
		return new \WP_Error( 'too_many_items', __( 'Too many product lines in one order.', 'farm-stand-manager' ) );
	}

	$normalized = [];
	foreach ( $items as $line ) {
		if ( ! is_array( $line ) ) {
			continue;
		}
		$product_id = (int) ( $line['product_id'] ?? 0 );
		$qty        = max( 1, min( MAX_QTY, (int) ( $line['qty'] ?? 0 ) ) );

		$product = get_post( $product_id );
		if ( ! $product || $product->post_type !== 'lfuf_product' || $product->post_status !== 'publish' ) {
			return new \WP_Error( 'invalid_product', __( 'One of the selected products is unavailable.', 'farm-stand-manager' ) );
		}

		// Merge duplicate lines for the same product.
		if ( isset( $normalized[ $product_id ] ) ) {
			$normalized[ $product_id ]['qty'] = min( MAX_QTY, $normalized[ $product_id ]['qty'] + $qty );
			continue;
		}

		$normalized[ $product_id ] = [
			'product_id' => $product_id,
			'qty'        => $qty,
			'title'      => $product->post_title,
			'unit'       => (string) get_post_meta( $product_id, '_lfuf_unit', true ),
			'price'      => (string) get_post_meta( $product_id, '_lfuf_price', true ),
		];
	}

	return $normalized ? array_values( $normalized ) : new \WP_Error(
		'items_required',
		__( 'Please choose at least one product.', 'farm-stand-manager' ),
	);
}

/**
 * Validate a pickup date: today through +MAX_PICKUP_DAYS, site timezone.
 */
function validate_pickup_date( string $date ): bool {
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		return false;
	}
	$today = current_time( 'Y-m-d' );
	$max   = gmdate( 'Y-m-d', strtotime( $today . ' +' . MAX_PICKUP_DAYS . ' days' ) );
	return $date >= $today && $date <= $max;
}

/**
 * Pickup constraints for a location, derived from data other modules
 * already store: the stand-status weekly schedule (allowed weekdays),
 * season dates, and the location's pickup blackout dates.
 *
 * @return array{allowed_days: int[]|null, season_start: string, season_end: string, blackouts: string[]}
 */
function pickup_constraints( int $location_id ): array {
	$constraints = [
		'allowed_days' => null,
		'season_start' => '',
		'season_end'   => '',
		'blackouts'    => [],
	];
	if ( $location_id < 1 ) {
		return $constraints;
	}

	$schedule = json_decode( (string) get_post_meta( $location_id, '_lfuf_ss_schedule', true ), true );
	if ( is_array( $schedule ) && $schedule !== [] ) {
		$days = array_values(
			array_unique(
				array_filter(
					array_map( fn ( $entry ) => (int) ( $entry['day'] ?? -1 ), $schedule ),
					fn ( $day ) => $day >= 0 && $day <= 6,
				)
			)
		);
		if ( $days ) {
			sort( $days );
			$constraints['allowed_days'] = $days;
		}
	}

	$constraints['season_start'] = (string) get_post_meta( $location_id, '_lfuf_ss_season_start', true );
	$constraints['season_end']   = (string) get_post_meta( $location_id, '_lfuf_ss_season_end', true );

	$blackouts = json_decode( (string) get_post_meta( $location_id, '_lfuf_pickup_blackouts', true ), true );
	if ( is_array( $blackouts ) ) {
		$constraints['blackouts'] = array_values(
			array_filter(
				$blackouts,
				fn ( $date ) => is_string( $date ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ),
			)
		);
	}

	return $constraints;
}

/**
 * Weekday names for a list of 0-6 day numbers, localized.
 *
 * @param int[] $days
 * @return string[]
 */
function weekday_names( array $days ): array {
	global $wp_locale;
	return array_map( fn ( int $day ) => (string) $wp_locale->get_weekday( $day ), $days );
}

/**
 * Full pickup date validation: window, then the location's open days,
 * season, and blackout dates.
 */
function validate_pickup_date_for_location( string $date, int $location_id ): true|\WP_Error {
	if ( ! validate_pickup_date( $date ) ) {
		return new \WP_Error( 'invalid_pickup_date', __( 'Please choose a pickup date within the next month.', 'farm-stand-manager' ) );
	}

	$constraints = pickup_constraints( $location_id );

	if ( $constraints['allowed_days'] !== null ) {
		$weekday = (int) gmdate( 'w', strtotime( $date . ' 12:00:00' ) );
		if ( ! in_array( $weekday, $constraints['allowed_days'], true ) ) {
			return new \WP_Error(
				'pickup_day_closed',
				sprintf(
				/* translators: %s: comma-separated list of weekday names. */
					__( 'That day isn\'t a pickup day. Pickups are available on: %s.', 'farm-stand-manager' ),
					implode( ', ', weekday_names( $constraints['allowed_days'] ) ),
				)
			);
		}
	}

	if ( $constraints['season_start'] && $constraints['season_end']
		&& ( $date < $constraints['season_start'] || $date > $constraints['season_end'] )
	) {
		return new \WP_Error(
			'pickup_out_of_season',
			sprintf(
			/* translators: 1: season start date, 2: season end date. */
				__( 'That date is outside our season (%1$s – %2$s).', 'farm-stand-manager' ),
				$constraints['season_start'],
				$constraints['season_end'],
			)
		);
	}

	if ( in_array( $date, $constraints['blackouts'], true ) ) {
		return new \WP_Error( 'pickup_blackout', __( 'We\'re closed that day — please choose another date.', 'farm-stand-manager' ) );
	}

	return true;
}

/**
 * Create a pre-order with full validation and rate limiting.
 *
 * @param array{
 *     name:         string,
 *     items:        array|string,
 *     pickup_date:  string,
 *     location_id?: int,
 *     email?:       string,
 *     phone?:       string,
 *     note?:        string,
 *     honeypot?:    string,
 * } $data
 * @return array|\WP_Error Public-safe order array on success.
 */
function create_order( array $data ): array|\WP_Error {
	global $wpdb;

	// ── Honeypot: fake success so bots don't learn they were caught. ──
	if ( ! empty( $data['honeypot'] ?? '' ) ) {
		return [
			'id'     => 0,
			'token'  => wp_generate_password( 32, false ),
			'status' => 'pending',
		];
	}

	$name = sanitize_text_field( $data['name'] ?? '' );
	if ( $name === '' ) {
		return new \WP_Error( 'name_required', __( 'Please provide your name.', 'farm-stand-manager' ) );
	}

	$location_id = (int) ( $data['location_id'] ?? 0 );
	if ( $location_id > 0 ) {
		$location = get_post( $location_id );
		if ( ! $location || $location->post_type !== 'lfuf_location' || $location->post_status !== 'publish' ) {
			return new \WP_Error( 'invalid_location', __( 'Pickup location not found.', 'farm-stand-manager' ) );
		}
	}

	$pickup_date  = sanitize_text_field( $data['pickup_date'] ?? '' );
	$pickup_valid = validate_pickup_date_for_location( $pickup_date, $location_id );
	if ( is_wp_error( $pickup_valid ) ) {
		return $pickup_valid;
	}

	$items = normalize_items( $data['items'] ?? [] );
	if ( is_wp_error( $items ) ) {
		return $items;
	}

	// ── Rate limiting by IP. ──
	$ip_hashed = hash_ip( get_client_ip() );
	$rate_key  = 'lfuf_preorder_rate_' . md5( $ip_hashed );

	/**
	 * Filters the max pre-orders per IP per hour. Raise for sites whose
	 * visitors share an address (school groups, market Wi-Fi, CGNAT).
	 *
	 * @param int $limit Default 3.
	 */
	$rate_limit = (int) apply_filters( 'lfuf_preorder_rate_limit', RATE_LIMIT_PER_IP );

	$recent = (int) get_transient( $rate_key );
	if ( $recent >= $rate_limit ) {
		return new \WP_Error( 'rate_limited', __( 'Too many pre-orders from this connection. Please try again later.', 'farm-stand-manager' ) );
	}

	$token = wp_generate_password( 32, false );
	$row   = [
		'token'       => $token,
		'location_id' => $location_id,
		'name'        => $name,
		'email'       => sanitize_email( $data['email'] ?? '' ),
		'phone'       => sanitize_text_field( $data['phone'] ?? '' ),
		'pickup_date' => $pickup_date,
		'status'      => 'pending',
		'items'       => (string) wp_json_encode( $items ),
		'note'        => sanitize_text_field( $data['note'] ?? '' ),
		'ip_hash'     => $ip_hashed,
	];

	$inserted = $wpdb->insert( table_name(), $row, [ '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ] );
	// Capture immediately: set_transient() below writes to wp_options and
	// would overwrite $wpdb->insert_id.
	$order_id = (int) $wpdb->insert_id;
	if ( ! $inserted || ! $order_id ) {
		return new \WP_Error( 'db_error', __( 'Could not save the pre-order.', 'farm-stand-manager' ) );
	}

	set_transient( $rate_key, $recent + 1, HOUR_IN_SECONDS );

	$order = to_public( array_merge( [ 'id' => $order_id ], $row ) );

	/**
	 * Fires after a new pre-order is created.
	 *
	 * @param array $order Public-safe order data including id and token.
	 */
	do_action( 'lfuf_preorder_created', $order );

	return $order;
}

/**
 * Shape a row for public consumption (drops ip_hash, decodes items).
 */
function to_public( array|object $row ): array {
	$row = (array) $row;
	return [
		'id'            => (int) $row['id'],
		'token'         => (string) $row['token'],
		'location_id'   => (int) $row['location_id'],
		'location_name' => $row['location_id'] ? (string) get_the_title( (int) $row['location_id'] ) : '',
		'name'          => (string) $row['name'],
		'email'         => (string) $row['email'],
		'phone'         => (string) $row['phone'],
		'pickup_date'   => (string) $row['pickup_date'],
		'status'        => (string) $row['status'],
		'items'         => is_array( $row['items'] ) ? $row['items'] : ( json_decode( (string) $row['items'], true ) ?: [] ),
		'note'          => (string) $row['note'],
		'created_at'    => (string) ( $row['created_at'] ?? '' ),
	];
}

/**
 * Look up an order by its token (the customer's bearer secret).
 */
function get_order_by_token( string $token ): ?array {
	global $wpdb;
	$table = table_name();
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s LIMIT 1", $token ) );
	return $row ? to_public( $row ) : null;
}

/**
 * Cancel an order by token. Only pending/confirmed orders can be
 * cancelled by the customer.
 */
function cancel_order_by_token( string $token ): bool|\WP_Error {
	global $wpdb;

	$order = get_order_by_token( $token );
	if ( ! $order ) {
		return new \WP_Error( 'not_found', __( 'Pre-order not found.', 'farm-stand-manager' ) );
	}
	if ( ! in_array( $order['status'], [ 'pending', 'confirmed' ], true ) ) {
		return new \WP_Error( 'not_cancellable', __( 'This pre-order can no longer be cancelled online.', 'farm-stand-manager' ) );
	}

	$updated = (bool) $wpdb->update( table_name(), [ 'status' => 'cancelled' ], [ 'token' => $token ], [ '%s' ], [ '%s' ] );
	if ( $updated ) {
		$order['status'] = 'cancelled';
		do_action( 'lfuf_preorder_cancelled', $order );
	}
	return $updated;
}

/**
 * Update an order's status (staff action).
 */
function update_status( int $id, string $status ): bool|\WP_Error {
	global $wpdb;

	if ( ! in_array( $status, valid_statuses(), true ) ) {
		return new \WP_Error( 'invalid_status', __( 'Unknown pre-order status.', 'farm-stand-manager' ) );
	}

	$table = table_name();
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ) );
	if ( ! $row ) {
		return new \WP_Error( 'not_found', __( 'Pre-order not found.', 'farm-stand-manager' ) );
	}

	$old = (string) $row->status;
	if ( $old === $status ) {
		return true;
	}

	$updated = (bool) $wpdb->update( $table, [ 'status' => $status ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );
	if ( $updated ) {
		do_action( 'lfuf_preorder_status_changed', to_public( $row ), $old, $status );
	}
	return $updated;
}

/**
 * Aggregate active pre-orders into a harvest list: per pickup date (and
 * location), the total quantity of each product to have ready. This is
 * the sheet a farmer takes to the field.
 *
 * @param array{date_from?: string, date_to?: string, location_id?: int} $args
 * @return array<int, array{
 *     pickup_date: string,
 *     location_id: int,
 *     location_name: string,
 *     order_count: int,
 *     items: array<int, array{product_id: int, title: string, unit: string, total_qty: int, order_count: int}>,
 * }>
 */
function get_harvest_list( array $args = [] ): array {
	global $wpdb;

	$table = table_name();
	$today = current_time( 'Y-m-d' );

	$date_from = sanitize_text_field( $args['date_from'] ?? $today );
	$date_to   = sanitize_text_field( $args['date_to'] ?? gmdate( 'Y-m-d', strtotime( $today . ' +' . MAX_PICKUP_DAYS . ' days' ) ) );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
		return [];
	}
	$location_id = (int) ( $args['location_id'] ?? 0 );

	// Only orders that still need fulfilling.
	$where = $wpdb->prepare(
		"pickup_date BETWEEN %s AND %s AND status IN ('pending', 'confirmed', 'ready')",
		$date_from,
		$date_to,
	);
	if ( $location_id > 0 ) {
		$where .= $wpdb->prepare( ' AND location_id = %d', $location_id );
	}

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is prepared above.
	$rows = $wpdb->get_results( "SELECT pickup_date, location_id, items FROM {$table} WHERE {$where} ORDER BY pickup_date ASC" );

	// Group by date + location, summing per product (items are JSON per row).
	$groups = [];
	foreach ( $rows ?: [] as $row ) {
		$key = $row->pickup_date . '|' . $row->location_id;
		if ( ! isset( $groups[ $key ] ) ) {
			$groups[ $key ] = [
				'pickup_date'   => (string) $row->pickup_date,
				'location_id'   => (int) $row->location_id,
				'location_name' => $row->location_id ? (string) get_the_title( (int) $row->location_id ) : '',
				'order_count'   => 0,
				'items'         => [],
			];
		}
		++$groups[ $key ]['order_count'];

		foreach ( json_decode( (string) $row->items, true ) ?: [] as $item ) {
			$pid = (int) ( $item['product_id'] ?? 0 );
			if ( ! isset( $groups[ $key ]['items'][ $pid ] ) ) {
				$groups[ $key ]['items'][ $pid ] = [
					'product_id'  => $pid,
					'title'       => (string) ( $item['title'] ?? '' ),
					'unit'        => (string) ( $item['unit'] ?? '' ),
					'total_qty'   => 0,
					'order_count' => 0,
				];
			}
			$groups[ $key ]['items'][ $pid ]['total_qty'] += (int) ( $item['qty'] ?? 0 );
			++$groups[ $key ]['items'][ $pid ]['order_count'];
		}
	}

	// Stable output: items sorted by title, groups already date-ordered.
	return array_values(
		array_map(
			function ( array $group ): array {
				usort( $group['items'], fn ( $a, $b ) => strcasecmp( $a['title'], $b['title'] ) );
				$group['items'] = array_values( $group['items'] );
				return $group;
			},
			$groups
		)
	);
}

/**
 * List orders for the admin screen / ability.
 *
 * @param array{status?: string, limit?: int, offset?: int} $args
 * @return array{orders: array[], total: int}
 */
function get_orders( array $args = [] ): array {
	global $wpdb;

	$table  = table_name();
	$status = sanitize_key( $args['status'] ?? '' );
	$limit  = max( 1, min( 100, (int) ( $args['limit'] ?? 50 ) ) );
	$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );

	if ( $status !== '' && in_array( $status, valid_statuses(), true ) ) {
		$where = $wpdb->prepare( 'WHERE status = %s', $status );
	} else {
		$where = '';
	}

    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is prepared above; identifiers are internal.
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
	$rows  = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} {$where} ORDER BY pickup_date ASC, created_at ASC LIMIT %d OFFSET %d",
			$limit,
			$offset,
		)
	);
    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	return [
		'orders' => array_map( __NAMESPACE__ . '\\to_public', $rows ?: [] ),
		'total'  => $total,
	];
}
