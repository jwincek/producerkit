<?php
/**
 * Stand-status REST extensions.
 *
 * Adds a richer toggle endpoint and a public summary endpoint
 * that front-end blocks can poll without authentication.
 */

declare(strict_types=1);

namespace Leftfield\StandStatus\REST;

defined('ABSPATH') || exit;

add_action('rest_api_init', __NAMESPACE__ . '\\register_routes');

function register_routes(): void {
    $ns = 'lfuf/v1';

    /**
     * PATCH /lfuf/v1/stand/{id}/status
     *
     * Enhanced toggle: sets is_open, status_message, and records timestamp.
     * Designed to be called from the WP mobile app or admin bar.
     */
    register_rest_route($ns, '/stand/(?P<id>\d+)/status', [
        'methods'             => \WP_REST_Server::EDITABLE,
        'callback'            => __NAMESPACE__ . '\\update_stand_status',
        'permission_callback' => fn () => current_user_can('edit_posts'),
        'args'                => [
            'id' => [
                'type'              => 'integer',
                'required'          => true,
                'sanitize_callback' => 'absint',
            ],
            'is_open' => [
                'type'     => 'boolean',
                'required' => true,
            ],
            'status_message' => [
                'type'    => 'string',
                'default' => '',
            ],
        ],
    ]);

    /**
     * GET /lfuf/v1/stand/{id}/info
     *
     * Public, cacheable summary for front-end blocks.
     * Returns everything a visitor needs: open/closed, message, hours,
     * address, Venmo, schedule, season dates.
     */
    register_rest_route($ns, '/stand/(?P<id>\d+)/info', [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => __NAMESPACE__ . '\\get_stand_info',
        'permission_callback' => '__return_true',
        'args'                => [
            'id' => [
                'type'              => 'integer',
                'required'          => true,
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);

    /**
     * GET /lfuf/v1/stands
     *
     * Public list of all stand-type locations with their current status.
     */
    register_rest_route($ns, '/stands', [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => __NAMESPACE__ . '\\list_stands',
        'permission_callback' => '__return_true',
    ]);
}

/* ───────────────────────────────────────────────
 * Callbacks
 * ─────────────────────────────────────────────── */

function update_stand_status(\WP_REST_Request $request): \WP_REST_Response {
    $location_id    = $request->get_param('id');
    $is_open        = $request->get_param('is_open');
    $status_message = $request->get_param('status_message');

    $post = get_post($location_id);
    if (! $post || $post->post_type !== 'lfuf_location') {
        return new \WP_REST_Response(['message' => 'Location not found.'], 404);
    }

    // Update core meta.
    update_post_meta($location_id, '_lfuf_is_open', $is_open);

    // Update stand-status-specific meta.
    update_post_meta($location_id, '_lfuf_ss_status_message', sanitize_text_field($status_message));
    update_post_meta($location_id, '_lfuf_ss_last_toggled', gmdate('c'));

    /**
     * Fires after the stand status is toggled.
     *
     * Feature plugins can hook here for notifications, logging, etc.
     *
     * @param int    $location_id
     * @param bool   $is_open
     * @param string $status_message
     */
    do_action('lfuf_stand_status_changed', $location_id, $is_open, $status_message);

    return new \WP_REST_Response([
        'id'             => $location_id,
        'is_open'        => $is_open,
        'status_message' => $status_message,
        'last_toggled'   => get_post_meta($location_id, '_lfuf_ss_last_toggled', true),
    ], 200);
}

function get_stand_info(\WP_REST_Request $request): \WP_REST_Response {
    $location_id = $request->get_param('id');

    $post = get_post($location_id);
    if (! $post || $post->post_type !== 'lfuf_location' || $post->post_status !== 'publish') {
        return new \WP_REST_Response(['message' => 'Stand not found.'], 404);
    }

    return new \WP_REST_Response(build_stand_data($post), 200);
}

function list_stands(\WP_REST_Request $request): \WP_REST_Response {
    $locations = get_posts([
        'post_type'   => 'lfuf_location',
        'post_status' => 'publish',
        'numberposts' => 50,
        'meta_query'  => [
            [
                'key'   => '_lfuf_location_type',
                'value' => 'stand',
            ],
        ],
    ]);

    $result = array_map(fn (\WP_Post $p) => build_stand_data($p), $locations);

    return new \WP_REST_Response($result, 200);
}

/* ───────────────────────────────────────────────
 * Helpers
 * ─────────────────────────────────────────────── */

function build_stand_data(\WP_Post $post): array {
    $id = $post->ID;

    $is_open     = (bool) get_post_meta($id, '_lfuf_is_open', true);
    $schedule    = get_post_meta($id, '_lfuf_ss_schedule', true);
    $auto_toggle = (bool) get_post_meta($id, '_lfuf_ss_auto_toggle', true);

    // If auto-toggle is enabled, compute status from schedule.
    if ($auto_toggle && $schedule) {
        $is_open = compute_schedule_status($schedule);
    }

    // Season boundary check.
    $season_start = get_post_meta($id, '_lfuf_ss_season_start', true);
    $season_end   = get_post_meta($id, '_lfuf_ss_season_end', true);
    $in_season    = is_in_season($season_start, $season_end);

    if (! $in_season) {
        $is_open = false;
    }

    return [
        'id'              => $id,
        'name'            => $post->post_title,
        'is_open'         => $is_open,
        'in_season'       => $in_season,
        'status_message'  => get_post_meta($id, '_lfuf_ss_status_message', true),
        'last_toggled'    => get_post_meta($id, '_lfuf_ss_last_toggled', true),
        'address'         => get_post_meta($id, '_lfuf_address', true),
        'hours'           => get_post_meta($id, '_lfuf_hours', true),
        'schedule'        => $schedule ? json_decode($schedule, true) : null,
        'season_start'    => $season_start,
        'season_end'      => $season_end,
        'venmo_handle'    => get_post_meta($id, '_lfuf_venmo_handle', true),
        'payment_methods' => \Leftfield\Core\Payments\get_payment_methods($id),
        'lat'             => (float) get_post_meta($id, '_lfuf_lat', true),
        'lng'             => (float) get_post_meta($id, '_lfuf_lng', true),
    ];
}

/**
 * Determine open/closed from a JSON-encoded weekly schedule.
 *
 * Schedule format: [{ "day": 6, "open": "13:00", "close": "16:00" }, ...]
 * Days: 0 = Sunday, 6 = Saturday (matches PHP date('w')).
 */
function compute_schedule_status(string $schedule_json): bool {
    $schedule = json_decode($schedule_json, true);
    if (! is_array($schedule)) {
        return false;
    }

    $now     = current_datetime();
    $today   = (int) $now->format('w');
    $current = $now->format('H:i');

    foreach ($schedule as $entry) {
        if (
            isset($entry['day'], $entry['open'], $entry['close'])
            && (int) $entry['day'] === $today
            && $current >= $entry['open']
            && $current < $entry['close']
        ) {
            return true;
        }
    }

    return false;
}

/**
 * Check whether today falls within the season boundaries.
 */
function is_in_season(string $start, string $end): bool {
    if (empty($start) || empty($end)) {
        return true; // No bounds defined = always in season.
    }

    $today      = current_time('Y-m-d');
    $start_date = $start;
    $end_date   = $end;

    return $today >= $start_date && $today <= $end_date;
}

/**
 * Compute the next scheduled opening as a human-readable string.
 *
 * @param string $schedule_json JSON-encoded schedule array.
 * @return string e.g. "Saturday at 1:00 PM"
 */
function compute_next_open(string $schedule_json): string {
    $schedule = json_decode($schedule_json, true);
    if (! is_array($schedule) || empty($schedule)) {
        return '';
    }

    $now   = current_datetime();
    $today = (int) $now->format('w');
    $time  = $now->format('H:i');

    $day_names = [
        0 => __('Sunday', 'farm-stand-manager'),
        1 => __('Monday', 'farm-stand-manager'),
        2 => __('Tuesday', 'farm-stand-manager'),
        3 => __('Wednesday', 'farm-stand-manager'),
        4 => __('Thursday', 'farm-stand-manager'),
        5 => __('Friday', 'farm-stand-manager'),
        6 => __('Saturday', 'farm-stand-manager'),
    ];

    $candidates = [];
    foreach ($schedule as $entry) {
        $day  = (int) ($entry['day'] ?? -1);
        $open = $entry['open'] ?? '';
        if ($day < 0 || $day > 6 || ! $open) {
            continue;
        }

        $delta = $day - $today;
        if ($delta < 0) {
            $delta += 7;
        }
        if ($delta === 0 && $time >= ($entry['close'] ?? '23:59')) {
            $delta = 7;
        }

        $candidates[] = [
            'delta' => $delta,
            'day'   => $day,
            'open'  => $open,
        ];
    }

    if (empty($candidates)) {
        return '';
    }

    usort($candidates, fn ($a, $b) => $a['delta'] <=> $b['delta']);
    $next = $candidates[0];

    $formatted_time = date_i18n('g:i A', strtotime('2000-01-01 ' . $next['open']));

    if ($next['delta'] === 0) {
        /* translators: %s: opening time (e.g. "9:00 AM"). */
        return sprintf(__('Today at %s', 'farm-stand-manager'), $formatted_time);
    }
    if ($next['delta'] === 1) {
        /* translators: %s: opening time (e.g. "9:00 AM"). */
        return sprintf(__('Tomorrow at %s', 'farm-stand-manager'), $formatted_time);
    }

    return sprintf('%s at %s', $day_names[$next['day']], $formatted_time);
}
