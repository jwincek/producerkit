<?php
/**
 * Turning a request into something WooCommerce can take money for.
 *
 * A commission becomes a single hidden product at the quoted price. A
 * pre-order becomes one line per product, priced from the catalogue.
 *
 * The products are hidden from the shop catalogue rather than published:
 * "Dana's walnut bowl, £185" is not something another customer should be able
 * to find and buy.
 */

declare(strict_types=1);

namespace ProducerKit\WooCommerce\Checkout;

use ProducerKit\Commissions\Store as Commissions;
use ProducerKit\WooCommerce\Settlement;


defined( 'ABSPATH' ) || exit;

/** Meta linking a generated product back to the request it came from. */
const REQUEST_META = '_pkit_request';

// The confirmation page fires this when a customer accepts. Without a listener
// checkout_for_commission() had no caller at all outside the tests, so an
// accepted commission never produced a pay link and nothing ever wrote
// product_id — which is why the reuse guard's wrong meta key went unnoticed.
add_action( 'pkit_quote_accepted_onsite', __NAMESPACE__ . '\\on_quote_accepted' );

/**
 * Raise an order for a freshly accepted commission and remember its pay link.
 *
 * Failure is deliberately soft: the commission is already accepted and that
 * must stand whatever WooCommerce does. The maker sees the problem in the
 * admin and can still arrange payment directly, which is how a maker without
 * WooCommerce works anyway.
 *
 * @param array $commission Public-safe commission data.
 */
function on_quote_accepted( array $commission ): void {
	$id = (int) ( $commission['id'] ?? 0 );
	if ( $id < 1 || ! function_exists( 'wc_create_order' ) ) {
		return;
	}

	$checkout = checkout_for_commission( $id );

	if ( is_wp_error( $checkout ) ) {
		// Surfaced on the commissions screen rather than swallowed: the
		// customer has agreed and is waiting for a way to pay, so the maker
		// needs to know the order was not raised.
		set_transient( 'pkit_settlement_error_' . $id, $checkout->get_error_message(), WEEK_IN_SECONDS );
		return;
	}

	delete_transient( 'pkit_settlement_error_' . $id );
	set_transient( 'pkit_pay_url_' . $id, $checkout['pay_url'], WEEK_IN_SECONDS );
}

/**
 * Create the hidden product a commission is paid through.
 *
 * @return int|\WP_Error Product id.
 */
function product_for_commission( int $commission_id ): int|\WP_Error {
	if ( ! class_exists( '\WC_Product_Simple' ) ) {
		return new \WP_Error( 'no_woocommerce', __( 'WooCommerce is not available.', 'producerkit' ) );
	}

	$commission = Commissions\get( $commission_id );
	if ( null === $commission ) {
		return new \WP_Error( 'not_found', __( 'Commission not found.', 'producerkit' ) );
	}

	$price = $commission['quoted_price'] ?? null;
	if ( null === $price || (float) $price <= 0 ) {
		return new \WP_Error( 'not_quoted', __( 'This commission has no quoted price to charge.', 'producerkit' ) );
	}

	// Reuse the product if one was already made, so a second accept — a retry,
	// a double click — does not litter the catalogue. Note the key:
	// attach_product() writes `product_id`, and reading `wc_product_id` here
	// always missed, so every retry made another product.
	$existing = (int) ( $commission['product_id'] ?? 0 );
	if ( $existing > 0 && 'product' === get_post_type( $existing ) ) {
		return $existing;
	}

	$product = new \WC_Product_Simple();
	$product->set_name(
		sprintf(
			/* translators: %s: customer name. */
			__( 'Commission for %s', 'producerkit' ),
			(string) $commission['name']
		)
	);
	$product->set_regular_price( number_format( (float) $price, 2, '.', '' ) );
	// Hidden from the catalogue AND unreachable directly. catalog_visibility
	// alone leaves the post published, so /?p=<id> served the customer's name
	// and their verbatim commission description to anyone who guessed the id.
	$product->set_catalog_visibility( 'hidden' );
	$product->set_status( 'private' );
	$product->set_sold_individually( true );
	$product->set_virtual( false );
	$product->set_stock_status( 'instock' );
	// Deliberately not the customer's description: it is their words about a
	// private commission, and a product post is the wrong place to keep them.
	// The admin queue is where the maker reads the request.
	$product->set_description(
		sprintf(
			/* translators: %d: commission reference number. */
			__( 'Commission #%d — agreed at the quoted price.', 'producerkit' ),
			$commission_id
		)
	);

	$product_id = (int) $product->save();
	if ( $product_id < 1 ) {
		return new \WP_Error( 'db_error', __( 'Could not create the product.', 'producerkit' ) );
	}

	update_post_meta( $product_id, REQUEST_META, 'commission:' . $commission_id );
	Commissions\attach_product( $commission_id, $product_id );

	return $product_id;
}

/**
 * Read a price string that is safe to charge, or null if it is not.
 *
 * Deliberately NOT parse_price(). That takes the first run of digits it finds,
 * which is right for schema.org markup where a wrong number is cosmetic, and
 * wrong for money:
 *
 *   "2 for $5"   -> 2.00      undercharges by 60%
 *   "$1,200.00"  -> 1.00      undercharges by a factor of 1200
 *
 * The rule here is that the string must contain **exactly one** number. That
 * is what separates the two shapes: in "$6.50/loaf" the number is the price
 * and the rest is a unit, while in "2 for $5" the first number is a quantity
 * and reading it as a price is a mischarge. A second number means the string
 * says something about quantity that this cannot safely interpret, so it
 * refuses and the operator is told which product to fix.
 *
 * Free text cannot be made completely safe, so the rule errs toward refusing:
 * anything it cannot read as one price plus a unit stops the checkout with the
 * product named, which is a problem the operator can see and fix.
 *
 * @param string $display Free-text price as entered.
 * @return float|null Amount, or null when the string cannot be charged.
 */
function chargeable_price( string $display ): ?float {
	$trimmed = trim( $display );
	if ( '' === $trimmed ) {
		return null;
	}

	// Thousands separators first, so 1,200.00 is one number and not two.
	$normalised = preg_replace( '/(?<=\d),(?=\d{3}\b)/', '', $trimmed );

	// Exactly one number, or the string is saying something extra.
	if ( 1 !== preg_match_all( '/\d+(?:\.\d+)?/', $normalised, $matches ) ) {
		return null;
	}

	$number = $matches[0][0];

	// A leading minus would otherwise be swallowed as punctuation and the
	// amount cast to a positive — the same trap as absint() turning -5 into 5,
	// except here it would charge for a credit.
	if ( preg_match( '/-\s*' . preg_quote( $number, '/' ) . '/', $normalised ) ) {
		return null;
	}

	// Reject more precision than money has; "4.005" is not a price.
	if ( str_contains( $number, '.' ) && strlen( explode( '.', $number )[1] ) > 2 ) {
		return null;
	}

	// Whatever surrounds it must be a currency mark or a unit, never digits
	// dressed up as words.
	$remainder = trim( str_replace( $number, '', $normalised ) );
	if ( '' !== $remainder && ! preg_match( '~^\p{Sc}?[\p{L}\s/\-.]*$~u', $remainder ) ) {
		return null;
	}

	$amount = (float) $number;

	return $amount > 0 ? $amount : null;
}

/**
 * Total a pre-order from its line items.
 *
 * Any line that is not unambiguously priced refuses the whole checkout and
 * names the product, which turns a silent mischarge into a visible problem the
 * operator can fix.
 *
 * @param array $order Public-safe pre-order data.
 * @return array{lines: array<int, array{product_id: int, qty: int, title: string, price: float}>, total: float}|\WP_Error
 */
function price_preorder( array $order ): array|\WP_Error {
	$lines = [];
	$total = 0.0;

	foreach ( (array) ( $order['items'] ?? [] ) as $item ) {
		$parsed = chargeable_price( (string) ( $item['price'] ?? '' ) );

		if ( null === $parsed ) {
			return new \WP_Error(
				'unpriceable',
				sprintf(
					/* translators: %s: product name. */
					__( '“%s” has no numeric price, so this order cannot be paid for online. Give it a price like "4.00" and try again.', 'producerkit' ),
					(string) ( $item['title'] ?? '' )
				)
			);
		}

		$qty     = max( 1, (int) ( $item['qty'] ?? 1 ) );
		$each    = (float) $parsed;
		$total  += $each * $qty;
		$lines[] = [
			'product_id' => (int) ( $item['product_id'] ?? 0 ),
			'qty'        => $qty,
			'title'      => (string) ( $item['title'] ?? '' ),
			'price'      => $each,
		];
	}

	if ( ! $lines ) {
		return new \WP_Error( 'empty_order', __( 'That order has no items to charge for.', 'producerkit' ) );
	}

	return [
		'lines' => $lines,
		'total' => round( $total, 2 ),
	];
}

/**
 * Raise a pending WooCommerce order for a request and hand back its pay URL.
 *
 * A draft order rather than a cart redirect: the customer may be opening the
 * link from an email days later, on a different device, where a cart built in
 * their old session no longer exists.
 *
 * @param string                                                           $type  'commission' or 'preorder'.
 * @param int                                                              $id    Request id.
 * @param array{name: string, email: string, lines: array<int, array>}     $args  Customer and line data.
 * @return array{order_id: int, pay_url: string}|\WP_Error
 */
function create_order( string $type, int $id, array $args ): array|\WP_Error {
	if ( ! function_exists( 'wc_create_order' ) ) {
		return new \WP_Error( 'no_woocommerce', __( 'WooCommerce is not available.', 'producerkit' ) );
	}

	$order = wc_create_order();
	if ( is_wp_error( $order ) ) {
		return $order;
	}

	foreach ( $args['lines'] as $line ) {
		$product = isset( $line['wc_product_id'] ) ? wc_get_product( (int) $line['wc_product_id'] ) : null;

		if ( $product ) {
			$order->add_product( $product, (int) ( $line['qty'] ?? 1 ) );
			continue;
		}

		// No WooCommerce product behind this line — a catalogue item that was
		// never mirrored — so add it as a fee-style line at the parsed price.
		$item = new \WC_Order_Item_Product();
		$item->set_name( (string) $line['title'] );
		$item->set_quantity( (int) ( $line['qty'] ?? 1 ) );
		$item->set_subtotal( (string) ( (float) $line['price'] * (int) ( $line['qty'] ?? 1 ) ) );
		$item->set_total( (string) ( (float) $line['price'] * (int) ( $line['qty'] ?? 1 ) ) );
		$order->add_item( $item );
	}

	if ( ! empty( $args['email'] ) ) {
		$order->set_billing_email( (string) $args['email'] );
	}
	if ( ! empty( $args['name'] ) ) {
		$parts = explode( ' ', trim( (string) $args['name'] ), 2 );
		$order->set_billing_first_name( $parts[0] );
		$order->set_billing_last_name( $parts[1] ?? '' );
	}

	$order->update_meta_data( REQUEST_META, $type . ':' . $id );
	$order->calculate_totals();
	$order->set_status( 'pending', __( 'Awaiting payment for a ProducerKit request.', 'producerkit' ) );
	$order->save();

	$order_id = (int) $order->get_id();

	$linked = Settlement\attach_order(
		$type,
		$id,
		$order_id,
		(int) ( $args['product_id'] ?? 0 )
	);

	if ( ! $linked ) {
		// Without the link, paying the order settles nothing — the request
		// stays open forever and the customer has paid. Better to fail loudly
		// here than hand back a pay URL that quietly leads nowhere.
		$order->set_status( 'cancelled', __( 'Could not link this order to its ProducerKit request.', 'producerkit' ) );
		$order->save();

		return new \WP_Error(
			'not_linked',
			__( 'Could not link the order to this request, so it was cancelled rather than left unpayable. Please try again.', 'producerkit' )
		);
	}

	return [
		'order_id' => $order_id,
		'pay_url'  => (string) $order->get_checkout_payment_url(),
	];
}

/**
 * The whole flow for an accepted commission: product, order, pay link.
 *
 * @return array{order_id: int, pay_url: string}|\WP_Error
 */
function checkout_for_commission( int $commission_id ): array|\WP_Error {
	$commission = Commissions\get( $commission_id );
	if ( null === $commission ) {
		return new \WP_Error( 'not_found', __( 'Commission not found.', 'producerkit' ) );
	}

	$product_id = product_for_commission( $commission_id );
	if ( is_wp_error( $product_id ) ) {
		return $product_id;
	}

	return create_order(
		'commission',
		$commission_id,
		[
			'name'       => (string) $commission['name'],
			'email'      => (string) $commission['email'],
			'product_id' => $product_id,
			'lines'      => [
				[
					'wc_product_id' => $product_id,
					'qty'           => 1,
					'title'         => (string) $commission['name'],
					'price'         => (float) $commission['quoted_price'],
				],
			],
		]
	);
}
