<?php
/**
 * Custom table: {prefix}_lfuf_availability
 *
 * This is the shared, time-sensitive status layer. Each row represents:
 *   "Product X is [status] at Location Y as of [date]."
 *
 * Feature plugins (availability board, stand widget, pre-order builder)
 * all read/write this same table instead of maintaining their own state.
 */

declare(strict_types=1);

namespace Leftfield\Core\Availability;

defined('ABSPATH') || exit;

/**
 * Return the full table name with WP prefix.
 */
function table_name(): string {
    global $wpdb;
    return $wpdb->prefix . 'lfuf_availability';
}

/**
 * Create or update the table schema. Safe to call on every activation.
 */
function create_table(): void {
    global $wpdb;

    $table   = table_name();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        product_id      BIGINT UNSIGNED NOT NULL,
        location_id     BIGINT UNSIGNED NOT NULL DEFAULT 0,
        status          VARCHAR(20)     NOT NULL DEFAULT 'available',
        quantity_note   VARCHAR(255)    NOT NULL DEFAULT '',
        effective_date  DATE            NOT NULL,
        expires_date    DATE            DEFAULT NULL,
        notes           TEXT            NOT NULL DEFAULT '',
        created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_product_location (product_id, location_id),
        KEY idx_effective (effective_date),
        KEY idx_status (status)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    update_option('lfuf_availability_db_version', '1.0.0');
}

/**
 * Valid status values — used for validation in REST and admin.
 *
 * @return string[]
 */
function valid_statuses(): array {
    return ['abundant', 'available', 'limited', 'sold_out', 'unavailable'];
}

/* ───────────────────────────────────────────────
 * CRUD helpers
 * ─────────────────────────────────────────────── */

/**
 * Upsert an availability record.
 *
 * If a row already exists for the same product + location + effective_date,
 * it will be updated. Otherwise a new row is inserted.
 *
 * @param array{
 *     product_id:     int,
 *     location_id?:   int,
 *     status:         string,
 *     quantity_note?: string,
 *     effective_date: string,
 *     expires_date?:  string|null,
 *     notes?:         string,
 * } $data
 * @return int|false  Row ID on success, false on failure.
 */
function upsert(array $data): int|false {
    global $wpdb;

    $table = table_name();

    if (! in_array($data['status'] ?? '', valid_statuses(), true)) {
        return false;
    }

    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table}
         WHERE product_id = %d AND location_id = %d AND effective_date = %s
         LIMIT 1",
        (int) $data['product_id'],
        (int) ($data['location_id'] ?? 0),
        $data['effective_date'],
    ));

    $row = [
        'product_id'     => (int) $data['product_id'],
        'location_id'    => (int) ($data['location_id'] ?? 0),
        'status'         => $data['status'],
        'quantity_note'  => sanitize_text_field($data['quantity_note'] ?? ''),
        'effective_date' => $data['effective_date'],
        'expires_date'   => $data['expires_date'] ?? null,
        'notes'          => sanitize_textarea_field($data['notes'] ?? ''),
    ];

    $formats = ['%d', '%d', '%s', '%s', '%s', '%s', '%s'];

    if ($existing) {
        $wpdb->update($table, $row, ['id' => (int) $existing], $formats, ['%d']);
        return (int) $existing;
    }

    $wpdb->insert($table, $row, $formats);
    return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
}

/**
 * Get current availability for a product, optionally filtered by location.
 *
 * @return object[]
 */
function get_current(int $product_id, int $location_id = 0): array {
    global $wpdb;

    $table = table_name();
    $today = current_time('Y-m-d');

    $where = $wpdb->prepare(
        "product_id = %d AND effective_date <= %s AND (expires_date IS NULL OR expires_date >= %s)",
        $product_id,
        $today,
        $today,
    );

    if ($location_id > 0) {
        $where .= $wpdb->prepare(" AND location_id = %d", $location_id);
    }

    return $wpdb->get_results("SELECT * FROM {$table} WHERE {$where} ORDER BY effective_date DESC");
}

/**
 * Get all current availability rows (for the board / widget).
 *
 * @return object[]
 */
function get_all_current(): array {
    global $wpdb;

    $table = table_name();
    $today = current_time('Y-m-d');

    return $wpdb->get_results($wpdb->prepare(
        "SELECT a.*, p.post_title AS product_name
         FROM {$table} a
         INNER JOIN {$wpdb->posts} p ON p.ID = a.product_id AND p.post_status = 'publish'
         WHERE a.effective_date <= %s
           AND (a.expires_date IS NULL OR a.expires_date >= %s)
         ORDER BY a.status ASC, p.post_title ASC",
        $today,
        $today,
    ));
}

/**
 * Delete a single availability row.
 */
function delete_row(int $id): bool {
    global $wpdb;
    return (bool) $wpdb->delete(table_name(), ['id' => $id], ['%d']);
}

/**
 * Delete availability rows referencing a product or location that is
 * being permanently deleted, so the table never holds orphaned rows.
 *
 * Runs on before_delete_post (permanent delete only, not trash —
 * trashed posts can be restored, so their rows are kept).
 */
function on_post_delete(int $post_id, \WP_Post $post): void {
    global $wpdb;

    if ($post->post_type === 'lfuf_product') {
        $wpdb->delete(table_name(), ['product_id' => $post_id], ['%d']);
    } elseif ($post->post_type === 'lfuf_location') {
        // location_id = 0 means "all locations" — only exact matches are orphans.
        $wpdb->delete(table_name(), ['location_id' => $post_id], ['%d']);
    }
}

add_action('before_delete_post', __NAMESPACE__ . '\\on_post_delete', 10, 2);

/* ───────────────────────────────────────────────
 * Expiration cleanup (WP-Cron)
 * ─────────────────────────────────────────────── */

/**
 * Purge expired availability rows.
 *
 * Deletes any row where expires_date is in the past.
 * Called daily via WP-Cron (lfuf_availability_cleanup).
 *
 * @return int Number of rows deleted.
 */
function purge_expired(): int {
    global $wpdb;

    $table = table_name();
    $today = current_time('Y-m-d');

    $count = (int) $wpdb->query($wpdb->prepare(
        "DELETE FROM {$table} WHERE expires_date IS NOT NULL AND expires_date < %s",
        $today,
    ));

    if ($count > 0) {
        do_action('lfuf_availability_expired_purged', $count);
    }

    return $count;
}

add_action('lfuf_availability_cleanup', __NAMESPACE__ . '\\purge_expired');

/**
 * Schedule the daily cleanup cron event.
 * Safe to call multiple times — only schedules if not already scheduled.
 */
function schedule_cleanup(): void {
    if (! wp_next_scheduled('lfuf_availability_cleanup')) {
        wp_schedule_event(
            strtotime('tomorrow 03:00', current_time('timestamp')),
            'daily',
            'lfuf_availability_cleanup',
        );
    }
}

/**
 * Unschedule the cleanup cron event.
 */
function unschedule_cleanup(): void {
    $timestamp = wp_next_scheduled('lfuf_availability_cleanup');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'lfuf_availability_cleanup');
    }
}