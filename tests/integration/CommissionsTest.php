<?php
/**
 * Commissions: the request lifecycle, the transition table, and the token
 * rules that let a guest act without an account.
 */

declare(strict_types=1);

use ProducerKit\Commissions\Store;

final class CommissionsTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// Rate limiting is transient-backed; a fresh window per test.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%pkit_commission_rate%'" );
	}

	/**
	 * @param array<string, mixed> $overrides
	 */
	private function submit( array $overrides = [] ): array|WP_Error {
		return Store\create(
			array_merge(
				[
					'name'        => 'Dana Rivers',
					'email'       => 'dana@example.com',
					'description' => 'A walnut bowl, about 10 inches, food safe finish.',
				],
				$overrides
			)
		);
	}

	/* ── Creation ─────────────────────────────────────────────── */

	public function test_a_valid_request_is_stored(): void {
		$c = $this->submit(
			[
				'budget_range' => '100-200',
				'phone'        => '555-0100',
			]
		);

		$this->assertIsArray( $c );
		$this->assertGreaterThan( 0, $c['id'] );
		$this->assertSame( 'new', $c['status'] );
		$this->assertSame( 'Dana Rivers', $c['name'] );
		$this->assertSame( '100-200', $c['budget_range'] );
		$this->assertNotEmpty( $c['token'] );
	}

	public function test_name_email_and_description_are_required(): void {
		$this->assertWPError( $this->submit( [ 'name' => '' ] ) );
		$this->assertWPError( $this->submit( [ 'description' => '   ' ] ) );
	}

	/**
	 * Unlike a pre-order there is no counter to collect this at, so a quote
	 * has to be able to reach them.
	 */
	public function test_a_usable_email_is_required(): void {
		$this->assertWPError( $this->submit( [ 'email' => '' ] ) );
		$this->assertWPError( $this->submit( [ 'email' => 'not-an-address' ] ) );
	}

	public function test_an_unknown_budget_range_is_dropped_not_stored(): void {
		$c = $this->submit( [ 'budget_range' => 'a-squillion' ] );
		$this->assertSame( '', $c['budget_range'] );
	}

	public function test_an_impossible_deadline_is_rejected(): void {
		$this->assertWPError( $this->submit( [ 'deadline' => '2026-02-31' ] ) );
		$this->assertWPError( $this->submit( [ 'deadline' => 'next tuesday' ] ) );

		$ok = $this->submit( [ 'deadline' => '2026-12-24' ] );
		$this->assertIsArray( $ok );
		$this->assertSame( '2026-12-24', $ok['deadline'] );
	}

	public function test_an_overlong_description_is_rejected(): void {
		$this->assertWPError( $this->submit( [ 'description' => str_repeat( 'a', 5001 ) ] ) );
	}

	/**
	 * A tripped honeypot gets a plausible receipt and no row, so the bot
	 * cannot tell which field caught it.
	 */
	public function test_the_honeypot_fakes_success_without_storing(): void {
		$before = Store\list_commissions()['total'];

		$c = $this->submit( [ 'honeypot' => 'http://spam.example' ] );

		$this->assertIsArray( $c );
		$this->assertNotWPError( $c );
		$this->assertSame( 0, $c['id'] );
		$this->assertNotEmpty( $c['token'] );
		$this->assertSame( $before, Store\list_commissions()['total'], 'A tripped honeypot must not write a row.' );
	}

	public function test_requests_are_rate_limited_per_ip(): void {
		add_filter( 'pkit_commission_rate_limit', fn () => 2 );

		$this->assertIsArray( $this->submit() );
		$this->assertIsArray( $this->submit() );
		$this->assertWPError( $this->submit() );

		remove_all_filters( 'pkit_commission_rate_limit' );
	}

	public function test_the_stored_ip_is_never_returned(): void {
		$c = $this->submit();
		$this->assertArrayNotHasKey( 'ip_hash', $c );
	}

	/* ── Quoting ──────────────────────────────────────────────── */

	public function test_sending_a_quote_moves_it_to_quoted_and_issues_a_token(): void {
		$c = $this->submit();

		$quoted = Store\send_quote( $c['id'], 185.00, '2026-11-01', 'Two coats of tung oil.' );

		$this->assertIsArray( $quoted );
		$this->assertSame( 'quoted', $quoted['status'] );
		$this->assertSame( 185.00, $quoted['quoted_price'] );
		$this->assertSame( '2026-11-01', $quoted['estimated_date'] );
		$this->assertNotEmpty( $quoted['quote_token'] );
	}

	public function test_a_quote_needs_a_positive_price(): void {
		$c = $this->submit();

		$this->assertWPError( Store\send_quote( $c['id'], 0.0 ) );
		$this->assertWPError( Store\send_quote( $c['id'], -5.0 ) );
	}

	public function test_the_quote_token_is_withheld_from_ordinary_reads(): void {
		$c = $this->submit();
		Store\send_quote( $c['id'], 100.0 );

		$fetched = Store\to_public( (array) Store\get( $c['id'] ) );

		$this->assertArrayNotHasKey(
			'quote_token',
			$fetched,
			'The quote token is a capability, not a display field.'
		);
	}

	public function test_an_expired_quote_token_does_not_resolve(): void {
		global $wpdb;

		$c      = $this->submit();
		$quoted = Store\send_quote( $c['id'], 100.0 );
		$token  = $quoted['quote_token'];

		$this->assertNotNull( Store\find_by_quote_token( $token ) );

		$wpdb->update(
			Store\table_name(),
			[ 'quote_expires' => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ],
			[ 'id' => $c['id'] ]
		);

		$this->assertNull( Store\find_by_quote_token( $token ), 'A stale accept link must not still work.' );
	}

	/* ── Transitions ──────────────────────────────────────────── */

	public function test_the_happy_path_runs_end_to_end(): void {
		$c = $this->submit();
		$this->assertSame( 'new', $c['status'] );

		$this->assertSame( 'quoted', Store\send_quote( $c['id'], 185.0 )['status'] );
		$this->assertSame( 'accepted', Store\set_status( $c['id'], 'accepted' )['status'] );
		$this->assertSame( 'in_progress', Store\set_status( $c['id'], 'in_progress' )['status'] );
		$this->assertSame( 'complete', Store\set_status( $c['id'], 'complete' )['status'] );
	}

	/**
	 * The reason transitions are enforced rather than advisory: a stale admin
	 * tab must not be able to revive a decision the customer already made.
	 */
	public function test_a_declined_commission_cannot_be_accepted(): void {
		$c = $this->submit();
		Store\send_quote( $c['id'], 100.0 );
		Store\set_status( $c['id'], 'declined' );

		$result = Store\set_status( $c['id'], 'accepted' );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_transition', $result->get_error_code() );
		$this->assertSame( 'declined', Store\get( $c['id'] )['status'] );
	}

	public function test_a_new_commission_cannot_skip_straight_to_complete(): void {
		$c = $this->submit();
		$this->assertWPError( Store\set_status( $c['id'], 'complete' ) );
	}

	public function test_an_unquoted_commission_cannot_be_accepted(): void {
		$c = $this->submit();
		$this->assertWPError( Store\set_status( $c['id'], 'accepted' ) );
	}

	public function test_terminal_states_are_terminal(): void {
		foreach ( [ 'complete', 'declined', 'cancelled' ] as $terminal ) {
			$this->assertSame( [], Store\transitions()[ $terminal ] );
		}
	}

	public function test_an_unknown_status_is_refused(): void {
		$c = $this->submit();
		$this->assertWPError( Store\set_status( $c['id'], 'haunted' ) );
	}

	public function test_setting_the_same_status_is_a_no_op_not_an_error(): void {
		$c      = $this->submit();
		$result = Store\set_status( $c['id'], 'new' );

		$this->assertIsArray( $result );
		$this->assertSame( 'new', $result['status'] );
	}

	/**
	 * Accepting spends the token, so the emailed link cannot be replayed.
	 */
	public function test_deciding_spends_the_quote_token(): void {
		$c     = $this->submit();
		$token = Store\send_quote( $c['id'], 100.0 )['quote_token'];

		Store\set_status( $c['id'], 'accepted' );

		$this->assertNull( Store\find_by_quote_token( $token ) );
	}

	/* ── Lookup ───────────────────────────────────────────────── */

	public function test_the_customer_token_survives_the_quote_being_spent(): void {
		$c = $this->submit();
		Store\send_quote( $c['id'], 100.0 );
		Store\set_status( $c['id'], 'accepted' );

		$found = Store\find_by_token( $c['token'] );

		$this->assertNotNull( $found, 'A customer must still be able to see their own commission.' );
		$this->assertSame( (int) $c['id'], (int) $found['id'] );
	}

	public function test_lookup_by_a_bogus_token_finds_nothing(): void {
		$this->assertNull( Store\find_by_token( 'nope' ) );
		$this->assertNull( Store\find_by_token( '' ) );
		$this->assertNull( Store\find_by_quote_token( '' ) );
	}

	public function test_listing_filters_by_status(): void {
		$a = $this->submit( [ 'name' => 'A' ] );
		$b = $this->submit( [ 'name' => 'B' ] );
		Store\send_quote( $b['id'], 50.0 );

		$this->assertSame( 2, Store\list_commissions()['total'] );
		$this->assertSame( 1, Store\list_commissions( [ 'status' => 'quoted' ] )['total'] );
		$this->assertSame( 1, Store\list_commissions( [ 'status' => 'new' ] )['total'] );
		$this->assertSame( 'A', Store\list_commissions( [ 'status' => 'new' ] )['commissions'][0]['name'] );
	}

	/* ── Product attachment ───────────────────────────────────── */

	public function test_a_catalogue_product_can_be_attached(): void {
		$c       = $this->submit();
		$product = self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'publish',
			]
		);

		$this->assertTrue( Store\attach_product( $c['id'], $product ) );
		$this->assertSame( $product, (int) Store\get( $c['id'] )['product_id'] );
	}
}
