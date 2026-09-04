<?php
/**
 * The two location-scoped availability queries, which answer opposite halves
 * of the same customer question: "what is on the shelf in this shop" and
 * "which shop has this jar".
 *
 * Both hide sold-out rows by default, because the caller is normally someone
 * deciding where to drive.
 */

declare(strict_types=1);

use function ProducerKit\Core\Availability\get_for_location;
use function ProducerKit\Core\Availability\get_locations_for_product;
use function ProducerKit\Core\Availability\upsert;

final class StockedAtTest extends WP_UnitTestCase {

	private function make( string $type, string $title ): int {
		return self::factory()->post->create(
			[
				'post_type'   => $type,
				'post_status' => 'publish',
				'post_title'  => $title,
			]
		);
	}

	private function stock( int $product, int $location, string $status, string $note = '' ): void {
		upsert(
			[
				'product_id'     => $product,
				'location_id'    => $location,
				'status'         => $status,
				'quantity_note'  => $note,
				'effective_date' => current_time( 'Y-m-d' ),
			]
		);
	}

	/**
	 * @return array{0:int,1:int,2:int,3:int} [ shop_a, shop_b, bear, comb ]
	 */
	private function two_shops(): array {
		$shop_a = $this->make( 'pkit_location', 'Oil City Feed' );
		$shop_b = $this->make( 'pkit_location', 'Franklin Market' );
		$bear   = $this->make( 'pkit_product', 'Honey Bear' );
		$comb   = $this->make( 'pkit_product', 'Comb Honey' );

		$this->stock( $bear, $shop_a, 'abundant', 'two cases' );
		$this->stock( $comb, $shop_a, 'limited', '3 left' );
		$this->stock( $bear, $shop_b, 'available' );
		$this->stock( $comb, $shop_b, 'sold_out' );

		return [ $shop_a, $shop_b, $bear, $comb ];
	}

	public function test_shelf_lists_what_that_shop_has(): void {
		[ $shop_a, , $bear, $comb ] = $this->two_shops();

		$names = wp_list_pluck( get_for_location( $shop_a ), 'product_name' );

		$this->assertContains( 'Honey Bear', $names );
		$this->assertContains( 'Comb Honey', $names );
	}

	public function test_shelf_hides_sold_out_unless_asked(): void {
		[ , $shop_b ] = $this->two_shops();

		$this->assertSame(
			[ 'Honey Bear' ],
			wp_list_pluck( get_for_location( $shop_b ), 'product_name' )
		);

		$with = wp_list_pluck( get_for_location( $shop_b, true ), 'product_name' );
		$this->assertContains( 'Comb Honey', $with );
	}

	public function test_shelf_includes_rows_available_everywhere(): void {
		[ $shop_a ] = $this->two_shops();

		$wax = $this->make( 'pkit_product', 'Beeswax Block' );
		$this->stock( $wax, 0, 'available' );

		$this->assertContains(
			'Beeswax Block',
			wp_list_pluck( get_for_location( $shop_a ), 'product_name' ),
			'A product stocked everywhere is stocked here too.'
		);
	}

	public function test_shelf_excludes_other_shops(): void {
		[ $shop_a, $shop_b, , $comb ] = $this->two_shops();

		// Comb is sold out at B; it must not leak onto B's shelf from A's row.
		$this->assertNotContains(
			'Comb Honey',
			wp_list_pluck( get_for_location( $shop_b ), 'product_name' )
		);
		$this->assertContains(
			'Comb Honey',
			wp_list_pluck( get_for_location( $shop_a ), 'product_name' )
		);
	}

	public function test_shelf_skips_unpublished_products(): void {
		[ $shop_a, , $bear ] = $this->two_shops();

		wp_update_post(
			[
				'ID'          => $bear,
				'post_status' => 'draft',
			]
		);

		$this->assertNotContains(
			'Honey Bear',
			wp_list_pluck( get_for_location( $shop_a ), 'product_name' ),
			'A drafted product must not stay listed on a public shelf.'
		);
	}

	public function test_stocked_by_names_every_shop_that_has_it(): void {
		[ $shop_a, $shop_b, $bear ] = $this->two_shops();

		$ids = array_map( 'intval', wp_list_pluck( get_locations_for_product( $bear ), 'location_id' ) );

		sort( $ids );
		$expected = [ $shop_a, $shop_b ];
		sort( $expected );

		$this->assertSame( $expected, $ids );
	}

	public function test_stocked_by_drops_the_shop_that_sold_out(): void {
		[ $shop_a, , , $comb ] = $this->two_shops();

		$this->assertSame(
			[ $shop_a ],
			array_map( 'intval', wp_list_pluck( get_locations_for_product( $comb ), 'location_id' ) )
		);
	}

	public function test_stocked_by_ignores_the_everywhere_row(): void {
		$wax = $this->make( 'pkit_product', 'Beeswax Block' );
		$this->stock( $wax, 0, 'available' );

		$this->assertSame(
			[],
			get_locations_for_product( $wax ),
			'A general availability row names no shop, so it cannot answer "which shop".'
		);
	}

	public function test_one_row_per_product_and_per_location(): void {
		[ $shop_a, , $bear ] = $this->two_shops();

		// A correction made today replaces the earlier statement rather than
		// appearing beside it.
		$this->stock( $bear, $shop_a, 'limited', 'nearly out' );

		$shelf = array_values(
			array_filter(
				get_for_location( $shop_a ),
				static fn ( $row ): bool => (int) $row->product_id === $bear
			)
		);

		$this->assertCount( 1, $shelf );
		$this->assertSame( 'limited', $shelf[0]->status );
	}

	public function test_both_queries_refuse_a_meaningless_id(): void {
		$this->assertSame( [], get_for_location( 0 ) );
		$this->assertSame( [], get_for_location( -1 ) );
		$this->assertSame( [], get_locations_for_product( 0 ) );
		$this->assertSame( [], get_locations_for_product( -1 ) );
	}
}
