<?php
/**
 * One rule for which availability applies at a location, and which row wins (#98).
 *
 * Three paths used to answer these differently: get_for_location() applied
 * the #53 rule, the board hardcoded the version from before it, and
 * get_current() matched the location exactly. The board also chose each
 * product's row by best status rather than latest, so a correction made this
 * morning lost to last week's "abundant".
 *
 * Every assertion here is written as agreement between paths, because that is
 * what broke.
 */

declare(strict_types=1);

namespace ProducerKit\Tests\Integration;

use WP_REST_Request;
use WP_UnitTestCase;

use ProducerKit\Core\Availability;

class AvailabilityOneRuleTest extends WP_UnitTestCase {

	private int $stand;
	private int $shop;

	public function set_up(): void {
		parent::set_up();

		\ProducerKit\Core\Post_Types\register();
		\ProducerKit\Core\Taxonomies\register();
		Availability\create_table();

		$this->stand = $this->location( 'stand' );
		$this->shop  = $this->location( 'retailer' );
	}

	private function location( string $type ): int {
		$id = (int) self::factory()->post->create(
			[
				'post_type'   => 'pkit_location',
				'post_status' => 'publish',
			]
		);
		update_post_meta( $id, '_pkit_location_type', $type );
		return $id;
	}

	private function product( string $title ): int {
		return (int) self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'publish',
				'post_title'  => $title,
			]
		);
	}

	private function say( int $product, int $location, string $status, string $when = 'today' ): void {
		Availability\upsert(
			[
				'product_id'     => $product,
				'location_id'    => $location,
				'status'         => $status,
				'effective_date' => 'today' === $when ? current_time( 'Y-m-d' ) : gmdate( 'Y-m-d', (int) strtotime( $when ) ),
			]
		);
	}

	/**
	 * What the board says about each product at a location, by title.
	 *
	 * @return array<string, string>
	 */
	private function board( int $location, string $status = '' ): array {
		$request = new WP_REST_Request( 'GET', '/producerkit/v1/board' );
		$request->set_param( 'location', $location );
		if ( '' !== $status ) {
			$request->set_param( 'status', $status );
		}

		$out = [];
		foreach ( (array) ( rest_do_request( $request )->get_data()['groups'] ?? [] ) as $group ) {
			foreach ( $group['items'] as $item ) {
				$out[ get_the_title( (int) $item['product_id'] ) ] = $item['status'];
			}
		}
		return $out;
	}

	/**
	 * @return array<string, string>
	 */
	private function helper( int $location ): array {
		$out = [];
		foreach ( Availability\get_for_location( $location, true ) as $row ) {
			$out[ get_the_title( (int) $row->product_id ) ] = $row->status;
		}
		return $out;
	}

	/* ── Which rows apply ─────────────────────────────── */

	/**
	 * "Available everywhere I sell" applies at your own stand and not at a
	 * shop, which carries only what it stocks — on every path.
	 */
	public function test_a_general_row_applies_at_your_stand_and_not_at_a_shop(): void {
		$honey = $this->product( 'Honey' );
		$this->say( $honey, 0, 'available' );

		foreach ( [
			'stand' => [ $this->stand, true ],
			'shop'  => [ $this->shop, false ],
		] as $where => [ $location, $expected ] ) {
			$this->assertSame( $expected, isset( $this->helper( $location )['Honey'] ), "get_for_location() at the $where." );
			$this->assertSame( $expected, isset( $this->board( $location )['Honey'] ), "The board at the $where." );
			$this->assertSame( $expected, [] !== Availability\get_current( $honey, $location ), "get_current() at the $where." );
		}
	}

	/**
	 * The regression from #53, in the board: a shop's page listing what the
	 * producer sells rather than what the shop carries.
	 */
	public function test_a_board_on_a_shops_page_lists_only_what_the_shop_carries(): void {
		$this->say( $this->product( 'Honey' ), 0, 'available' );
		$this->say( $this->product( 'Jam' ), $this->shop, 'available' );

		$this->assertSame( [ 'Jam' => 'available' ], $this->board( $this->shop ) );
	}

	/* ── Which row wins ───────────────────────────────── */

	/**
	 * The board used to pick by best status, so this morning's sold out lost
	 * to last week's abundant.
	 */
	public function test_todays_correction_beats_last_weeks_statement_everywhere(): void {
		$honey = $this->product( 'Honey' );
		$this->say( $honey, $this->stand, 'abundant', '-7 days' );
		$this->say( $honey, $this->stand, 'sold_out' );

		$this->assertSame( 'sold_out', $this->helper( $this->stand )['Honey'] );
		$this->assertSame( 'sold_out', $this->board( $this->stand )['Honey'] );
		$this->assertSame( 'sold_out', Availability\get_current( $honey, $this->stand )[0]->status );
	}

	/**
	 * On the same day, what you said about this place beats what you said
	 * about everywhere.
	 */
	public function test_on_the_same_day_a_statement_about_this_place_wins(): void {
		$honey = $this->product( 'Honey' );
		$this->say( $honey, 0, 'available' );
		$this->say( $honey, $this->stand, 'limited' );

		$this->assertSame( 'limited', $this->helper( $this->stand )['Honey'] );
		$this->assertSame( 'limited', $this->board( $this->stand )['Honey'] );
		$this->assertSame( 'limited', Availability\get_current( $honey, $this->stand )[0]->status );
	}

	/**
	 * Filtering before choosing would let last week's abundant survive a
	 * filter that this morning's sold out did not, and win. The get-board
	 * ability passes a status, so an agent would have been told the honey was
	 * abundant.
	 */
	public function test_a_status_filter_never_resurrects_a_corrected_row(): void {
		$honey = $this->product( 'Honey' );
		$this->say( $honey, $this->stand, 'abundant', '-7 days' );
		$this->say( $honey, $this->stand, 'sold_out' );

		$this->assertArrayNotHasKey(
			'Honey',
			$this->board( $this->stand, 'abundant,available' ),
			'Sold out this morning, so it must not appear among what is abundant.'
		);
	}

	/* ── And the board still looks the same ───────────── */

	public function test_the_board_still_shows_best_news_first_then_alphabetically(): void {
		foreach ( [ [ 'Zucchini', 'abundant' ], [ 'Apples', 'limited' ], [ 'Beets', 'abundant' ], [ 'Carrots', 'available' ] ] as [ $title, $status ] ) {
			$this->say( $this->product( $title ), $this->stand, $status );
		}

		$this->assertSame(
			[ 'Beets', 'Zucchini', 'Carrots', 'Apples' ],
			array_keys( $this->board( $this->stand ) )
		);
	}
}
