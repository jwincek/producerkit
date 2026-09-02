<?php
/**
 * Custom table: {prefix}_pkit_commissions
 *
 * A commission is the other public request type. Where a pre-order reserves
 * things that already exist, a commission asks for something that does not:
 * there is no product and no price until the maker quotes one, which is why
 * this is a second table rather than a nullable column on pkit_preorders.
 *
 * The guard rails in front of it — salted IP hash, honeypot, spam check,
 * token issue — come from Core\Requests, shared with pre-orders and RSVPs.
 *
 * Two tokens, deliberately:
 *   token        the customer's own long-lived reference to their request
 *   quote_token  short-lived, issued with a quote, spends on accept/decline
 *
 * Splitting them means an expired quote link does not also destroy the
 * customer's ability to look at their own commission.
 */

declare(strict_types=1);

namespace ProducerKit\Commissions\Store;

use ProducerKit\Core\Requests;

defined( 'ABSPATH' ) || exit;

/** Schema version; bumped to trigger the self-heal below. */
const DB_VERSION = '1.0.0';

/** Max commission requests from one IP per hour. */
const RATE_LIMIT_PER_IP = 3;

/** How long an accept/decline link stays good, in days. */
const QUOTE_TTL_DAYS = 30;

/** Longest accepted free-text description. */
const MAX_DESCRIPTION = 5000;

add_action(
	'plugins_loaded',
	function (): void {
		if ( get_option( 'pkit_commissions_db_version' ) !== DB_VERSION ) {
			create_table();
		}
	},
	20
);

function table_name(): string {
	global $wpdb;
	return $wpdb->prefix . 'pkit_commissions';
}

/**
 * Schema.
 *
 * Note the absence of DEFAULT on the TEXT columns: MySQL forbids defaults on
 * BLOB/TEXT, and a permissive server silently drops the column while a strict
 * one (STRICT_TRANS_TABLES, the default since MySQL 5.7) rejects the whole
 * CREATE — which is how the availability table came to not exist at all on
 * some hosts.
 */
function schema_sql( string $table ): string {
	global $wpdb;
	$charset = $wpdb->get_charset_collate();

	return "CREATE TABLE {$table} (
        id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        token          VARCHAR(64)     NOT NULL,
        name           VARCHAR(200)    NOT NULL,
        email          VARCHAR(200)    NOT NULL DEFAULT '',
        phone          VARCHAR(50)     NOT NULL DEFAULT '',
        description    TEXT            NOT NULL,
        product_type   VARCHAR(100)    NOT NULL DEFAULT '',
        material       VARCHAR(100)    NOT NULL DEFAULT '',
        budget_range   VARCHAR(40)     NOT NULL DEFAULT '',
        deadline       DATE            DEFAULT NULL,
        status         VARCHAR(20)     NOT NULL DEFAULT 'new',
        quoted_price   DECIMAL(10,2)   DEFAULT NULL,
        estimated_date DATE            DEFAULT NULL,
        maker_note     TEXT            NOT NULL,
        quote_token    VARCHAR(64)     NOT NULL DEFAULT '',
        quote_expires  DATETIME        DEFAULT NULL,
        product_id     BIGINT UNSIGNED NOT NULL DEFAULT 0,
        ip_hash        VARCHAR(64)     NOT NULL DEFAULT '',
        created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY idx_token (token),
        KEY idx_quote_token (quote_token),
        KEY idx_status (status),
        KEY idx_created (created_at)
    ) {$charset};";
}

function create_table(): void {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( schema_sql( table_name() ) );
	update_option( 'pkit_commissions_db_version', DB_VERSION );
}

/* ───────────────────────────────────────────────
 * Status machine
 * ─────────────────────────────────────────────── */

/**
 * @return string[]
 */
function valid_statuses(): array {
	return [ 'new', 'quoted', 'accepted', 'in_progress', 'complete', 'declined', 'cancelled' ];
}

/**
 * Legal transitions.
 *
 * Enforced rather than advisory: a commission that has been declined must not
 * quietly become accepted because a stale tab was left open on the admin
 * screen.
 *
 * @return array<string, string[]>
 */
function transitions(): array {
	return [
		'new'         => [ 'quoted', 'declined', 'cancelled' ],
		'quoted'      => [ 'accepted', 'declined', 'cancelled' ],
		'accepted'    => [ 'in_progress', 'cancelled' ],
		'in_progress' => [ 'complete', 'cancelled' ],
		'complete'    => [],
		'declined'    => [],
		'cancelled'   => [],
	];
}

function can_transition( string $from, string $to ): bool {
	return in_array( $to, transitions()[ $from ] ?? [], true );
}

/**
 * Budget ranges offered on the request form.
 *
 * Values are stored, so they are stable identifiers; labels are translated
 * on the way out.
 *
 * @return array<string, string>
 */
function budget_ranges(): array {
	$ranges = [
		'under-50'      => __( 'Under $50', 'producerkit' ),
		'50-100'        => __( '$50 – $100', 'producerkit' ),
		'100-200'       => __( '$100 – $200', 'producerkit' ),
		'200-500'       => __( '$200 – $500', 'producerkit' ),
		'500-plus'      => __( '$500+', 'producerkit' ),
		'no-preference' => __( 'No preference', 'producerkit' ),
	];

	/**
	 * Filters the budget ranges offered on the commission form.
	 *
	 * @param array<string, string> $ranges Value => label.
	 */
	return (array) apply_filters( 'pkit_commission_budget_ranges', $ranges );
}

/* ───────────────────────────────────────────────
 * Create
 * ─────────────────────────────────────────────── */

/**
 * Record a public commission request.
 *
 * @param array{
 *     name:          string,
 *     email:         string,
 *     description:   string,
 *     phone?:        string,
 *     product_type?: string,
 *     material?:     string,
 *     budget_range?: string,
 *     deadline?:     string,
 *     honeypot?:     string,
 * } $data
 * @return array|\WP_Error Public-safe commission on success.
 */
function create( array $data ): array|\WP_Error {
	global $wpdb;

	// The guard scores content/author/email, and needs its own hidden fields
	// forwarded because $_POST is empty for a JSON REST body.
	$guard_fields = [
		'content' => trim( (string) ( $data['description'] ?? '' ) ),
		'author'  => (string) ( $data['name'] ?? '' ),
		'email'   => (string) ( $data['email'] ?? '' ),
	];
	foreach ( Requests\spam_guard_fields() as $field ) {
		if ( isset( $data[ $field ] ) ) {
			$guard_fields[ $field ] = (string) $data[ $field ];
		}
	}

	$guard = Requests\guard( $guard_fields, 'commission', $data['honeypot'] ?? '' );

	// False means the honeypot tripped: hand back a plausible receipt so the
	// bot does not learn which field caught it.
	if ( false === $guard ) {
		return [
			'id'     => 0,
			'token'  => Requests\issue_token(),
			'status' => 'new',
		];
	}
	if ( is_wp_error( $guard ) ) {
		return $guard;
	}

	$name = sanitize_text_field( $data['name'] ?? '' );
	if ( '' === $name ) {
		return new \WP_Error( 'name_required', __( 'Please provide your name.', 'producerkit' ) );
	}

	$email = sanitize_email( $data['email'] ?? '' );
	if ( ! is_email( $email ) ) {
		// Unlike a pre-order, there is no counter to collect this at — a
		// quote has to be able to reach them.
		return new \WP_Error( 'email_required', __( 'Please provide an email address so we can send you a quote.', 'producerkit' ) );
	}

	$description = trim( sanitize_textarea_field( $data['description'] ?? '' ) );
	if ( '' === $description ) {
		return new \WP_Error( 'description_required', __( 'Please describe what you would like made.', 'producerkit' ) );
	}
	if ( mb_strlen( $description ) > MAX_DESCRIPTION ) {
		return new \WP_Error( 'description_too_long', __( 'That description is too long. Please shorten it.', 'producerkit' ) );
	}

	$deadline = sanitize_text_field( $data['deadline'] ?? '' );
	if ( '' !== $deadline && ! valid_date( $deadline ) ) {
		return new \WP_Error( 'invalid_deadline', __( 'That deadline is not a valid date.', 'producerkit' ) );
	}

	$budget = sanitize_key( $data['budget_range'] ?? '' );
	if ( '' !== $budget && ! isset( budget_ranges()[ $budget ] ) ) {
		$budget = '';
	}

	// ── Rate limiting by IP. ──
	$ip_hashed = Requests\hash_ip( Requests\get_client_ip() );
	$rate_key  = 'pkit_commission_rate_' . md5( $ip_hashed );

	/**
	 * Filters the max commission requests per IP per hour.
	 *
	 * @param int $limit Default 3.
	 */
	$rate_limit = (int) apply_filters( 'pkit_commission_rate_limit', RATE_LIMIT_PER_IP );

	$recent = (int) get_transient( $rate_key );
	if ( $recent >= $rate_limit ) {
		return new \WP_Error( 'rate_limited', __( 'Too many requests from this connection. Please try again later.', 'producerkit' ) );
	}

	$row = [
		'token'        => Requests\issue_token(),
		'name'         => $name,
		'email'        => $email,
		'phone'        => sanitize_text_field( $data['phone'] ?? '' ),
		'description'  => $description,
		'product_type' => sanitize_title( $data['product_type'] ?? '' ),
		'material'     => sanitize_title( $data['material'] ?? '' ),
		'budget_range' => $budget,
		'deadline'     => '' !== $deadline ? $deadline : null,
		'status'       => 'new',
		'maker_note'   => '',
		'ip_hash'      => $ip_hashed,
	];

	$inserted = $wpdb->insert(
		table_name(),
		$row,
		[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
	);

	// Capture before set_transient(): that writes to wp_options and would
	// overwrite insert_id.
	$id = (int) $wpdb->insert_id;

	if ( ! $inserted || ! $id ) {
		return new \WP_Error( 'db_error', __( 'Could not save the request.', 'producerkit' ) );
	}

	set_transient( $rate_key, $recent + 1, HOUR_IN_SECONDS );

	$commission = to_public( array_merge( [ 'id' => $id ], $row ) );

	/**
	 * Fires after a commission request is submitted.
	 *
	 * @param array $commission Public-safe commission data.
	 */
	do_action( 'pkit_commission_created', $commission );

	return $commission;
}

/* ───────────────────────────────────────────────
 * Read
 * ─────────────────────────────────────────────── */

function get( int $id ): ?array {
	global $wpdb;

	$table = table_name();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

	return $row ?: null;
}

/**
 * Look a commission up by the customer's long-lived token.
 */
function find_by_token( string $token ): ?array {
	global $wpdb;

	if ( '' === $token ) {
		return null;
	}

	$table = table_name();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s", $token ), ARRAY_A );

	return $row ?: null;
}

/**
 * Look a commission up by an unexpired quote token.
 *
 * Returns null for an expired one so the caller cannot accidentally treat a
 * stale accept link as valid.
 */
function find_by_quote_token( string $token ): ?array {
	global $wpdb;

	if ( '' === $token ) {
		return null;
	}

	$table = table_name();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE quote_token = %s", $token ), ARRAY_A );

	if ( ! $row ) {
		return null;
	}

	$expires = (string) ( $row['quote_expires'] ?? '' );
	if ( '' !== $expires && strtotime( $expires ) < time() ) {
		return null;
	}

	return $row;
}

/**
 * List commissions for the admin screen.
 *
 * @param array{status?: string, limit?: int, offset?: int} $args
 * @return array{commissions: array[], total: int}
 */
function list_commissions( array $args = [] ): array {
	global $wpdb;

	$status = (string) ( $args['status'] ?? '' );
	$limit  = max( 1, min( 200, (int) ( $args['limit'] ?? 50 ) ) );
	$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );
	$table  = table_name();

	$filtered = '' !== $status && in_array( $status, valid_statuses(), true );

	/*
	 * Written as two explicit query pairs rather than one with a $where
	 * variable. Building the clause dynamically works, but it hides the
	 * placeholders from static analysis — which is exactly the readability
	 * that makes an injection easy to miss on the next edit.
	 */
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized.
	if ( $filtered ) {
		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status )
		);
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$status,
				$limit,
				$offset
			),
			ARRAY_A
		);
	} else {
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);
	}
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	return [
		'commissions' => array_map( __NAMESPACE__ . '\\to_public', $rows ?: [] ),
		'total'       => $total,
	];
}

/* ───────────────────────────────────────────────
 * Lifecycle
 * ─────────────────────────────────────────────── */

/**
 * Send a quote: price, an estimated date, and an accept/decline token.
 *
 * @return array|\WP_Error The updated commission, including quote_token.
 */
function send_quote( int $id, float $price, string $estimated_date = '', string $maker_note = '' ): array|\WP_Error {
	global $wpdb;

	$commission = get( $id );
	if ( null === $commission ) {
		return new \WP_Error( 'not_found', __( 'Commission not found.', 'producerkit' ) );
	}

	if ( ! can_transition( (string) $commission['status'], 'quoted' ) ) {
		return new \WP_Error(
			'invalid_transition',
			/* translators: %s: current commission status. */
			sprintf( __( 'A commission that is %s cannot be quoted.', 'producerkit' ), (string) $commission['status'] )
		);
	}

	if ( $price <= 0 ) {
		return new \WP_Error( 'invalid_price', __( 'A quote needs a price greater than zero.', 'producerkit' ) );
	}

	if ( '' !== $estimated_date && ! valid_date( $estimated_date ) ) {
		return new \WP_Error( 'invalid_date', __( 'That estimated date is not a valid date.', 'producerkit' ) );
	}

	$quote_token = Requests\issue_token();
	$expires     = gmdate( 'Y-m-d H:i:s', time() + ( QUOTE_TTL_DAYS * DAY_IN_SECONDS ) );

	$updated = $wpdb->update(
		table_name(),
		[
			'status'         => 'quoted',
			'quoted_price'   => number_format( $price, 2, '.', '' ),
			'estimated_date' => '' !== $estimated_date ? $estimated_date : null,
			'maker_note'     => sanitize_textarea_field( $maker_note ),
			'quote_token'    => $quote_token,
			'quote_expires'  => $expires,
		],
		[ 'id' => $id ],
		[ '%s', '%s', '%s', '%s', '%s', '%s' ],
		[ '%d' ]
	);

	if ( false === $updated ) {
		return new \WP_Error( 'db_error', __( 'Could not save the quote.', 'producerkit' ) );
	}

	$fresh = to_public( (array) get( $id ), true );

	/**
	 * Fires when a quote is sent to the customer.
	 *
	 * @param array $commission Commission data including the quote token.
	 */
	do_action( 'pkit_commission_quoted', $fresh );

	return $fresh;
}

/**
 * Move a commission to a new status, enforcing the transition table.
 *
 * @return array|\WP_Error The updated commission.
 */
function set_status( int $id, string $status ): array|\WP_Error {
	global $wpdb;

	if ( ! in_array( $status, valid_statuses(), true ) ) {
		return new \WP_Error( 'invalid_status', __( 'Unknown commission status.', 'producerkit' ) );
	}

	$commission = get( $id );
	if ( null === $commission ) {
		return new \WP_Error( 'not_found', __( 'Commission not found.', 'producerkit' ) );
	}

	$from = (string) $commission['status'];
	if ( $from === $status ) {
		return to_public( $commission );
	}

	if ( ! can_transition( $from, $status ) ) {
		return new \WP_Error(
			'invalid_transition',
			/* translators: 1: current status, 2: requested status. */
			sprintf( __( 'A commission cannot go from %1$s to %2$s.', 'producerkit' ), $from, $status )
		);
	}

	$fields  = [ 'status' => $status ];
	$formats = [ '%s' ];

	// An accept or decline spends the quote token; leaving it live would let
	// the link be replayed.
	if ( in_array( $status, [ 'accepted', 'declined', 'cancelled' ], true ) ) {
		$fields['quote_token']   = '';
		$fields['quote_expires'] = null;
		$formats[]               = '%s';
		$formats[]               = '%s';
	}

	$updated = $wpdb->update( table_name(), $fields, [ 'id' => $id ], $formats, [ '%d' ] );

	if ( false === $updated ) {
		return new \WP_Error( 'db_error', __( 'Could not update the commission.', 'producerkit' ) );
	}

	$fresh = to_public( (array) get( $id ) );

	/**
	 * Fires whenever a commission changes status.
	 *
	 * @param array  $commission Public-safe commission data.
	 * @param string $from       Previous status.
	 * @param string $to         New status.
	 */
	do_action( 'pkit_commission_status_changed', $fresh, $from, $status );

	/**
	 * Fires on the specific transition, for notification listeners.
	 *
	 * @param array $commission Public-safe commission data.
	 */
	do_action( 'pkit_commission_' . $status, $fresh );

	return $fresh;
}

/**
 * Attach the catalogue product created for an accepted commission.
 */
function attach_product( int $id, int $product_id ): bool {
	global $wpdb;

	return false !== $wpdb->update(
		table_name(),
		[ 'product_id' => max( 0, $product_id ) ],
		[ 'id' => $id ],
		[ '%d' ],
		[ '%d' ]
	);
}

/* ───────────────────────────────────────────────
 * Helpers
 * ─────────────────────────────────────────────── */

function valid_date( string $date ): bool {
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		return false;
	}

	[ $y, $m, $d ] = array_map( 'intval', explode( '-', $date ) );

	return checkdate( $m, $d, $y );
}

/**
 * Shape a row for output.
 *
 * Drops ip_hash always, and the quote token unless the caller is the one
 * sending it — it is a capability, not a display field.
 *
 * @param array $row
 */
function to_public( array $row, bool $with_quote_token = false ): array {
	unset( $row['ip_hash'] );

	if ( ! $with_quote_token ) {
		unset( $row['quote_token'] );
	}

	$row['id']         = (int) ( $row['id'] ?? 0 );
	$row['product_id'] = (int) ( $row['product_id'] ?? 0 );

	if ( array_key_exists( 'quoted_price', $row ) && null !== $row['quoted_price'] ) {
		$row['quoted_price'] = (float) $row['quoted_price'];
	}

	return $row;
}
