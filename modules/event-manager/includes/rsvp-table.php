<?php
/**
 * Custom table: {prefix}_pkit_rsvps
 *
 * Lightweight RSVP/headcount tracking with security hardening:
 *   - Rate limiting per IP (transient-based)
 *   - Duplicate detection by name + event
 *   - Honeypot field check
 *   - Server-side party size cap
 *   - Atomic cap enforcement via SELECT FOR UPDATE
 */

declare(strict_types=1);

namespace ProducerKit\EventManager\RSVP;

use ProducerKit\Core\Requests;

defined( 'ABSPATH' ) || exit;

/** Max RSVPs from one IP per event per hour. */
const RATE_LIMIT_PER_IP = 5;

/** Max party size allowed server-side. */
const MAX_PARTY_SIZE = 10;

add_action(
	'plugins_loaded',
	function (): void {
		if ( get_option( 'pkit_rsvp_db_version' ) !== '1.1.0' ) {
			create_table();
		}
	},
	20
);

function table_name(): string {
	global $wpdb;
	return $wpdb->prefix . 'pkit_rsvps';
}

function create_table(): void {
	global $wpdb;

	$table   = table_name();
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        event_id    BIGINT UNSIGNED NOT NULL,
        name        VARCHAR(200)    NOT NULL,
        email       VARCHAR(200)    NOT NULL DEFAULT '',
        party_size  SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        note        VARCHAR(500)    NOT NULL DEFAULT '',
        ip_hash     VARCHAR(64)     NOT NULL DEFAULT '',
        token       VARCHAR(64)     NOT NULL,
        created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_event (event_id),
        KEY idx_ip_event (ip_hash, event_id),
        UNIQUE KEY idx_token (token)
    ) {$charset};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	update_option( 'pkit_rsvp_db_version', '1.1.0' );
}

/**
 * Hash an IP address for storage (privacy-preserving).
 */
function hash_ip( string $ip ): string {
	return Requests\hash_ip( $ip );
}

/**
 * Get the client IP.
 */
function get_client_ip(): string {
	return Requests\get_client_ip();
}

/**
 * Remove RSVPs for an event being permanently deleted.
 *
 * These rows hold attendee names, email addresses and notes. Left behind they
 * point at a post id that no longer exists, are invisible in the admin, and
 * outlive the event indefinitely — the availability table has cleaned up after
 * itself this way since it was written; RSVPs never did.
 *
 * before_delete_post, not trash: a trashed event can be restored, and its
 * guest list should come back with it.
 */
function on_post_delete( int $post_id, \WP_Post $post ): void {
	global $wpdb;

	if ( 'pkit_event' !== $post->post_type ) {
		return;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; no cache to invalidate.
	$wpdb->delete( table_name(), [ 'event_id' => $post_id ], [ '%d' ] );
}

add_action( 'before_delete_post', __NAMESPACE__ . '\\on_post_delete', 10, 2 );

/**
 * Add an RSVP to an event with full validation and rate limiting.
 *
 * @param array{
 *     event_id:    int,
 *     name:        string,
 *     email?:      string,
 *     party_size?: int,
 *     note?:       string,
 *     honeypot?:   string,
 * } $data
 * @return array|\WP_Error
 */
function add_rsvp( array $data ): array|\WP_Error {
	global $wpdb;

	// ── Honeypot check ──
	if ( ! empty( $data['honeypot'] ?? '' ) ) {
		// Bots fill this hidden field. Silently reject with a fake success
		// so the bot doesn't know it was caught.
		return [
			'id'         => 0,
			'name'       => sanitize_text_field( $data['name'] ?? '' ),
			'party_size' => 1,
			'token'      => wp_generate_password( 32, false ),
		];
	}

	$event_id = (int) ( $data['event_id'] ?? 0 );
	$event    = get_post( $event_id );

	if ( ! $event || $event->post_type !== 'pkit_event' || $event->post_status !== 'publish' ) {
		return new \WP_Error( 'invalid_event', __( 'Event not found.', 'producerkit' ) );
	}

	// Check if cancelled.
	if ( (bool) get_post_meta( $event_id, '_pkit_em_cancelled', true ) ) {
		return new \WP_Error( 'event_cancelled', __( 'This event has been cancelled.', 'producerkit' ) );
	}

	// Check if RSVPs are enabled.
	if ( ! (bool) get_post_meta( $event_id, '_pkit_em_rsvp_enabled', true ) ) {
		return new \WP_Error( 'rsvp_disabled', __( 'RSVPs are not enabled for this event.', 'producerkit' ) );
	}

	// Check if manually closed.
	if ( (bool) get_post_meta( $event_id, '_pkit_em_rsvp_closed', true ) ) {
		return new \WP_Error( 'rsvp_closed', __( 'RSVPs are closed for this event.', 'producerkit' ) );
	}

	$name = sanitize_text_field( $data['name'] ?? '' );
	if ( empty( $name ) ) {
		return new \WP_Error( 'name_required', __( 'Please provide your name.', 'producerkit' ) );
	}

	// ── Server-side party size cap ──
	$party_size = max( 1, min( MAX_PARTY_SIZE, (int) ( $data['party_size'] ?? 1 ) ) );

	// ── Rate limiting by IP ──
	$client_ip = get_client_ip();
	$ip_hashed = hash_ip( $client_ip );
	$rate_key  = 'pkit_rsvp_rate_' . md5( $ip_hashed . '_' . $event_id );

	$recent_count = (int) get_transient( $rate_key );
	if ( $recent_count >= RATE_LIMIT_PER_IP ) {
		return new \WP_Error(
			'rate_limited',
			__( 'Too many RSVPs from this connection. Please try again later.', 'producerkit' ),
		);
	}

	// ── Duplicate detection ──
	$table           = table_name();
	$normalized_name = mb_strtolower( trim( $name ) );

	$existing = $wpdb->get_var(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized.
			"SELECT id FROM {$table}
         WHERE event_id = %d AND LOWER(TRIM(name)) = %s
         LIMIT 1",
			$event_id,
			$normalized_name,
		)
	);

	if ( $existing ) {
		return new \WP_Error(
			'duplicate_rsvp',
			__( 'It looks like you\'ve already RSVP\'d to this event!', 'producerkit' ),
		);
	}

	// ── Atomic cap enforcement ──
	// Use a transaction to prevent race conditions.
	$cap = (int) get_post_meta( $event_id, '_pkit_rsvp_cap', true );
	if ( $cap > 0 ) {
		$wpdb->query( 'START TRANSACTION' );

		// Lock the rows for this event to prevent concurrent inserts.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized. Disabled rather than ignored because the interpolation sits inside a multi-line string.
		$current_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(party_size), 0)
             FROM {$table}
             WHERE event_id = %d
             FOR UPDATE",
				$event_id,
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $current_count + $party_size > $cap ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error(
				'rsvp_full',
				__( 'Sorry, this event is at capacity.', 'producerkit' ),
			);
		}
	}

	// ── Insert ──
	$token = wp_generate_password( 32, false );

	$row = [
		'event_id'   => $event_id,
		'name'       => $name,
		'email'      => sanitize_email( $data['email'] ?? '' ),
		'party_size' => $party_size,
		'note'       => sanitize_text_field( $data['note'] ?? '' ),
		'ip_hash'    => $ip_hashed,
		'token'      => $token,
	];

	$inserted = $wpdb->insert( $table, $row, [ '%d', '%s', '%s', '%d', '%s', '%s', '%s' ] );

	if ( $cap > 0 ) {
		if ( $inserted ) {
			$wpdb->query( 'COMMIT' );
		} else {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'db_error', __( 'Could not save RSVP.', 'producerkit' ) );
		}
	}

	if ( ! $wpdb->insert_id ) {
		return new \WP_Error( 'db_error', __( 'Could not save RSVP.', 'producerkit' ) );
	}

	// Increment rate limit counter.
	set_transient( $rate_key, $recent_count + 1, HOUR_IN_SECONDS );

	$row['id'] = (int) $wpdb->insert_id;

	/**
	 * Fires after a new RSVP is added.
	 *
	 * @param array $row      The RSVP data including id and token.
	 * @param int   $event_id
	 */
	do_action( 'pkit_rsvp_added', $row, $event_id );

	return $row;
}

/**
 * Capability required to read or manage a guest list.
 *
 * Not edit_posts. These rows hold names and email addresses, and Contributor
 * is too low a bar for that. Defined here rather than on the admin screen so
 * the REST route and the screen cannot drift apart — which they immediately
 * did when this lived in an is_admin()-gated file and the route kept its own
 * looser check.
 */
function manage_cap(): string {
	/**
	 * Filters the capability required to view RSVPs.
	 *
	 * @param string $cap Default 'edit_others_posts'.
	 */
	return (string) apply_filters( 'pkit_rsvp_manage_cap', 'edit_others_posts' );
}

/**
 * Defuse a cell a spreadsheet would treat as a formula.
 *
 * A guest controls their own name and note. A value starting = + - @ or a
 * control character executes on open in Excel and Sheets, where HYPERLINK and
 * WEBSERVICE can send the rest of the sheet elsewhere — so exporting a guest
 * list is a real injection sink. Numbers are left alone so numeric columns
 * still import as numbers.
 */
function esc_csv_field( string $value ): string {
	if ( '' === $value || is_numeric( $value ) ) {
		return $value;
	}

	return preg_match( '/^[=+\-@\t\r]/', $value ) ? "'" . $value : $value;
}

/**
 * Find one RSVP by its token.
 *
 * The token is the guest's only credential — they have no account — so this is
 * how the confirmation page resolves a booking before showing or cancelling
 * it. Returns null for an unknown token rather than distinguishing "no such
 * booking" from "already cancelled", which tells a guesser nothing.
 *
 * @return array|null Row as an associative array, or null.
 */
function find_by_token( string $token ): ?array {
	global $wpdb;

	if ( '' === $token ) {
		return null;
	}

	$table = table_name();
	$row   = $wpdb->get_row(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized.
			"SELECT * FROM {$table} WHERE token = %s LIMIT 1",
			$token,
		),
		ARRAY_A
	);

	return $row ?: null;
}

/**
 * Find one RSVP by its row id.
 *
 * The token is the guest's credential and is not handed to staff tools — the
 * admin screen and the abilities address a booking by id, then cancel through
 * the token they read here, so cancellation stays a single code path however
 * it was triggered.
 *
 * @return array|null Row as an associative array, or null.
 */
function find_by_id( int $id ): ?array {
	global $wpdb;

	if ( $id < 1 ) {
		return null;
	}

	$table = table_name();
	$row   = $wpdb->get_row(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized.
			"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
			$id,
		),
		ARRAY_A
	);

	return $row ?: null;
}

/**
 * Cancel an RSVP by its token.
 */
function cancel_rsvp( string $token ): bool {
	global $wpdb;

	$table = table_name();
	$rsvp  = $wpdb->get_row(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized.
			"SELECT * FROM {$table} WHERE token = %s LIMIT 1",
			$token,
		)
	);

	if ( ! $rsvp ) {
		return false;
	}

	$deleted = (bool) $wpdb->delete( $table, [ 'token' => $token ], [ '%s' ] );

	if ( $deleted ) {
		do_action( 'pkit_rsvp_cancelled', (array) $rsvp, (int) $rsvp->event_id );
	}

	return $deleted;
}

/**
 * Get the total headcount (sum of party_size) for an event.
 */
function get_headcount( int $event_id ): int {
	global $wpdb;
	$table = table_name();
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized.
			"SELECT COALESCE(SUM(party_size), 0) FROM {$table} WHERE event_id = %d",
			$event_id,
		)
	);
}

/**
 * Get the RSVP count (number of rows) for an event.
 */
function get_rsvp_count( int $event_id ): int {
	global $wpdb;
	$table = table_name();
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized.
			"SELECT COUNT(*) FROM {$table} WHERE event_id = %d",
			$event_id,
		)
	);
}

/**
 * Get all RSVPs for an event (admin view).
 *
 * @return object[]
 */
function get_event_rsvps( int $event_id ): array {
	global $wpdb;
	$table = table_name();
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized. Disabled rather than ignored because the interpolation sits inside a multi-line string.
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, name, email, party_size, note, created_at
         FROM {$table}
         WHERE event_id = %d
         ORDER BY created_at ASC",
			$event_id,
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/**
 * Get RSVP summary for an event (public-safe).
 */
function get_event_rsvp_summary( int $event_id ): array {
	$cap        = (int) get_post_meta( $event_id, '_pkit_rsvp_cap', true );
	$headcount  = get_headcount( $event_id );
	$rsvp_count = get_rsvp_count( $event_id );
	$enabled    = (bool) get_post_meta( $event_id, '_pkit_em_rsvp_enabled', true );
	$closed     = (bool) get_post_meta( $event_id, '_pkit_em_rsvp_closed', true );

	return [
		'enabled'    => $enabled,
		'closed'     => $closed,
		'headcount'  => $headcount,
		'rsvp_count' => $rsvp_count,
		'cap'        => $cap,
		'spots_left' => $cap > 0 ? max( 0, $cap - $headcount ) : null,
		'is_full'    => $cap > 0 && $headcount >= $cap,
	];
}
