<?php
/**
 * Write-side meta sanitizers registered via register_post_meta — the
 * layer that keeps hostile values out of REST/ability output.
 */

declare(strict_types=1);

final class MetaSanitizersTest extends WP_UnitTestCase {

	private int $location;
	private int $event;

	public function set_up(): void {
		parent::set_up();
		$this->location = self::factory()->post->create(
			[
				'post_type'   => 'pkit_location',
				'post_status' => 'publish',
			]
		);
		$this->event    = self::factory()->post->create(
			[
				'post_type'   => 'pkit_event',
				'post_status' => 'publish',
			]
		);
	}

	/** @dataProvider handle_provider */
	public function test_venmo_handle( string $input, string $expected ): void {
		update_post_meta( $this->location, '_pkit_venmo_handle', $input );
		$this->assertSame( $expected, get_post_meta( $this->location, '_pkit_venmo_handle', true ) );
	}

	public function handle_provider(): array {
		return [
			'leading @ stripped'   => [ '@examplefarm', 'examplefarm' ],
			'charset enforced'     => [ 'evil/../x?q=1#f', 'evilxq1f' ],
			'markup stripped'      => [ '<script>x</script>', 'scriptxscript' ],
			'length capped at 30'  => [ str_repeat( 'a', 40 ), str_repeat( 'a', 30 ) ],
			'valid passes through' => [ 'good-handle_1', 'good-handle_1' ],
		];
	}

	/** @dataProvider url_provider */
	public function test_donation_link( string $input, string $expected ): void {
		update_post_meta( $this->event, '_pkit_donation_link', $input );
		$this->assertSame( $expected, get_post_meta( $this->event, '_pkit_donation_link', true ) );
	}

	public function url_provider(): array {
		return [
			'javascript scheme'  => [ 'javascript:alert(document.domain)', '' ],
			'data scheme'        => [ 'data:text/html,x', '' ],
			'https passes'       => [ 'https://venmo.com/examplefarm', 'https://venmo.com/examplefarm' ],
			'query preserved'    => [ 'https://ko-fi.com/farm?ref=1', 'https://ko-fi.com/farm?ref=1' ],
			'whitespace trimmed' => [ '  https://example.com/x  ', 'https://example.com/x' ],
		];
	}

	public function test_pickup_blackouts_drop_junk_and_sort(): void {
		update_post_meta( $this->location, '_pkit_pickup_blackouts', [ '2026-12-25', 'not-a-date', '<script>', '2026-11-26', '2026-12-25' ] );
		$this->assertSame(
			'["2026-11-26","2026-12-25"]',
			get_post_meta( $this->location, '_pkit_pickup_blackouts', true ),
		);
	}

	public function test_pickup_blackouts_accept_json_string_input(): void {
		update_post_meta( $this->location, '_pkit_pickup_blackouts', '["2027-01-01",""]' );
		$this->assertSame( '["2027-01-01"]', get_post_meta( $this->location, '_pkit_pickup_blackouts', true ) );
	}
}
