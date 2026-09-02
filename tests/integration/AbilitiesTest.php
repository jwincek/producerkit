<?php
/**
 * Abilities API surface: every ability registers, executes against its
 * declared schemas, and enforces its permission callback.
 *
 * This class is the early-warning system for core Abilities API drift
 * between WordPress releases (it would have caught the original
 * `callback` vs `execute_callback` breakage instantly).
 */

declare(strict_types=1);

final class AbilitiesTest extends WP_UnitTestCase {

	private const EXPECTED = [
		'producerkit/list-products',
		'producerkit/get-product-sources',
		'producerkit/get-availability',
		'producerkit/update-availability',
		'producerkit/list-locations',
		'producerkit/toggle-stand-status',
		'producerkit/get-stand-info',
		'producerkit/get-board',
		'producerkit/list-upcoming-events',
		'producerkit/rsvp-to-event',
		'producerkit/create-preorder',
		'producerkit/list-preorders',
		'producerkit/update-preorder-status',
		'producerkit/get-harvest-list',
	];

	/** Staff-only abilities that must refuse anonymous callers. */
	private const STAFF_ONLY = [
		'producerkit/update-availability',
		'producerkit/toggle-stand-status',
		'producerkit/list-preorders',
		'producerkit/update-preorder-status',
		'producerkit/get-harvest-list',
	];

	public function test_all_abilities_register(): void {
		$registered = array_keys(
			array_filter(
				wp_get_abilities(),
				fn ( $ability ) => str_starts_with( $ability->get_name(), 'producerkit/' ),
			)
		);
		sort( $registered );
		$expected = self::EXPECTED;
		sort( $expected );
		$this->assertSame( $expected, $registered );
	}

	public function test_ability_categories_register(): void {
		$categories = array_keys( \WP_Ability_Categories_Registry::get_instance()->get_all_registered() );
		foreach ( [ 'producerkit-products', 'producerkit-availability', 'producerkit-locations', 'producerkit-events', 'producerkit-preorders' ] as $slug ) {
			$this->assertContains( $slug, $categories );
		}
	}

	public function test_readonly_abilities_execute_and_validate_output(): void {
		// Fixture data so outputs are non-trivial.
		$product = self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'publish',
			]
		);
		update_post_meta( $product, '_pkit_price', '4.00' );
		self::factory()->post->create(
			[
				'post_type'   => 'pkit_location',
				'post_status' => 'publish',
			]
		);

		// Public readonly abilities with optional input must run with NO input:
		// execute() validates output against each declared schema, so a pass
		// here covers schema conformance too.
		foreach ( [ 'list-products', 'get-availability', 'list-locations', 'get-board', 'list-upcoming-events' ] as $short ) {
			$result = wp_get_ability( "producerkit/{$short}" )->execute();
			$this->assertNotWPError(
				$result,
				"producerkit/{$short} failed: "
				. ( is_wp_error( $result ) ? $result->get_error_message() : '' )
			);
		}
	}

	public function test_staff_abilities_refuse_anonymous(): void {
		wp_set_current_user( 0 );
		foreach ( self::STAFF_ONLY as $name ) {
			$ability = wp_get_ability( $name );
			$this->assertNotNull( $ability, "{$name} not registered" );
			$this->assertNotTrue( $ability->check_permissions(), "{$name} allowed anonymous access" );
		}
	}

	public function test_staff_abilities_allow_editors(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		foreach ( self::STAFF_ONLY as $name ) {
			$this->assertTrue( wp_get_ability( $name )->check_permissions(), "{$name} refused an editor" );
		}
	}
}
