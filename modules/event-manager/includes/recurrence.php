<?php
/**
 * Reading an iCal recurrence rule, and expanding it into dates.
 *
 * `_pkit_recurrence_rule` has stored an RFC 5545 RRULE since it was
 * registered and nothing ever read it. That is worse than an unused field:
 * /events/upcoming filters on _pkit_start_datetime, so a weekly market that
 * began eight weeks ago is absent from the upcoming feed and present in the
 * past one. As far as the API is concerned the market is over.
 *
 * This is a deliberate subset of RFC 5545, not an implementation of it. The
 * full specification is a large piece of software — BYSETPOS alone interacts
 * with every other BY* part — and a plugin with no runtime dependencies
 * should not carry a half-built one. What is supported covers how a market,
 * a class or a pickup day actually recurs:
 *
 *   FREQ        DAILY, WEEKLY, MONTHLY, YEARLY
 *   INTERVAL    every N of those
 *   COUNT       stop after N occurrences
 *   UNTIL       stop on a date
 *   BYDAY       MO,WE,FR for weekly; 1SA or -1SU for monthly and yearly
 *   BYMONTHDAY  the 1st and the 15th
 *   BYMONTH     which months a yearly rule fires in
 *
 * Everything else is refused by name rather than ignored. A rule carrying
 * BYSETPOS that silently expanded without it would produce confidently wrong
 * dates, and a producer would find out when nobody came.
 */

declare(strict_types=1);

namespace ProducerKit\EventManager\Recurrence;

defined( 'ABSPATH' ) || exit;

/** Parts this subset understands. */
const SUPPORTED_PARTS = [ 'FREQ', 'INTERVAL', 'COUNT', 'UNTIL', 'BYDAY', 'BYMONTHDAY', 'BYMONTH', 'WKST' ];

/** Frequencies this subset understands. */
const SUPPORTED_FREQ = [ 'DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY' ];

/** RFC 5545 weekday codes, in the order PHP's `w` format uses. */
const WEEKDAYS = [ 'SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA' ];

/**
 * How far ahead an unbounded rule is expanded, in days.
 *
 * A rule with neither COUNT nor UNTIL is infinite, so something has to stop.
 * Filterable because a CSA running eighteen months out is as reasonable as a
 * market running three.
 */
function horizon_days(): int {
	return (int) apply_filters( 'pkit_recurrence_horizon_days', 400 );
}

/**
 * The most occurrences one rule may produce, whatever it asks for.
 *
 * A guard against COUNT=100000 rather than a product limit: every occurrence
 * becomes a post, so an accidental extra zero would be a lot of rows.
 */
function max_occurrences(): int {
	return (int) apply_filters( 'pkit_recurrence_max_occurrences', 500 );
}

/**
 * Parse an RRULE string into its parts.
 *
 * @return array<string, string>|\WP_Error Upper-cased part => raw value.
 */
function parse( string $rule ): array|\WP_Error {
	$rule = trim( $rule );

	if ( '' === $rule ) {
		return new \WP_Error( 'empty_rule', __( 'No recurrence rule.', 'producerkit' ) );
	}

	// "RRULE:FREQ=WEEKLY" and "FREQ=WEEKLY" are both in the wild.
	if ( 0 === stripos( $rule, 'RRULE:' ) ) {
		$rule = substr( $rule, 6 );
	}

	$parts = [];

	foreach ( explode( ';', $rule ) as $chunk ) {
		$chunk = trim( $chunk );

		if ( '' === $chunk ) {
			continue;
		}

		if ( ! str_contains( $chunk, '=' ) ) {
			return new \WP_Error(
				'malformed_part',
				sprintf(
					/* translators: %s: the offending fragment of the rule. */
					__( '"%s" is not a NAME=VALUE pair.', 'producerkit' ),
					$chunk
				)
			);
		}

		[ $name, $value ] = explode( '=', $chunk, 2 );

		$name  = strtoupper( trim( $name ) );
		$value = strtoupper( trim( $value ) );

		if ( isset( $parts[ $name ] ) ) {
			return new \WP_Error(
				'duplicate_part',
				sprintf(
					/* translators: %s: the repeated part name, e.g. FREQ. */
					__( '%s appears more than once.', 'producerkit' ),
					$name
				)
			);
		}

		$parts[ $name ] = $value;
	}

	return $parts;
}

/**
 * Check a rule is one this subset can honour.
 *
 * Refusing by name matters more than accepting: a rule whose unsupported part
 * is dropped still expands, just to the wrong dates.
 *
 * @return true|\WP_Error
 */
function validate( string $rule ): bool|\WP_Error {
	$parts = parse( $rule );

	if ( is_wp_error( $parts ) ) {
		return $parts;
	}

	$unsupported = array_diff( array_keys( $parts ), SUPPORTED_PARTS );

	if ( $unsupported ) {
		return new \WP_Error(
			'unsupported_part',
			sprintf(
				/* translators: %s: comma-separated list of rule parts, e.g. "BYSETPOS". */
				__( 'This plugin does not support %s in a recurrence rule. Supported: FREQ, INTERVAL, COUNT, UNTIL, BYDAY, BYMONTHDAY, BYMONTH.', 'producerkit' ),
				implode( ', ', $unsupported )
			)
		);
	}

	if ( ! isset( $parts['FREQ'] ) ) {
		return new \WP_Error( 'missing_freq', __( 'A recurrence rule needs a FREQ.', 'producerkit' ) );
	}

	if ( ! in_array( $parts['FREQ'], SUPPORTED_FREQ, true ) ) {
		return new \WP_Error(
			'unsupported_freq',
			sprintf(
				/* translators: %s: the frequency given, e.g. HOURLY. */
				__( 'FREQ=%s is not supported. Use DAILY, WEEKLY, MONTHLY or YEARLY.', 'producerkit' ),
				$parts['FREQ']
			)
		);
	}

	// RFC 5545 §3.3.10: COUNT and UNTIL must not both appear.
	if ( isset( $parts['COUNT'], $parts['UNTIL'] ) ) {
		return new \WP_Error(
			'count_and_until',
			__( 'A rule cannot have both COUNT and UNTIL.', 'producerkit' )
		);
	}

	if ( isset( $parts['INTERVAL'] ) && ( ! ctype_digit( $parts['INTERVAL'] ) || (int) $parts['INTERVAL'] < 1 ) ) {
		return new \WP_Error( 'bad_interval', __( 'INTERVAL must be a whole number of 1 or more.', 'producerkit' ) );
	}

	if ( isset( $parts['COUNT'] ) && ( ! ctype_digit( $parts['COUNT'] ) || (int) $parts['COUNT'] < 1 ) ) {
		return new \WP_Error( 'bad_count', __( 'COUNT must be a whole number of 1 or more.', 'producerkit' ) );
	}

	if ( isset( $parts['UNTIL'] ) && null === parse_until( $parts['UNTIL'] ) ) {
		return new \WP_Error( 'bad_until', __( 'UNTIL must be a date like 20261231 or 20261231T235959Z.', 'producerkit' ) );
	}

	if ( isset( $parts['BYDAY'] ) ) {
		$byday = parse_byday( $parts['BYDAY'] );

		if ( is_wp_error( $byday ) ) {
			return $byday;
		}

		// An ordinal only means something within a month or a year. "The
		// second Saturday of every week" is not a thing.
		if ( 'WEEKLY' === $parts['FREQ'] || 'DAILY' === $parts['FREQ'] ) {
			foreach ( $byday as $day ) {
				if ( 0 !== $day['ordinal'] ) {
					return new \WP_Error(
						'ordinal_byday_on_weekly',
						__( 'An ordinal like 2SA only makes sense with FREQ=MONTHLY or FREQ=YEARLY.', 'producerkit' )
					);
				}
			}
		}
	}

	if ( isset( $parts['BYMONTHDAY'] ) ) {
		foreach ( explode( ',', $parts['BYMONTHDAY'] ) as $day ) {
			$day = (int) $day;

			if ( 0 === $day || $day < -31 || $day > 31 ) {
				return new \WP_Error( 'bad_bymonthday', __( 'BYMONTHDAY must be between 1 and 31, or -1 to -31.', 'producerkit' ) );
			}
		}
	}

	if ( isset( $parts['BYMONTH'] ) ) {
		foreach ( explode( ',', $parts['BYMONTH'] ) as $month ) {
			if ( ! ctype_digit( $month ) || (int) $month < 1 || (int) $month > 12 ) {
				return new \WP_Error( 'bad_bymonth', __( 'BYMONTH must be between 1 and 12.', 'producerkit' ) );
			}
		}
	}

	return true;
}

/**
 * Split a BYDAY value into ordinal/weekday pairs.
 *
 * "MO,WE" gives two entries with ordinal 0; "2SA" gives one with ordinal 2;
 * "-1SU" the last Sunday.
 *
 * @return array<int, array{ordinal: int, day: string}>|\WP_Error
 */
function parse_byday( string $value ): array|\WP_Error {
	$out = [];

	foreach ( explode( ',', $value ) as $token ) {
		$token = trim( $token );

		if ( ! preg_match( '/^([+-]?\d{1,2})?(SU|MO|TU|WE|TH|FR|SA)$/', $token, $m ) ) {
			return new \WP_Error(
				'bad_byday',
				sprintf(
					/* translators: %s: the offending BYDAY token, e.g. "XX". */
					__( '"%s" is not a weekday. Use MO, TU, WE, TH, FR, SA or SU, optionally with a number like 2SA.', 'producerkit' ),
					$token
				)
			);
		}

		$ordinal = ( '' === ( $m[1] ?? '' ) ) ? 0 : (int) $m[1];

		if ( $ordinal < -5 || $ordinal > 5 ) {
			return new \WP_Error( 'byday_ordinal_range', __( 'A BYDAY ordinal must be between -5 and 5.', 'producerkit' ) );
		}

		$out[] = [
			'ordinal' => $ordinal,
			'day'     => $m[2],
		];
	}

	return $out;
}

/**
 * Read an UNTIL value into a timestamp, or null if it is not a date.
 *
 * Accepts the three forms RFC 5545 allows: a bare date, a local datetime, and
 * a UTC datetime.
 */
function parse_until( string $value ): ?int {
	foreach ( [ 'Ymd\THis\Z', 'Ymd\THis', 'Ymd' ] as $format ) {
		$date = \DateTimeImmutable::createFromFormat( '!' . $format, $value, new \DateTimeZone( 'UTC' ) );

		if ( $date instanceof \DateTimeImmutable ) {
			// UNTIL is inclusive, and a bare date means the whole of that day.
			return 'Ymd' === $format
				? $date->setTime( 23, 59, 59 )->getTimestamp()
				: $date->getTimestamp();
		}
	}

	return null;
}

/**
 * Expand a rule into occurrence datetimes.
 *
 * Works in a timezone rather than on timestamps, because a market that starts
 * at 09:00 starts at 09:00 on both sides of a daylight-saving change. Adding
 * seven days to a zoned DateTimeImmutable keeps the wall clock; adding 604800
 * seconds does not, and the market would drift an hour every spring.
 *
 * The rule's own limits are honoured first — COUNT, UNTIL — and the horizon
 * and hard cap only bound a rule that has none.
 *
 * @param string             $rule     The RRULE.
 * @param \DateTimeImmutable $start    First occurrence, in the site's timezone.
 * @param \DateTimeImmutable|null $until_max Stop here regardless. Defaults to the horizon.
 * @return \DateTimeImmutable[]|\WP_Error Ascending, starting with $start.
 */
function expand( string $rule, \DateTimeImmutable $start, ?\DateTimeImmutable $until_max = null ): array|\WP_Error {
	$valid = validate( $rule );

	if ( is_wp_error( $valid ) ) {
		return $valid;
	}

	$parts    = parse( $rule );
	$freq     = $parts['FREQ'];
	$interval = max( 1, (int) ( $parts['INTERVAL'] ?? 1 ) );
	$count    = isset( $parts['COUNT'] ) ? (int) $parts['COUNT'] : 0;
	$zone     = $start->getTimezone();

	// The horizon exists to stop a rule that never stops. A rule with COUNT
	// already says when to stop, and applying the horizon to it truncates the
	// series instead — a yearly rule with COUNT=3 needs three years, not 400
	// days. An explicit $until_max from the caller always wins.
	if ( null !== $until_max ) {
		$horizon = $until_max;
	} elseif ( isset( $parts['COUNT'] ) ) {
		// COUNT and the hard cap do the bounding; this only keeps the loop
		// condition well-defined.
		$horizon = $start->add( new \DateInterval( 'P100Y' ) );
	} else {
		$horizon = $start->add( new \DateInterval( 'P' . horizon_days() . 'D' ) );
	}

	if ( isset( $parts['UNTIL'] ) ) {
		$until = ( new \DateTimeImmutable( '@' . parse_until( $parts['UNTIL'] ) ) )->setTimezone( $zone );

		if ( $until < $horizon ) {
			$horizon = $until;
		}
	}

	$byday      = isset( $parts['BYDAY'] ) ? parse_byday( $parts['BYDAY'] ) : [];
	$bymonthday = isset( $parts['BYMONTHDAY'] ) ? array_map( 'intval', explode( ',', $parts['BYMONTHDAY'] ) ) : [];
	$bymonth    = isset( $parts['BYMONTH'] ) ? array_map( 'intval', explode( ',', $parts['BYMONTH'] ) ) : [];

	$cap    = max_occurrences();
	$dates  = [];
	$found  = 0;
	$cursor = $start;

	// Each turn of this loop is one interval period, not one occurrence: a
	// weekly rule with BYDAY=MO,WE,FR yields three dates per turn.
	$guard = 0;

	while ( $cursor <= $horizon && $found < $cap ) {
		if ( ++$guard > $cap * 4 ) {
			// A period that produced nothing repeatedly — BYMONTHDAY=31 in
			// February, say — must not spin forever.
			break;
		}

		foreach ( period_dates( $freq, $cursor, $start, $byday, $bymonthday, $bymonth ) as $date ) {
			if ( $date < $start || $date > $horizon ) {
				continue;
			}

			$key = $date->format( 'Y-m-d H:i:s' );

			if ( isset( $dates[ $key ] ) ) {
				continue;
			}

			$dates[ $key ] = $date;
			++$found;

			if ( $count > 0 && $found >= $count ) {
				break 2;
			}

			if ( $found >= $cap ) {
				break 2;
			}
		}

		$cursor = advance( $freq, $cursor, $interval );
	}

	$dates = array_values( $dates );

	usort(
		$dates,
		static fn ( \DateTimeImmutable $a, \DateTimeImmutable $b ): int => $a <=> $b
	);

	return $dates;
}

/**
 * The occurrences falling inside one period of the rule.
 *
 * @param array<int, array{ordinal: int, day: string}> $byday
 * @param int[]                                        $bymonthday
 * @param int[]                                        $bymonth
 * @return \DateTimeImmutable[]
 */
function period_dates(
	string $freq,
	\DateTimeImmutable $cursor,
	\DateTimeImmutable $start,
	array $byday,
	array $bymonthday,
	array $bymonth
): array {
	if ( 'DAILY' === $freq ) {
		return [ $cursor ];
	}

	if ( 'WEEKLY' === $freq ) {
		if ( ! $byday ) {
			return [ $cursor ];
		}

		// Monday-based week containing the cursor, so BYDAY=MO,SA yields both
		// days of the same week rather than straddling two.
		$monday = $cursor->modify( 'monday this week' )->setTime(
			(int) $start->format( 'H' ),
			(int) $start->format( 'i' ),
			(int) $start->format( 's' )
		);

		$out = [];

		foreach ( $byday as $spec ) {
			$offset = ( array_search( $spec['day'], WEEKDAYS, true ) + 6 ) % 7;
			$out[]  = $monday->add( new \DateInterval( 'P' . $offset . 'D' ) );
		}

		return $out;
	}

	if ( 'YEARLY' === $freq ) {
		// advance() steps a yearly cursor from 1 January, so the cursor's own
		// month is meaningless. Without BYMONTH the month comes from the
		// start date — an Independence Day market recurs in July, not January.
		$months = $bymonth ?: [ (int) $start->format( 'n' ) ];
		$out    = [];

		foreach ( $months as $month ) {
			$month_start = $cursor->setDate( (int) $cursor->format( 'Y' ), $month, 1 );
			$out         = array_merge( $out, month_dates( $month_start, $start, $byday, $bymonthday ) );
		}

		return $out;
	}

	return month_dates( $cursor, $start, $byday, $bymonthday );
}

/**
 * The occurrences inside the month the cursor sits in.
 *
 * @param array<int, array{ordinal: int, day: string}> $byday
 * @param int[]                                        $bymonthday
 * @return \DateTimeImmutable[]
 */
function month_dates(
	\DateTimeImmutable $cursor,
	\DateTimeImmutable $start,
	array $byday,
	array $bymonthday
): array {
	$year  = (int) $cursor->format( 'Y' );
	$month = (int) $cursor->format( 'n' );
	$days  = (int) $cursor->format( 't' );

	$hour   = (int) $start->format( 'H' );
	$minute = (int) $start->format( 'i' );
	$second = (int) $start->format( 's' );

	$out = [];

	if ( $bymonthday ) {
		foreach ( $bymonthday as $day ) {
			// Negative counts back from the end; -1 is the last day.
			$actual = $day > 0 ? $day : $days + $day + 1;

			// A month that has no 31st simply does not fire, which is what
			// RFC 5545 says and what a person expects.
			if ( $actual < 1 || $actual > $days ) {
				continue;
			}

			$out[] = $cursor->setDate( $year, $month, $actual )->setTime( $hour, $minute, $second );
		}

		return $out;
	}

	if ( $byday ) {
		foreach ( $byday as $spec ) {
			$matches = [];

			for ( $day = 1; $day <= $days; $day++ ) {
				$date = $cursor->setDate( $year, $month, $day )->setTime( $hour, $minute, $second );

				if ( WEEKDAYS[ (int) $date->format( 'w' ) ] === $spec['day'] ) {
					$matches[] = $date;
				}
			}

			if ( 0 === $spec['ordinal'] ) {
				$out = array_merge( $out, $matches );
				continue;
			}

			$index = $spec['ordinal'] > 0
				? $spec['ordinal'] - 1
				: count( $matches ) + $spec['ordinal'];

			if ( isset( $matches[ $index ] ) ) {
				$out[] = $matches[ $index ];
			}
		}

		return $out;
	}

	// No BY* parts: the same day-of-month as the start. A rule starting on
	// the 31st skips the months that have no 31st rather than sliding into
	// the 1st of the next one, which is what DateTime's own month arithmetic
	// would do.
	$day = (int) $start->format( 'j' );

	if ( $day > $days ) {
		return [];
	}

	return [ $cursor->setDate( $year, $month, $day )->setTime( $hour, $minute, $second ) ];
}

/**
 * Move the cursor on by one interval.
 *
 * Months are stepped from the first of the month, never from the current day:
 * PHP's "+1 month" on 31 January gives 3 March, so a monthly rule anchored on
 * a long month would skip February and land twice in March.
 */
function advance( string $freq, \DateTimeImmutable $cursor, int $interval ): \DateTimeImmutable {
	return match ( $freq ) {
		'DAILY'   => $cursor->add( new \DateInterval( 'P' . $interval . 'D' ) ),
		'WEEKLY'  => $cursor->add( new \DateInterval( 'P' . ( $interval * 7 ) . 'D' ) ),
		'MONTHLY' => $cursor->setDate( (int) $cursor->format( 'Y' ), (int) $cursor->format( 'n' ), 1 )
			->add( new \DateInterval( 'P' . $interval . 'M' ) ),
		'YEARLY'  => $cursor->setDate( (int) $cursor->format( 'Y' ), 1, 1 )
			->add( new \DateInterval( 'P' . $interval . 'Y' ) ),
		default   => $cursor->add( new \DateInterval( 'P1D' ) ),
	};
}
