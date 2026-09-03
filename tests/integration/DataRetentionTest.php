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

	private function an_event(): int {
		$event = self::factory()->post->create(
			[
				'post_type'   => 'pkit_event',
				'post_status' => 'publish',
			]
		);

		update_post_meta( $event, '_pkit_em_rsvp_enabled', 1 );

		return $event;
	}
}
