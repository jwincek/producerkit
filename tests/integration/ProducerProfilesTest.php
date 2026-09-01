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
				[ 'lfuf_product_type', 'lfuf_season', 'lfuf_event_type' ]
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

		$this->assertContains( 'farm', $slugs );
		$this->assertContains( 'beekeeping', $slugs );
	}

	public function test_default_profile_is_farm(): void {
		delete_option( Profiles\OPTION );
		$this->assertSame( 'farm', Profiles\active_slug() );
	}

	public function test_unknown_profile_falls_back_to_the_default(): void {
		update_option( Profiles\OPTION, 'basket-weaving' );
		$this->assertSame( Profiles\DEFAULT_SLUG, Profiles\active_slug() );
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

		foreach ( [ 'lfuf_material', 'lfuf_finish', 'lfuf_component' ] as $taxonomy ) {
			$this->assertTrue( taxonomy_exists( $taxonomy ), "'{$taxonomy}' should be registered for a beekeeper." );
			$this->assertContains( 'lfuf_product', get_taxonomy( $taxonomy )->object_type );
		}
	}

	/* ── Re-labelling ─────────────────────────────────────────── */

	public function test_beekeeping_relabels_material_as_floral_source(): void {
		$this->activate( 'beekeeping' );

		$labels = get_taxonomy( 'lfuf_material' )->labels;
		$this->assertSame( 'Floral Source', $labels->singular_name );
		$this->assertSame( 'Floral Sources', $labels->name );
		// Derived labels follow the override, not the default.
		$this->assertSame( 'Search Floral Sources', $labels->search_items );
	}

	public function test_woodworking_relabels_material_as_wood_species(): void {
		$this->activate( 'woodworking' );
		$this->assertSame( 'Wood Species', get_taxonomy( 'lfuf_material' )->labels->singular_name );
	}

	public function test_a_profile_without_an_override_keeps_the_default_name(): void {
		$this->activate( 'general' );
		$this->assertSame( 'Material', get_taxonomy( 'lfuf_material' )->labels->singular_name );
	}

	public function test_relabelling_ignores_a_half_declared_override(): void {
		$names = ProfileTaxonomies\filter_names( [ 'Material', 'Materials' ], 'lfuf_nonexistent' );
		$this->assertSame( [ 'Material', 'Materials' ], $names );
	}

	/* ── Seed terms ───────────────────────────────────────────── */

	public function test_profile_replaces_core_default_terms(): void {
		update_option( Profiles\OPTION, 'beekeeping' );

		$terms = apply_filters(
			'lfuf_taxonomy_default_terms',
			[ 'Produce', 'Bread' ],
			'lfuf_product_type'
		);

		$this->assertContains( 'Honey', $terms );
		$this->assertContains( 'Nucleus Colony', $terms );
		$this->assertNotContains( 'Produce', $terms, 'A beekeeper should not be seeded farm-stand produce.' );
	}

	public function test_general_profile_seeds_nothing(): void {
		update_option( Profiles\OPTION, 'general' );

		$this->assertSame(
			[],
			apply_filters( 'lfuf_taxonomy_default_terms', [ 'Produce' ], 'lfuf_product_type' ),
			'The blank-slate profile should seed no vocabulary.'
		);
	}

	public function test_a_taxonomy_the_profile_ignores_keeps_core_defaults(): void {
		update_option( Profiles\OPTION, 'woodworking' );

		// Woodworking says nothing about seasons, so core's list survives.
		$this->assertSame(
			[ 'Spring', 'Summer' ],
			apply_filters( 'lfuf_taxonomy_default_terms', [ 'Spring', 'Summer' ], 'lfuf_season' )
		);
	}

	/**
	 * The promise made on the settings screen: switching adds, never removes.
	 */
	public function test_switching_profiles_never_removes_existing_terms(): void {
		$this->activate( 'farm' );

		\ProducerKit\Core\Taxonomies\seed_terms( 'lfuf_product_type', [ 'Produce', 'Bread' ] );
		$mine = wp_insert_term( 'Heirloom Tomatoes', 'lfuf_product_type' );
		$this->assertIsArray( $mine );

		// Switch trades entirely.
		$this->activate( 'beekeeping' );
		\ProducerKit\Core\Taxonomies\seed_terms( 'lfuf_product_type', [ 'Produce', 'Bread' ] );

		$this->assertNotEmpty( term_exists( 'Heirloom Tomatoes', 'lfuf_product_type' ), 'A hand-added term was lost on switch.' );
		$this->assertNotEmpty( term_exists( 'Produce', 'lfuf_product_type' ), 'A previously seeded term was lost on switch.' );
		$this->assertNotEmpty( term_exists( 'Honey', 'lfuf_product_type' ), 'The new profile did not seed its own vocabulary.' );
	}

	public function test_seed_terms_is_idempotent(): void {
		$this->activate( 'farm' );

		\ProducerKit\Core\Taxonomies\seed_terms( 'lfuf_product_type', [ 'Produce' ] );
		\ProducerKit\Core\Taxonomies\seed_terms( 'lfuf_product_type', [ 'Produce' ] );

		$found = get_terms(
			[
				'taxonomy'   => 'lfuf_product_type',
				'hide_empty' => false,
				'name'       => 'Produce',
			]
		);

		$this->assertCount( 1, $found, 'Re-seeding duplicated a term.' );
	}
}
