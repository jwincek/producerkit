<?php
/**
 * Four things any event can have.
 *
 * Written as musician fields to begin with — support acts, doors, an age
 * limit, a ticket link — until it became clear that two farmers sharing a
 * booth is a support act, and so is a co-teacher on a workshop. What they
 * record is the same in every trade; only the word changes, and that comes
 * from the producer profile.
 */

declare(strict_types=1);

use ProducerKit\Core\MetaLabels;
use ProducerKit\EventManager\Meta;

final class EventWhoAndWhenTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		\ProducerKit\Core\Post_Types\register();
		\ProducerKit\Core\Meta_Fields\register();
		Meta\register();
	}

	public function tear_down(): void {
		delete_option( 'pkit_producer_profile' );
		parent::tear_down();
	}

	/**
	 * A time on a day that is always ahead of today.
	 *
	 * These tests used to hardcode 2026-09-12, which worked until the day it
	 * did not: once that date was in the past the event dropped out of the
	 * upcoming feed, and the suite began failing on main for every pull
	 * request after it. A test that asserts something is upcoming cannot name
	 * a date.
	 */
	private function soon( string $time ): string {
		return gmdate( 'Y-m-d\\TH:i:s', (int) strtotime( '+2 weeks ' . $time ) );
	}

	private function event( string $start = '' ): int {
		$start = '' === $start ? gmdate( 'Y-m-d\\TH:i:s', strtotime( '+2 weeks 19:00' ) ) : $start;
		$id    = self::factory()->post->create(
			[
				'post_type'   => 'pkit_event',
				'post_status' => 'publish',
			]
		);

		update_post_meta( $id, '_pkit_start_datetime', $start );

		return $id;
	}

	/* ── The same field, different words ────────────────── */

	public function test_the_words_follow_the_trade(): void {
		update_option( 'pkit_producer_profile', [ 'musician' ] );
		$this->assertSame( 'Support Acts', MetaLabels\label( '_pkit_also_appearing' ) );
		$this->assertSame( 'Doors', MetaLabels\label( '_pkit_doors_datetime' ) );

		update_option( 'pkit_producer_profile', [ 'farm' ] );
		$this->assertSame( 'Sharing the booth', MetaLabels\label( '_pkit_also_appearing' ) );
		$this->assertSame( 'Gates open', MetaLabels\label( '_pkit_doors_datetime' ) );
	}

	public function test_a_trade_with_no_override_gets_the_neutral_word(): void {
		update_option( 'pkit_producer_profile', [ 'general' ] );

		$this->assertSame( 'Also appearing', MetaLabels\label( '_pkit_also_appearing' ) );
		$this->assertSame( 'Doors open', MetaLabels\label( '_pkit_doors_datetime' ) );
	}

	public function test_the_meta_keys_are_the_same_for_everyone(): void {
		// The whole point: one field, many words. If a profile ever changed
		// which key was written, the data would fork by trade.
		$id = $this->event();
		update_option( 'pkit_producer_profile', [ 'musician' ] );
		update_post_meta( $id, '_pkit_also_appearing', 'The Hold Steady' );

		update_option( 'pkit_producer_profile', [ 'farm' ] );

		$this->assertSame( 'The Hold Steady', get_post_meta( $id, '_pkit_also_appearing', true ) );
	}

	/* ── Doors ──────────────────────────────────────────── */

	public function test_doors_before_the_start_are_shown(): void {
		$id = $this->event( $this->soon( '19:00' ) );
		update_post_meta( $id, '_pkit_doors_datetime', $this->soon( '18:30' ) );

		$this->assertSame( $this->soon( '18:30' ), Meta\doors_datetime( $id ) );
	}

	public function test_doors_at_or_after_the_start_are_not_shown(): void {
		// Storing what was typed and declining to render nonsense beats
		// discarding their input on save or printing "Doors 9pm, starts 7pm".
		$id = $this->event( $this->soon( '19:00' ) );

		update_post_meta( $id, '_pkit_doors_datetime', $this->soon( '21:00' ) );
		$this->assertSame( '', Meta\doors_datetime( $id ) );

		update_post_meta( $id, '_pkit_doors_datetime', $this->soon( '19:00' ) );
		$this->assertSame( '', Meta\doors_datetime( $id ) );
	}

	public function test_what_was_typed_is_still_there(): void {
		// The value is kept so the editor can explain the problem. Silently
		// clearing it is the "control that did nothing" failure.
		$id = $this->event( $this->soon( '19:00' ) );
		update_post_meta( $id, '_pkit_doors_datetime', $this->soon( '21:00' ) );

		$this->assertSame( $this->soon( '21:00' ), get_post_meta( $id, '_pkit_doors_datetime', true ) );
	}

	public function test_doors_with_no_start_are_taken_at_face_value(): void {
		$id = self::factory()->post->create( [ 'post_type' => 'pkit_event' ] );
		update_post_meta( $id, '_pkit_doors_datetime', $this->soon( '18:30' ) );

		$this->assertSame( $this->soon( '18:30' ), Meta\doors_datetime( $id ) );
	}

	public function test_a_malformed_doors_value_is_refused(): void {
		$id = $this->event();
		update_post_meta( $id, '_pkit_doors_datetime', 'half past six' );

		$this->assertSame( '', get_post_meta( $id, '_pkit_doors_datetime', true ) );
	}

	/* ── Ticket link ────────────────────────────────────── */

	public function test_a_ticket_link_is_kept(): void {
		$id = $this->event();
		update_post_meta( $id, '_pkit_ticket_url', 'https://example.test/tickets' );

		$this->assertSame( 'https://example.test/tickets', get_post_meta( $id, '_pkit_ticket_url', true ) );
	}

	public function test_a_javascript_url_is_refused(): void {
		// It is rendered as an href, so this would be a hole rather than a typo.
		$id = $this->event();
		update_post_meta( $id, '_pkit_ticket_url', 'javascript:alert(1)' );

		$this->assertSame( '', get_post_meta( $id, '_pkit_ticket_url', true ) );
	}

	/* ── Age restriction ────────────────────────────────── */

	public function test_age_restriction_is_free_text(): void {
		// A dropdown would be wrong within a week: "18+", "All ages",
		// "Licensed premises", "Under-12s with an adult".
		$id = $this->event();

		foreach ( [ '18+', 'All ages', 'Under-12s with an adult' ] as $value ) {
			update_post_meta( $id, '_pkit_age_restriction', $value );
			$this->assertSame( $value, get_post_meta( $id, '_pkit_age_restriction', true ) );
		}
	}

	/* ── They have readers ──────────────────────────────── */

	public function test_the_rest_response_carries_them(): void {
		// The lesson from #36 and #37: a field with no reader, or a reader
		// with no field, is complete on every layer except a useful one.
		$id = $this->event( $this->soon( '19:00' ) );
		update_post_meta( $id, '_pkit_also_appearing', 'The Hold Steady' );
		update_post_meta( $id, '_pkit_doors_datetime', $this->soon( '18:30' ) );
		update_post_meta( $id, '_pkit_age_restriction', '18+' );
		update_post_meta( $id, '_pkit_ticket_url', 'https://example.test/tickets' );

		// Both readers: the detail route and the upcoming feed.
		$detail = rest_do_request(
			new WP_REST_Request( 'GET', '/producerkit/v1/events/' . $id . '/details' )
		)->get_data();

		$this->assertSame( 'The Hold Steady', $detail['event']['also_appearing'] );
		$this->assertSame( $this->soon( '18:30' ), $detail['event']['doors'] );
		$this->assertSame( '18+', $detail['event']['age_restriction'] );
		$this->assertSame( 'https://example.test/tickets', $detail['event']['ticket_url'] );

		$feed  = rest_do_request( new WP_REST_Request( 'GET', '/producerkit/v1/events/upcoming' ) )->get_data();
		$found = null;
		foreach ( (array) $feed as $item ) {
			if ( (int) ( $item['id'] ?? 0 ) === $id ) {
				$found = $item;
			}
		}

		$this->assertNotNull( $found, 'The event should be in the upcoming feed.' );
		$this->assertSame( 'The Hold Steady', $found['also_appearing'] );
		$this->assertSame( '18+', $found['age_restriction'] );
	}

	public function test_the_rest_response_omits_impossible_doors(): void {
		$id = $this->event( $this->soon( '19:00' ) );
		update_post_meta( $id, '_pkit_doors_datetime', $this->soon( '21:00' ) );

		$data = rest_do_request(
			new WP_REST_Request( 'GET', '/producerkit/v1/events/' . $id . '/details' )
		)->get_data();

		$this->assertSame( '', $data['event']['doors'] );
	}

	public function test_the_event_page_renders_them_under_the_trades_own_words(): void {
		update_option( 'pkit_producer_profile', [ 'musician' ] );

		$id = $this->event( $this->soon( '19:00' ) );
		update_post_meta( $id, '_pkit_also_appearing', 'The Hold Steady' );
		update_post_meta( $id, '_pkit_age_restriction', '18+' );
		update_post_meta( $id, '_pkit_ticket_url', 'https://example.test/tickets' );

		$html = \ProducerKit\Core\SingleContent\render_event_details( get_post( $id ) );

		$this->assertStringContainsString( 'Support Acts', $html );
		$this->assertStringContainsString( 'The Hold Steady', $html );
		$this->assertStringContainsString( '18+', $html );
		$this->assertStringContainsString( 'https://example.test/tickets', $html );
	}

	public function test_an_event_with_none_of_them_renders_none_of_them(): void {
		$html = \ProducerKit\Core\SingleContent\render_event_details( get_post( $this->event() ) );

		$this->assertStringNotContainsString( 'Also appearing', $html );
		$this->assertStringNotContainsString( 'Age restriction', $html );
		$this->assertStringNotContainsString( 'Buy tickets', $html );
	}
}
