<?php
/**
 * Bounds on expansion.
 *
 * Every occurrence becomes a post, so a rule that expands without limit is a
 * rule that fills the database. These are the two things standing between an
 * honest mistake and thousands of rows.
 */

declare(strict_types=1);

use function ProducerKit\EventManager\Recurrence\expand;

final class RecurrenceGuardTest extends PHPUnit\Framework\TestCase {

	private function start(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-05-02 09:00', new DateTimeZone( 'America/New_York' ) );
	}

	public function test_an_unbounded_rule_stops_at_the_horizon(): void {
		// FREQ=DAILY with nothing else is infinite.
		$dates = expand( 'FREQ=DAILY', $this->start() );

		$this->assertIsArray( $dates );
		$this->assertNotEmpty( $dates );
		$this->assertLessThanOrEqual(
			401,
			count( $dates ),
			'A daily rule should stop at the 400-day horizon, not run forever.'
		);
	}

	public function test_an_absurd_count_is_capped(): void {
		// A producer meaning COUNT=100 and typing COUNT=100000.
		$dates = expand( 'FREQ=DAILY;COUNT=100000', $this->start() );

		$this->assertIsArray( $dates );
		$this->assertLessThanOrEqual( 500, count( $dates ) );
	}

	public function test_a_rule_whose_periods_mostly_produce_nothing_terminates(): void {
		// Only seven months of the year have a 31st, so most periods yield
		// nothing. Without the loop guard this is where it would spin.
		$dates = expand( 'FREQ=MONTHLY;BYMONTHDAY=31', $this->start() );

		$this->assertIsArray( $dates );
		$this->assertNotEmpty( $dates );

		foreach ( $dates as $date ) {
			$this->assertSame( '31', $date->format( 'j' ) );
		}
	}

	public function test_a_rule_that_can_never_fire_returns_nothing_rather_than_hanging(): void {
		// February 30th does not come round.
		$dates = expand( 'FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=30', $this->start() );

		$this->assertSame( [], $dates );
	}

	public function test_an_invalid_rule_is_refused_rather_than_expanded(): void {
		$result = expand( 'FREQ=MONTHLY;BYSETPOS=-1', $this->start() );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_a_caller_supplied_ceiling_wins(): void {
		$stop = new DateTimeImmutable( '2026-05-20 09:00', new DateTimeZone( 'America/New_York' ) );

		$dates = expand( 'FREQ=DAILY;COUNT=100', $this->start(), $stop );

		$this->assertIsArray( $dates );
		$this->assertCount( 19, $dates, 'The ceiling should cut a COUNT rule short.' );
		$this->assertSame( '2026-05-20', end( $dates )->format( 'Y-m-d' ) );
	}
}
