<?php
/**
 * What a pre-order line collects up front, and what it leaves for pickup.
 *
 * Core owns this because the settings are product data, alongside price and
 * unit, and because the confirmation page has to state the split whether or
 * not WooCommerce is installed. Core does not process payment: it answers
 * "how much of this is due now", and the WooCommerce module turns that answer
 * into orders.
 *
 * Three modes, per product:
 *
 *   none     Reserve only. Nothing is charged up front, which is how every
 *            pre-order behaved before deposits existed and remains the
 *            default, so enabling the module changes nothing on its own.
 *   deposit  Part now, the rest at pickup. A nucleus colony at $200 with a
 *            $50 fixed deposit; two of them is $100 down and $300 later.
 *   full     The whole line up front, for things a producer will not hold
 *            without payment.
 *
 * A deposit is either a fixed amount per unit or a percentage of the line.
 * Fixed scales with quantity, which is what "$50 per nuc" means. Percentage
 * is proportional by construction.
 */

declare(strict_types=1);

namespace ProducerKit\Core\Deposits;

defined( 'ABSPATH' ) || exit;

/** Reserve only; nothing charged up front. */
const MODE_NONE = 'none';

/** Part now, balance at pickup. */
const MODE_DEPOSIT = 'deposit';

/** The whole line up front. */
const MODE_FULL = 'full';

/**
 * The payment policy recorded on a product.
 *
 * @return array{mode: string, kind: string, value: float}
 */
function for_product( int $product_id ): array {
	$mode = (string) get_post_meta( $product_id, '_pkit_payment_mode', true );
	$kind = (string) get_post_meta( $product_id, '_pkit_deposit_kind', true );

	$mode = in_array( $mode, [ MODE_NONE, MODE_DEPOSIT, MODE_FULL ], true ) ? $mode : MODE_NONE;

	return [
		'mode'  => $mode,
		'kind'  => 'percent' === $kind ? 'percent' : 'fixed',
		'value' => max( 0.0, (float) get_post_meta( $product_id, '_pkit_deposit_value', true ) ),
	];
}

/**
 * Split one order line into what is due now and what is due at pickup.
 *
 * The two halves always sum to the line total exactly. The balance is derived
 * by subtraction rather than computed independently, because two separately
 * rounded figures can miss the total by a cent and a customer who is quoted
 * $50.00 now and $149.99 later on a $200.00 line will notice.
 *
 * A deposit is never allowed to exceed the line. A producer who types 150 in
 * the percent box, or a $60 deposit against a $50 product, gets the whole
 * line charged up front rather than an order that owes them money.
 *
 * @param float $line_total Price × quantity, already rounded to cents.
 * @param array{mode: string, kind: string, value: float} $policy From for_product().
 * @return array{due_now: float, balance: float, mode: string}
 */
function split_line( float $line_total, array $policy ): array {
	$line_total = round( max( 0.0, $line_total ), 2 );
	$mode       = (string) ( $policy['mode'] ?? MODE_NONE );

	if ( MODE_FULL === $mode ) {
		return [
			'due_now' => $line_total,
			'balance' => 0.0,
			'mode'    => MODE_FULL,
		];
	}

	if ( MODE_DEPOSIT !== $mode ) {
		return [
			'due_now' => 0.0,
			'balance' => $line_total,
			'mode'    => MODE_NONE,
		];
	}

	$value = max( 0.0, (float) ( $policy['value'] ?? 0 ) );

	$due_now = 'percent' === ( $policy['kind'] ?? 'fixed' )
		? round( $line_total * ( $value / 100 ), 2 )
		: round( $value, 2 );

	// A deposit of zero is a reservation with extra steps; say so plainly
	// rather than raising an order for nothing.
	if ( $due_now <= 0 ) {
		return [
			'due_now' => 0.0,
			'balance' => $line_total,
			'mode'    => MODE_NONE,
		];
	}

	if ( $due_now >= $line_total ) {
		return [
			'due_now' => $line_total,
			'balance' => 0.0,
			'mode'    => MODE_FULL,
		];
	}

	return [
		'due_now' => $due_now,
		'balance' => round( $line_total - $due_now, 2 ),
		'mode'    => MODE_DEPOSIT,
	];
}

/**
 * A human sentence describing what a line will collect.
 *
 * Used on the product page and in the pre-order confirmation, so a customer
 * knows before submitting that money is expected and roughly how much.
 */
function describe( array $policy ): string {
	$mode = (string) ( $policy['mode'] ?? MODE_NONE );

	if ( MODE_FULL === $mode ) {
		return __( 'Paid in full when you order.', 'producerkit' );
	}

	if ( MODE_DEPOSIT !== $mode || ( (float) ( $policy['value'] ?? 0 ) ) <= 0 ) {
		return '';
	}

	if ( 'percent' === ( $policy['kind'] ?? 'fixed' ) ) {
		return sprintf(
			/* translators: %s: percentage, e.g. "25". */
			__( '%s%% deposit when you order, balance at pickup.', 'producerkit' ),
			(string) round( (float) $policy['value'], 2 )
		);
	}

	return sprintf(
		/* translators: %s: formatted money amount, e.g. "$50.00". */
		__( '%s deposit per item when you order, balance at pickup.', 'producerkit' ),
		money( (float) $policy['value'] )
	);
}

/**
 * Format an amount for display.
 *
 * Defers to WooCommerce when it is present so the store's own currency and
 * separators are used, and falls back to a plain two-decimal figure when it is
 * not — deposits are declared on products whether or not a store exists.
 */
function money( float $amount ): string {
	if ( function_exists( 'wc_price' ) ) {
		// wc_price() returns markup whose currency symbol is an entity —
		// "&#36;400.00". Stripping the tags leaves the entity behind, which
		// then reaches an order-item name and a plain-text email as literal
		// "&#36;". Decode it back to the character it stands for.
		return html_entity_decode(
			wp_strip_all_tags( (string) wc_price( $amount ) ),
			ENT_QUOTES,
			'UTF-8'
		);
	}

	return number_format_i18n( $amount, 2 );
}
