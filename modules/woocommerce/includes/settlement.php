<?php
/**
 * Settlement: how a request gets paid for.
 *
 * Both request types — pre-orders and commissions — can settle two ways:
 *
 *   direct  the default, and what the plugin did before WooCommerce was
 *           involved at all. Cash at the stand, a Venmo link, an invoice the
 *           maker sends. No money moves through the plugin.
 *   wc      the customer pays at WooCommerce checkout, and the request is
 *           marked settled when that order is paid.
 *
 * The columns live here rather than in the request modules because they only
 * mean anything when WooCommerce is installed. A site that never installs it
 * never grows them.
 *
 * Added with a guarded ALTER rather than dbDelta: dbDelta needs the whole
 * CREATE TABLE to diff against, which would mean this module holding a copy
 * of two schemas owned by other modules — and they would drift.
 */

declare(strict_types=1);

namespace ProducerKit\WooCommerce\Settlement;

defined( 'ABSPATH' ) || exit;

const DB_VERSION = '1.0.0';
const OPTION     = 'pkit_wc_settlement_db_version';

/** How a request is paid for. */
const DIRECT = 'direct';
const VIA_WC = 'wc';

/** Which half of a two-part payment an order represents. */
const LEG_DEPOSIT = 'deposit';
const LEG_BALANCE = 'balance';

/**
 * The request tables that gain settlement columns.
 *
 * @return array<string, string> Key => fully-qualified table name.
 */
function tables(): array {
	$tables = [];

	if ( function_exists( '\ProducerKit\PreOrder\Orders\table_name' ) ) {
		$tables['preorder'] = \ProducerKit\PreOrder\Orders\table_name();
	}

	if ( function_exists( '\ProducerKit\Commissions\Store\table_name' ) ) {
		$tables['commission'] = \ProducerKit\Commissions\Store\table_name();
	}

	return $tables;
}

/**
 * Columns added to each request table.
 *
 * @return array<string, string> Column => definition.
 */
function columns(): array {
	return [
		'settlement'    => "VARCHAR(20) NOT NULL DEFAULT '" . DIRECT . "'",
		'wc_order_id'   => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
		'wc_product_id' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
		'settled_at'    => 'DATETIME DEFAULT NULL',

		// The balance leg. A request may be paid in two goes — a deposit now
		// and the rest at pickup — and the producer decides per pre-order
		// whether that second leg is taken in person or through a second
		// order, so both a link and a hand-marked settlement have to be
		// recordable.
		'deposit_due'        => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
		'balance_due'        => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
		'balance_order_id'   => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
		'balance_settled_at' => 'DATETIME DEFAULT NULL',
		'balance_method'     => "VARCHAR(20) NOT NULL DEFAULT ''",
	];
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\maybe_upgrade', 30 );

/**
 * Add any missing columns.
 *
 * Runs at priority 30, after the request modules have created their tables at
 * 20.
 *
 * Genuinely self-healing, which the version-option early return it used to
 * open with was not: a site that enabled the commissions module *after* this
 * one had already stamped the option got a commissions table with no
 * settlement columns and no way back, so every attach_order() failed silently
 * for good. The columns are the source of truth, not the option.
 *
 * The cost is one cached SHOW COLUMNS per table per request, which is why
 * has_column() memoises.
 */
function maybe_upgrade(): void {
	foreach ( tables() as $table ) {
		add_columns( $table );
	}

	update_option( OPTION, DB_VERSION );
}

/**
 * Add the settlement columns to one table, skipping any that exist.
 *
 * MySQL 8 has no ADD COLUMN IF NOT EXISTS — only MariaDB does — so the
 * existence check has to be explicit.
 */
function add_columns( string $table ): void {
	global $wpdb;

	$added = false;

	foreach ( columns() as $column => $definition ) {
		if ( has_column( $table, $column ) ) {
			continue;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Identifiers and definitions are internal constants; neither can be parameterized.
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN {$column} {$definition}" );
		$added = true;
	}

	// The memoised answers describe the old shape.
	if ( $added ) {
		flush_column_cache();
	}
}

function has_column( string $table, string $column ): bool {
	global $wpdb;

	// Memoised per request. find_by_order() consults this for every table on
	// every order status transition — including on stores that never use
	// settlement — and an uncached SHOW COLUMNS there put four extra queries
	// on each transition for nothing.
	$cache = &column_cache();
	$key   = $table . '.' . $column;

	if ( ! array_key_exists( $key, $cache ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is an internal identifier; the column name is bound.
		$cache[ $key ] = (bool) $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) );
	}

	return $cache[ $key ];
}

/**
 * Backing store for has_column()'s memoisation.
 *
 * A named holder rather than a static inside has_column(), so the cache can
 * be cleared when the table shape actually changes.
 *
 * @return array<string, bool>
 */
function &column_cache(): array {
	static $cache = [];
	return $cache;
}

/**
 * Forget the memoised column checks.
 *
 * Needed after add_columns() alters a table mid-request, and by the tests,
 * which create and drop tables between cases.
 */
function flush_column_cache(): void {
	$cache = &column_cache();
	$cache = [];
}

/* ───────────────────────────────────────────────
 * Reading and writing settlement state
 * ─────────────────────────────────────────────── */

/**
 * Resolve a request type to its table, or null if that module is off.
 */
function table_for( string $type ): ?string {
	return tables()[ $type ] ?? null;
}

/**
 * Mark a request as settling through WooCommerce, against an order.
 *
 * @param string $type       'preorder' or 'commission'.
 * @param int    $id         Request id.
 * @param int    $order_id   WooCommerce order id.
 * @param int    $product_id Generated product, when there is one.
 */
function attach_order( string $type, int $id, int $order_id, int $product_id = 0 ): bool {
	global $wpdb;

	$table = table_for( $type );
	if ( null === $table || $id < 1 ) {
		return false;
	}

	$data = [
		'settlement'  => VIA_WC,
		'wc_order_id' => max( 0, $order_id ),
	];
	if ( $product_id > 0 ) {
		$data['wc_product_id'] = $product_id;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table.
	return false !== $wpdb->update( $table, $data, [ 'id' => $id ] );
}

/**
 * Record that the money has actually arrived.
 */
function mark_settled( string $type, int $id ): bool {
	global $wpdb;

	$table = table_for( $type );
	if ( null === $table || $id < 1 ) {
		return false;
	}

	// Only the first settlement counts. on_paid() is hooked to both
	// `processing` and `completed`, so a normal payment followed by the maker
	// marking the order complete fires it twice — and without this the second
	// pass overwrote settled_at with the fulfilment time and re-fired
	// pkit_request_settled, so anything listening ran twice per payment.
	//
	// The NULL check lives in the WHERE clause rather than in a read-then-write
	// so two concurrent status transitions cannot both see it unset.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is an internal identifier; values are bound. Disabled rather than ignored because the interpolation sits on a different line from the call.
	$updated = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$table} SET settled_at = %s WHERE id = %d AND settled_at IS NULL",
			current_time( 'mysql', true ),
			$id
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	// Zero rows means it was already settled, which is success for the caller
	// and a signal to on_paid() that it has nothing further to do.
	return (int) $updated > 0;
}

/**
 * Record what this request takes now and what it leaves for pickup.
 *
 * Stored rather than recomputed because a product's deposit can be edited
 * after an order is placed, and the customer is owed the split they agreed
 * to, not the one the settings would produce today.
 */
function record_split( string $type, int $id, float $due_now, float $balance ): bool {
	global $wpdb;

	$table = table_for( $type );
	if ( null === $table || $id < 1 ) {
		return false;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table.
	return false !== $wpdb->update(
		$table,
		[
			'deposit_due' => round( max( 0.0, $due_now ), 2 ),
			'balance_due' => round( max( 0.0, $balance ), 2 ),
		],
		[ 'id' => $id ]
	);
}

/**
 * Attach the second, balance-paying order to a request.
 */
function attach_balance_order( string $type, int $id, int $order_id ): bool {
	global $wpdb;

	$table = table_for( $type );
	if ( null === $table || $id < 1 || $order_id < 1 ) {
		return false;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table.
	return false !== $wpdb->update( $table, [ 'balance_order_id' => $order_id ], [ 'id' => $id ] );
}

/**
 * Record that the balance has been paid.
 *
 * $method distinguishes the two routes the producer may take — 'wc' when a
 * second order was raised and paid, 'direct' when they took cash at the table
 * and said so. Both are settlements; only one of them WooCommerce knows about.
 *
 * Like mark_settled(), the NULL check lives in the WHERE clause so two
 * concurrent transitions cannot both claim the settlement, and a repeat call
 * reports false rather than overwriting the original time.
 */
function mark_balance_settled( string $type, int $id, string $method = VIA_WC ): bool {
	global $wpdb;

	$table = table_for( $type );
	if ( null === $table || $id < 1 ) {
		return false;
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is an internal identifier; values are bound. Disabled rather than ignored because the interpolation sits on a different line from the call.
	$updated = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$table} SET balance_settled_at = %s, balance_method = %s
			 WHERE id = %d AND balance_settled_at IS NULL",
			current_time( 'mysql', true ),
			VIA_WC === $method ? VIA_WC : DIRECT,
			$id
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	return (int) $updated > 0;
}

/**
 * The payment split recorded against one request.
 *
 * Returns null when the request is gone or its table never gained the balance
 * columns, so callers can tell "no balance owed" from "cannot answer".
 *
 * @return array{deposit_due: float, balance_due: float, balance_order_id: int, balance_settled_at: ?string, balance_method: string}|null
 */
function get_split( string $type, int $id ): ?array {
	global $wpdb;

	$table = table_for( $type );
	if ( null === $table || $id < 1 || ! has_column( $table, 'balance_due' ) ) {
		return null;
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is an internal identifier; the id is bound. Disabled rather than ignored because the interpolation sits on a different line from the call.
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT deposit_due, balance_due, balance_order_id, balance_settled_at, balance_method
			 FROM {$table} WHERE id = %d",
			$id
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	if ( ! $row ) {
		return null;
	}

	return [
		'deposit_due'        => (float) $row['deposit_due'],
		'balance_due'        => (float) $row['balance_due'],
		'balance_order_id'   => (int) $row['balance_order_id'],
		'balance_settled_at' => $row['balance_settled_at'],
		'balance_method'     => (string) $row['balance_method'],
	];
}

/**
 * Find the request an order was raised for.
 *
 * @return array{type: string, id: int}|null
 */
function find_by_order( int $order_id ): ?array {
	global $wpdb;

	if ( $order_id < 1 ) {
		return null;
	}

	foreach ( tables() as $type => $table ) {
		// A table whose upgrade did not land is skipped rather than queried.
		// The availability table taught this one: a schema that silently
		// failed to apply turns every later read into a database error.
		if ( ! has_column( $table, 'wc_order_id' ) ) {
			continue;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is an internal identifier; the order id is bound. Disabled rather than ignored because the interpolation sits on a different line from the call.
		$id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE wc_order_id = %d LIMIT 1", $order_id )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $id > 0 ) {
			return [
				'type' => $type,
				'id'   => $id,
				'leg'  => LEG_DEPOSIT,
			];
		}

		// The same order id cannot be both legs, so this is only reached when
		// the first lookup missed. A pre-order whose balance was taken through
		// a second order settles here.
		if ( ! has_column( $table, 'balance_order_id' ) ) {
			continue;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is an internal identifier; the order id is bound. Disabled rather than ignored because the interpolation sits on a different line from the call.
		$id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE balance_order_id = %d LIMIT 1", $order_id )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $id > 0 ) {
			return [
				'type' => $type,
				'id'   => $id,
				'leg'  => LEG_BALANCE,
			];
		}
	}

	return null;
}
