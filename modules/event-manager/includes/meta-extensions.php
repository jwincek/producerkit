<?php
/**
 * Event Manager meta extensions on lfuf_event.
 *
 * Adds fields for RSVP configuration, display options,
 * and event-specific details beyond what core provides.
 * Namespaced with _lfuf_em_ to stay out of core's space.
 */

declare(strict_types=1);

namespace ProducerKit\EventManager\Meta;

defined( 'ABSPATH' ) || exit;

add_action( 'init', __NAMESPACE__ . '\\register' );

function register(): void {
	$fields = [
		'_lfuf_em_rsvp_enabled'  => [
			'type'        => 'boolean',
			'description' => 'Whether RSVP / headcount is enabled for this event.',
			'default'     => false,
		],
		'_lfuf_em_rsvp_label'    => [
			'type'        => 'string',
			'description' => 'Custom RSVP button label (e.g. "I\'m coming!", "Count me in").',
			'default'     => '',
		],
		'_lfuf_em_rsvp_closed'   => [
			'type'        => 'boolean',
			'description' => 'Manually close RSVPs (independent of cap).',
			'default'     => false,
		],
		'_lfuf_em_what_to_bring' => [
			'type'        => 'string',
			'description' => 'What to bring note (e.g. "a dish to share", "your own bowl").',
			'default'     => '',
		],
		'_lfuf_em_cost_note'     => [
			'type'        => 'string',
			'description' => 'Cost/donation note (e.g. "Donation-based", "$10 suggested").',
			'default'     => '',
		],
		'_lfuf_em_cancelled'     => [
			'type'        => 'boolean',
			'description' => 'Whether this event has been cancelled.',
			'default'     => false,
		],
	];

	foreach ( $fields as $key => $args ) {
		register_post_meta(
			'lfuf_event',
			$key,
			[
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => $args['type'],
				'description'       => $args['description'],
				'default'           => $args['default'],
				'sanitize_callback' => match ( $args['type'] ) {
					'boolean' => 'rest_sanitize_boolean',
					default   => 'sanitize_text_field',
				},
				'auth_callback'     => fn () => current_user_can( 'edit_posts' ),
			]
		);
	}
}
