<?php
/**
 * The route that lets the editor ask what a rule means.
 *
 * The parser has always refused a rule it cannot honour and said exactly why.
 * None of it reached anybody: the rule was set through the raw custom-field
 * box, the sanitiser dropped an invalid one, and the write returned 200 with
 * the value gone — a control that appeared to do nothing. This is how the
 * reason gets to the person who caused it.
 */

declare(strict_types=1);

final class RecurrencePreviewTest extends WP_UnitTestCase {

	private function ask( string $rule, string $start = '2026-09-05T09:00:00' ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/producerkit/v1/recurrence/preview' );

		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(
			(string) wp_json_encode(
				[
					'rule'  => $rule,
					'start' => $start,
				]
			)
		);

		return rest_do_request( $request );
	}

	public function set_up(): void {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	public function test_a_good_rule_returns_its_dates(): void {
		$data = $this->ask( 'FREQ=WEEKLY;BYDAY=SA;COUNT=4' )->get_data();

		$this->assertTrue( $data['valid'] );
		$this->assertSame( 4, $data['total'] );
		$this->assertFalse( $data['more'] );
		$this->assertSame( '2026-09-05T09:00:00', $data['dates'][0]['iso'] );
		$this->assertNotSame( '', $data['dates'][0]['label'], 'A date a person can read.' );
	}

	public function test_the_preview_is_capped_and_says_so(): void {
		// Enough to see the shape, not a calendar.
		$data = $this->ask( 'FREQ=WEEKLY;BYDAY=SA' )->get_data();

		$this->assertCount( 8, $data['dates'] );
		$this->assertGreaterThan( 8, $data['total'] );
		$this->assertTrue( $data['more'] );
	}

	/**
	 * @dataProvider refusals
	 */
	public function test_a_refusal_comes_back_with_its_reason( string $rule, string $expected ): void {
		$response = $this->ask( $rule );
		$data     = $response->get_data();

		// 200, not 400: an invalid rule is an expected answer to the question
		// being asked, not a failed request. A 400 would have the editor's
		// fetch treat it as a network problem and show nothing — the silence
		// this route exists to end.
		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $data['valid'] );
		$this->assertSame( [], $data['dates'] );
		$this->assertStringContainsString( $expected, $data['message'] );
	}

	public static function refusals(): array {
		return [
			'unsupported part' => [ 'FREQ=MONTHLY;BYDAY=SA;BYSETPOS=-1', 'BYSETPOS' ],
			'sub-daily'        => [ 'FREQ=HOURLY', 'HOURLY' ],
			'count and until'  => [ 'FREQ=WEEKLY;COUNT=5;UNTIL=20261231', 'cannot have both' ],
			'ordinal weekly'   => [ 'FREQ=WEEKLY;BYDAY=2SA', '2SA' ],
			'bad weekday'      => [ 'FREQ=WEEKLY;BYDAY=XX', 'XX' ],
			'no frequency'     => [ 'INTERVAL=2', 'FREQ' ],
		];
	}

	public function test_an_empty_rule_is_not_an_error(): void {
		// Clearing the field is how you stop an event recurring.
		$data = $this->ask( '' )->get_data();

		$this->assertTrue( $data['valid'] );
		$this->assertSame( [], $data['dates'] );
	}

	public function test_a_missing_start_previews_from_today(): void {
		// The producer is most likely mid-way through the form.
		$data = $this->ask( 'FREQ=WEEKLY;COUNT=2', '' )->get_data();

		$this->assertTrue( $data['valid'] );
		$this->assertCount( 2, $data['dates'] );
	}

	public function test_a_malformed_start_does_not_error(): void {
		$data = $this->ask( 'FREQ=WEEKLY;COUNT=2', 'not a date' )->get_data();

		$this->assertTrue( $data['valid'] );
		$this->assertCount( 2, $data['dates'] );
	}

	public function test_it_is_staff_only(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$this->assertSame( 403, $this->ask( 'FREQ=WEEKLY' )->get_status() );
	}

	public function test_it_refuses_the_logged_out(): void {
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->ask( 'FREQ=WEEKLY' )->get_status() );
	}
}
