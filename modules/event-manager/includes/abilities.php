<?php
/**
 * Abilities API registration for the Event Manager module.
 */

declare(strict_types=1);

namespace Leftfield\EventManager\Abilities;

defined('ABSPATH') || exit;

add_action('wp_abilities_api_categories_init', function (): void {
    if (! function_exists('wp_register_ability_category')) {
        return;
    }

    wp_register_ability_category('farm-events', [
        'label'       => __('Farm Events', 'farm-stand-manager'),
        'description' => __('Abilities for farm events — pizza nights, potlucks, workshops, and tours.', 'farm-stand-manager'),
    ]);
});

add_action('wp_abilities_api_init', function (): void {
    if (! function_exists('wp_register_ability')) {
        return;
    }

    wp_register_ability('farm-stand-manager/list-upcoming-events', [
        'label'       => __('List Upcoming Events', 'farm-stand-manager'),
        'description' => __('Retrieve upcoming farm events with location, RSVP status, and event type.', 'farm-stand-manager'),
        'category'    => 'farm-events',
        'execute_callback' => function (array $input = []): array {
            $request = new \WP_REST_Request('GET', '/lfuf/v1/events/upcoming');
            $request->set_param('per_page', (int) ($input['per_page'] ?? 10));
            $request->set_param('event_type', $input['event_type'] ?? '');

            $response = \Leftfield\EventManager\REST\get_upcoming_events($request);
            return $response->get_data();
        },
        'input_schema' => [
            'type'       => 'object',
            'properties' => [
                'per_page'   => ['type' => 'integer', 'description' => 'Max events to return (default 10).'],
                'event_type' => ['type' => 'string',  'description' => 'Filter by event type slug.'],
            ],
            'default'    => [],
        ],
        'output_schema' => [
            'type'  => 'array',
            'items' => [
                'type'       => 'object',
                'properties' => [
                    'id'         => ['type' => 'integer'],
                    'title'      => ['type' => 'string'],
                    'start'      => ['type' => 'string'],
                    'end'        => ['type' => 'string'],
                    'cancelled'  => ['type' => 'boolean'],
                    'permalink'  => ['type' => 'string'],
                ],
            ],
        ],
        'permission_callback' => '__return_true',
        'meta' => [
            'show_in_rest' => true,
            'annotations'  => ['readonly' => true],
        ],
    ]);

    wp_register_ability('farm-stand-manager/rsvp-to-event', [
        'label'       => __('RSVP to Event', 'farm-stand-manager'),
        'description' => __('Submit an RSVP to a farm event. Returns a cancellation token.', 'farm-stand-manager'),
        'category'    => 'farm-events',
        'execute_callback' => function (array $input): array {
            $result = \Leftfield\EventManager\RSVP\add_rsvp($input);
            if (is_wp_error($result)) {
                return ['success' => false, 'message' => $result->get_error_message()];
            }
            return [
                'success' => true,
                'rsvp_id' => $result['id'],
                'token'   => $result['token'],
            ];
        },
        'input_schema' => [
            'type'       => 'object',
            'properties' => [
                'event_id'   => ['type' => 'integer', 'description' => 'Event post ID.'],
                'name'       => ['type' => 'string',  'description' => 'Attendee name.', 'minLength' => 1],
                'email'      => ['type' => 'string',  'description' => 'Optional email.'],
                'party_size' => ['type' => 'integer', 'description' => 'Number of people (default 1).'],
                'note'       => ['type' => 'string',  'description' => 'Optional note.'],
            ],
            'required' => ['event_id', 'name'],
        ],
        'output_schema' => [
            'type'       => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'rsvp_id' => ['type' => 'integer'],
                'token'   => ['type' => 'string'],
                'message' => ['type' => 'string'],
            ],
        ],
        'permission_callback' => '__return_true',
        'meta' => [
            'show_in_rest' => true,
            'annotations'  => ['idempotent' => false],
        ],
    ]);
});
