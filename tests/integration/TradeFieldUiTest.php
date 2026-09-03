<?php
/**
 * Surfacing the trade fields a producer profile switches on.
 *
 * They could be entered and, until recently, not displayed. They still could
 * not be filtered on the board, and in the editor they appeared in WordPress's
 * own generic panels rather than beside the plugin's other product fields.
 */

declare(strict_types=1);

use ProducerKit\AvailabilityBoard\REST as Board;
use ProducerKit\ProducerProfiles\Profiles;
use ProducerKit\ProducerProfiles\Taxonomies as ProfileTaxonomies;

final class TradeFieldUiTest extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( Profiles\OPTION );

		foreach ( array_keys( Profiles\optional_taxonomies() ) as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				unregister_taxonomy( $taxonomy );
			}
		}

		parent::tear_down();
	}

	/* ── REST bases are public route names ────────────────────── */

	/**
	 * Pluralising by appending "s" gave `finishs`. A REST base is a public
	 * route name, so it is expensive to correct once anything consumes it.
	 */
	public function test_rest_bases_are_spelled_not_pluralised_by_rule(): void {
		$this->assertSame( 'materials', ProfileTaxonomies\rest_base_for( 'pkit_material' ) );
		$this->assertSame( 'finishes', ProfileTaxonomies\rest_base_for( 'pkit_finish' ) );
		$this->assertSame( 'components', ProfileTaxonomies\rest_base_for( 'pkit_component' ) );
	}

	public function test_the_registered_taxonomies_use_those_bases(): void {
		$this->activate( 'pottery' );

		$this->assertSame( 'finishes', get_taxonomy( 'pkit_finish' )->rest_base );
		$this->assertSame( 'materials', get_taxonomy( 'pkit_material' )->rest_base );
	}

	/* ── Board filter rows ────────────────────────────────────── */

	public function test_a_filter_row_is_offered_per_trade_field_in_use(): void {
		$this->activate( 'pottery' );

		$items = [
			[
				'traits' => [
					'pkit_material' => [ 'stoneware' ],
					'pkit_finish'   => [ 'ash-glaze' ],
				],
			],
			[
				'traits' => [
					'pkit_material' => [ 'porcelain' ],
					'pkit_finish'   => [ 'celadon' ],
				],
			],
		];

		$this->seed_terms(
			[
				'pkit_material' => [ 'Stoneware', 'Porcelain' ],
				'pkit_finish'   => [ 'Ash Glaze', 'Celadon' ],
			]
		);

		$rows = Board\collect_trait_filters( $items );

		$this->assertCount( 2, $rows );

		$labels = wp_list_pluck( $rows, 'label' );
		$this->assertContains( 'Clay Body', $labels, 'Labelled as the viewer reads it, not as the slug.' );
		$this->assertContains( 'Glaze', $labels );
	}

	/**
	 * A row with one option filters nothing; it is a control that cannot
	 * change what you see.
	 */
	public function test_a_field_with_one_value_gets_no_row(): void {
		$this->activate( 'pottery' );
		$this->seed_terms( [ 'pkit_material' => [ 'Stoneware' ] ] );

		$rows = Board\collect_trait_filters(
			[
				[ 'traits' => [ 'pkit_material' => [ 'stoneware' ] ] ],
				[ 'traits' => [ 'pkit_material' => [ 'stoneware' ] ] ],
			]
		);

		$this->assertSame( [], $rows );
	}

	/**
	 * Offering a term nothing on the board carries is a dead end, so rows are
	 * built from the items rather than the whole taxonomy.
	 */
	public function test_rows_list_only_terms_present_on_the_board(): void {
		$this->activate( 'pottery' );
		$this->seed_terms( [ 'pkit_material' => [ 'Stoneware', 'Porcelain', 'Earthenware' ] ] );

		$rows = Board\collect_trait_filters(
			[
				[ 'traits' => [ 'pkit_material' => [ 'stoneware' ] ] ],
				[ 'traits' => [ 'pkit_material' => [ 'porcelain' ] ] ],
			]
		);

		$slugs = wp_list_pluck( $rows[0]['terms'], 'slug' );

		$this->assertContains( 'stoneware', $slugs );
		$this->assertContains( 'porcelain', $slugs );
		$this->assertNotContains( 'earthenware', $slugs, 'Nothing on the board is earthenware.' );
	}

	public function test_a_farm_gets_no_trade_filters_at_all(): void {
		$this->activate( 'farm' );

		$this->assertSame( [], Board\collect_trait_filters( [ [ 'traits' => [] ] ] ) );
	}

	/* ── What an item carries ─────────────────────────────────── */

	public function test_an_item_reports_its_trade_terms_keyed_by_taxonomy(): void {
		$this->activate( 'pottery' );

		$product = self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'publish',
			]
		);
		wp_set_object_terms( $product, 'Stoneware', 'pkit_material' );

		$traits = Board\trait_slugs( $product );

		$this->assertSame( [ 'stoneware' ], $traits['pkit_material'] ?? null );
		$this->assertArrayNotHasKey( 'pkit_finish', $traits, 'An untagged field should not appear at all.' );
	}

	public function test_trade_terms_are_empty_when_no_profile_asks_for_them(): void {
		$this->activate( 'farm' );

		$product = self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'publish',
			]
		);

		$this->assertSame( [], Board\trait_slugs( $product ) );
	}

	/* ── Helpers ──────────────────────────────────────────────── */

	private function activate( string $slug ): void {
		update_option( Profiles\OPTION, [ $slug ] );

		foreach ( array_keys( Profiles\optional_taxonomies() ) as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				unregister_taxonomy( $taxonomy );
			}
		}

		ProfileTaxonomies\register();
	}

	/**
	 * @param array<string, string[]> $map Taxonomy => term names.
	 */
	private function seed_terms( array $map ): void {
		foreach ( $map as $taxonomy => $names ) {
			foreach ( $names as $name ) {
				if ( ! term_exists( $name, $taxonomy ) ) {
					wp_insert_term( $name, $taxonomy );
				}
			}
		}
	}
}
