<?php
/**
 * Refusing a rule this subset cannot honour.
 *
 * This matters more than the expansion. A rule whose unsupported part is
 * dropped still expands — to the wrong dates, confidently, and a producer
 * finds out when nobody comes. Every refusal below is a rule that would
 * otherwise have looked like it worked.
 */

declare(strict_types=1);

use function ProducerKit\EventManager\Recurrence\parse;
use function ProducerKit\EventManager\Recurrence\validate;

final class RecurrenceValidateTest extends PHPUnit\Framework\TestCase {

	private function refusal( string $rule ): string {
		$result = validate( $rule );

		$this->assertInstanceOf( WP_Error::class, $result, 'Expected "' . $rule . '" to be refused.' );

		return $result->get_error_code();
	}

	public function test_the_rules_a_producer_actually_writes_are_accepted(): void {
		foreach (
			[
				'FREQ=WEEKLY',
				'FREQ=WEEKLY;BYDAY=SA',
				'FREQ=WEEKLY;BYDAY=WE,SA;INTERVAL=2',
				'FREQ=MONTHLY;BYDAY=1SA',
				'FREQ=MONTHLY;BYMONTHDAY=1,15',
				'FREQ=MONTHLY;BYMONTHDAY=-1',
				'FREQ=YEARLY;BYMONTH=6,9',
				'FREQ=DAILY;COUNT=10',
				'FREQ=WEEKLY;UNTIL=20261231',
				'FREQ=WEEKLY;UNTIL=20261231T235959Z',
				'RRULE:FREQ=WEEKLY',
			] as $rule
		) {
			$this->assertTrue( validate( $rule ), $rule . ' should be accepted.' );
		}
	}

	public function test_an_unsupported_part_is_refused_by_name(): void {
		$result = validate( 'FREQ=MONTHLY;BYDAY=SA;BYSETPOS=-1' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'unsupported_part', $result->get_error_code() );
		$this->assertStringContainsString(
			'BYSETPOS',
			$result->get_error_message(),
			'The message must name the part, or nobody can fix the rule.'
		);
	}

	/**
	 * @dataProvider unsupported_parts
	 */
	public function test_every_unimplemented_part_is_refused( string $part ): void {
		$this->assertSame( 'unsupported_part', $this->refusal( 'FREQ=WEEKLY;' . $part ) );
	}

	public static function unsupported_parts(): array {
		return [
			'BYSETPOS'  => [ 'BYSETPOS=-1' ],
			'BYWEEKNO'  => [ 'BYWEEKNO=20' ],
			'BYYEARDAY' => [ 'BYYEARDAY=100' ],
			'BYHOUR'    => [ 'BYHOUR=9' ],
			'BYMINUTE'  => [ 'BYMINUTE=30' ],
			'BYSECOND'  => [ 'BYSECOND=0' ],
		];
	}

	/**
	 * @dataProvider sub_daily
	 */
	public function test_sub_daily_frequencies_are_refused( string $freq ): void {
		// Each would become a post per occurrence. An hourly rule over a year
		// is 8,760 posts.
		$this->assertSame( 'unsupported_freq', $this->refusal( 'FREQ=' . $freq ) );
	}

	public static function sub_daily(): array {
		return [
			'SECONDLY' => [ 'SECONDLY' ],
			'MINUTELY' => [ 'MINUTELY' ],
			'HOURLY'   => [ 'HOURLY' ],
		];
	}

	public function test_count_and_until_together_are_refused(): void {
		// RFC 5545 §3.3.10 makes them mutually exclusive, and honouring one
		// silently would pick a winner the producer did not choose.
		$this->assertSame( 'count_and_until', $this->refusal( 'FREQ=WEEKLY;COUNT=5;UNTIL=20261231' ) );
	}

	public function test_a_missing_frequency_is_refused(): void {
		$this->assertSame( 'missing_freq', $this->refusal( 'INTERVAL=2;BYDAY=SA' ) );
	}

	public function test_an_ordinal_weekday_on_a_weekly_rule_is_refused(): void {
		// "The second Saturday of every week" is not a thing, and expanding
		// it by ignoring the 2 would quietly mean every Saturday.
		$this->assertSame( 'ordinal_byday_on_weekly', $this->refusal( 'FREQ=WEEKLY;BYDAY=2SA' ) );
	}

	/**
	 * @dataProvider malformed
	 */
	public function test_malformed_values_are_refused( string $rule, string $code ): void {
		$this->assertSame( $code, $this->refusal( $rule ) );
	}

	public static function malformed(): array {
		return [
			'empty'             => [ '', 'empty_rule' ],
			'not a pair'        => [ 'FREQ=WEEKLY;JUSTAWORD', 'malformed_part' ],
			'repeated part'     => [ 'FREQ=WEEKLY;FREQ=DAILY', 'duplicate_part' ],
			'zero interval'     => [ 'FREQ=WEEKLY;INTERVAL=0', 'bad_interval' ],
			'negative interval' => [ 'FREQ=WEEKLY;INTERVAL=-2', 'bad_interval' ],
			'zero count'        => [ 'FREQ=WEEKLY;COUNT=0', 'bad_count' ],
			'until not a date'  => [ 'FREQ=WEEKLY;UNTIL=soon', 'bad_until' ],
			'bad weekday'       => [ 'FREQ=WEEKLY;BYDAY=XX', 'bad_byday' ],
			'monthday zero'     => [ 'FREQ=MONTHLY;BYMONTHDAY=0', 'bad_bymonthday' ],
			'monthday too big'  => [ 'FREQ=MONTHLY;BYMONTHDAY=32', 'bad_bymonthday' ],
			'month too big'     => [ 'FREQ=YEARLY;BYMONTH=13', 'bad_bymonth' ],
			'ordinal too big'   => [ 'FREQ=MONTHLY;BYDAY=6SA', 'byday_ordinal_range' ],
		];
	}

	public function test_parsing_is_case_insensitive_and_tolerates_spacing(): void {
		$parts = parse( ' freq=weekly ; byday = sa ' );

		$this->assertSame( 'WEEKLY', $parts['FREQ'] );
		$this->assertSame( 'SA', $parts['BYDAY'] );
	}
}
