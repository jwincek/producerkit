<?php
/**
 * Whether an RSVP can be reached after it is made — by the guest who made it,
 * and by the person running the event.
 *
 * Before this it could be reached by neither. Every notification went to the
 * site admin, so the guest got no confirmation at all; "Cancel my RSVP" worked
 * only in the tab they had submitted from, because the token lived in that
 * page's Interactivity context; and the organiser had a count on the events
 * list and no screen behind it.
 */

declare(strict_types=1);

use ProducerKit\EventManager\RSVP;
use ProducerKit\EventManager\RSVPResponse;

final class RsvpLoopTest extends WP_UnitTestCase {

	/** @var array<int, array> */
	private array $mail = [];

	public function set_up(): void {
		parent::set_up();

		// add_rsvp() issues START TRANSACTION for its cap check, and MySQL has
		// no nested transactions — that implicitly commits the one
		// WP_UnitTestCase opened, so rows written here survive the rollback
		// and leak into the next test. Clear the table rather than rely on it.
		global $wpdb;
		$table = RSVP\table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Test fixture teardown; the table name is a $wpdb->prefix identifier.
		$wpdb->query( "DELETE FROM {$table}" );

		$this->mail = [];
		add_filter(
			'wp_mail',
			function ( array $args ): array {
				$this->mail[] = $args;
				return $args;
			}
		);
	}

	/* ── The guest gets a way back ────────────────────────────── */

	public function test_a_guest_who_leaves_an_email_gets_a_confirmation_with_a_cancel_link(): void {
		$event = $this->an_event();
		$rsvp  = RSVP\add_rsvp(
			[
				'event_id' => $event,
				'name'     => 'Jimmy',
				'email'    => 'jimmy@example.com',
			]
		);

		$to_guest = $this->mail_to( 'jimmy@example.com' );

		$this->assertNotNull( $to_guest, 'The guest previously received nothing at all.' );
		$this->assertStringContainsString( 'pkit_rsvp=', $to_guest['message'], 'Without the link the booking is unreachable once the tab closes.' );
		$this->assertStringContainsString( $rsvp['token'], $to_guest['message'] );
	}

	/**
	 * Email is optional on the form. A guest who leaves it blank simply has no
	 * remote cancellation — that is a normal path, not an error to log.
	 */
	public function test_no_confirmation_is_attempted_without_an_address(): void {
		RSVP\add_rsvp(
			[
				'event_id' => $this->an_event(),
				'name'     => 'Anonymous',
			]
		);

		$this->assertSame( [], $this->mail_addressed_beyond_admin() );
	}

	/**
	 * The honeypot hands back a fake receipt with id 0 and stores nothing.
	 * Mailing on that would confirm to a bot that its address is live.
	 */
	public function test_the_honeypot_receipt_does_not_send_mail(): void {
		RSVP\add_rsvp(
			[
				'event_id' => $this->an_event(),
				'name'     => 'Bot',
				'email'    => 'bot@example.com',
				'honeypot' => 'gotcha',
			]
		);

		$this->assertNull( $this->mail_to( 'bot@example.com' ) );
	}

	public function test_the_confirmation_can_be_suppressed(): void {
		add_filter( 'pkit_notify_rsvp_confirmation', '__return_false' );

		RSVP\add_rsvp(
			[
				'event_id' => $this->an_event(),
				'name'     => 'Jimmy',
				'email'    => 'jimmy@example.com',
			]
		);

		$this->assertNull( $this->mail_to( 'jimmy@example.com' ) );

		remove_filter( 'pkit_notify_rsvp_confirmation', '__return_false' );
	}

	/* ── The token resolves ───────────────────────────────────── */

	public function test_a_token_resolves_to_its_booking(): void {
		$event = $this->an_event();
		$rsvp  = RSVP\add_rsvp(
			[
				'event_id'   => $event,
				'name'       => 'Jimmy',
				'party_size' => 3,
			]
		);

		$found = RSVP\find_by_token( $rsvp['token'] );

		$this->assertNotNull( $found );
		$this->assertSame( 'Jimmy', $found['name'] );
		$this->assertSame( $event, (int) $found['event_id'] );
	}

	/**
	 * Same answer for an unknown token and one already cancelled, so a guesser
	 * learns nothing from the difference.
	 */
	public function test_an_unknown_token_resolves_to_nothing(): void {
		$this->assertNull( RSVP\find_by_token( 'nosuchtokenatall1234' ) );
		$this->assertNull( RSVP\find_by_token( '' ) );
	}

	public function test_cancelling_by_token_frees_the_place(): void {
		$event = $this->an_event( 6 );
		$rsvp  = RSVP\add_rsvp(
			[
				'event_id'   => $event,
				'name'       => 'Jimmy',
				'party_size' => 2,
			]
		);

		$this->assertNotWPError( $rsvp, is_wp_error( $rsvp ) ? $rsvp->get_error_message() : '' );
		$this->assertSame( 2, RSVP\get_event_rsvp_summary( $event )['headcount'] );

		RSVP\cancel_rsvp( $rsvp['token'] );

		$this->assertSame( 0, RSVP\get_event_rsvp_summary( $event )['headcount'] );
		$this->assertNull( RSVP\find_by_token( $rsvp['token'] ) );
	}

	public function test_the_booking_url_carries_the_token(): void {
		$this->assertStringContainsString( 'pkit_rsvp=abc123', RSVPResponse\url_for( 'abc123' ) );
	}

	/* ── The organiser can read them ──────────────────────────── */

	public function test_the_organiser_can_list_who_is_coming(): void {
		$event = $this->an_event();
		RSVP\add_rsvp(
			[
				'event_id'   => $event,
				'name'       => 'Jimmy',
				'party_size' => 2,
			]
		);
		RSVP\add_rsvp(
			[
				'event_id' => $event,
				'name'     => 'Johnny',
			]
		);

		$names = wp_list_pluck( RSVP\get_event_rsvps( $event ), 'name' );

		$this->assertCount( 2, $names );
		$this->assertContains( 'Jimmy', $names );
		$this->assertContains( 'Johnny', $names );
	}

	/**
	 * A guest list is names and email addresses; Contributor is too low a bar.
	 */
	public function test_reading_a_guest_list_requires_more_than_contributor(): void {
		$cap = RSVP\manage_cap();

		$this->assertFalse( user_can( self::factory()->user->create( [ 'role' => 'contributor' ] ), $cap ) );
		$this->assertTrue( user_can( self::factory()->user->create( [ 'role' => 'editor' ] ), $cap ) );
	}

	/* ── The export is a spreadsheet, which executes things ───── */

	/**
	 * A guest controls their own name and note. A cell starting = + - @ runs
	 * as a formula when the organiser opens the list in Excel or Sheets, where
	 * HYPERLINK and WEBSERVICE can send the rest of the sheet somewhere.
	 *
	 * @dataProvider csv_provider
	 */
	public function test_csv_cells_cannot_become_formulas( string $input, string $expected ): void {
		$this->assertSame( $expected, RSVP\esc_csv_field( $input ) );
	}

	public function csv_provider(): array {
		return [
			'ordinary name'   => [ 'Jimmy', 'Jimmy' ],
			'apostrophe name' => [ "O'Brien", "O'Brien" ],
			'equals'          => [ '=HYPERLINK("http://evil.example","click")', "'=HYPERLINK(\"http://evil.example\",\"click\")" ],
			'plus'            => [ '+1-555-0100', "'+1-555-0100" ],
			'minus'           => [ '-2+3', "'-2+3" ],
			'at'              => [ '@SUM(A1:A9)', "'@SUM(A1:A9)" ],
			'tab'             => [ "\tinjected", "'\tinjected" ],
			'plain number'    => [ '42', '42' ],
			'decimal'         => [ '4.50', '4.50' ],
			'empty'           => [ '', '' ],
		];
	}

	/* ── Helpers ──────────────────────────────────────────────── */

	private function an_event( int $cap = 0 ): int {
		$event = self::factory()->post->create(
			[
				'post_type'   => 'pkit_event',
				'post_status' => 'publish',
			]
		);

		update_post_meta( $event, '_pkit_em_rsvp_enabled', 1 );
		update_post_meta( $event, '_pkit_start_datetime', gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );

		if ( $cap > 0 ) {
			update_post_meta( $event, '_pkit_rsvp_cap', $cap );
		}

		return $event;
	}

	private function mail_to( string $address ): ?array {
		foreach ( $this->mail as $mail ) {
			if ( in_array( $address, (array) $mail['to'], true ) ) {
				return $mail;
			}
		}

		return null;
	}

	/**
	 * Mail sent to anyone other than the site admin.
	 *
	 * @return array<int, array>
	 */
	private function mail_addressed_beyond_admin(): array {
		$admin = get_option( 'admin_email' );

		return array_values(
			array_filter(
				$this->mail,
				static fn ( array $m ): bool => (array) $m['to'] !== [ $admin ]
			)
		);
	}
}
