<?php
/**
 * Keeping a request in step with the order that pays for it.
 *
 * One direction only: WooCommerce is the authority on whether money arrived,
 * and the request follows. Nothing here writes back to the order.
 */

declare(strict_types=1);

namespace ProducerKit\WooCommerce\OrderSync;

use ProducerKit\Commissions\Store as Commissions;
use ProducerKit\WooCommerce\Settlement;

defined( 'ABSPATH' ) || exit;

// Both fire on payment; processing is the usual one, completed covers an
// order marked paid by hand in the admin.
add_action( 'woocommerce_order_status_processing', __NAMESPACE__ . '\\on_paid' );
add_action( 'woocommerce_order_status_completed', __NAMESPACE__ . '\\on_paid' );
add_action( 'woocommerce_order_status_cancelled', __NAMESPACE__ . '\\on_cancelled' );
add_action( 'woocommerce_order_status_refunded', __NAMESPACE__ . '\\on_cancelled' );

/**
 * Money arrived: mark the request settled and move it along.
 */
function on_paid( int $order_id ): void {
	$request = Settlement\find_by_order( $order_id );
	if ( null === $request ) {
		return;
	}

	// False means it was already settled — the second of the processing /
	// completed pair, or a status flapped by hand. Everything below is
	// once-only, so stop here rather than re-running it.
	if ( ! Settlement\mark_settled( $request['type'], $request['id'] ) ) {
		return;
	}

	if ( 'commission' === $request['type'] ) {
		// A paid commission is work to start. set_status() enforces the
		// transition table, so this is a no-op if the maker already moved it
		// on by hand, and it cannot resurrect a cancelled one.
		Commissions\set_status( $request['id'], 'in_progress' );
	}

	/**
	 * Fires when a request has been paid for through WooCommerce.
	 *
	 * @param string $type     'preorder' or 'commission'.
	 * @param int    $id       Request id.
	 * @param int    $order_id WooCommerce order id.
	 */
	do_action( 'pkit_request_settled', $request['type'], $request['id'], $order_id );
}

/**
 * The order went away.
 *
 * Deliberately does not cancel the request. A refund is often a correction
 * rather than an abandonment — the maker re-quoting, a payment method
 * changing — and silently cancelling a commission someone has already started
 * cutting wood for would be worse than leaving it for a human to decide.
 */
function on_cancelled( int $order_id ): void {
	$request = Settlement\find_by_order( $order_id );
	if ( null === $request ) {
		return;
	}

	/**
	 * Fires when the order paying for a request is cancelled or refunded.
	 *
	 * @param string $type     'preorder' or 'commission'.
	 * @param int    $id       Request id.
	 * @param int    $order_id WooCommerce order id.
	 */
	do_action( 'pkit_request_payment_reversed', $request['type'], $request['id'], $order_id );
}
