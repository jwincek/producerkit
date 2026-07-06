<?php
/**
 * Custom table: {prefix}_lfuf_rsvps
 *
 * Lightweight RSVP/headcount tracking with security hardening:
 *   - Rate limiting per IP (transient-based)
 *   - Duplicate detection by name + event
 *   - Honeypot field check
 *   - Server-side party size cap
 *   - Atomic cap enforcement via SELECT FOR UPDATE
 */

declare(strict_types=1);

namespace Leftfield\EventManager\RSVP;

defined('ABSPATH') || exit;

/** Max RSVPs from one IP per event per hour. */
const RATE_LIMIT_PER_IP = 5;

/** Max party size allowed server-side. */
const MAX_PARTY_SIZE = 10;

add_action('plugins_loaded', function (): void {
    if (get_option('lfuf_rsvp_db_version') !== '1.1.0') {
        create_table();
    }
}, 20);

function table_name(): string {
    global $wpdb;
    return $wpdb->prefix . 'lfuf_rsvps';
}

function create_table(): void {
    global $wpdb;

    $table   = table_name();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        event_id    BIGINT UNSIGNED NOT NULL,
        name        VARCHAR(200)    NOT NULL,
        email       VARCHAR(200)    NOT NULL DEFAULT '',
        party_size  SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        note        VARCHAR(500)    NOT NULL DEFAULT '',
        ip_hash     VARCHAR(64)     NOT NULL DEFAULT '',
        token       VARCHAR(64)     NOT NULL,
        created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_event (event_id),
        KEY idx_ip_event (ip_hash, event_id),
        UNIQUE KEY idx_token (token)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    update_option('lfuf_rsvp_db_version', '1.1.0');
}

/**
 * Hash an IP address for storage (privacy-preserving).
 */
function hash_ip(string $ip): string {
    // Salted hash so the IP can't be reversed from the DB alone.
    return hash('sha256', $ip . wp_salt('auth'));
}

/**
 * Get the client IP (best effort behind proxies).
 */
function get_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    // Trust X-Forwarded-For only if behind a known proxy.
    // For a small farm site, REMOTE_ADDR is sufficient.
    return sanitize_text_field($ip);
}

/**
 * Add an RSVP to an event with full validation and rate limiting.
 *
 * @param array{
 *     event_id:    int,
 *     name:        string,
 *     email?:      string,
 *     party_size?: int,
 *     note?:       string,
 *     honeypot?:   string,
 * } $data
 * @return array|\WP_Error
 */
function add_rsvp(array $data): array|\WP_Error {
    global $wpdb;

    // ── Honeypot check ──
    if (! empty($data['honeypot'] ?? '')) {
        // Bots fill this hidden field. Silently reject with a fake success
        // so the bot doesn't know it was caught.
        return [
            'id'         => 0,
            'name'       => sanitize_text_field($data['name'] ?? ''),
            'party_size' => 1,
            'token'      => wp_generate_password(32, false),
        ];
    }

    $event_id = (int) ($data['event_id'] ?? 0);
    $event    = get_post($event_id);

    if (! $event || $event->post_type !== 'lfuf_event' || $event->post_status !== 'publish') {
        return new \WP_Error('invalid_event', __('Event not found.', 'farm-stand-manager'));
    }

    // Check if cancelled.
    if ((bool) get_post_meta($event_id, '_lfuf_em_cancelled', true)) {
        return new \WP_Error('event_cancelled', __('This event has been cancelled.', 'farm-stand-manager'));
    }

    // Check if RSVPs are enabled.
    if (! (bool) get_post_meta($event_id, '_lfuf_em_rsvp_enabled', true)) {
        return new \WP_Error('rsvp_disabled', __('RSVPs are not enabled for this event.', 'farm-stand-manager'));
    }

    // Check if manually closed.
    if ((bool) get_post_meta($event_id, '_lfuf_em_rsvp_closed', true)) {
        return new \WP_Error('rsvp_closed', __('RSVPs are closed for this event.', 'farm-stand-manager'));
    }

    $name = sanitize_text_field($data['name'] ?? '');
    if (empty($name)) {
        return new \WP_Error('name_required', __('Please provide your name.', 'farm-stand-manager'));
    }

    // ── Server-side party size cap ──
    $party_size = max(1, min(MAX_PARTY_SIZE, (int) ($data['party_size'] ?? 1)));

    // ── Rate limiting by IP ──
    $client_ip = get_client_ip();
    $ip_hashed = hash_ip($client_ip);
    $rate_key  = 'lfuf_rsvp_rate_' . md5($ip_hashed . '_' . $event_id);

    $recent_count = (int) get_transient($rate_key);
    if ($recent_count >= RATE_LIMIT_PER_IP) {
        return new \WP_Error(
            'rate_limited',
            __('Too many RSVPs from this connection. Please try again later.', 'farm-stand-manager'),
        );
    }

    // ── Duplicate detection ──
    $table = table_name();
    $normalized_name = mb_strtolower(trim($name));

    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table}
         WHERE event_id = %d AND LOWER(TRIM(name)) = %s
         LIMIT 1",
        $event_id,
        $normalized_name,
    ));

    if ($existing) {
        return new \WP_Error(
            'duplicate_rsvp',
            __('It looks like you\'ve already RSVP\'d to this event!', 'farm-stand-manager'),
        );
    }

    // ── Atomic cap enforcement ──
    // Use a transaction to prevent race conditions.
    $cap = (int) get_post_meta($event_id, '_lfuf_rsvp_cap', true);
    if ($cap > 0) {
        $wpdb->query('START TRANSACTION');

        // Lock the rows for this event to prevent concurrent inserts.
        $current_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(party_size), 0)
             FROM {$table}
             WHERE event_id = %d
             FOR UPDATE",
            $event_id,
        ));

        if ($current_count + $party_size > $cap) {
            $wpdb->query('ROLLBACK');
            return new \WP_Error(
                'rsvp_full',
                __('Sorry, this event is at capacity.', 'farm-stand-manager'),
            );
        }
    }

    // ── Insert ──
    $token = wp_generate_password(32, false);

    $row = [
        'event_id'   => $event_id,
        'name'       => $name,
        'email'      => sanitize_email($data['email'] ?? ''),
        'party_size' => $party_size,
        'note'       => sanitize_text_field($data['note'] ?? ''),
        'ip_hash'    => $ip_hashed,
        'token'      => $token,
    ];

    $inserted = $wpdb->insert($table, $row, ['%d', '%s', '%s', '%d', '%s', '%s', '%s']);

    if ($cap > 0) {
        if ($inserted) {
            $wpdb->query('COMMIT');
        } else {
            $wpdb->query('ROLLBACK');
            return new \WP_Error('db_error', __('Could not save RSVP.', 'farm-stand-manager'));
        }
    }

    if (! $wpdb->insert_id) {
        return new \WP_Error('db_error', __('Could not save RSVP.', 'farm-stand-manager'));
    }

    // Increment rate limit counter.
    set_transient($rate_key, $recent_count + 1, HOUR_IN_SECONDS);

    $row['id'] = (int) $wpdb->insert_id;

    /**
     * Fires after a new RSVP is added.
     *
     * @param array $row      The RSVP data including id and token.
     * @param int   $event_id
     */
    do_action('lfuf_rsvp_added', $row, $event_id);

    return $row;
}

/**
 * Cancel an RSVP by its token.
 */
function cancel_rsvp(string $token): bool {
    global $wpdb;

    $table = table_name();
    $rsvp  = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE token = %s LIMIT 1",
        $token,
    ));

    if (! $rsvp) {
        return false;
    }

    $deleted = (bool) $wpdb->delete($table, ['token' => $token], ['%s']);

    if ($deleted) {
        do_action('lfuf_rsvp_cancelled', (array) $rsvp, (int) $rsvp->event_id);
    }

    return $deleted;
}

/**
 * Get the total headcount (sum of party_size) for an event.
 */
function get_headcount(int $event_id): int {
    global $wpdb;
    $table = table_name();
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(party_size), 0) FROM {$table} WHERE event_id = %d",
        $event_id,
    ));
}

/**
 * Get the RSVP count (number of rows) for an event.
 */
function get_rsvp_count(int $event_id): int {
    global $wpdb;
    $table = table_name();
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE event_id = %d",
        $event_id,
    ));
}

/**
 * Get all RSVPs for an event (admin view).
 *
 * @return object[]
 */
function get_event_rsvps(int $event_id): array {
    global $wpdb;
    $table = table_name();
    return $wpdb->get_results($wpdb->prepare(
        "SELECT id, name, email, party_size, note, created_at
         FROM {$table}
         WHERE event_id = %d
         ORDER BY created_at ASC",
        $event_id,
    ));
}

/**
 * Get RSVP summary for an event (public-safe).
 */
function get_event_rsvp_summary(int $event_id): array {
    $cap        = (int) get_post_meta($event_id, '_lfuf_rsvp_cap', true);
    $headcount  = get_headcount($event_id);
    $rsvp_count = get_rsvp_count($event_id);
    $enabled    = (bool) get_post_meta($event_id, '_lfuf_em_rsvp_enabled', true);
    $closed     = (bool) get_post_meta($event_id, '_lfuf_em_rsvp_closed', true);

    return [
        'enabled'    => $enabled,
        'closed'     => $closed,
        'headcount'  => $headcount,
        'rsvp_count' => $rsvp_count,
        'cap'        => $cap,
        'spots_left' => $cap > 0 ? max(0, $cap - $headcount) : null,
        'is_full'    => $cap > 0 && $headcount >= $cap,
    ];
}