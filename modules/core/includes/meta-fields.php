<?php
/**
 * Meta field registration for all plugin CPTs.
 *
 * Every field is registered with show_in_rest so it's available
 * to Gutenberg and the REST API out of the box.
 */

declare(strict_types=1);

namespace Leftfield\Core\Meta_Fields;

defined('ABSPATH') || exit;

function register(): void {
    register_product_meta();
    register_source_meta();
    register_location_meta();
    register_event_meta();
}

/* ───────────────────────────────────────────────
 * Product meta
 * ─────────────────────────────────────────────── */
function register_product_meta(): void {
    $fields = [
        '_lfuf_source_ids' => [
            'type'         => 'array',
            'description'  => 'Related source (grain/farm) post IDs.',
            'default'      => [],
            'items'        => ['type' => 'integer'],
        ],
        '_lfuf_unit' => [
            'type'         => 'string',
            'description'  => 'Unit of sale — bunch, loaf, pint, lb, each, etc.',
            'default'      => '',
        ],
        '_lfuf_price' => [
            'type'         => 'string',
            'description'  => 'Display price (free-text to allow "donation" or "$5/loaf").',
            'default'      => '',
        ],
        '_lfuf_growing_notes' => [
            'type'         => 'string',
            'description'  => 'Brief growing / baking notes shown on front end.',
            'default'      => '',
        ],
    ];

    foreach ($fields as $key => $args) {
        register_post_meta('lfuf_product', $key, [
            'show_in_rest'      => is_array($args['default'])
                ? ['schema' => ['type' => 'array', 'items' => $args['items']]]
                : true,
            'single'            => true,
            'type'              => $args['type'],
            'description'       => $args['description'],
            'default'           => $args['default'],
            'sanitize_callback' => $args['type'] === 'array' ? __NAMESPACE__ . '\\sanitize_int_array' : 'sanitize_text_field',
            'auth_callback'     => fn () => current_user_can('edit_posts'),
        ]);
    }
}

/* ───────────────────────────────────────────────
 * Source meta
 * ─────────────────────────────────────────────── */
function register_source_meta(): void {
    $fields = [
        '_lfuf_source_farm_name' => [
            'type'        => 'string',
            'description' => 'Name of the partner farm or grain origin.',
            'default'     => '',
        ],
        '_lfuf_source_location' => [
            'type'        => 'string',
            'description' => 'Geographic location of source (county, state).',
            'default'     => '',
        ],
        '_lfuf_source_history' => [
            'type'        => 'string',
            'description' => 'Historical / heritage notes about the grain or ingredient.',
            'default'     => '',
        ],
        '_lfuf_milling_notes' => [
            'type'        => 'string',
            'description' => 'Notes on milling process, grind, etc.',
            'default'     => '',
        ],
    ];

    foreach ($fields as $key => $args) {
        register_post_meta('lfuf_source', $key, [
            'show_in_rest'      => true,
            'single'            => true,
            'type'              => $args['type'],
            'description'       => $args['description'],
            'default'           => $args['default'],
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => fn () => current_user_can('edit_posts'),
        ]);
    }
}

/* ───────────────────────────────────────────────
 * Location meta
 * ─────────────────────────────────────────────── */
function register_location_meta(): void {
    $fields = [
        '_lfuf_address' => [
            'type'        => 'string',
            'description' => 'Street address.',
            'default'     => '',
        ],
        '_lfuf_location_type' => [
            'type'        => 'string',
            'description' => 'Type: stand, market, on-farm, other.',
            'default'     => 'stand',
        ],
        '_lfuf_venmo_handle' => [
            'type'        => 'string',
            'description' => 'Venmo handle for payment at this location.',
            'default'     => '',
        ],
        '_lfuf_hours' => [
            'type'        => 'string',
            'description' => 'Human-readable hours string.',
            'default'     => '',
        ],
        '_lfuf_is_open' => [
            'type'        => 'boolean',
            'description' => 'Quick toggle — is this location currently open?',
            'default'     => false,
        ],
        '_lfuf_lat' => [
            'type'        => 'number',
            'description' => 'Latitude.',
            'default'     => 0,
        ],
        '_lfuf_lng' => [
            'type'        => 'number',
            'description' => 'Longitude.',
            'default'     => 0,
        ],
    ];

    foreach ($fields as $key => $args) {
        register_post_meta('lfuf_location', $key, [
            'show_in_rest'      => true,
            'single'            => true,
            'type'              => $args['type'],
            'description'       => $args['description'],
            'default'           => $args['default'],
            'sanitize_callback' => match ($args['type']) {
                'boolean' => 'rest_sanitize_boolean',
                'number'  => fn ($v) => (float) $v,
                default   => 'sanitize_text_field',
            },
            'auth_callback'     => fn () => current_user_can('edit_posts'),
        ]);
    }
}

/* ───────────────────────────────────────────────
 * Event meta
 * ─────────────────────────────────────────────── */
function register_event_meta(): void {
    $fields = [
        '_lfuf_event_location_id' => [
            'type'        => 'integer',
            'description' => 'Related lfuf_location post ID.',
            'default'     => 0,
        ],
        '_lfuf_featured_product_ids' => [
            'type'        => 'array',
            'description' => 'Featured product post IDs for this event.',
            'default'     => [],
            'items'       => ['type' => 'integer'],
        ],
        '_lfuf_start_datetime' => [
            'type'        => 'string',
            'description' => 'ISO 8601 start date/time.',
            'default'     => '',
        ],
        '_lfuf_end_datetime' => [
            'type'        => 'string',
            'description' => 'ISO 8601 end date/time.',
            'default'     => '',
        ],
        '_lfuf_recurrence_rule' => [
            'type'        => 'string',
            'description' => 'iCal RRULE string for recurring events.',
            'default'     => '',
        ],
        '_lfuf_rsvp_cap' => [
            'type'        => 'integer',
            'description' => 'Maximum attendees (0 = unlimited).',
            'default'     => 0,
        ],
        '_lfuf_donation_link' => [
            'type'        => 'string',
            'description' => 'Venmo deeplink or external donation URL.',
            'default'     => '',
        ],
    ];

    foreach ($fields as $key => $args) {
        $rest_schema = match (true) {
            $args['type'] === 'array' => ['schema' => ['type' => 'array', 'items' => $args['items']]],
            default                   => true,
        };

        $sanitize = match ($args['type']) {
            'integer' => fn ($v) => (int) $v,
            'array'   => __NAMESPACE__ . '\\sanitize_int_array',
            default   => 'sanitize_text_field',
        };

        register_post_meta('lfuf_event', $key, [
            'show_in_rest'      => $rest_schema,
            'single'            => true,
            'type'              => $args['type'],
            'description'       => $args['description'],
            'default'           => $args['default'],
            'sanitize_callback' => $sanitize,
            'auth_callback'     => fn () => current_user_can('edit_posts'),
        ]);
    }
}

/* ───────────────────────────────────────────────
 * Shared sanitizers
 * ─────────────────────────────────────────────── */
function sanitize_int_array(mixed $value): array {
    if (! is_array($value)) {
        return [];
    }
    return array_values(array_map('intval', $value));
}
