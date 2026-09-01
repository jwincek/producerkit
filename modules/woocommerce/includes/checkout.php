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

use function ProducerKit\Core\StructuredData\parse_price;

defined( 'ABSPATH' ) || exit;

/** Meta linking a generated product back to the request it came from. */
const REQUEST_META = '_lfuf_request';

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
	// a double click — does not litter the catalogue.
	$existing = (int) ( $commission['wc_product_id'] ?? 0 );
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
	$product->set_catalog_visibility( 'hidden' );
	$product->set_sold_individually( true );
	$product->set_virtual( false );
	$product->set_stock_status( 'instock' );
	$product->set_description( (string) $commission['description'] );

	$product_id = (int) $product->save();
	if ( $product_id < 1 ) {
		return new \WP_Error( 'db_error', __( 'Could not create the product.', 'producerkit' ) );
	}

	update_post_meta( $product_id, REQUEST_META, 'commission:' . $commission_id );
	Commissions\attach_product( $commission_id, $product_id );

	return $product_id;
}

/**
 * Total a pre-order from its line items.
 *
 * Prices are free text on purpose — "$4/bunch", "market price" — because a
 * farm stand's sign is not a price list. parse_price() reads a number out of
 * that for schema.org markup, where a wrong guess is cosmetic.
 *
 * Charging money on the same guess is not cosmetic: "2 for $5" parses to 2.00
 * and would undercharge every time. So any line that does not parse cleanly
 * refuses the whole checkout and names the product, which turns a silent
 * mischarge into a visible problem the operator can fix.
 *
 * @param array $order Public-safe pre-order data.
 * @return array{lines: array<int, array{product_id: int, qty: int, title: string, price: float}>, total: float}|\WP_Error
 */
function price_preorder( array $order ): array|\WP_Error {
	$lines = [];
	$total = 0.0;

	foreach ( (array) ( $order['items'] ?? [] ) as $item ) {
		$parsed = parse_price( (string) ( $item['price'] ?? '' ) );

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

	Settlement\attach_order(
		$type,
		$id,
		$order_id,
		(int) ( $args['product_id'] ?? 0 )
	);

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
