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
const OPTION     = 'lfuf_wc_settlement_db_version';

/** How a request is paid for. */
const DIRECT = 'direct';
const VIA_WC = 'wc';

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
	];
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\maybe_upgrade', 30 );

/**
 * Add any missing columns.
 *
 * Runs at priority 30, after the request modules have created their tables at
 * 20. Self-healing like every other schema here: it checks the columns rather
 * than trusting the version option, so a half-applied upgrade repairs itself.
 */
function maybe_upgrade(): void {
	if ( get_option( OPTION ) === DB_VERSION ) {
		return;
	}

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

	foreach ( columns() as $column => $definition ) {
		if ( has_column( $table, $column ) ) {
			continue;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Identifiers and definitions are internal constants; neither can be parameterized.
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN {$column} {$definition}" );
	}
}

function has_column( string $table, string $column ): bool {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is an internal identifier; the column name is bound.
	return (bool) $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) );
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

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table.
	return false !== $wpdb->update(
		$table,
		[ 'settled_at' => current_time( 'mysql', true ) ],
		[ 'id' => $id ]
	);
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
			];
		}
	}

	return null;
}
