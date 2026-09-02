<?php
/**
 * Availability table: upsert semantics, current-row queries, expiry
 * purge, and orphan cleanup when products/locations are deleted.
 */

declare(strict_types=1);

use function ProducerKit\Core\Availability\get_all_current;
use function ProducerKit\Core\Availability\get_current;
use function ProducerKit\Core\Availability\purge_expired;
use function ProducerKit\Core\Availability\table_name;
use function ProducerKit\Core\Availability\upsert;

final class AvailabilityTest extends WP_UnitTestCase {

	private function make_product( string $title = 'Kale' ): int {
		return self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'publish',
				'post_title'  => $title,
			]
		);
	}

	private function count_rows_for( int $product_id ): int {
		global $wpdb;
		$table = table_name();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE product_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$product_id,
			)
		);
	}

	public function test_upsert_updates_same_product_location_date(): void {
		$product = $this->make_product();
		$today   = current_time( 'Y-m-d' );

		$first  = upsert(
			[
				'product_id'     => $product,
				'status'         => 'available',
				'effective_date' => $today,
			]
		);
		$second = upsert(
			[
				'product_id'     => $product,
				'status'         => 'limited',
				'effective_date' => $today,
			]
		);

		$this->assertSame( $first, $second, 'same product+location+date must update, not insert' );
		$rows = get_current( $product );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'limited', $rows[0]->status );
	}

	public function test_upsert_rejects_invalid_status(): void {
		$this->assertFalse(
			upsert(
				[
					'product_id'     => $this->make_product(),
					'status'         => 'plentiful',
					'effective_date' => current_time( 'Y-m-d' ),
				]
			)
		);
	}

	public function test_get_all_current_joins_published_products_only(): void {
		$product = $this->make_product( 'Join Me' );
		upsert(
			[
				'product_id'     => $product,
				'status'         => 'abundant',
				'effective_date' => current_time( 'Y-m-d' ),
			]
		);

		$draft = self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'draft',
			]
		);
		upsert(
			[
				'product_id'     => $draft,
				'status'         => 'abundant',
				'effective_date' => current_time( 'Y-m-d' ),
			]
		);

		$names = array_column( get_all_current(), 'product_name' );
		$this->assertContains( 'Join Me', $names );
		$this->assertCount( 1, $names, 'draft products must not appear' );
	}

	public function test_purge_expired_removes_only_past_expiries(): void {
		$product = $this->make_product();
		$today   = current_time( 'Y-m-d' );

		upsert(
			[
				'product_id'     => $product,
				'status'         => 'available',
				'effective_date' => '2020-01-01',
				'expires_date'   => '2020-01-02',
			]
		);
		upsert(
			[
				'product_id'     => $product,
				'status'         => 'available',
				'effective_date' => $today,
			]
		);

		$this->assertSame( 1, purge_expired() );
		$this->assertSame( 1, $this->count_rows_for( $product ) );
	}

	public function test_deleting_product_removes_its_rows_but_trash_keeps_them(): void {
		$product = $this->make_product();
		upsert(
			[
				'product_id'     => $product,
				'status'         => 'available',
				'effective_date' => current_time( 'Y-m-d' ),
			]
		);

		wp_trash_post( $product );
		$this->assertSame( 1, $this->count_rows_for( $product ), 'trashed products keep rows (restorable)' );

		wp_delete_post( $product, true );
		$this->assertSame( 0, $this->count_rows_for( $product ), 'permanent delete must cascade' );
	}

	public function test_deleting_location_removes_only_its_rows(): void {
		$product  = $this->make_product();
		$location = self::factory()->post->create(
			[
				'post_type'   => 'pkit_location',
				'post_status' => 'publish',
			]
		);
		$today    = current_time( 'Y-m-d' );

		upsert(
			[
				'product_id'     => $product,
				'location_id'    => 0,
				'status'         => 'available',
				'effective_date' => $today,
			]
		);
		upsert(
			[
				'product_id'     => $product,
				'location_id'    => $location,
				'status'         => 'limited',
				'effective_date' => $today,
			]
		);

		wp_delete_post( $location, true );
		$this->assertSame( 1, $this->count_rows_for( $product ), 'location_id=0 ("all locations") rows must survive' );
	}

	/**
	 * The schema must survive STRICT_TRANS_TABLES, the MySQL default since 5.7.
	 *
	 * Regression: `notes` was declared TEXT NOT NULL DEFAULT ''. MySQL forbids
	 * defaults on BLOB/TEXT columns — non-strict servers drop the default with
	 * a warning (and dbDelta then retries the impossible ALTER on every run),
	 * but a strict server rejects the CREATE outright, so the availability
	 * table was never created and every feature reading it silently failed.
	 *
	 * Runs against a throwaway table so the live one is untouched.
	 */
	public function test_schema_is_accepted_under_strict_sql_mode(): void {
		global $wpdb;

		$probe    = $wpdb->prefix . 'pkit_schema_probe';
		$previous = (string) $wpdb->get_var( 'SELECT @@SESSION.sql_mode' );

		$wpdb->query( $wpdb->prepare( 'SET SESSION sql_mode = %s', 'STRICT_TRANS_TABLES' ) );

		// phpcs:disable WordPress.DB.PreparedSQL -- $probe is a $wpdb->prefix identifier built in this method, and schema_sql() returns a complete DDL statement. Neither can be parameterized.
		$wpdb->query( "DROP TABLE IF EXISTS `$probe`" );

		$wpdb->suppress_errors( true );
		$created = $wpdb->query( \ProducerKit\Core\Availability\schema_sql( $probe ) );
		$error   = $wpdb->last_error;
		$wpdb->suppress_errors( false );

		$wpdb->query( "DROP TABLE IF EXISTS `$probe`" );
		// phpcs:enable WordPress.DB.PreparedSQL

		$wpdb->query( $wpdb->prepare( 'SET SESSION sql_mode = %s', $previous ) );

		$this->assertNotFalse( $created, "schema rejected under strict mode: $error" );
		$this->assertSame( '', $error, 'schema produced a database error under strict mode' );
	}

	/**
	 * upsert() must not depend on a column default that the schema cannot
	 * carry — under strict mode an omitted NOT NULL column is a hard error.
	 */
	public function test_upsert_supplies_notes_so_no_column_default_is_needed(): void {
		global $wpdb;

		$product  = $this->make_product();
		$previous = (string) $wpdb->get_var( 'SELECT @@SESSION.sql_mode' );

		$wpdb->query( $wpdb->prepare( 'SET SESSION sql_mode = %s', 'STRICT_TRANS_TABLES' ) );

		$id = upsert(
			[
				'product_id'     => $product,
				'status'         => 'available',
				'effective_date' => current_time( 'Y-m-d' ),
			]
		);

		$error = $wpdb->last_error;
		$wpdb->query( $wpdb->prepare( 'SET SESSION sql_mode = %s', $previous ) );

		$this->assertNotFalse( $id, "insert failed under strict mode: $error" );

		// get_row(), not get_var(): wpdb::get_var() returns null for an empty
		// string — its guard is `'' !== $values[ $column_offset ]` — so it
		// cannot tell "no row" from "empty column", and neither can get_col(),
		// which calls it. '' is exactly the value under test here.
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT notes FROM {$wpdb->prefix}pkit_availability WHERE id = %d", $id ),
			ARRAY_A
		);

		$this->assertIsArray( $row, 'the inserted row was not found' );
		$this->assertSame( '', $row['notes'] );
	}

	/**
	 * The cleanup cron must fire at 03:00 *site-local*, on any timezone.
	 *
	 * Regression: the schedule was computed from current_time( 'timestamp' ),
	 * which is epoch + gmt_offset rather than a real epoch. wp_schedule_event()
	 * wants a real epoch, so the job ran gmt_offset hours away from 3am — four
	 * hours off on a UTC-4 site, and silently correct only on UTC.
	 */
	public function test_cleanup_cron_is_scheduled_for_three_am_site_local(): void {
		foreach ( [ 'UTC', 'America/New_York', 'Asia/Kolkata', 'Pacific/Auckland' ] as $tz ) {
			update_option( 'timezone_string', $tz );
			wp_clear_scheduled_hook( 'pkit_availability_cleanup' );

			\ProducerKit\Core\Availability\schedule_cleanup();

			$ts = wp_next_scheduled( 'pkit_availability_cleanup' );
			$this->assertNotFalse( $ts, "cleanup was not scheduled under $tz" );

			// Render the scheduled instant in the site's own timezone.
			$local = ( new DateTimeImmutable( '@' . $ts ) )->setTimezone( wp_timezone() );

			$this->assertSame(
				'03:00',
				$local->format( 'H:i' ),
				"cleanup should run at 03:00 local under $tz, got {$local->format( 'H:i' )}"
			);
		}

		wp_clear_scheduled_hook( 'pkit_availability_cleanup' );
	}
}
