<?php
/**
 * Event Manager meta extensions on pkit_event.
 *
 * Adds fields for RSVP configuration, display options,
 * and event-specific details beyond what core provides.
 *
 * These use the plain _pkit_ prefix, like every other field on this post
 * type. They carried a _pkit_em_ infix until 2.6.0, on the reasoning that a
 * module should stay out of core's space — but there is no second party to
 * collide with inside one plugin, and the infix pinned a field to the module
 * that happened to register it. Core already registered _pkit_rsvp_cap, so
 * one feature's settings sat under two prefixes. See includes/upgrade.php.
 */

declare(strict_types=1);

namespace ProducerKit\EventManager\Meta;

defined( 'ABSPATH' ) || exit;

add_action( 'init', __NAMESPACE__ . '\\register' );

function register(): void {
	$fields = [
		'_pkit_rsvp_enabled'    => [
			'type'        => 'boolean',
			'description' => 'Whether RSVP / headcount is enabled for this event.',
			'default'     => false,
		],
		'_pkit_rsvp_label'      => [
			'type'        => 'string',
			'description' => 'Custom RSVP button label (e.g. "I\'m coming!", "Count me in").',
			'default'     => '',
		],
		'_pkit_rsvp_closed'     => [
			'type'        => 'boolean',
			'description' => 'Manually close RSVPs (independent of cap).',
			'default'     => false,
		],
		'_pkit_what_to_bring'   => [
			'type'        => 'string',
			'description' => 'What to bring note (e.g. "a dish to share", "your own bowl").',
			'default'     => '',
		],
		'_pkit_cost_note'       => [
			'type'        => 'string',
			'description' => 'Cost/donation note (e.g. "Donation-based", "$10 suggested").',
			'default'     => '',
		],
		// Four things any event can have. Written as musician fields
		// originally — support acts, doors, age limit, ticket link — until it
		// became clear that two farmers sharing a booth is a support act, a
		// co-teacher is a support act, and the rest follow. The words differ
		// by trade and come from pkit_meta_labels; the fields do not.
		'_pkit_also_appearing'  => [
			'sanitize'    => __NAMESPACE__ . '\\sanitize_also_appearing',
			'type'        => 'string',
			'description' => 'Who else is on this — the other act, the other stallholder, a co-teacher.',
			'default'     => '',
		],
		'_pkit_doors_datetime'  => [
			'type'        => 'string',
			'description' => 'When people can arrive, if that differs from when it starts. Y-m-d\TH:i:s in site time.',
			'default'     => '',
			'sanitize'    => __NAMESPACE__ . '\\sanitize_doors_datetime',
		],
		'_pkit_age_restriction' => [
			'type'        => 'string',
			'description' => 'Who can come — "18+", "All ages", "Under-12s with an adult". Free text on purpose.',
			'default'     => '',
		],
		'_pkit_ticket_url'      => [
			'type'        => 'string',
			'description' => 'Where tickets are sold. This plugin does not sell them; a link out is the honest answer.',
			'default'     => '',
			'sanitize'    => __NAMESPACE__ . '\\sanitize_ticket_url',
		],
		'_pkit_cancelled'       => [
			'type'        => 'boolean',
			'description' => 'Whether this event has been cancelled.',
			'default'     => false,
		],
	];

	foreach ( $fields as $key => $args ) {
		register_post_meta(
			'pkit_event',
			$key,
			[
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => $args['type'],
				'description'       => $args['description'],
				'default'           => $args['default'],
				'sanitize_callback' => $args['sanitize'] ?? match ( $args['type'] ) {
					'boolean' => 'rest_sanitize_boolean',
					default   => 'sanitize_text_field',
				},
				'auth_callback'     => fn () => current_user_can( 'edit_posts' ),
			]
		);
	}
}

/**
 * Keep a doors time to the shape the rest of the plugin uses.
 *
 * Whether it is *sensible* — before the event starts — cannot be decided
 * here: a sanitize_callback is handed the value with no object id, so it
 * cannot see the start time to compare against. That judgement lives in
 * doors_datetime() below, which is what every reader goes through, and in the
 * editor panel, which is where a person can be told.
 */
function sanitize_doors_datetime( mixed $value ): string {
	$value = trim( (string) $value );

	if ( '' === $value ) {
		return '';
	}

	return preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $value ) ? $value : '';
}

/**
 * A ticket link, or nothing.
 *
 * esc_url_raw() rather than sanitize_text_field: this is rendered as an href
 * and a javascript: scheme in it would be a hole rather than a typo.
 */
function sanitize_ticket_url( mixed $value ): string {
	$value = trim( (string) $value );

	return '' === $value ? '' : (string) esc_url_raw( $value, [ 'http', 'https' ] );
}

/**
 * Split "who else is here" into a label and a link.
 *
 * One string still, so nothing migrates: a trailing http(s) address becomes
 * the link and whatever precedes it becomes the label. "The bakery next door
 * https://example.com" reads as the name, pointing at their site. An
 * address on its own is labelled with its host, because a bare URL is not
 * what a visitor is looking for. Anything without an address is plain text,
 * exactly as before.
 *
 * This exists because two producers who keep separate sites and share a stall
 * have nowhere else to say so — each site's availability board is correct
 * about its own goods and silent about the other's, so the event is where a
 * visitor finds out (#23, #76).
 *
 * @return array{text: string, url: string}
 */
function also_appearing( string $value ): array {
	$value = trim( $value );

	if ( '' === $value ) {
		return [
			'text' => '',
			'url'  => '',
		];
	}

	// Only a trailing address is treated as the link. One in the middle is
	// part of what they wrote, and rewriting their sentence around it would be
	// a guess.
	if ( ! preg_match( '#^(.*?)\s*(https?://\S+)$#i', $value, $m ) ) {
		return [
			'text' => $value,
			'url'  => '',
		];
	}

	$url = (string) esc_url_raw( $m[2], [ 'http', 'https' ] );

	if ( '' === $url ) {
		return [
			'text' => $value,
			'url'  => '',
		];
	}

	$text = trim( $m[1] );

	if ( '' === $text ) {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		$text = '' !== $host ? preg_replace( '#^www\.#', '', $host ) : $url;
	}

	return [
		'text' => $text,
		'url'  => $url,
	];
}

/**
 * Keep the text, and keep only an address we would be willing to render.
 *
 * sanitize_text_field would leave a javascript: scheme sitting in a value that
 * becomes an href, so the address half goes through esc_url_raw() the way
 * sanitize_ticket_url() does. A rejected address is dropped rather than
 * silently turning the whole entry into a link to nowhere.
 */
function sanitize_also_appearing( mixed $value ): string {
	$raw = trim( (string) $value );

	if ( '' === $raw ) {
		return '';
	}

	if ( ! preg_match( '#^(.*?)\s*(\S+://\S+)$#', $raw, $m ) ) {
		return sanitize_text_field( $raw );
	}

	$text = sanitize_text_field( trim( $m[1] ) );
	$url  = (string) esc_url_raw( $m[2], [ 'http', 'https' ] );

	if ( '' === $url ) {
		return $text;
	}

	return '' === $text ? $url : $text . ' ' . $url;
}

/**
 * The doors time, if there is a sensible one.
 *
 * Returns nothing when doors fall at or after the start. Storing what the
 * producer typed and declining to show nonsense is better than either
 * silently discarding their input on save or printing "Doors 9pm, starts
 * 7pm" on the front of the site. The editor panel says so while they are
 * still looking at it.
 *
 * @return string Y-m-d\TH:i:s, or '' if there is none worth showing.
 */
function doors_datetime( int $event_id ): string {
	$doors = trim( (string) get_post_meta( $event_id, '_pkit_doors_datetime', true ) );

	if ( '' === $doors ) {
		return '';
	}

	$start = trim( (string) get_post_meta( $event_id, '_pkit_start_datetime', true ) );

	// With no start to compare against, take it at face value.
	if ( '' === $start ) {
		return $doors;
	}

	return $doors < $start ? $doors : '';
}
