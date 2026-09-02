<?php
/**
 * Running more than one trade on a site.
 *
 * The split under test: what is *structural* — which fields exist, which
 * vocabulary is seeded — unions across every active profile and is one answer
 * for the whole install. What is *wording* cannot union, because there is a
 * single Material field with a single label, so it resolves per viewer
 * instead. Two people sharing an install each read their own trade's words
 * over the same underlying fields.
 */

declare(strict_types=1);

use ProducerKit\Core\Post_Types;
use ProducerKit\ProducerProfiles\Profiles;
use ProducerKit\ProducerProfiles\Taxonomies as ProfileTaxonomies;

final class MultiProfileTest extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( Profiles\OPTION );

		foreach ( array_keys( Profiles\optional_taxonomies() ) as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				unregister_taxonomy( $taxonomy );
			}
		}

		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * The case this was built for: a farm and a bakery on one site.
	 */
	private function farm_and_bakery(): void {
		update_option( Profiles\OPTION, [ 'farm', 'bakery' ] );
		ProfileTaxonomies\register();
		Post_Types\register();
	}

	/* ── Structure unions ─────────────────────────────────────── */

	public function test_fields_union_across_the_active_profiles(): void {
		// Farm alone wants none of the optional fields.
		update_option( Profiles\OPTION, [ 'farm' ] );
		$this->assertSame( [], Profiles\active_taxonomies() );

		// Adding the bakery brings its three along.
		$this->farm_and_bakery();

		$this->assertSame(
			[ 'pkit_material', 'pkit_finish', 'pkit_component' ],
			Profiles\active_taxonomies()
		);

		foreach ( [ 'pkit_material', 'pkit_finish', 'pkit_component' ] as $taxonomy ) {
			$this->assertTrue( taxonomy_exists( $taxonomy ) );
		}
	}

	public function test_seeded_vocabulary_unions_across_the_active_profiles(): void {
		$this->farm_and_bakery();

		$types = apply_filters( 'pkit_taxonomy_default_terms', [ 'CORE' ], 'pkit_product_type' );

		$this->assertContains( 'Produce', $types, 'The farm vocabulary should survive.' );
		$this->assertContains( 'Sourdough', $types, 'The bakery vocabulary should be added.' );
		$this->assertNotContains( 'CORE', $types );
	}

	public function test_a_term_both_profiles_name_is_not_duplicated(): void {
		update_option( Profiles\OPTION, [ 'bakery', 'musician' ] );

		$events = apply_filters( 'pkit_taxonomy_default_terms', [], 'pkit_event_type' );

		$this->assertSame( array_values( array_unique( $events ) ), $events );
		$this->assertContains( 'Market', $events, 'Both profiles list Market; it should appear once.' );
		$this->assertSame( 1, count( array_keys( $events, 'Market', true ) ) );
	}

	public function test_a_taxonomy_no_active_profile_mentions_keeps_core_defaults(): void {
		update_option( Profiles\OPTION, [ 'woodworking', 'pottery' ] );

		$this->assertSame(
			[ 'Spring', 'Summer' ],
			apply_filters( 'pkit_taxonomy_default_terms', [ 'Spring', 'Summer' ], 'pkit_season' )
		);
	}

	/* ── Wording resolves per viewer ──────────────────────────── */

	public function test_two_people_read_the_same_field_in_their_own_words(): void {
		$grower = self::factory()->user->create( [ 'role' => 'editor' ] );
		$baker  = self::factory()->user->create( [ 'role' => 'editor' ] );

		$this->farm_and_bakery();
		update_user_meta( $grower, Profiles\USER_META, 'farm' );
		update_user_meta( $baker, Profiles\USER_META, 'bakery' );

		wp_set_current_user( $baker );
		ProfileTaxonomies\register();
		$this->assertSame( 'Flour', get_taxonomy( 'pkit_material' )->labels->singular_name );

		unregister_taxonomy( 'pkit_material' );

		// The farm says nothing about Material, so the grower gets the generic
		// name rather than the baker's.
		wp_set_current_user( $grower );
		ProfileTaxonomies\register();
		$this->assertSame( 'Material', get_taxonomy( 'pkit_material' )->labels->singular_name );
	}

	public function test_the_menu_label_follows_the_person_too(): void {
		$baker = self::factory()->user->create( [ 'role' => 'editor' ] );

		update_option( Profiles\OPTION, [ 'farm', 'bakery' ] );
		update_user_meta( $baker, Profiles\USER_META, 'bakery' );

		wp_set_current_user( $baker );
		Post_Types\register();
		$this->assertSame( 'Bakery', get_post_type_object( 'pkit_product' )->labels->menu_name );

		wp_set_current_user( 0 );
		Post_Types\register();
		$this->assertSame( 'Catalog', get_post_type_object( 'pkit_product' )->labels->menu_name );
	}

	/**
	 * A logged-out visitor must not see whichever admin happened to log in
	 * last, so the front end resolves to the first active profile.
	 */
	public function test_the_front_end_is_deterministic(): void {
		update_option( Profiles\OPTION, [ 'bakery', 'farm' ] );
		wp_set_current_user( 0 );

		$this->assertSame( 'bakery', Profiles\labelling_slug() );
	}

	/**
	 * Turning a trade off must not strand whoever was reading in its words.
	 */
	public function test_a_person_on_a_retired_profile_falls_back(): void {
		$baker = self::factory()->user->create( [ 'role' => 'editor' ] );
		update_user_meta( $baker, Profiles\USER_META, 'bakery' );
		wp_set_current_user( $baker );

		update_option( Profiles\OPTION, [ 'farm' ] );

		$this->assertSame( 'farm', Profiles\labelling_slug() );
		$this->assertSame( '', Profiles\user_slug( $baker ), 'The stale choice should not be reported as theirs.' );
	}

	public function test_someone_who_has_chosen_nothing_follows_the_site(): void {
		$user = self::factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $user );

		update_option( Profiles\OPTION, [ 'farm', 'bakery' ] );

		$this->assertSame( 'farm', Profiles\labelling_slug() );
	}

	public function test_the_labelling_profile_is_filterable(): void {
		update_option( Profiles\OPTION, [ 'farm', 'bakery' ] );

		$override = static fn (): string => 'bakery';
		add_filter( 'pkit_labelling_profile', $override );

		$this->assertSame( 'bakery', Profiles\labelling_slug() );

		remove_filter( 'pkit_labelling_profile', $override );
	}

	/* ── Degenerate input ─────────────────────────────────────── */

	public function test_an_empty_selection_falls_back_rather_than_stripping_every_label(): void {
		update_option( Profiles\OPTION, [] );

		$this->assertSame( [ Profiles\DEFAULT_SLUG ], Profiles\active_slugs() );
	}

	public function test_unknown_slugs_are_dropped_and_the_rest_kept(): void {
		update_option( Profiles\OPTION, [ 'bakery', 'basket-weaving', 'farm' ] );

		$this->assertSame( [ 'bakery', 'farm' ], Profiles\active_slugs() );
	}
}
