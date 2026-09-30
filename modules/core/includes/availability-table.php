<?php
/**
 * Custom table: {prefix}_pkit_availability
 *
 * This is the shared, time-sensitive status layer. Each row represents:
 *   "Product X is [status] at Location Y as of [date]."
 *
 * Feature plugins (availability board, stand widget, pre-order builder)
 * all read/write this same table instead of maintaining their own state.
 */

declare(strict_types=1);

namespace ProducerKit\Core\Availability;

defined( 'ABSPATH' ) || exit;

/** Bumped whenever the schema changes, to trigger the self-heal below. */
const DB_VERSION = '1.0.1';

/**
 * Re-run the schema when the stored version is behind.
 *
 * The RSVP and pre-order tables already self-heal this way. Availability did
 * not: it was created on activation only, and wrote pkit_availability_db_version
 * without anything ever reading it. That mattered once the schema was found to
 * be rejected outright by strict-mode MySQL — those sites have no table at all,
 * and without this they would need a manual deactivate/reactivate to get one.
 */
add_action(
	'plugins_loaded',
	function (): void {
		if ( get_option( 'pkit_availability_db_version' ) !== DB_VERSION ) {
			create_table();
		}
	},
	20
);

/**
 * Return the full table name with WP prefix.
 */
function table_name(): string {
	global $wpdb;
	return $wpdb->prefix . 'pkit_availability';
}

/**
 * Schema for the availability table.
 *
 * Separated from create_table() so the schema can be exercised against a
 * throwaway table name in tests without touching the live one.
 *
 * Note there is no DEFAULT on `notes`. MySQL forbids defaults on BLOB/TEXT
 * columns: on a non-strict server it drops the default with a warning, but
 * under STRICT_TRANS_TABLES — the default since MySQL 5.7 — the CREATE fails
 * outright and the table is never created. `notes` needs no default anyway,
 * since upsert() is the only writer and always supplies a value.
 *
 * @param string $table Fully-qualified table name.
 */
function schema_sql( string $table ): string {
	global $wpdb;

	$charset = $wpdb->get_charset_collate();

	return "CREATE TABLE {$table} (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        product_id      BIGINT UNSIGNED NOT NULL,
        location_id     BIGINT UNSIGNED NOT NULL DEFAULT 0,
        status          VARCHAR(20)     NOT NULL DEFAULT 'available',
        quantity_note   VARCHAR(255)    NOT NULL DEFAULT '',
        effective_date  DATE            NOT NULL,
        expires_date    DATE            DEFAULT NULL,
        notes           TEXT            NOT NULL,
        created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_product_location (product_id, location_id),
        KEY idx_effective (effective_date),
        KEY idx_status (status)
    ) {$charset};";
}

function create_table(): void {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( schema_sql( table_name() ) );

	update_option( 'pkit_availability_db_version', DB_VERSION );
}

/**
 * Valid status values — used for validation in REST and admin.
 *
 * @return string[]
 */
function valid_statuses(): array {
	return [ 'abundant', 'available', 'limited', 'sold_out', 'unavailable' ];
}

/**
 * What a status is called, in the site's language.
 *
 * The only place a status becomes words. Until #101 there were eleven others,
 * each building the label from the slug with ucfirst( str_replace( '_', ' ' ) )
 * — which produces English and can never be translated. So on a translated
 * site every availability badge stayed in English: the board, the product
 * card, the badge block, the product page, the admin columns and the
 * dashboard. The Fresh Sheet alone had a translated version, and nothing else
 * used it.
 *
 * bin/validate-config.php now fails the build on the slug-building pattern,
 * because an enumerated fix of eleven sites is what produced eleven sites.
 */
function status_label( string $status ): string {
	return match ( $status ) {
		'abundant'    => __( 'Abundant', 'producerkit' ),
		'available'   => __( 'Available', 'producerkit' ),
		'limited'     => __( 'Limited', 'producerkit' ),
		'sold_out'    => __( 'Sold out', 'producerkit' ),
		'unavailable' => __( 'Unavailable', 'producerkit' ),
		default       => $status,
	};
}

/* ───────────────────────────────────────────────
 * CRUD helpers
 * ─────────────────────────────────────────────── */

/**
 * Upsert an availability record.
 *
 * If a row already exists for the same product + location + effective_date,
 * it will be updated. Otherwise a new row is inserted.
 *
 * @param array{
 *     product_id:     int,
 *     location_id?:   int,
 *     status:         string,
 *     quantity_note?: string,
 *     effective_date: string,
 *     expires_date?:  string|null,
 *     notes?:         string,
 * } $data
 * @return int|false  Row ID on success, false on failure.
 */
function upsert( array $data ): int|false {
	global $wpdb;

	$table = table_name();

	if ( ! in_array( $data['status'] ?? '', valid_statuses(), true ) ) {
		return false;
	}

	$existing = $wpdb->get_var(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized.
			"SELECT id FROM {$table}
         WHERE product_id = %d AND location_id = %d AND effective_date = %s
         LIMIT 1",
			(int) $data['product_id'],
			(int) ( $data['location_id'] ?? 0 ),
			$data['effective_date'],
		)
	);

	$row = [
		'product_id'     => (int) $data['product_id'],
		'location_id'    => (int) ( $data['location_id'] ?? 0 ),
		'status'         => $data['status'],
		'quantity_note'  => sanitize_text_field( $data['quantity_note'] ?? '' ),
		'effective_date' => $data['effective_date'],
		'expires_date'   => $data['expires_date'] ?? null,
		'notes'          => sanitize_textarea_field( $data['notes'] ?? '' ),
	];

	$formats = [ '%d', '%d', '%s', '%s', '%s', '%s', '%s' ];

	if ( $existing ) {
		$wpdb->update( $table, $row, [ 'id' => (int) $existing ], $formats, [ '%d' ] );
		return (int) $existing;
	}

	$wpdb->insert( $table, $row, $formats );
	return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
}

/**
 * Get current availability for a product, optionally filtered by location.
 *
 * @return object[]
 */
function get_current( int $product_id, int $location_id = 0 ): array {
	global $wpdb;

	$table = table_name();
	$today = current_time( 'Y-m-d' );

	$where = $wpdb->prepare(
		'product_id = %d AND effective_date <= %s AND (expires_date IS NULL OR expires_date >= %s)',
		$product_id,
		$today,
		$today,
	);

	if ( $location_id > 0 ) {
		$where .= ' AND ' . location_clause( $location_id );
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The interpolated fragments are $wpdb->prepare() output.
	$rows = (array) $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where}" );

	// Every row that applies, the winning one first — callers read [0].
	usort( $rows, __NAMESPACE__ . '\\compare_precedence' );

	return $rows;
}

/**
 * Get all current availability rows (for the board / widget).
 *
 * @return object[]
 */
function get_all_current(): array {
	global $wpdb;

	$table = table_name();
	$today = current_time( 'Y-m-d' );

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized. Disabled rather than ignored because the interpolation sits inside a multi-line string.
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT a.*, p.post_title AS product_name
         FROM {$table} a
         INNER JOIN {$wpdb->posts} p ON p.ID = a.product_id AND p.post_status = 'publish'
         WHERE a.effective_date <= %s
           AND (a.expires_date IS NULL OR a.expires_date >= %s)
         ORDER BY a.status ASC, p.post_title ASC",
			$today,
			$today,
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/**
 * Delete a single availability row.
 */
function delete_row( int $id ): bool {
	global $wpdb;
	return (bool) $wpdb->delete( table_name(), [ 'id' => $id ], [ '%d' ] );
}

/**
 * Delete availability rows referencing a product or location that is
 * being permanently deleted, so the table never holds orphaned rows.
 *
 * Runs on before_delete_post (permanent delete only, not trash —
 * trashed posts can be restored, so their rows are kept).
 */
function on_post_delete( int $post_id, \WP_Post $post ): void {
	global $wpdb;

	if ( $post->post_type === 'pkit_product' ) {
		$wpdb->delete( table_name(), [ 'product_id' => $post_id ], [ '%d' ] );
	} elseif ( $post->post_type === 'pkit_location' ) {
		// location_id = 0 means "all locations" — only exact matches are orphans.
		$wpdb->delete( table_name(), [ 'location_id' => $post_id ], [ '%d' ] );
	}
}

add_action( 'before_delete_post', __NAMESPACE__ . '\\on_post_delete', 10, 2 );

/* ───────────────────────────────────────────────
 * Expiration cleanup (WP-Cron)
 * ─────────────────────────────────────────────── */

/**
 * Does a general availability row apply at this location?
 *
 * A row with location_id 0 means "generally available", and for a producer's
 * own places that is the whole point: if honey is available, it is available
 * at your stand and at the market you run.
 *
 * It is not true of somebody else's shop. A retailer carries what you
 * delivered to them and nothing else, so a general row is no evidence at all
 * that they have it. Rendering one made a shop stocking four things list
 * eleven — which reads as a mistake to anyone who has been in, and it is the
 * one screen a customer might act on by driving there.
 */
function general_rows_apply_at( int $location_id ): bool {
	$type = (string) get_post_meta( $location_id, '_pkit_location_type', true );

	/**
	 * Filters whether "available everywhere" reaches this location.
	 *
	 * @param bool   $applies Whether general rows are shown here.
	 * @param int    $location_id Location post ID.
	 * @param string $type    The location's type.
	 */
	return (bool) apply_filters( 'pkit_general_rows_apply_at', 'retailer' !== $type, $location_id, $type );
}

/**
 * The SQL condition selecting the rows that apply at one location.
 *
 * The only place that turns general_rows_apply_at() into SQL. Until #98 there
 * were three: get_for_location() applied the rule, the availability board
 * hardcoded the version from before #53 — so a board on a retailer's page
 * listed everything the producer makes — and get_current() matched the
 * location exactly, so at the producer's own stand a product marked
 * "available everywhere I sell" disappeared. Three answers to one question.
 *
 * @param string $column The column to test, as the caller's query names it.
 *                       A literal chosen in code, never input — and checked,
 *                       since it is interpolated into a prepare() format.
 */
function location_clause( int $location_id, string $column = 'location_id' ): string {
	global $wpdb;

	if ( ! preg_match( '/^[a-z_]+(\.[a-z_]+)?$/', $column ) ) {
		$column = 'location_id';
	}

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $column is a validated identifier above; the location is bound.
	$clause = general_rows_apply_at( $location_id )
		? $wpdb->prepare( "( {$column} = %d OR {$column} = 0 )", $location_id )
		: $wpdb->prepare( "{$column} = %d", $location_id );
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	return (string) $clause;
}

/**
 * Whether row $a beats row $b as the statement about one product.
 *
 * The most recent statement wins, so a correction made today replaces last
 * week's. On the same day, a statement about this specific place beats a
 * general one. After that, the one written last.
 *
 * The availability board used to pick by best status instead — its display
 * order — so a product marked sold out this morning kept showing last week's
 * "abundant". Display order and precedence are different questions; this
 * answers only the second.
 *
 * @return int Negative when $a wins, positive when $b wins.
 */
function compare_precedence( object $a, object $b ): int {
	$rank = static fn ( object $row ): array => [
		(string) ( $row->effective_date ?? '' ),
		(int) ( $row->location_id ?? 0 ),
		(int) ( $row->id ?? $row->availability_id ?? 0 ),
	];

	return $rank( $b ) <=> $rank( $a );
}

/**
 * One row per product: the one that wins.
 *
 * Products keep the order in which they first appear, so a caller's own
 * ordering across products survives; only the choice within a product is
 * made here.
 *
 * @param array<int, object> $rows
 * @return array<int, object>
 */
function winners( array $rows ): array {
	$best  = [];
	$order = [];

	foreach ( $rows as $row ) {
		$product = (int) $row->product_id;

		if ( ! isset( $best[ $product ] ) ) {
			$order[]          = $product;
			$best[ $product ] = $row;
			continue;
		}

		if ( compare_precedence( $row, $best[ $product ] ) < 0 ) {
			$best[ $product ] = $row;
		}
	}

	return array_map( static fn ( int $product ): object => $best[ $product ], $order );
}

/**
 * Everything currently available at one location.
 *
 * The inverse of get_current(), which answers "where is this product". A shop
 * page needs the other direction: what is on the shelf here.
 *
 * Rows marked "available everywhere" are included for a producer's own places
 * and excluded for a retailer — see general_rows_apply_at().
 *
 * @param int  $location_id      Location post ID.
 * @param bool $include_sold_out Whether to keep sold-out rows, which a "we
 *                               had this last week" list may want and a
 *                               "walk here now" list does not.
 * @return array<int, object> Rows joined to their product, newest first.
 */
function get_for_location( int $location_id, bool $include_sold_out = false ): array {
	global $wpdb;

	if ( $location_id < 1 ) {
		return [];
	}

	$table = table_name();
	$today = current_time( 'Y-m-d' );

	$status_clause = $include_sold_out
		? ''
		: " AND a.status NOT IN ( 'sold_out', 'unavailable' )";

	$location_clause = location_clause( $location_id, 'a.location_id' );

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier; the status clause is a literal chosen above; the location clause is location_clause()'s prepared output, integers only. The dates are bound.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT a.*, p.post_title AS product_name, p.ID AS product_post_id
			 FROM {$table} a
			 INNER JOIN {$wpdb->posts} p ON p.ID = a.product_id
			 WHERE {$location_clause}
			   AND p.post_type = 'pkit_product'
			   AND p.post_status = 'publish'
			   AND a.effective_date <= %s
			   AND ( a.expires_date IS NULL OR a.expires_date >= %s )
			   {$status_clause}
			 ORDER BY a.effective_date DESC, p.post_title ASC",
			$today,
			$today
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	return winners( (array) $rows );
}

/**
 * Which locations currently have this product.
 *
 * The "stocked by" view: a customer looking at a jar of wildflower honey
 * wants to know which shop to walk to.
 *
 * @return array<int, object> Rows with location_id and status.
 */
function get_locations_for_product( int $product_id, bool $include_sold_out = false ): array {
	global $wpdb;

	if ( $product_id < 1 ) {
		return [];
	}

	$table = table_name();
	$today = current_time( 'Y-m-d' );

	$status_clause = $include_sold_out
		? ''
		: " AND a.status NOT IN ( 'sold_out', 'unavailable' )";

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier and the status clause is one of two literals chosen above; neither is user input. The product and dates are bound.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT a.* FROM {$table} a
			 WHERE a.product_id = %d
			   AND a.location_id > 0
			   AND a.effective_date <= %s
			   AND ( a.expires_date IS NULL OR a.expires_date >= %s )
			   {$status_clause}
			 ORDER BY a.effective_date DESC",
			$product_id,
			$today,
			$today
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$seen = [];
	$out  = [];
	foreach ( (array) $rows as $row ) {
		if ( isset( $seen[ $row->location_id ] ) ) {
			continue;
		}
		$seen[ $row->location_id ] = true;
		$out[]                     = $row;
	}

	return $out;
}

/**
 * Purge expired availability rows.
 *
 * Deletes any row where expires_date is in the past.
 * Called daily via WP-Cron (pkit_availability_cleanup).
 *
 * @return int Number of rows deleted.
 */
function purge_expired(): int {
	global $wpdb;

	$table = table_name();
	$today = current_time( 'Y-m-d' );

	$count = (int) $wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a $wpdb->prefix identifier, not user input; identifiers cannot be parameterized.
			"DELETE FROM {$table} WHERE expires_date IS NOT NULL AND expires_date < %s",
			$today,
		)
	);

	if ( $count > 0 ) {
		do_action( 'pkit_availability_expired_purged', $count );
	}

	return $count;
}

add_action( 'pkit_availability_cleanup', __NAMESPACE__ . '\\purge_expired' );

/**
 * Schedule the daily cleanup cron event.
 * Safe to call multiple times — only schedules if not already scheduled.
 */
function schedule_cleanup(): void {
	if ( ! wp_next_scheduled( 'pkit_availability_cleanup' ) ) {
		// wp_schedule_event() wants a true UTC epoch. Basing the calculation on
		// current_time( 'timestamp' ) produced a local-wall-clock epoch instead,
		// so the "03:00" cleanup actually ran gmt_offset hours away from 3am.
		// Resolving 'tomorrow 03:00' inside wp_timezone() and taking the real
		// timestamp gets 3am site-local, correctly expressed in UTC.
		$next_run = new \DateTimeImmutable( 'tomorrow 03:00', wp_timezone() );

		wp_schedule_event(
			$next_run->getTimestamp(),
			'daily',
			'pkit_availability_cleanup',
		);
	}
}

/**
 * Unschedule the cleanup cron event.
 */
function unschedule_cleanup(): void {
	$timestamp = wp_next_scheduled( 'pkit_availability_cleanup' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'pkit_availability_cleanup' );
	}
}
