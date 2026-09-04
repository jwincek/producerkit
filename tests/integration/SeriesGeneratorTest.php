<?php
/**
 * Generating events from a recurrence rule.
 *
 * The occurrences of a recurring event are real posts, so RSVPs, capacity,
 * cancellation and guest lists work on them unchanged. The hard part is not
 * making them; it is making them again without undoing what a producer has
 * changed by hand — cancelling the Saturday that falls on a holiday, or
 * moving one week's start time.
 */

declare(strict_types=1);

use ProducerKit\EventManager\Series;

final class SeriesGeneratorTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		\ProducerKit\Core\Post_Types\register();
		\ProducerKit\Core\Meta_Fields\register();

		if ( function_exists( '\ProducerKit\EventManager\Meta\register' ) ) {
			\ProducerKit\EventManager\Meta\register();
		}
	}

	private function series( string $rule = 'FREQ=WEEKLY;BYDAY=SA;COUNT=4', array $meta = [] ): int {
		$id = self::factory()->post->create(
			[
				'post_type'   => 'pkit_event',
				'post_status' => 'publish',
				'post_title'  => 'Saturday Market',
			]
		);

		update_post_meta( $id, '_pkit_start_datetime', '2026-09-05T09:00:00' );
		update_post_meta( $id, '_pkit_recurrence_rule', $rule );

		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}

		return $id;
	}

	/**
	 * @return string[]
	 */
	private function dates( int $series ): array {
		return array_map(
			static fn ( WP_Post $p ): string => substr(
				(string) get_post_meta( $p->ID, '_pkit_start_datetime', true ),
				0,
				10
			),
			Series\occurrences( $series )
		);
	}

	public function test_it_generates_one_post_per_occurrence(): void {
		$series = $this->series();

		Series\generate( $series );

		$this->assertSame(
			[ '2026-09-05', '2026-09-12', '2026-09-19', '2026-09-26' ],
			$this->dates( $series )
		);
	}

	public function test_generating_twice_creates_nothing_new(): void {
		// It runs on every save and on a schedule. A generator that produced
		// duplicates would be found as a hundred Saturdays.
		$series = $this->series();

		Series\generate( $series );
		$stats = Series\generate( $series );

		$this->assertSame( 0, $stats['created'] );
		$this->assertCount( 4, Series\occurrences( $series ) );
	}

	public function test_occurrences_inherit_the_series_settings(): void {
		$series = $this->series(
			'FREQ=WEEKLY;BYDAY=SA;COUNT=2',
			[
				'_pkit_rsvp_cap'         => 20,
				'_pkit_em_rsvp_enabled'  => 1,
				'_pkit_em_cost_note'     => 'Free',
			]
		);

		Series\generate( $series );

		foreach ( Series\occurrences( $series ) as $occurrence ) {
			$this->assertSame( '20', (string) get_post_meta( $occurrence->ID, '_pkit_rsvp_cap', true ) );
			$this->assertSame( 'Free', get_post_meta( $occurrence->ID, '_pkit_em_cost_note', true ) );
		}
	}

	public function test_the_rule_itself_is_not_inherited(): void {
		// An occurrence carrying the rule would be a series of its own and
		// would generate again, endlessly.
		$series = $this->series();

		Series\generate( $series );

		foreach ( Series\occurrences( $series ) as $occurrence ) {
			$this->assertSame( '', (string) get_post_meta( $occurrence->ID, '_pkit_recurrence_rule', true ) );
			$this->assertFalse( Series\is_series( $occurrence->ID ) );
		}
	}

	public function test_each_occurrence_keeps_the_series_duration(): void {
		$series = $this->series( 'FREQ=WEEKLY;BYDAY=SA;COUNT=2' );
		update_post_meta( $series, '_pkit_end_datetime', '2026-09-05T13:00:00' );

		Series\generate( $series );

		foreach ( Series\occurrences( $series ) as $occurrence ) {
			$start = new DateTimeImmutable( (string) get_post_meta( $occurrence->ID, '_pkit_start_datetime', true ) );
			$end   = new DateTimeImmutable( (string) get_post_meta( $occurrence->ID, '_pkit_end_datetime', true ) );

			$this->assertSame( 4, (int) $start->diff( $end )->format( '%h' ) );
		}
	}

	/* ── The part that matters: edits survive ───────────── */

	public function test_saving_an_occurrence_marks_it_edited(): void {
		$series = $this->series();
		Series\generate( $series );

		$occurrence = Series\occurrences( $series )[1];

		$this->assertFalse( Series\edited( $occurrence->ID ) );

		wp_update_post(
			[
				'ID'         => $occurrence->ID,
				'post_title' => 'Closed for the fair',
			]
		);

		$this->assertTrue( Series\edited( $occurrence->ID ) );
	}

	public function test_generating_does_not_mark_its_own_work_as_edited(): void {
		// wp_insert_post() fires save_post. Without the guard the generator
		// would flag everything it created and never touch it again — the
		// feature would appear to work once and then stop.
		$series = $this->series();
		Series\generate( $series );

		foreach ( Series\occurrences( $series ) as $occurrence ) {
			$this->assertFalse( Series\edited( $occurrence->ID ) );
		}
	}

	public function test_a_cancelled_occurrence_survives_regeneration(): void {
		// The requirement this whole design exists for.
		$series = $this->series();
		Series\generate( $series );

		$holiday = Series\occurrences( $series )[2];
		update_post_meta( $holiday->ID, '_pkit_em_cancelled', 1 );
		wp_update_post(
			[
				'ID'         => $holiday->ID,
				'post_title' => 'Closed for the fair',
			]
		);

		wp_update_post(
			[
				'ID'         => $series,
				'post_title' => 'Saturday Farmers Market',
			]
		);
		Series\generate( $series );

		$this->assertSame( 'Closed for the fair', get_the_title( $holiday->ID ) );
		$this->assertSame( '1', (string) get_post_meta( $holiday->ID, '_pkit_em_cancelled', true ) );
	}

	public function test_untouched_occurrences_do_follow_the_series(): void {
		$series = $this->series();
		Series\generate( $series );

		wp_update_post(
			[
				'ID'         => $series,
				'post_title' => 'Saturday Farmers Market',
			]
		);
		Series\generate( $series );

		foreach ( Series\occurrences( $series ) as $occurrence ) {
			$this->assertSame( 'Saturday Farmers Market', get_the_title( $occurrence->ID ) );
		}
	}

	/* ── Dropping dates ─────────────────────────────────── */

	public function test_a_dropped_untouched_occurrence_is_deleted(): void {
		$series = $this->series();
		Series\generate( $series );

		$all  = Series\occurrences( $series );
		$last = end( $all );

		update_post_meta( $series, '_pkit_recurrence_rule', 'FREQ=WEEKLY;BYDAY=SA;COUNT=2' );
		Series\generate( $series );

		$this->assertNull( get_post( $last->ID ) );
		$this->assertCount( 2, Series\occurrences( $series ) );
	}

	public function test_a_dropped_edited_occurrence_is_detached_not_destroyed(): void {
		// Deleting an occurrence somebody deliberately changed would throw
		// away their work, and any bookings taken on it.
		$series = $this->series();
		Series\generate( $series );

		$all  = Series\occurrences( $series );
		$last = end( $all );
		wp_update_post(
			[
				'ID'         => $last->ID,
				'post_title' => 'Special last market',
			]
		);

		update_post_meta( $series, '_pkit_recurrence_rule', 'FREQ=WEEKLY;BYDAY=SA;COUNT=2' );
		Series\generate( $series );

		$survivor = get_post( $last->ID );

		$this->assertInstanceOf( WP_Post::class, $survivor );
		$this->assertSame( 0, $survivor->post_parent, 'It should become an ordinary one-off event.' );
		$this->assertSame( 'Special last market', $survivor->post_title );
	}

	public function test_removing_the_rule_releases_the_occurrences(): void {
		$series = $this->series();
		Series\generate( $series );

		$ids = wp_list_pluck( Series\occurrences( $series ), 'ID' );

		update_post_meta( $series, '_pkit_recurrence_rule', '' );
		wp_update_post( [ 'ID' => $series ] );

		foreach ( $ids as $id ) {
			$this->assertNull( get_post( $id ) );
		}
	}

	public function test_deleting_a_series_takes_its_occurrences_with_it(): void {
		// wp_delete_post() only re-parents children of hierarchical types,
		// and pkit_event is not one — without this they would linger,
		// visible on the site, pointing at a post that no longer exists.
		$series = $this->series();
		Series\generate( $series );

		$ids = wp_list_pluck( Series\occurrences( $series ), 'ID' );
		$this->assertCount( 4, $ids );

		wp_delete_post( $series, true );

		foreach ( $ids as $id ) {
			$this->assertNull( get_post( $id ), 'Occurrence ' . $id . ' outlived its series.' );
		}
	}

	/* ── Refusals ───────────────────────────────────────── */

	public function test_a_series_with_no_start_is_refused(): void {
		$id = self::factory()->post->create( [ 'post_type' => 'pkit_event' ] );
		update_post_meta( $id, '_pkit_recurrence_rule', 'FREQ=WEEKLY' );

		$result = Series\generate( $id );

		$this->assertWPError( $result );
		$this->assertSame( 'no_start', $result->get_error_code() );
	}

	public function test_an_event_without_a_rule_is_not_a_series(): void {
		$id = self::factory()->post->create( [ 'post_type' => 'pkit_event' ] );

		$this->assertFalse( Series\is_series( $id ) );
		$this->assertWPError( Series\generate( $id ) );
	}
}
