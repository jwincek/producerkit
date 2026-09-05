<?php
/**
 * Letting the editor ask what a recurrence rule means.
 *
 * The parser already refuses a rule it cannot honour and says exactly why —
 * "This plugin does not support BYSETPOS", "COUNT and UNTIL cannot both
 * appear", "an ordinal like 2SA only makes sense with FREQ=MONTHLY". None of
 * that reached anybody. The rule was set through WordPress's raw custom-field
 * box, the sanitiser dropped an invalid one, and the write returned 200 with
 * the value gone: a control that appeared to do nothing.
 *
 * This is how the panel gets an answer. Validation stays in one place — the
 * alternative was reimplementing RFC 5545 in JavaScript and keeping two
 * parsers agreeing with each other forever — and the editor asks the server
 * what it thinks before anything is saved.
 *
 * It also returns the next few dates. A rule is hard to read and a list of
 * Saturdays is not, and seeing "the 5th, 12th, 19th" is how a producer
 * notices they picked the wrong weekday.
 */

declare(strict_types=1);

namespace ProducerKit\EventManager\RecurrenceRest;

use ProducerKit\EventManager\Recurrence;

defined( 'ABSPATH' ) || exit;

/** How many dates the preview returns. Enough to see the shape, not a calendar. */
const PREVIEW_LIMIT = 8;

add_action( 'rest_api_init', __NAMESPACE__ . '\\register_routes' );

function register_routes(): void {
	register_rest_route(
		'producerkit/v1',
		'/recurrence/preview',
		[
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => __NAMESPACE__ . '\\preview',
			// Staff only: it is an editor aid, and it reveals nothing a
			// visitor has any use for.
			'permission_callback' => static fn (): bool => current_user_can( 'edit_posts' ),
			'args'                => [
				'rule'  => [
					'type'     => 'string',
					'required' => true,
				],
				'start' => [
					'type'        => 'string',
					'required'    => false,
					'description' => 'The event start, as Y-m-d\TH:i:s in site time. Defaults to now.',
				],
			],
		]
	);
}

/**
 * What does this rule mean, and is it one we can honour?
 */
function preview( \WP_REST_Request $request ): \WP_REST_Response {
	$rule = trim( (string) $request->get_param( 'rule' ) );

	if ( '' === $rule ) {
		return new \WP_REST_Response(
			[
				'valid'   => true,
				'message' => '',
				'dates'   => [],
			]
		);
	}

	$valid = Recurrence\validate( $rule );

	if ( is_wp_error( $valid ) ) {
		// 200 rather than 400: an invalid rule is an expected answer to the
		// question the editor is asking, not a failed request. A 400 would
		// have the editor's fetch treat it as a network problem and show
		// nothing, which is the silence this route exists to end.
		return new \WP_REST_Response(
			[
				'valid'   => false,
				'code'    => $valid->get_error_code(),
				'message' => $valid->get_error_message(),
				'dates'   => [],
			]
		);
	}

	$start = parse_start( (string) $request->get_param( 'start' ) );

	$dates = Recurrence\expand( $rule, $start );

	if ( is_wp_error( $dates ) ) {
		return new \WP_REST_Response(
			[
				'valid'   => false,
				'code'    => $dates->get_error_code(),
				'message' => $dates->get_error_message(),
				'dates'   => [],
			]
		);
	}

	$out = [];

	foreach ( array_slice( $dates, 0, PREVIEW_LIMIT ) as $date ) {
		$out[] = [
			'iso'   => $date->format( 'Y-m-d\TH:i:s' ),
			// Formatted server-side with the site's own date format, so the
			// preview reads the way every other date in the admin does.
			'label' => wp_date( (string) get_option( 'date_format' ), $date->getTimestamp() ),
		];
	}

	return new \WP_REST_Response(
		[
			'valid'   => true,
			'message' => '',
			'dates'   => $out,
			'total'   => count( $dates ),
			'more'    => count( $dates ) > PREVIEW_LIMIT,
		]
	);
}

/**
 * Read the start the editor sent, falling back to now.
 *
 * A malformed or missing start is not worth an error: the producer is most
 * likely mid-way through filling the form, and previewing from today still
 * shows them the shape of the rule.
 */
function parse_start( string $raw ): \DateTimeImmutable {
	$raw = trim( $raw );

	if ( '' !== $raw ) {
		try {
			$parsed = new \DateTimeImmutable( $raw, wp_timezone() );
		} catch ( \Exception ) {
			$parsed = null;
		}

		if ( null !== $parsed ) {
			return $parsed;
		}
	}

	return new \DateTimeImmutable( 'now', wp_timezone() );
}
