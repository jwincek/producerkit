<?php
/**
 * Pre-order email notifications.
 *
 * Hooks the pre-order module's actions; if that module is disabled the
 * actions simply never fire. Suppress filters:
 *   - 'lfuf_notify_preorder_created'        → bool (staff email)
 *   - 'lfuf_notify_preorder_confirmation'   → bool (customer email)
 *   - 'lfuf_notify_preorder_status_changed' → bool (customer "ready" email)
 */

declare(strict_types=1);

namespace Leftfield\Notifications\PreOrder;

use function Leftfield\Notifications\Email\send;

defined( 'ABSPATH' ) || exit;

/**
 * Render the order's item lines as simple HTML rows.
 */
function items_html( array $order ): string {
	$html = '<table style="border-collapse:collapse;width:100%;margin:12px 0;">';
	foreach ( $order['items'] as $item ) {
		$html .= '<tr><td style="padding:6px 12px;border-bottom:1px solid #f3f4f6;">'
			. (int) $item['qty'] . ' × <strong>' . esc_html( $item['title'] ) . '</strong>'
			. ( $item['unit'] !== '' ? ' <span style="color:#6b7280;">(' . esc_html( $item['unit'] ) . ')</span>' : '' )
			. '</td><td style="padding:6px 12px;border-bottom:1px solid #f3f4f6;color:#4b5563;">'
			. esc_html( $item['price'] )
			. '</td></tr>';
	}
	$html .= '</table>';
	return $html;
}

/* ── New pre-order → staff ─────────────────────── */

add_action(
	'lfuf_preorder_created',
	function ( array $order ): void {
		if ( ! apply_filters( 'lfuf_notify_preorder_created', true, $order ) ) {
			return;
		}

		$subject = 'New pre-order: ' . $order['name'] . ' → pickup ' . $order['pickup_date'];

		$body  = '<p><strong>' . esc_html( $order['name'] ) . '</strong> placed a pre-order for pickup on <strong>' . esc_html( $order['pickup_date'] ) . '</strong>';
		$body .= $order['location_name'] ? ' at <strong>' . esc_html( $order['location_name'] ) . '</strong>.</p>' : '.</p>';
		$body .= items_html( $order );
		if ( $order['email'] ) {
			$body .= '<p>Email: ' . esc_html( $order['email'] ) . '</p>';
		}
		if ( $order['phone'] ) {
			$body .= '<p>Phone: ' . esc_html( $order['phone'] ) . '</p>';
		}
		if ( $order['note'] ) {
			$body .= '<p>Note: ' . esc_html( $order['note'] ) . '</p>';
		}
		$body .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=farm-stand-preorders' ) ) . '" style="color:#065f46;">Manage pre-orders →</a></p>';

		send( $subject, $body );

		// Confirmation to the customer, when they left an email.
		if ( $order['email'] && apply_filters( 'lfuf_notify_preorder_confirmation', true, $order ) ) {
			$confirm_subject = 'Your pre-order for ' . $order['pickup_date'];
			$confirm         = '<p>Thanks, ' . esc_html( $order['name'] ) . '! We received your pre-order for pickup on <strong>' . esc_html( $order['pickup_date'] ) . '</strong>';
			$confirm        .= $order['location_name'] ? ' at <strong>' . esc_html( $order['location_name'] ) . '</strong>.</p>' : '.</p>';
			$confirm        .= items_html( $order );
			$confirm        .= '<p>Payment is at pickup. We\'ll email you when your order is ready.</p>';

			send( $confirm_subject, $confirm, [ $order['email'] ] );
		}
	},
	10,
	1
);

/* ── Ready for pickup → customer ───────────────── */

add_action(
	'lfuf_preorder_status_changed',
	function ( array $order, string $old, string $new ): void {
		if ( $new !== 'ready' || ! $order['email'] ) {
			return;
		}
		if ( ! apply_filters( 'lfuf_notify_preorder_status_changed', true, $order, $old, $new ) ) {
			return;
		}

		$subject = 'Your pre-order is ready for pickup!';

		$body  = '<p>Hi ' . esc_html( $order['name'] ) . ' — your pre-order is <strong>ready for pickup</strong>';
		$body .= $order['location_name'] ? ' at <strong>' . esc_html( $order['location_name'] ) . '</strong>' : '';
		$body .= ' on <strong>' . esc_html( $order['pickup_date'] ) . '</strong>.</p>';
		$body .= items_html( $order );
		$body .= '<p>Payment is at pickup — see you soon!</p>';

		send( $subject, $body, [ $order['email'] ] );
	},
	10,
	3
);
