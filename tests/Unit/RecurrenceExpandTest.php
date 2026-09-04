<?php
/**
 * Expanding an RRULE into dates.
 *
 * Pure date arithmetic, so it belongs in the unit suite — and date arithmetic
 * is where confidently wrong answers come from. Every case below is one a
 * market, a class or a pickup day actually produces.
 */

declare(strict_types=1);

use function ProducerKit\EventManager\Recurrence\expand;

final class RecurrenceExpandTest extends PHPUnit\Framework\TestCase {

	private function start( string $when, string $zone = 'America/New_York' ): DateTimeImmutable {
		return new DateTimeImmutable( $when, new DateTimeZone( $zone ) );
	}

	/**
	 * @return string[]
	 */
	private function dates( string $rule, string $when, string $format = 'Y-m-d', ?string $stop = null ): array {
		$start = $this->start( $when );

		$result = expand(
			$rule,
			$start,
			$stop ? new DateTimeImmutable( $stop, new DateTimeZone( 'America/New_York' ) ) : null
		);

		if ( $result instanceof WP_Error ) {
			$this->fail( 'expand() refused the rule: ' . $result->get_error_message() );
		}

		return array_map( static fn ( DateTimeImmutable $d ): string => $d->format( $format ), $result );
	}

	public function test_weekly_lands_on_the_same_weekday(): void {
		$dates = $this->dates( 'FREQ=WEEKLY;COUNT=4', '2026-05-02 09:00' );

		$this->assertSame( [ '2026-05-02', '2026-05-09', '2026-05-16', '2026-05-23' ], $dates );
	}

	public function test_weekly_with_several_days(): void {
		// A stand open Wednesday and Saturday.
		$dates = $this->dates( 'FREQ=WEEKLY;BYDAY=WE,SA;COUNT=4', '2026-05-02 09:00' );

		$this->assertSame( [ '2026-05-02', '2026-05-06', '2026-05-09', '2026-05-13' ], $dates );
	}

	public function test_fortnightly(): void {
		$dates = $this->dates( 'FREQ=WEEKLY;INTERVAL=2;COUNT=3', '2026-05-02 09:00' );

		$this->assertSame( [ '2026-05-02', '2026-05-16', '2026-05-30' ], $dates );
	}

	public function test_the_clock_survives_a_daylight_saving_change(): void {
		// The market opens at 09:00 on both sides of the November change.
		// Adding 604800 seconds instead of seven days would make it 08:00.
		$times = $this->dates( 'FREQ=WEEKLY;COUNT=4', '2026-10-24 09:00', 'Y-m-d H:i' );

		$this->assertSame(
			[ '2026-10-24 09:00', '2026-10-31 09:00', '2026-11-07 09:00', '2026-11-14 09:00' ],
			$times
		);
	}

	public function test_monthly_on_an_ordinal_weekday(): void {
		// First Saturday of the month.
		$dates = $this->dates( 'FREQ=MONTHLY;BYDAY=1SA;COUNT=4', '2026-05-02 09:00' );

		$this->assertSame( [ '2026-05-02', '2026-06-06', '2026-07-04', '2026-08-01' ], $dates );
	}

	public function test_monthly_on_the_last_weekday(): void {
		$dates = $this->dates( 'FREQ=MONTHLY;BYDAY=-1SU;COUNT=3', '2026-05-31 09:00' );

		$this->assertSame( [ '2026-05-31', '2026-06-28', '2026-07-26' ], $dates );
	}

	public function test_monthly_by_day_of_month(): void {
		$dates = $this->dates( 'FREQ=MONTHLY;BYMONTHDAY=1,15;COUNT=4', '2026-05-01 09:00' );

		$this->assertSame( [ '2026-05-01', '2026-05-15', '2026-06-01', '2026-06-15' ], $dates );
	}

	public function test_a_month_without_a_thirty_first_is_skipped(): void {
		// RFC 5545 says the occurrence simply does not happen. PHP's own
		// "+1 month" would slide 31 January into 3 March instead.
		$dates = $this->dates( 'FREQ=MONTHLY;BYMONTHDAY=31;COUNT=4', '2026-01-31 09:00' );

		$this->assertSame( [ '2026-01-31', '2026-03-31', '2026-05-31', '2026-07-31' ], $dates );
	}

	public function test_monthly_anchored_on_a_long_month_does_not_double_up(): void {
		// Stepping from the 31st with "+1 month" lands twice in March.
		$dates = $this->dates( 'FREQ=MONTHLY;COUNT=4', '2026-01-31 09:00' );

		$this->assertSame( [ '2026-01-31', '2026-03-31', '2026-05-31', '2026-07-31' ], $dates );
	}

	public function test_last_day_of_every_month(): void {
		$dates = $this->dates( 'FREQ=MONTHLY;BYMONTHDAY=-1;COUNT=4', '2026-01-31 09:00' );

		$this->assertSame( [ '2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30' ], $dates );
	}

	public function test_yearly(): void {
		$dates = $this->dates( 'FREQ=YEARLY;COUNT=3', '2026-07-04 09:00' );

		$this->assertSame( [ '2026-07-04', '2027-07-04', '2028-07-04' ], $dates );
	}

	public function test_yearly_in_named_months(): void {
		$dates = $this->dates( 'FREQ=YEARLY;BYMONTH=6,9;BYMONTHDAY=1;COUNT=4', '2026-06-01 09:00' );

		$this->assertSame( [ '2026-06-01', '2026-09-01', '2027-06-01', '2027-09-01' ], $dates );
	}

	public function test_daily(): void {
		$dates = $this->dates( 'FREQ=DAILY;COUNT=3', '2026-05-02 09:00' );

		$this->assertSame( [ '2026-05-02', '2026-05-03', '2026-05-04' ], $dates );
	}

	public function test_until_stops_the_series_and_is_inclusive(): void {
		$dates = $this->dates( 'FREQ=WEEKLY;UNTIL=20260523', '2026-05-02 09:00' );

		$this->assertSame( [ '2026-05-02', '2026-05-09', '2026-05-16', '2026-05-23' ], $dates );
	}

	public function test_an_unbounded_rule_stops_at_the_horizon(): void {
		$dates = $this->dates( 'FREQ=WEEKLY', '2026-05-02 09:00', 'Y-m-d', '2026-06-01 09:00' );

		$this->assertSame( [ '2026-05-02', '2026-05-09', '2026-05-16', '2026-05-23', '2026-05-30' ], $dates );
	}

	public function test_the_first_occurrence_is_always_the_start(): void {
		foreach ( [ 'FREQ=DAILY', 'FREQ=WEEKLY', 'FREQ=MONTHLY', 'FREQ=YEARLY' ] as $rule ) {
			$dates = $this->dates( $rule . ';COUNT=2', '2026-05-02 09:00' );

			$this->assertSame( '2026-05-02', $dates[0], $rule . ' should begin at its start date.' );
		}
	}

	public function test_occurrences_are_unique_and_ascending(): void {
		// BYDAY=SA on a Saturday start could otherwise emit the start twice.
		$dates = $this->dates( 'FREQ=WEEKLY;BYDAY=SA;COUNT=5', '2026-05-02 09:00' );

		$this->assertSame( $dates, array_values( array_unique( $dates ) ) );
		$sorted = $dates;
		sort( $sorted );
		$this->assertSame( $sorted, $dates );
	}

	public function test_nothing_before_the_start_is_emitted(): void {
		// The start is a Wednesday; BYDAY=MO,WE would put Monday first in
		// that week, which is before the series began.
		$dates = $this->dates( 'FREQ=WEEKLY;BYDAY=MO,WE;COUNT=3', '2026-05-06 09:00' );

		$this->assertSame( [ '2026-05-06', '2026-05-11', '2026-05-13' ], $dates );
	}
}
