<?php
/**
 * What happens to personal data when the thing it belongs to goes away.
 *
 * These rows hold names, email addresses and phone numbers. Left behind they
 * point at a post id that no longer exists, are invisible in every admin
 * screen, and outlive the event or location indefinitely.
 */

declare(strict_types=1);

use ProducerKit\EventManager\RSVP;
use ProducerKit\PreOrder\Orders;

final class DataRetentionTest extends WP_UnitTestCase {

	/* ── RSVPs follow their event ─────────────────────────────── */

	public function test_deleting_an_event_removes_its_rsvps(): void {
		global $wpdb;

		$event = $this->an_event();
		$this->assertNotWPError(
			RSVP\add_rsvp(
				[
					'event_id' => $event,
					'name'     => 'Jimmy',
				]
			)
		);

		$table = RSVP\table_name();
		$count = static fn (): int => (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE event_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$event
			)
		);

		$this->assertSame( 1, $count(), 'Precondition: the RSVP is stored.' );

		wp_delete_post( $event, true );

		$this->assertSame( 0, $count(), 'Attendee names and emails must not outlive the event.' );
	}

	/**
	 * A trashed event can be restored, and its guest list should come back
	 * with it — so trashing must not delete anything.
	 */
	public function test_trashing_an_event_keeps_its_rsvps(): void {
		global $wpdb;

		$event = $this->an_event();
		RSVP\add_rsvp(
			[
				'event_id' => $event,
				'name'     => 'Johnny',
			]
		);

		wp_trash_post( $event );

		$table = RSVP\table_name();
		$this->assertSame(
			1,
			(int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE event_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$event
				)
			)
		);
	}

	public function test_deleting_an_unrelated_post_leaves_rsvps_alone(): void {
		global $wpdb;

		$event = $this->an_event();
		RSVP\add_rsvp(
			[
				'event_id' => $event,
				'name'     => 'Jimmy',
			]
		);

		wp_delete_post( self::factory()->post->create(), true );

		$table = RSVP\table_name();
		$this->assertSame(
			1,
			(int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE event_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$event
				)
			)
		);
	}

	/* ── Pre-orders follow their pickup location ──────────────── */

	public function test_deleting_a_location_removes_its_preorders(): void {
		global $wpdb;

		$location = self::factory()->post->create(
			[
				'post_type'   => 'pkit_location',
				'post_status' => 'publish',
			]
		);

		$wpdb->insert(
			Orders\table_name(),
			[
				'token'       => 'tok' . wp_generate_password( 20, false ),
				'location_id' => $location,
				'name'        => 'Dana',
				'email'       => 'dana@example.com',
				'pickup_date' => gmdate( 'Y-m-d' ),
				'status'      => 'pending',
				'items'       => '[]',
			]
		);

		$table = Orders\table_name();
		$count = static fn (): int => (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE location_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$location
			)
		);

		$this->assertSame( 1, $count() );

		wp_delete_post( $location, true );

		$this->assertSame( 0, $count(), 'An order that cannot be collected should not keep the customer on file.' );
	}

	/* ── Uninstall ────────────────────────────────────────────── */

	public function test_uninstall_is_gated_and_defaults_to_keeping_content(): void {
		$this->assertFalse(
			(bool) get_option( 'pkit_delete_data_on_uninstall' ),
			'Deleting a plugin to troubleshoot must not destroy a catalogue by default.'
		);
	}

	/**
	 * Every identifier uninstall.php removes should be one the plugin really
	 * creates — a stale name there deletes nothing and hides the fact.
	 */
	public function test_uninstall_names_only_real_tables(): void {
		global $wpdb;

		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/uninstall.php' );

		// Deliberately not matched against the loop's syntax — an earlier
		// version of this test pinned the variable name and broke when it was
		// renamed, which told us nothing about whether the list was right.
		foreach ( [ 'pkit_availability', 'pkit_rsvps', 'pkit_preorders', 'pkit_commissions' ] as $table ) {
			$this->assertStringContainsString(
				"'{$table}'",
				$source,
				"uninstall.php should name {$table}."
			);

			$name = $wpdb->prefix . $table;
			$this->assertSame(
				$name,
				$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) ),
				"uninstall.php names {$table}, so the plugin must really create it."
			);
		}
	}

	public function test_uninstall_refuses_to_run_outside_wordpress(): void {
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/uninstall.php' );

		$this->assertStringContainsString(
			"defined( 'WP_UNINSTALL_PLUGIN' ) || exit;",
			$source,
			'Without the guard the file is a remotely reachable script that drops tables.'
		);
	}

	/**
	 * The kept-content path: what goes regardless, and what stays for a
	 * reinstall to pick up again.
	 */
	public function test_uninstall_always_removes_bookkeeping(): void {
		update_option( 'pkit_meta_key_version', 2 );
		update_option( 'pkit_wc_settlement_db_version', '1.0.0' );
		update_option( 'pkit_disabled_modules', [ 'commissions' ] );

		$producer = self::factory()->user->create();
		update_user_meta( $producer, 'pkit_producer_name', 'Hilltop Honey' );

		$product = self::factory()->post->create( [ 'post_type' => 'pkit_product' ] );

		$this->run_uninstall();

		$this->assertFalse( get_option( 'pkit_meta_key_version' ), 'A schema version means nothing without the plugin.' );
		$this->assertFalse( get_option( 'pkit_wc_settlement_db_version' ), 'A schema version means nothing without the plugin.' );

		$this->assertSame( [ 'commissions' ], get_option( 'pkit_disabled_modules' ), 'Settings stay unless deletion was asked for.' );
		$this->assertSame( 'Hilltop Honey', get_user_meta( $producer, 'pkit_producer_name', true ) );
		$this->assertInstanceOf( WP_Post::class, get_post( $product ) );
	}

	/**
	 * The deletion path has to reach every setting, including per-person ones.
	 */
	public function test_uninstall_on_request_removes_settings_and_user_meta(): void {
		update_option( 'pkit_delete_data_on_uninstall', 1 );
		update_option( 'pkit_disabled_modules', [ 'commissions' ] );

		$producer = self::factory()->user->create();
		update_user_meta( $producer, 'pkit_producer_name', 'Hilltop Honey' );
		update_user_meta( $producer, 'pkit_producer_profile', 'potter' );

		$page = self::factory()->post->create( [ 'post_type' => 'page' ] );
		update_post_meta( $page, '_pkit_generated_page', '1' );

		$this->run_uninstall();

		$this->assertFalse( get_option( 'pkit_disabled_modules' ) );
		$this->assertSame( '', get_user_meta( $producer, 'pkit_producer_name', true ) );
		$this->assertSame( '', get_user_meta( $producer, 'pkit_producer_profile', true ) );

		$this->assertInstanceOf( WP_Post::class, get_post( $page ), 'A generated page is an ordinary page by now, and may have been edited.' );
		$this->assertSame( '', get_post_meta( $page, '_pkit_generated_page', true ), 'The marker should not outlive the plugin that set it.' );
	}

	private function an_event(): int {
		$event = self::factory()->post->create(
			[
				'post_type'   => 'pkit_event',
				'post_status' => 'publish',
			]
		);

		update_post_meta( $event, '_pkit_rsvp_enabled', 1 );

		return $event;
	}

	/**
	 * Run uninstall.php for real.
	 *
	 * Safe inside a test: the test case rewrites DROP TABLE to DROP TEMPORARY
	 * TABLE, which leaves the real tables alone and does not commit, so every
	 * deletion rolls back with the rest of the test.
	 */
	private function run_uninstall(): void {
		defined( 'WP_UNINSTALL_PLUGIN' ) || define( 'WP_UNINSTALL_PLUGIN', 'producerkit/producerkit.php' );

		// The file calls pkit_uninstall() itself, and can only be loaded once.
		if ( function_exists( 'pkit_uninstall' ) ) {
			pkit_uninstall();
			return;
		}

		require dirname( __DIR__, 2 ) . '/uninstall.php';
	}
}
