<?php
/**
 * The rolling horizon.
 *
 * A rule with no COUNT or UNTIL never ends, so something has to decide how
 * far ahead to generate. The trap is measuring that window from the series
 * start: it stops moving the moment the series is saved, so a market created
 * in 2026 generates into 2027 and then quietly runs out of Saturdays with
 * nothing to notice it. The window is measured from today, and a daily job
 * keeps it sliding.
 */

declare(strict_types=1);

use ProducerKit\EventManager\Series;

final class SeriesHorizonTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		\ProducerKit\Core\Post_Types\register();
		\ProducerKit\Core\Meta_Fields\register();

		if ( function_exists( '\ProducerKit\EventManager\Meta\register' ) ) {
			\ProducerKit\EventManager\Meta\register();
		}
	}

	public function tear_down(): void {
		Series\unschedule_extend();
		parent::tear_down();
	}

	private function series( string $start_modifier, string $rule = 'FREQ=WEEKLY' ): int {
		$id = self::factory()->post->create(
			[
				'post_type'   => 'pkit_event',
				'post_status' => 'publish',
				'post_title'  => 'Saturday Market',
			]
		);

		$start = ( new DateTimeImmutable( 'now', wp_timezone() ) )
			->modify( $start_modifier )
			->setTime( 9, 0 );

		update_post_meta( $id, '_pkit_start_datetime', $start->format( 'Y-m-d\TH:i:s' ) );
		update_post_meta( $id, '_pkit_recurrence_rule', $rule );

		return $id;
	}

	private function upcoming_count( int $series ): int {
		$today = ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d' );
		$count = 0;

		foreach ( Series\occurrences( $series ) as $occurrence ) {
			if ( substr( (string) get_post_meta( $occurrence->ID, '_pkit_start_datetime', true ), 0, 10 ) > $today ) {
				++$count;
			}
		}

		return $count;
	}

	public function test_an_old_series_still_has_dates_ahead_of_it(): void {
		// The failure a start-anchored window produces: a year-old market
		// with nothing left to come.
		$series = $this->series( '-1 year' );

		Series\generate( $series );

		$this->assertGreaterThan(
			40,
			$this->upcoming_count( $series ),
			'A weekly market that began a year ago should still have most of a year ahead of it.'
		);
	}

	public function test_a_series_starting_in_the_future_is_not_empty(): void {
		// The window opens from whichever is later, so a festival booked
		// eighteen months out is not silently blank.
		$series = $this->series( '+18 months', 'FREQ=WEEKLY;COUNT=3' );

		Series\generate( $series );

		$this->assertCount( 3, Series\occurrences( $series ) );
	}

	public function test_extending_adds_only_what_the_window_has_reached(): void {
		$series = $this->series( '-2 weeks' );
		Series\generate( $series );

		$before = count( Series\occurrences( $series ) );

		$this->assertSame( 0, Series\extend( $series ), 'Nothing new should appear on an unchanged window.' );
		$this->assertCount( $before, Series\occurrences( $series ) );

		add_filter( 'pkit_recurrence_horizon_days', static fn (): int => 430 );

		$this->assertGreaterThan( 0, Series\extend( $series ), 'A wider window should bring dates into range.' );

		remove_all_filters( 'pkit_recurrence_horizon_days' );
	}

	public function test_extending_never_touches_an_existing_occurrence(): void {
		// It runs unattended. A cron job that rewrites content while nobody
		// is watching is how an edit made months ago disappears.
		$series = $this->series( '-2 weeks' );
		Series\generate( $series );

		$occurrence = Series\occurrences( $series )[1];
		wp_update_post(
			[
				'ID'         => $occurrence->ID,
				'post_title' => 'Closed for the fair',
			]
		);

		wp_update_post(
			[
				'ID'         => $series,
				'post_title' => 'Renamed After The Fact',
			]
		);

		add_filter( 'pkit_recurrence_horizon_days', static fn (): int => 430 );
		Series\extend( $series );
		remove_all_filters( 'pkit_recurrence_horizon_days' );

		$this->assertSame( 'Closed for the fair', get_the_title( $occurrence->ID ) );
	}

	public function test_extending_never_deletes(): void {
		$series = $this->series( '-2 weeks' );
		Series\generate( $series );

		$before = count( Series\occurrences( $series ) );

		// A narrower window would make generate() prune. extend() must not.
		add_filter( 'pkit_recurrence_horizon_days', static fn (): int => 30 );
		Series\extend( $series );
		remove_all_filters( 'pkit_recurrence_horizon_days' );

		$this->assertCount( $before, Series\occurrences( $series ) );
	}

	public function test_all_series_finds_series_and_not_occurrences(): void {
		$series = $this->series( '-1 week', 'FREQ=WEEKLY;COUNT=3' );
		Series\generate( $series );

		$one_off = self::factory()->post->create( [ 'post_type' => 'pkit_event' ] );

		$found = Series\all_series();

		$this->assertContains( $series, $found );
		$this->assertNotContains( $one_off, $found );

		foreach ( Series\occurrences( $series ) as $occurrence ) {
			$this->assertNotContains( $occurrence->ID, $found, 'An occurrence is not a series.' );
		}
	}

	public function test_one_broken_series_does_not_stop_the_others(): void {
		$good = $this->series( '-1 week', 'FREQ=WEEKLY;COUNT=2' );

		$broken = self::factory()->post->create( [ 'post_type' => 'pkit_event' ] );
		update_post_meta( $broken, '_pkit_recurrence_rule', 'FREQ=WEEKLY' );
		// No start date, so generating it is refused.

		add_filter( 'pkit_recurrence_horizon_days', static fn (): int => 430 );
		$created = Series\extend_all();
		remove_all_filters( 'pkit_recurrence_horizon_days' );

		$this->assertIsInt( $created );
		$this->assertWPError( Series\extend( $broken ) );
	}

	public function test_the_daily_job_is_scheduled_and_removable(): void {
		Series\unschedule_extend();
		$this->assertFalse( wp_next_scheduled( 'pkit_series_extend' ) );

		Series\schedule_extend();
		$first = wp_next_scheduled( 'pkit_series_extend' );
		$this->assertIsInt( $first );

		// Safe to call repeatedly — it runs on every init.
		Series\schedule_extend();
		$this->assertSame( $first, wp_next_scheduled( 'pkit_series_extend' ) );

		Series\unschedule_extend();
		$this->assertFalse( wp_next_scheduled( 'pkit_series_extend' ) );
	}

	public function test_the_scheduled_time_is_local_not_utc(): void {
		// current_time( 'timestamp' ) yields a wall-clock epoch, which would
		// put the job gmt_offset hours away from where it reads. The
		// availability cleanup documents the same trap.
		Series\unschedule_extend();
		Series\schedule_extend();

		$this->assertSame( '03:30', wp_date( 'H:i', (int) wp_next_scheduled( 'pkit_series_extend' ) ) );
	}
}
