<?php
/**
 * Commission email notifications.
 *
 * Hooks the commissions module's actions; if that module is disabled the
 * actions simply never fire. Suppress filters:
 *   - 'pkit_notify_commission_created'   → bool (staff: a request arrived)
 *   - 'pkit_notify_commission_quoted'    → bool (customer: here is the quote)
 *   - 'pkit_notify_commission_accepted'  → bool (staff: they said yes)
 *   - 'pkit_notify_commission_declined'  → bool (staff: they said no)
 *   - 'pkit_notify_commission_complete'  → bool (customer: it is finished)
 *
 * wc-artisan-tools built these five on WC_Email subclasses, which meant the
 * commission workflow could not run without WooCommerce. Here they are plain
 * wp_mail through the notifications module, so a maker with no store still
 * gets their mail. The woocommerce module may later re-home them onto
 * WC_Email so they appear under WooCommerce → Settings → Emails, but that is
 * an enhancement rather than a dependency.
 */

declare(strict_types=1);

namespace ProducerKit\Notifications\Commissions;

use ProducerKit\Commissions\QuoteResponse;
use ProducerKit\Commissions\Store;

use function ProducerKit\Notifications\Email\send;

defined( 'ABSPATH' ) || exit;

/**
 * The customer-facing link for a quote decision.
 *
 * Filterable because the destination depends on where the site put the
 * commission-status page; the default points at the REST route, which is
 * always present.
 */
function decision_url( array $commission, string $decision ): string {
	// The confirmation page, not the REST route. That route is POST-only on
	// purpose — a mail client prefetching a link must not be able to accept a
	// quote — and a click is a GET, so linking straight at it 404'd every
	// customer who tried.
	$url = QuoteResponse\url_for( (string) ( $commission['quote_token'] ?? '' ) );

	/**
	 * Filters the accept/decline URL sent to the customer.
	 *
	 * @param string $url        Default REST endpoint.
	 * @param array  $commission Commission data.
	 * @param string $decision   'accept' or 'decline'.
	 */
	return (string) apply_filters( 'pkit_commission_decision_url', $url, $commission, $decision );
}

/**
 * Summarise what was asked for, using whatever the customer supplied.
 */
function request_summary( array $commission ): string {
	$html = '<p>' . nl2br( esc_html( (string) ( $commission['description'] ?? '' ) ) ) . '</p>';

	$candidates = [
		__( 'Type', 'producerkit' )     => (string) ( $commission['product_type'] ?? '' ),
		__( 'Material', 'producerkit' ) => (string) ( $commission['material'] ?? '' ),
		__( 'Budget', 'producerkit' )   => (string) ( $commission['budget_range'] ?? '' ),
		__( 'Deadline', 'producerkit' ) => (string) ( $commission['deadline'] ?? '' ),
	];

	$rows = [];
	foreach ( $candidates as $label => $value ) {
		if ( '' !== $value ) {
			$rows[ $label ] = $value;
		}
	}

	if ( ! $rows ) {
		return $html;
	}

	$html .= '<table style="border-collapse:collapse;width:100%;margin:12px 0;">';
	foreach ( $rows as $label => $value ) {
		$html .= '<tr><td style="padding:6px 12px;border-bottom:1px solid #f3f4f6;color:#6b7280;">'
			. esc_html( (string) $label )
			. '</td><td style="padding:6px 12px;border-bottom:1px solid #f3f4f6;"><strong>'
			. esc_html( $value )
			. '</strong></td></tr>';
	}

	return $html . '</table>';
}

/* ── New request → staff ───────────────────────── */

add_action(
	'pkit_commission_created',
	function ( array $commission ): void {
		// The honeypot receipt has id 0 and was never stored; do not mail on it.
		if ( 0 === (int) ( $commission['id'] ?? 0 ) ) {
			return;
		}

		if ( ! apply_filters( 'pkit_notify_commission_created', true, $commission ) ) {
			return;
		}

		$body  = '<p><strong>' . esc_html( (string) $commission['name'] ) . '</strong> asked about a commission.</p>';
		$body .= request_summary( $commission );
		$body .= '<p>Email: ' . esc_html( (string) $commission['email'] ) . '</p>';
		if ( ! empty( $commission['phone'] ) ) {
			$body .= '<p>Phone: ' . esc_html( (string) $commission['phone'] ) . '</p>';
		}

		send( 'New commission request from ' . (string) $commission['name'], $body );
	}
);

/* ── Quote sent → customer ─────────────────────── */

add_action(
	'pkit_commission_quoted',
	function ( array $commission ): void {
		if ( ! apply_filters( 'pkit_notify_commission_quoted', true, $commission ) ) {
			return;
		}

		$email = (string) ( $commission['email'] ?? '' );
		if ( ! is_email( $email ) ) {
			return;
		}

		$price = null !== ( $commission['quoted_price'] ?? null )
			? number_format( (float) $commission['quoted_price'], 2 )
			: '';

		$body = '<p>Thank you for your enquiry. Here is a quote for the piece you described.</p>';
		if ( '' !== $price ) {
			$body .= '<p style="font-size:20px;"><strong>' . esc_html( $price ) . '</strong></p>';
		}
		if ( ! empty( $commission['estimated_date'] ) ) {
			$body .= '<p>Estimated ready: <strong>' . esc_html( (string) $commission['estimated_date'] ) . '</strong></p>';
		}
		if ( ! empty( $commission['maker_note'] ) ) {
			$body .= '<p>' . nl2br( esc_html( (string) $commission['maker_note'] ) ) . '</p>';
		}

		$body .= '<p>' . esc_html__( 'To accept or decline, open:', 'producerkit' ) . '<br>';
		$body .= esc_url( decision_url( $commission, 'accept' ) ) . '</p>';
		$body .= '<p style="color:#6b7280;">This quote is good for 30 days.</p>';

		send( 'Your quote is ready', $body, [ $email ] );
	}
);

/* ── Accepted / declined → staff ───────────────── */

add_action(
	'pkit_commission_accepted',
	function ( array $commission ): void {
		if ( ! apply_filters( 'pkit_notify_commission_accepted', true, $commission ) ) {
			return;
		}

		send(
			'Commission accepted by ' . (string) $commission['name'],
			'<p><strong>' . esc_html( (string) $commission['name'] ) . '</strong> accepted the quote.</p>'
				. request_summary( $commission )
		);
	}
);

add_action(
	'pkit_commission_declined',
	function ( array $commission ): void {
		if ( ! apply_filters( 'pkit_notify_commission_declined', true, $commission ) ) {
			return;
		}

		send(
			'Commission declined by ' . (string) $commission['name'],
			'<p><strong>' . esc_html( (string) $commission['name'] ) . '</strong> declined the quote.</p>'
		);
	}
);

/* ── Complete → customer ───────────────────────── */

add_action(
	'pkit_commission_complete',
	function ( array $commission ): void {
		if ( ! apply_filters( 'pkit_notify_commission_complete', true, $commission ) ) {
			return;
		}

		$email = (string) ( $commission['email'] ?? '' );
		if ( ! is_email( $email ) ) {
			return;
		}

		send(
			'Your commission is finished',
			'<p>Your piece is done. We will be in touch about getting it to you.</p>',
			[ $email ]
		);
	}
);
