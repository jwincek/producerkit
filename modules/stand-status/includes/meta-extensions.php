<?php
/**
 * Stand-status-specific meta extensions on lfuf_location.
 *
 * These extend the core location meta without duplicating it.
 * Namespaced with _lfuf_ss_ to stay out of core's way.
 */

declare(strict_types=1);

namespace ProducerKit\StandStatus\Meta;

defined( 'ABSPATH' ) || exit;

add_action( 'init', __NAMESPACE__ . '\\register' );

function register(): void {
	$fields = [
		'_lfuf_ss_status_message' => [
			'type'        => 'string',
			'description' => 'Custom status message shown alongside the badge, e.g. "Back at 2 PM" or "Sold out for today".',
			'default'     => '',
		],
		'_lfuf_ss_last_toggled'   => [
			'type'        => 'string',
			'description' => 'ISO 8601 timestamp of the last open/closed toggle.',
			'default'     => '',
		],
		'_lfuf_ss_schedule'       => [
			'type'        => 'string',
			'description' => 'JSON-encoded weekly schedule array. Each entry: { day: 0-6, open: "HH:MM", close: "HH:MM" }.',
			'default'     => '',
		],
		'_lfuf_ss_season_start'   => [
			'type'        => 'string',
			'description' => 'Season opening date (YYYY-MM-DD).',
			'default'     => '',
		],
		'_lfuf_ss_season_end'     => [
			'type'        => 'string',
			'description' => 'Season closing date (YYYY-MM-DD).',
			'default'     => '',
		],
		'_lfuf_ss_auto_toggle'    => [
			'type'        => 'boolean',
			'description' => 'Whether to auto-toggle open/closed based on the weekly schedule.',
			'default'     => false,
		],
	];

	foreach ( $fields as $key => $args ) {
		register_post_meta(
			'lfuf_location',
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
