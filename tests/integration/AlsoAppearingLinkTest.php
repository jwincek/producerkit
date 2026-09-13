<?php
/**
 * "Sharing the booth" carrying a link (#76).
 *
 * Two producers who keep separate sites and share a stall have nowhere else to
 * say so: each site's availability board is correct about its own goods and
 * silent about the other's, so the event is where a visitor finds out.
 */

declare(strict_types=1);

namespace ProducerKit\Tests\Integration;

use WP_UnitTestCase;

use ProducerKit\EventManager\Meta;

class AlsoAppearingLinkTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		// reset_post_types() runs in the parent and unregisters everything,
		// taking registered meta and its sanitize callbacks with it.
		\ProducerKit\Core\Post_Types\register();
		\ProducerKit\Core\Taxonomies\register();
		Meta\register();
	}

	/**
	 * The case this exists for.
	 */
	public function test_a_name_and_address_become_a_named_link(): void {
		$parsed = Meta\also_appearing( 'Example Bakery https://bakery.example' );

		$this->assertSame( 'Example Bakery', $parsed['text'] );
		$this->assertSame( 'https://bakery.example', $parsed['url'] );
	}

	/**
	 * An address alone is labelled with its host — a bare URL is not what a
	 * visitor is looking for.
	 */
	public function test_a_bare_address_is_labelled_with_its_host(): void {
		$parsed = Meta\also_appearing( 'https://www.bakery.example/about' );

		$this->assertSame( 'bakery.example', $parsed['text'] );
		$this->assertSame( 'https://www.bakery.example/about', $parsed['url'] );
	}

	/**
	 * What everyone has stored today keeps working, unchanged.
	 */
	public function test_a_plain_name_is_untouched(): void {
		$parsed = Meta\also_appearing( 'Example Bakery' );

		$this->assertSame( 'Example Bakery', $parsed['text'] );
		$this->assertSame( '', $parsed['url'] );
	}

	public function test_empty_stays_empty(): void {
		$parsed = Meta\also_appearing( '   ' );

		$this->assertSame( '', $parsed['text'] );
		$this->assertSame( '', $parsed['url'] );
	}

	/**
	 * An address in the middle is part of what they wrote. Rewriting the
	 * sentence around it would be a guess.
	 */
	public function test_only_a_trailing_address_is_treated_as_the_link(): void {
		$parsed = Meta\also_appearing( 'See https://bakery.example for the bread' );

		$this->assertSame( 'See https://bakery.example for the bread', $parsed['text'] );
		$this->assertSame( '', $parsed['url'] );
	}

	/**
	 * This value becomes an href, so a scheme we would not render is dropped
	 * at save — the same reasoning as sanitize_ticket_url().
	 *
	 * @dataProvider dangerous_schemes
	 */
	public function test_a_dangerous_scheme_is_dropped_on_save( string $input ): void {
		$event = (int) self::factory()->post->create( [ 'post_type' => 'pkit_event' ] );

		update_post_meta( $event, '_pkit_also_appearing', $input );

		$stored = (string) get_post_meta( $event, '_pkit_also_appearing', true );
		$parsed = Meta\also_appearing( $stored );

		$this->assertSame( 'Example Bakery', $stored, 'The address should have been dropped, leaving the name.' );
		$this->assertSame( '', $parsed['url'], 'A rejected scheme must never become a link.' );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function dangerous_schemes(): array {
		return [
			'javascript' => [ 'Example Bakery javascript://alert(1)' ],
			'data'       => [ 'Example Bakery data://text/html,<script>' ],
			'ftp'        => [ 'Example Bakery ftp://bakery.example' ],
		];
	}

	/**
	 * Something merely scheme-ish is not an address at all.
	 *
	 * "javascript:alert(1)" has no //, so it never matches the link pattern.
	 * It stays as the text the producer typed and reaches the page through
	 * esc_html(), which is the right outcome: this is a name field, and
	 * mangling someone's words to defend against a string that was never
	 * going to be rendered as a link would be the wrong trade.
	 */
	public function test_a_scheme_without_slashes_stays_plain_text(): void {
		$parsed = Meta\also_appearing( 'Example Bakery javascript:alert(1)' );

		$this->assertSame( '', $parsed['url'], 'It must not become a link.' );
		$this->assertSame( 'Example Bakery javascript:alert(1)', $parsed['text'] );
	}

	/**
	 * A good address survives the round trip through meta.
	 */
	public function test_a_valid_link_round_trips_through_meta(): void {
		$event = (int) self::factory()->post->create( [ 'post_type' => 'pkit_event' ] );

		update_post_meta( $event, '_pkit_also_appearing', 'Example Bakery https://bakery.example' );

		$parsed = Meta\also_appearing( (string) get_post_meta( $event, '_pkit_also_appearing', true ) );

		$this->assertSame( 'Example Bakery', $parsed['text'] );
		$this->assertSame( 'https://bakery.example', $parsed['url'] );
	}

	/**
	 * And it reaches the page as an anchor, escaped.
	 */
	public function test_the_event_page_renders_an_anchor(): void {
		$event = (int) self::factory()->post->create(
			[
				'post_type'   => 'pkit_event',
				'post_status' => 'publish',
			]
		);

		update_post_meta( $event, '_pkit_start_datetime', '2026-10-03T09:00:00' );
		update_post_meta( $event, '_pkit_also_appearing', 'Example Bakery https://bakery.example' );

		$html = \ProducerKit\Core\SingleContent\render_event_details( get_post( $event ) );

		$this->assertStringContainsString( 'href="https://bakery.example"', $html );
		$this->assertStringContainsString( 'Example Bakery', $html );
	}

	/**
	 * A plain name still renders, and renders as text rather than a link.
	 */
	public function test_a_plain_name_renders_without_an_anchor(): void {
		$event = (int) self::factory()->post->create(
			[
				'post_type'   => 'pkit_event',
				'post_status' => 'publish',
			]
		);

		update_post_meta( $event, '_pkit_start_datetime', '2026-10-03T09:00:00' );
		update_post_meta( $event, '_pkit_also_appearing', 'Example Bakery' );

		$html = \ProducerKit\Core\SingleContent\render_event_details( get_post( $event ) );

		$this->assertStringContainsString( 'Example Bakery', $html );
		$this->assertStringNotContainsString( '<a href="http', $html );
	}
}
