<?php
/**
 * "Remove Sample Data" must remove the sample data and nothing else.
 *
 * The removal used to finish with two "delete anything orphaned" sweeps across
 * the availability and RSVP tables. Sample rows were gone either way — the
 * tables already clean themselves up on before_delete_post — but the sweeps
 * also collected rows left behind by real products and events the producer had
 * permanently deleted at some other time.
 */

declare(strict_types=1);

namespace ProducerKit\Tests\Integration;

use WP_UnitTestCase;

use ProducerKit\SampleData;
use ProducerKit\Core\Availability;


class SampleDataRemovalTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		// reset_post_types() runs in the parent and unregisters everything.
		\ProducerKit\Core\Post_Types\register();
		\ProducerKit\Core\Taxonomies\register();

		Availability\create_table();
	}

	private function make_product( bool $sample ): int {
		$id = (int) self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'publish',
			]
		);

		if ( $sample ) {
			update_post_meta( $id, SampleData\SAMPLE_META_KEY, '1' );
		}

		return $id;
	}

	private function availability_rows(): int {
		global $wpdb;

		$table = Availability\table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table in a test.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * The regression. A real product deleted at some other time leaves an
	 * orphaned availability row; removing sample data must not collect it.
	 */
	public function test_an_unrelated_orphan_row_survives_removal(): void {
		global $wpdb;

		$real = $this->make_product( false );

		Availability\upsert(
			[
				'product_id'     => $real,
				'status'         => 'available',
				'effective_date' => '2026-01-01',
			]
		);

		// Orphan it the way a permanent delete would, without firing the hook
		// that cleans up after itself — this is the state a site is left in
		// when rows outlive their post.
		$wpdb->update( $wpdb->posts, [ 'post_type' => 'revision' ], [ 'ID' => $real ] );
		wp_delete_post( $real, true );

		$this->assertSame( 1, $this->availability_rows(), 'Fixture did not produce an orphan.' );

		$sample = $this->make_product( true );
		update_option( 'pkit_sample_data_loaded', true );

		SampleData\remove_all();

		$this->assertSame(
			1,
			$this->availability_rows(),
			'Removing sample data collected an orphan belonging to a real product.'
		);

		$this->assertNull( get_post( $sample ), 'The sample product was not removed.' );
	}

	/**
	 * The sample rows themselves still go.
	 */
	public function test_sample_availability_rows_are_removed(): void {
		$sample = $this->make_product( true );

		Availability\upsert(
			[
				'product_id'     => $sample,
				'status'         => 'abundant',
				'effective_date' => '2026-01-01',
			]
		);

		$this->assertSame( 1, $this->availability_rows() );

		SampleData\remove_all();

		$this->assertSame( 0, $this->availability_rows(), 'Sample availability rows outlived removal.' );
	}

	/**
	 * A sample location takes its availability rows with it, matching what
	 * Availability\on_post_delete() does for any location.
	 */
	public function test_rows_at_a_sample_location_are_removed(): void {
		$product  = $this->make_product( false );
		$location = (int) self::factory()->post->create(
			[
				'post_type'   => 'pkit_location',
				'post_status' => 'publish',
			]
		);
		update_post_meta( $location, SampleData\SAMPLE_META_KEY, '1' );

		Availability\upsert(
			[
				'product_id'     => $product,
				'location_id'    => $location,
				'status'         => 'limited',
				'effective_date' => '2026-01-01',
			]
		);

		$this->assertSame( 1, $this->availability_rows() );

		SampleData\remove_all();

		$this->assertSame( 0, $this->availability_rows() );
		$this->assertNotNull( get_post( $product ), 'The real product should survive.' );
	}

	/**
	 * Removal used to stop at 200 posts per type.
	 */
	public function test_removal_is_not_capped(): void {
		for ( $i = 0; $i < 205; $i++ ) {
			$this->make_product( true );
		}

		SampleData\remove_all();

		$left = get_posts(
			[
				'post_type'     => 'pkit_product',
				'post_status'   => 'any',
				'numberposts'   => -1,
				'fields'        => 'ids',
				'no_found_rows' => true,
			]
		);

		$this->assertSame( [], $left, 'Removal left sample products behind.' );
	}
}
