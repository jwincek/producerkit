<?php
/**
 * Email notification handlers.
 *
 * Listens to existing plugin hooks and sends email to the site admin.
 * All notifications are filterable:
 *
 *   - 'lfuf_notify_recipients'            → array of email addresses
 *   - 'lfuf_notify_rsvp_added'            → bool, false to suppress
 *   - 'lfuf_notify_rsvp_cancelled'        → bool, false to suppress
 *   - 'lfuf_notify_stand_status_changed'  → bool, false to suppress
 *   - 'lfuf_notify_availability_expired'  → bool, false to suppress (default: suppressed)
 */

declare(strict_types=1);

namespace Leftfield\Notifications\Email;

defined( 'ABSPATH' ) || exit;

/* ───────────────────────────────────────────────
 * Helpers
 * ─────────────────────────────────────────────── */

/**
 * Get the notification recipients.
 *
 * Defaults to the site admin email. Filterable to add
 * additional addresses (e.g., a farm partner).
 *
 * @return string[]
 */
function get_recipients(): array {
	$admin_email = get_option( 'admin_email' );
	$recipients  = [ $admin_email ];

	/** @var string[] */
	return apply_filters( 'lfuf_notify_recipients', $recipients );
}

/**
 * Convert HTML email body to a plain-text alternative.
 *
 * Handles links, paragraphs, line breaks, and table rows
 * so the fallback is readable in text-only clients.
 */
function to_plain_text( string $html ): string {
	$text = $html;
	// Convert links to "text (url)" format.
	$text = preg_replace( '/<a[^>]+href="([^"]*)"[^>]*>([^<]*)<\/a>/', '$2 ($1)', $text );
	// Convert block-level closers to line breaks.
	$text = preg_replace( '/<\/p>/', "\n\n", $text );
	$text = preg_replace( '/<\/tr>/', "\n", $text );
	$text = preg_replace( '/<br\s*\/?>/', "\n", $text );
	$text = preg_replace( '/<hr[^>]*>/', "\n---\n", $text );
	// Strip remaining tags and decode entities.
	$text = wp_strip_all_tags( $text );
	$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	// Collapse excessive whitespace.
	$text = preg_replace( '/\n{3,}/', "\n\n", $text );
	return trim( $text );
}

/**
 * Send an HTML email with a plain-text fallback.
 *
 * @param string   $subject
 * @param string   $body    HTML body content (will be wrapped).
 * @param string[] $to      Recipients. Defaults to get_recipients().
 */
function send( string $subject, string $body, array $to = [] ): bool {
	if ( empty( $to ) ) {
		$to = get_recipients();
	}

	if ( empty( $to ) ) {
		return false;
	}

	$site_name    = get_bloginfo( 'name' );
	$full_subject = '[' . $site_name . '] ' . $subject;

	$html  = '<!DOCTYPE html><html><body style="font-family:-apple-system,BlinkMacSystemFont,sans-serif;max-width:600px;margin:0 auto;padding:20px;color:#1f2937;">';
	$html .= '<h2 style="color:#065f46;margin-top:0;">🥕 ' . esc_html( $site_name ) . '</h2>';
	$html .= $body;
	$html .= '<hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0 12px;">';
	$html .= '<p style="font-size:12px;color:#9ca3af;">This is an automated notification from the Farm Stand Manager plugin.</p>';
	$html .= '</body></html>';

	$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

	// Set a plain-text alternative for email clients that prefer it
	// and to improve deliverability with spam filters.
	$plain_text   = to_plain_text( $body );
	$set_alt_body = function ( \PHPMailer\PHPMailer\PHPMailer $phpmailer ) use ( $plain_text ): void {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer's own public property.
		$phpmailer->AltBody = $plain_text;
	};

	add_action( 'phpmailer_init', $set_alt_body );
	$result = wp_mail( $to, $full_subject, $html, $headers );
	remove_action( 'phpmailer_init', $set_alt_body );

	return $result;
}

/* ───────────────────────────────────────────────
 * RSVP Added
 * ─────────────────────────────────────────────── */

add_action(
	'lfuf_rsvp_added',
	function ( array $rsvp, int $event_id ): void {
		/** Allow suppressing this notification. */
		if ( ! apply_filters( 'lfuf_notify_rsvp_added', true, $rsvp, $event_id ) ) {
			return;
		}

		$event = get_post( $event_id );
		if ( ! $event ) {
			return;
		}

		$event_title = $event->post_title;
		$name        = esc_html( $rsvp['name'] ?? 'Someone' );
		$party_size  = (int) ( $rsvp['party_size'] ?? 1 );
		$note        = esc_html( $rsvp['note'] ?? '' );
		$email       = esc_html( $rsvp['email'] ?? '' );

		// Get current headcount.
		$headcount = 0;
		$cap       = 0;
		if ( function_exists( 'Leftfield\\EventManager\\RSVP\\get_headcount' ) ) {
			$headcount = \Leftfield\EventManager\RSVP\get_headcount( $event_id );
			$cap       = (int) get_post_meta( $event_id, '_lfuf_rsvp_cap', true );
		}

		$subject = 'New RSVP: ' . $name . ' → ' . $event_title;

		$body  = '<p><strong>' . $name . '</strong> just RSVP\'d for <strong>' . esc_html( $event_title ) . '</strong>.</p>';
		$body .= '<table style="border-collapse:collapse;width:100%;margin:12px 0;">';
		$body .= '<tr><td style="padding:6px 12px;border-bottom:1px solid #f3f4f6;color:#4b5563;">Party size</td>';
		$body .= '<td style="padding:6px 12px;border-bottom:1px solid #f3f4f6;"><strong>' . $party_size . ' ' . ( $party_size === 1 ? 'person' : 'people' ) . '</strong></td></tr>';

		if ( $email ) {
			$body .= '<tr><td style="padding:6px 12px;border-bottom:1px solid #f3f4f6;color:#4b5563;">Email</td>';
			$body .= '<td style="padding:6px 12px;border-bottom:1px solid #f3f4f6;">' . $email . '</td></tr>';
		}

		if ( $note ) {
			$body .= '<tr><td style="padding:6px 12px;border-bottom:1px solid #f3f4f6;color:#4b5563;">Note</td>';
			$body .= '<td style="padding:6px 12px;border-bottom:1px solid #f3f4f6;">' . $note . '</td></tr>';
		}

		$body .= '<tr><td style="padding:6px 12px;border-bottom:1px solid #f3f4f6;color:#4b5563;">Total headcount</td>';
		$body .= '<td style="padding:6px 12px;border-bottom:1px solid #f3f4f6;"><strong>' . $headcount . '</strong>';
		if ( $cap > 0 ) {
			$body     .= ' / ' . $cap;
			$remaining = $cap - $headcount;
			if ( $remaining <= 0 ) {
				$body .= ' <span style="color:#991b1b;font-weight:600;">FULL</span>';
			} elseif ( $remaining <= 5 ) {
				$body .= ' <span style="color:#92400e;">(' . $remaining . ' spots left)</span>';
			}
		}
		$body .= '</td></tr>';
		$body .= '</table>';

		$body .= '<p><a href="' . esc_url( get_edit_post_link( $event_id, 'raw' ) ) . '" style="color:#065f46;">View event in admin →</a></p>';

		send( $subject, $body );
	},
	10,
	2
);

/* ───────────────────────────────────────────────
 * RSVP Cancelled
 * ─────────────────────────────────────────────── */

add_action(
	'lfuf_rsvp_cancelled',
	function ( array $rsvp, int $event_id ): void {
		if ( ! apply_filters( 'lfuf_notify_rsvp_cancelled', true, $rsvp, $event_id ) ) {
			return;
		}

		$event = get_post( $event_id );
		if ( ! $event ) {
			return;
		}

		$event_title = $event->post_title;
		$name        = esc_html( $rsvp['name'] ?? 'Someone' );
		$party_size  = (int) ( $rsvp['party_size'] ?? 1 );

		$headcount = 0;
		$cap       = 0;
		if ( function_exists( 'Leftfield\\EventManager\\RSVP\\get_headcount' ) ) {
			$headcount = \Leftfield\EventManager\RSVP\get_headcount( $event_id );
			$cap       = (int) get_post_meta( $event_id, '_lfuf_rsvp_cap', true );
		}

		$subject = 'RSVP Cancelled: ' . $name . ' → ' . $event_title;

		$body  = '<p><strong>' . $name . '</strong> cancelled their RSVP for <strong>' . esc_html( $event_title ) . '</strong>';
		$body .= ' (' . $party_size . ' ' . ( $party_size === 1 ? 'person' : 'people' ) . ').</p>';
		$body .= '<p>Updated headcount: <strong>' . $headcount . '</strong>';
		if ( $cap > 0 ) {
			$remaining = $cap - $headcount;
			$body     .= ' / ' . $cap;
			if ( $remaining > 0 ) {
				$body .= ' <span style="color:#065f46;">(' . $remaining . ' ' . ( $remaining === 1 ? 'spot' : 'spots' ) . ' now available)</span>';
			}
		}
		$body .= '</p>';

		send( $subject, $body );
	},
	10,
	2
);

/* ───────────────────────────────────────────────
 * Stand Status Changed
 * ─────────────────────────────────────────────── */

add_action(
	'lfuf_stand_status_changed',
	function ( int $location_id, bool $is_open, string $status_message ): void {
		if ( ! apply_filters( 'lfuf_notify_stand_status_changed', true, $location_id, $is_open, $status_message ) ) {
			return;
		}

		// Rate limit: skip if this stand was already notified within 5 minutes.
		// Prevents email floods from repeated toggles (testing, accidents, etc.).
		$transient_key = 'lfuf_stand_notified_' . $location_id;
		if ( get_transient( $transient_key ) ) {
			return;
		}
		set_transient( $transient_key, true, 5 * MINUTE_IN_SECONDS );

		$location = get_post( $location_id );
		if ( ! $location ) {
			return;
		}

		$location_name = $location->post_title;
		$status_label  = $is_open ? 'OPEN' : 'CLOSED';
		$status_color  = $is_open ? '#065f46' : '#991b1b';
		$status_bg     = $is_open ? '#d1fae5' : '#fee2e2';

		$subject = $location_name . ' is now ' . $status_label;

		$body  = '<p><strong>' . esc_html( $location_name ) . '</strong> was just toggled to:</p>';
		$body .= '<p style="display:inline-block;padding:6px 16px;border-radius:6px;font-weight:700;font-size:18px;';
		$body .= 'background:' . $status_bg . ';color:' . $status_color . ';">';
		$body .= $status_label . '</p>';

		if ( $status_message ) {
			$body .= '<p>Status message: <em>' . esc_html( $status_message ) . '</em></p>';
		}

		// Include who made the change, if available (REST API calls are authenticated).
		$user = wp_get_current_user();
		if ( $user && $user->ID ) {
			$body .= '<p style="color:#4b5563;font-size:13px;">Toggled by ' . esc_html( $user->display_name ) . ' at ' . esc_html( current_time( 'M j, Y g:i A' ) ) . '</p>';
		} else {
			$body .= '<p style="color:#4b5563;font-size:13px;">Toggled at ' . esc_html( current_time( 'M j, Y g:i A' ) ) . '</p>';
		}

		send( $subject, $body );
	},
	10,
	3
);

/* ───────────────────────────────────────────────
 * Availability Rows Expired (daily summary)
 *
 * Suppressed by default — this is routine database cleanup
 * that requires no operator action. Enable via filter:
 *
 *   add_filter( 'lfuf_notify_availability_expired', '__return_true' );
 * ─────────────────────────────────────────────── */

add_action(
	'lfuf_availability_expired_purged',
	function ( int $count ): void {
		if ( ! apply_filters( 'lfuf_notify_availability_expired', false, $count ) ) {
			return;
		}

		$subject = $count . ' expired availability ' . ( $count === 1 ? 'entry' : 'entries' ) . ' cleaned up';

		$body  = '<p>The daily cleanup removed <strong>' . $count . '</strong> expired availability ';
		$body .= ( $count === 1 ? 'entry' : 'entries' ) . ' from the database.</p>';
		$body .= '<p>These were rows with an expiration date in the past. The availability board was already hiding them — this just cleans up the database.</p>';
		$body .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=lfuf-availability' ) ) . '" style="color:#065f46;">View current availability →</a></p>';

		send( $subject, $body );
	},
	10,
	1
);
