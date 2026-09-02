<?php
/**
 * Producer profiles: the registry, the taxonomy re-labelling, and the
 * guarantee that switching a profile only ever adds.
 */

declare(strict_types=1);

use ProducerKit\ProducerProfiles\Profiles;
use ProducerKit\ProducerProfiles\Taxonomies as ProfileTaxonomies;

final class ProducerProfilesTest extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( Profiles\OPTION );

		// Registration in a test leaks into the next one otherwise.
		foreach ( array_keys( Profiles\optional_taxonomies() ) as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				unregister_taxonomy( $taxonomy );
			}
		}

		parent::tear_down();
	}

	/**
	 * Switch profile and register the optional taxonomies it asks for.
	 */
	private function activate( string $slug ): void {
		update_option( Profiles\OPTION, $slug );
		ProfileTaxonomies\register();
	}

	/* ── Registry ─────────────────────────────────────────────── */

	public function test_every_profile_file_is_well_formed(): void {
		$slugs = Profiles\get_slugs();
		$this->assertNotEmpty( $slugs, 'No producer profiles were discovered.' );

		foreach ( $slugs as $slug ) {
			$profile = Profiles\get( $slug );

			$this->assertIsArray( $profile, "Profile '{$slug}' did not return an array." );
			$this->assertNotSame( '', (string) $profile['label'], "Profile '{$slug}' has no label." );
			$this->assertIsArray( $profile['taxonomies'], "Profile '{$slug}' has a malformed taxonomies list." );
			$this->assertIsArray( $profile['names'], "Profile '{$slug}' has a malformed names map." );
			$this->assertIsArray( $profile['terms'], "Profile '{$slug}' has a malformed terms map." );

			// A profile may only re-label a taxonomy it actually switches on,
			// or one of core's own.
			$known = array_merge(
				(array) $profile['taxonomies'],
				[ 'pkit_product_type', 'pkit_season', 'pkit_event_type' ]
			);
			foreach ( array_keys( $profile['names'] ) as $taxonomy ) {
				$this->assertContains( $taxonomy, $known, "Profile '{$slug}' re-labels '{$taxonomy}', which it does not enable." );
			}
			foreach ( array_keys( $profile['terms'] ) as $taxonomy ) {
				$this->assertContains( $taxonomy, $known, "Profile '{$slug}' seeds '{$taxonomy}', which it does not enable." );
			}
		}
	}

	public function test_the_ported_craft_profiles_are_all_present(): void {
		$slugs = Profiles\get_slugs();

		foreach ( [ 'woodworking', 'pottery', 'jewelry', 'metalwork', 'fiber-arts', 'leather', 'general' ] as $craft ) {
			$this->assertContains( $craft, $slugs, "Craft profile '{$craft}' was not ported." );
		}

		foreach ( [ 'bakery', 'author', 'painting', 'screen-printing', 'taxidermy', 'comics' ] as $added ) {
			$this->assertContains( $added, $slugs, "Profile '{$added}' is missing." );
		}

		$this->assertContains( 'farm', $slugs );
		$this->assertContains( 'beekeeping', $slugs );
		$this->assertContains( 'musician', $slugs );
	}

	public function test_musician_relabels_the_optional_fields_for_a_merch_table(): void {
		$this->activate( 'musician' );

		$this->assertSame( 'Format', get_taxonomy( 'pkit_material' )->labels->singular_name );
		$this->assertSame( 'Edition', get_taxonomy( 'pkit_finish' )->labels->singular_name );
		$this->assertSame( 'Packaging', get_taxonomy( 'pkit_component' )->labels->singular_name );
	}

	/**
	 * A profile may blank a core taxonomy it has no use for. Growing seasons
	 * mean nothing on a tour schedule, so the musician profile seeds none —
	 * which must be distinguishable from not mentioning the taxonomy at all.
	 */
	public function test_a_profile_can_blank_a_core_taxonomy_it_does_not_use(): void {
		update_option( Profiles\OPTION, 'musician' );

		$this->assertSame(
			[],
			apply_filters( 'pkit_taxonomy_default_terms', [ 'Spring', 'Summer' ], 'pkit_season' ),
			'The musician profile should seed no growing seasons.'
		);

		// But it does have plenty to say about event types.
		$events = apply_filters( 'pkit_taxonomy_default_terms', [ 'Potluck' ], 'pkit_event_type' );
		$this->assertContains( 'Show', $events );
		$this->assertContains( 'Residency', $events );
		$this->assertNotContains( 'Potluck', $events );
	}

	public function test_default_profile_is_farm(): void {
		delete_option( Profiles\OPTION );
		$this->assertSame( [ 'farm' ], Profiles\active_slugs() );
	}

	public function test_unknown_profile_falls_back_to_the_default(): void {
		update_option( Profiles\OPTION, 'basket-weaving' );
		$this->assertSame( [ Profiles\DEFAULT_SLUG ], Profiles\active_slugs() );
	}

	/** Sites saved before multi-profile support stored a bare string. */
	public function test_a_legacy_single_string_option_still_reads(): void {
		update_option( Profiles\OPTION, 'pottery' );
		$this->assertSame( [ 'pottery' ], Profiles\active_slugs() );
	}

	/** A slug reaches the filesystem, so it must not be able to walk out of it. */
	public function test_profile_slug_cannot_traverse_directories(): void {
		$this->assertNull( Profiles\get( '../../../../etc/passwd' ) );
		$this->assertNull( Profiles\get( 'farm/../farm' ) );
		$this->assertNull( Profiles\get( 'Farm' ) );
	}

	/* ── Optional taxonomies ──────────────────────────────────── */

	public function test_farm_profile_enables_no_optional_taxonomies(): void {
		$this->activate( 'farm' );

		foreach ( array_keys( Profiles\optional_taxonomies() ) as $taxonomy ) {
			$this->assertFalse(
				taxonomy_exists( $taxonomy ),
				"A farm should not be shown the '{$taxonomy}' field."
			);
		}
	}

	public function test_beekeeping_enables_all_three_optional_taxonomies(): void {
		$this->activate( 'beekeeping' );

		foreach ( [ 'pkit_material', 'pkit_finish', 'pkit_component' ] as $taxonomy ) {
			$this->assertTrue( taxonomy_exists( $taxonomy ), "'{$taxonomy}' should be registered for a beekeeper." );
			$this->assertContains( 'pkit_product', get_taxonomy( $taxonomy )->object_type );
		}
	}

	/* ── Re-labelling ─────────────────────────────────────────── */

	public function test_beekeeping_relabels_material_as_floral_source(): void {
		$this->activate( 'beekeeping' );

		$labels = get_taxonomy( 'pkit_material' )->labels;
		$this->assertSame( 'Floral Source', $labels->singular_name );
		$this->assertSame( 'Floral Sources', $labels->name );
		// Derived labels follow the override, not the default.
		$this->assertSame( 'Search Floral Sources', $labels->search_items );
	}

	public function test_woodworking_relabels_material_as_wood_species(): void {
		$this->activate( 'woodworking' );
		$this->assertSame( 'Wood Species', get_taxonomy( 'pkit_material' )->labels->singular_name );
	}

	public function test_a_profile_without_an_override_keeps_the_default_name(): void {
		$this->activate( 'general' );
		$this->assertSame( 'Material', get_taxonomy( 'pkit_material' )->labels->singular_name );
	}

	public function test_relabelling_ignores_a_half_declared_override(): void {
		$names = ProfileTaxonomies\filter_names( [ 'Material', 'Materials' ], 'pkit_nonexistent' );
		$this->assertSame( [ 'Material', 'Materials' ], $names );
	}

	/* ── Seed terms ───────────────────────────────────────────── */

	public function test_profile_replaces_core_default_terms(): void {
		update_option( Profiles\OPTION, 'beekeeping' );

		$terms = apply_filters(
			'pkit_taxonomy_default_terms',
			[ 'Produce', 'Bread' ],
			'pkit_product_type'
		);

		$this->assertContains( 'Honey', $terms );
		$this->assertContains( 'Nucleus Colony', $terms );
		$this->assertNotContains( 'Produce', $terms, 'A beekeeper should not be seeded farm-stand produce.' );
	}

	public function test_general_profile_seeds_nothing(): void {
		update_option( Profiles\OPTION, 'general' );

		$this->assertSame(
			[],
			apply_filters( 'pkit_taxonomy_default_terms', [ 'Produce' ], 'pkit_product_type' ),
			'The blank-slate profile should seed no vocabulary.'
		);
	}

	public function test_a_taxonomy_the_profile_ignores_keeps_core_defaults(): void {
		update_option( Profiles\OPTION, 'woodworking' );

		// Woodworking says nothing about seasons, so core's list survives.
		$this->assertSame(
			[ 'Spring', 'Summer' ],
			apply_filters( 'pkit_taxonomy_default_terms', [ 'Spring', 'Summer' ], 'pkit_season' )
		);
	}

	/**
	 * The promise made on the settings screen: switching adds, never removes.
	 */
	public function test_switching_profiles_never_removes_existing_terms(): void {
		$this->activate( 'farm' );

		\ProducerKit\Core\Taxonomies\seed_terms( 'pkit_product_type', [ 'Produce', 'Bread' ] );
		$mine = wp_insert_term( 'Heirloom Tomatoes', 'pkit_product_type' );
		$this->assertIsArray( $mine );

		// Switch trades entirely.
		$this->activate( 'beekeeping' );
		\ProducerKit\Core\Taxonomies\seed_terms( 'pkit_product_type', [ 'Produce', 'Bread' ] );

		$this->assertNotEmpty( term_exists( 'Heirloom Tomatoes', 'pkit_product_type' ), 'A hand-added term was lost on switch.' );
		$this->assertNotEmpty( term_exists( 'Produce', 'pkit_product_type' ), 'A previously seeded term was lost on switch.' );
		$this->assertNotEmpty( term_exists( 'Honey', 'pkit_product_type' ), 'The new profile did not seed its own vocabulary.' );
	}

	/* ── Detail terms reach the templates ─────────────────────── */

	/**
	 * The bug this covers: a profile switched Clay Body and Glaze on, the
	 * editor let you fill them in, and then nothing on the site displayed
	 * them. Fields written and never read.
	 */
	public function test_profile_terms_are_exposed_for_display(): void {
		$this->activate( 'pottery' );

		$product = self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'publish',
			]
		);
		wp_set_object_terms( $product, 'Stoneware', 'pkit_material' );
		wp_set_object_terms( $product, 'Ash Glaze', 'pkit_finish' );

		$details = \ProducerKit\Core\Taxonomies\detail_terms( $product );

		$this->assertSame( [ 'Stoneware' ], $details['Clay Body'] ?? null );
		$this->assertSame( [ 'Ash Glaze' ], $details['Glaze'] ?? null );
	}

	public function test_a_taxonomy_with_no_terms_is_left_out(): void {
		$this->activate( 'pottery' );

		$product = self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'publish',
			]
		);
		wp_set_object_terms( $product, 'Porcelain', 'pkit_material' );

		$details = \ProducerKit\Core\Taxonomies\detail_terms( $product );

		$this->assertArrayHasKey( 'Clay Body', $details );
		$this->assertArrayNotHasKey( 'Glaze', $details, 'An untagged field should not render an empty row.' );
	}

	/**
	 * A farm switches none of them on, so templates must get nothing rather
	 * than a stray empty section.
	 */
	public function test_a_profile_with_no_extra_fields_exposes_nothing(): void {
		$this->activate( 'farm' );

		$product = self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'publish',
			]
		);

		$this->assertSame( [], \ProducerKit\Core\Taxonomies\detail_terms( $product ) );
	}

	/**
	 * Core must not depend on the producer-profiles module: with nothing
	 * answering the filter, the templates behave as they did before profiles
	 * existed.
	 */
	public function test_core_exposes_nothing_when_no_module_answers(): void {
		$this->activate( 'pottery' );

		$product = self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'publish',
			]
		);
		wp_set_object_terms( $product, 'Stoneware', 'pkit_material' );

		$suppress = static fn (): array => [];
		add_filter( 'pkit_detail_taxonomies', $suppress, 99 );

		$this->assertSame( [], \ProducerKit\Core\Taxonomies\detail_terms( $product ) );

		remove_filter( 'pkit_detail_taxonomies', $suppress, 99 );
	}

	public function test_seed_terms_is_idempotent(): void {
		$this->activate( 'farm' );

		\ProducerKit\Core\Taxonomies\seed_terms( 'pkit_product_type', [ 'Produce' ] );
		\ProducerKit\Core\Taxonomies\seed_terms( 'pkit_product_type', [ 'Produce' ] );

		$found = get_terms(
			[
				'taxonomy'   => 'pkit_product_type',
				'hide_empty' => false,
				'name'       => 'Produce',
			]
		);

		$this->assertCount( 1, $found, 'Re-seeding duplicated a term.' );
	}
}
