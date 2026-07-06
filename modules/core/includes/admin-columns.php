<?php
/**
 * Custom admin columns for plugin CPT list tables.
 *
 * Adds sortable columns with key meta for:
 *   - Products: price, availability status
 *   - Events: start date, location, RSVP count
 *   - Locations: type, open/closed status
 *
 * Taxonomy columns (product type, season, event type) are handled
 * automatically by show_admin_column => true in taxonomy registration.
 */

declare(strict_types=1);

namespace Leftfield\Core\AdminColumns;

defined('ABSPATH') || exit;

/* ───────────────────────────────────────────────
 * Product columns
 * ─────────────────────────────────────────────── */

add_filter('manage_lfuf_product_posts_columns', function (array $columns): array {
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        // Insert after title.
        if ($key === 'title') {
            $new['lfuf_price']        = __('Price', 'farm-stand-manager');
            $new['lfuf_availability'] = __('Availability', 'farm-stand-manager');
        }
    }
    // Remove the default date column — not useful for products.
    unset($new['date']);
    return $new;
});

add_action('manage_lfuf_product_posts_custom_column', function (string $column, int $post_id): void {
    switch ($column) {
        case 'lfuf_price':
            $price = get_post_meta($post_id, '_lfuf_price', true);
            $unit  = get_post_meta($post_id, '_lfuf_unit', true);
            if ($price) {
                echo esc_html($price);
                if ($unit) {
                    echo '<span style="opacity:0.6"> / ' . esc_html($unit) . '</span>';
                }
            } else {
                echo '<span style="opacity:0.4">—</span>';
            }
            break;

        case 'lfuf_availability':
            $rows = \Leftfield\Core\Availability\get_current($post_id);
            if (! empty($rows)) {
                $row         = $rows[0];
                $status_text = ucfirst(str_replace('_', ' ', $row->status));
                $colors      = [
                    'abundant'    => '#065f46',
                    'available'   => '#1e40af',
                    'limited'     => '#92400e',
                    'sold_out'    => '#991b1b',
                    'unavailable' => '#6b7280',
                ];
                $bgs = [
                    'abundant'    => '#d1fae5',
                    'available'   => '#dbeafe',
                    'limited'     => '#fef3c7',
                    'sold_out'    => '#fee2e2',
                    'unavailable' => '#f3f4f6',
                ];
                $color = $colors[$row->status] ?? '#6b7280';
                $bg    = $bgs[$row->status] ?? '#f3f4f6';

                printf(
                    '<span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:12px;font-weight:600;background:%s;color:%s">%s</span>',
                    esc_attr($bg),
                    esc_attr($color),
                    esc_html($status_text),
                );
                if ($row->quantity_note) {
                    echo '<br><span style="font-size:12px;opacity:0.7">' . esc_html($row->quantity_note) . '</span>';
                }
            } else {
                echo '<span style="opacity:0.4">—</span>';
            }
            break;
    }
}, 10, 2);

// Make price column sortable.
add_filter('manage_edit-lfuf_product_sortable_columns', function (array $columns): array {
    $columns['lfuf_price'] = 'lfuf_price';
    return $columns;
});

add_action('pre_get_posts', function (\WP_Query $query): void {
    if (! is_admin() || ! $query->is_main_query()) {
        return;
    }
    if ($query->get('orderby') === 'lfuf_price') {
        $query->set('meta_key', '_lfuf_price');
        $query->set('orderby', 'meta_value');
    }
});

/* ───────────────────────────────────────────────
 * Event columns
 * ─────────────────────────────────────────────── */

add_filter('manage_lfuf_event_posts_columns', function (array $columns): array {
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ($key === 'title') {
            $new['lfuf_event_date']     = __('Event Date', 'farm-stand-manager');
            $new['lfuf_event_location'] = __('Location', 'farm-stand-manager');
            $new['lfuf_event_rsvp']     = __('RSVPs', 'farm-stand-manager');
        }
    }
    unset($new['date']);
    return $new;
});

add_action('manage_lfuf_event_posts_custom_column', function (string $column, int $post_id): void {
    switch ($column) {
        case 'lfuf_event_date':
            $start = get_post_meta($post_id, '_lfuf_start_datetime', true);
            $end   = get_post_meta($post_id, '_lfuf_end_datetime', true);

            if ($start) {
                $start_ts = strtotime($start);
                echo esc_html(date_i18n('M j, Y', $start_ts));
                echo '<br><span style="font-size:12px;opacity:0.7">';
                echo esc_html(date_i18n('g:i A', $start_ts));
                if ($end) {
                    echo ' – ' . esc_html(date_i18n('g:i A', strtotime($end)));
                }
                echo '</span>';

                // Past event indicator.
                if ($start_ts < current_time('timestamp')) {
                    echo '<br><span style="font-size:11px;color:#6b7280;font-style:italic">Past</span>';
                }
            } else {
                echo '<span style="opacity:0.4">—</span>';
            }

            // Cancelled badge.
            if ((bool) get_post_meta($post_id, '_lfuf_em_cancelled', true)) {
                echo '<br><span style="display:inline-block;padding:1px 6px;border-radius:3px;font-size:11px;font-weight:600;background:#fee2e2;color:#991b1b">Cancelled</span>';
            }
            break;

        case 'lfuf_event_location':
            $location_id = (int) get_post_meta($post_id, '_lfuf_event_location_id', true);
            if ($location_id > 0) {
                $location = get_post($location_id);
                if ($location) {
                    printf(
                        '<a href="%s">%s</a>',
                        esc_url(get_edit_post_link($location_id)),
                        esc_html($location->post_title),
                    );
                } else {
                    echo '<span style="opacity:0.4">—</span>';
                }
            } else {
                echo '<span style="opacity:0.4">—</span>';
            }
            break;

        case 'lfuf_event_rsvp':
            $enabled = (bool) get_post_meta($post_id, '_lfuf_em_rsvp_enabled', true);
            if (! $enabled) {
                echo '<span style="opacity:0.4">Off</span>';
                break;
            }

            if (function_exists('Leftfield\\EventManager\\RSVP\\get_headcount')) {
                $headcount = \Leftfield\EventManager\RSVP\get_headcount($post_id);
                $cap       = (int) get_post_meta($post_id, '_lfuf_rsvp_cap', true);
                $closed    = (bool) get_post_meta($post_id, '_lfuf_em_rsvp_closed', true);

                echo '<strong>' . (int) $headcount . '</strong>';
                if ($cap > 0) {
                    echo ' / ' . (int) $cap;
                }
                echo ' people';

                if ($closed) {
                    echo '<br><span style="font-size:11px;color:#92400e">Closed</span>';
                } elseif ($cap > 0 && $headcount >= $cap) {
                    echo '<br><span style="font-size:11px;color:#991b1b;font-weight:600">Full</span>';
                }
            } else {
                echo '<span style="opacity:0.4">—</span>';
            }
            break;
    }
}, 10, 2);

// Make event date sortable.
add_filter('manage_edit-lfuf_event_sortable_columns', function (array $columns): array {
    $columns['lfuf_event_date'] = 'lfuf_event_date';
    return $columns;
});

add_action('pre_get_posts', function (\WP_Query $query): void {
    if (! is_admin() || ! $query->is_main_query()) {
        return;
    }
    if ($query->get('orderby') === 'lfuf_event_date') {
        $query->set('meta_key', '_lfuf_start_datetime');
        $query->set('orderby', 'meta_value');
    }
});

// Default sort events by start date ascending.
add_action('pre_get_posts', function (\WP_Query $query): void {
    if (
        is_admin()
        && $query->is_main_query()
        && $query->get('post_type') === 'lfuf_event'
        && ! $query->get('orderby')
    ) {
        $query->set('meta_key', '_lfuf_start_datetime');
        $query->set('orderby', 'meta_value');
        $query->set('order', 'ASC');
    }
});

/* ───────────────────────────────────────────────
 * Location columns
 * ─────────────────────────────────────────────── */

add_filter('manage_lfuf_location_posts_columns', function (array $columns): array {
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ($key === 'title') {
            $new['lfuf_loc_type']   = __('Type', 'farm-stand-manager');
            $new['lfuf_loc_status'] = __('Status', 'farm-stand-manager');
            $new['lfuf_loc_address'] = __('Address', 'farm-stand-manager');
        }
    }
    unset($new['date']);
    return $new;
});

add_action('manage_lfuf_location_posts_custom_column', function (string $column, int $post_id): void {
    switch ($column) {
        case 'lfuf_loc_type':
            $type = get_post_meta($post_id, '_lfuf_location_type', true);
            echo $type ? esc_html(ucfirst($type)) : '<span style="opacity:0.4">—</span>';
            break;

        case 'lfuf_loc_status':
            $is_open = (bool) get_post_meta($post_id, '_lfuf_is_open', true);
            if ($is_open) {
                echo '<span style="display:inline-block;padding:2px 8px;border-radius:9999px;font-size:12px;font-weight:600;background:#d1fae5;color:#065f46">Open</span>';
            } else {
                echo '<span style="display:inline-block;padding:2px 8px;border-radius:9999px;font-size:12px;font-weight:600;background:#fee2e2;color:#991b1b">Closed</span>';
            }
            break;

        case 'lfuf_loc_address':
            $address = get_post_meta($post_id, '_lfuf_address', true);
            echo $address ? esc_html($address) : '<span style="opacity:0.4">—</span>';
            break;
    }
}, 10, 2);

// Make type column sortable.
add_filter('manage_edit-lfuf_location_sortable_columns', function (array $columns): array {
    $columns['lfuf_loc_type'] = 'lfuf_loc_type';
    return $columns;
});

add_action('pre_get_posts', function (\WP_Query $query): void {
    if (! is_admin() || ! $query->is_main_query()) {
        return;
    }
    if ($query->get('orderby') === 'lfuf_loc_type') {
        $query->set('meta_key', '_lfuf_location_type');
        $query->set('orderby', 'meta_value');
    }
});