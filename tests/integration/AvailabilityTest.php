<?php
/**
 * Availability table: upsert semantics, current-row queries, expiry
 * purge, and orphan cleanup when products/locations are deleted.
 */

declare(strict_types=1);

use function Leftfield\Core\Availability\get_all_current;
use function Leftfield\Core\Availability\get_current;
use function Leftfield\Core\Availability\purge_expired;
use function Leftfield\Core\Availability\table_name;
use function Leftfield\Core\Availability\upsert;

final class AvailabilityTest extends WP_UnitTestCase {

    private function make_product(string $title = 'Kale'): int {
        return self::factory()->post->create([
            'post_type'   => 'lfuf_product',
            'post_status' => 'publish',
            'post_title'  => $title,
        ]);
    }

    private function count_rows_for(int $product_id): int {
        global $wpdb;
        $table = table_name();
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE product_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $product_id,
        ));
    }

    public function test_upsert_updates_same_product_location_date(): void {
        $product = $this->make_product();
        $today   = current_time('Y-m-d');

        $first  = upsert(['product_id' => $product, 'status' => 'available', 'effective_date' => $today]);
        $second = upsert(['product_id' => $product, 'status' => 'limited', 'effective_date' => $today]);

        $this->assertSame($first, $second, 'same product+location+date must update, not insert');
        $rows = get_current($product);
        $this->assertCount(1, $rows);
        $this->assertSame('limited', $rows[0]->status);
    }

    public function test_upsert_rejects_invalid_status(): void {
        $this->assertFalse(upsert([
            'product_id'     => $this->make_product(),
            'status'         => 'plentiful',
            'effective_date' => current_time('Y-m-d'),
        ]));
    }

    public function test_get_all_current_joins_published_products_only(): void {
        $product = $this->make_product('Join Me');
        upsert(['product_id' => $product, 'status' => 'abundant', 'effective_date' => current_time('Y-m-d')]);

        $draft = self::factory()->post->create(['post_type' => 'lfuf_product', 'post_status' => 'draft']);
        upsert(['product_id' => $draft, 'status' => 'abundant', 'effective_date' => current_time('Y-m-d')]);

        $names = array_column(get_all_current(), 'product_name');
        $this->assertContains('Join Me', $names);
        $this->assertCount(1, $names, 'draft products must not appear');
    }

    public function test_purge_expired_removes_only_past_expiries(): void {
        $product = $this->make_product();
        $today   = current_time('Y-m-d');

        upsert(['product_id' => $product, 'status' => 'available', 'effective_date' => '2020-01-01', 'expires_date' => '2020-01-02']);
        upsert(['product_id' => $product, 'status' => 'available', 'effective_date' => $today]);

        $this->assertSame(1, purge_expired());
        $this->assertSame(1, $this->count_rows_for($product));
    }

    public function test_deleting_product_removes_its_rows_but_trash_keeps_them(): void {
        $product = $this->make_product();
        upsert(['product_id' => $product, 'status' => 'available', 'effective_date' => current_time('Y-m-d')]);

        wp_trash_post($product);
        $this->assertSame(1, $this->count_rows_for($product), 'trashed products keep rows (restorable)');

        wp_delete_post($product, true);
        $this->assertSame(0, $this->count_rows_for($product), 'permanent delete must cascade');
    }

    public function test_deleting_location_removes_only_its_rows(): void {
        $product  = $this->make_product();
        $location = self::factory()->post->create(['post_type' => 'lfuf_location', 'post_status' => 'publish']);
        $today    = current_time('Y-m-d');

        upsert(['product_id' => $product, 'location_id' => 0, 'status' => 'available', 'effective_date' => $today]);
        upsert(['product_id' => $product, 'location_id' => $location, 'status' => 'limited', 'effective_date' => $today]);

        wp_delete_post($location, true);
        $this->assertSame(1, $this->count_rows_for($product), 'location_id=0 ("all locations") rows must survive');
    }
}
