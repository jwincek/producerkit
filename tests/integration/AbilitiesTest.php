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
		'producerkit/list-commissions',
		'producerkit/count-commissions-by-status',
		'producerkit/send-commission-quote',
		'producerkit/update-commission-status',
		'producerkit/list-event-rsvps',
		'producerkit/cancel-rsvp',
	];

	/** Staff-only abilities that must refuse anonymous callers. */
	private const STAFF_ONLY = [
		'producerkit/update-availability',
		'producerkit/toggle-stand-status',
		'producerkit/list-preorders',
		'producerkit/update-preorder-status',
		'producerkit/get-harvest-list',
		'producerkit/list-commissions',
		'producerkit/count-commissions-by-status',
		'producerkit/send-commission-quote',
		'producerkit/update-commission-status',
		'producerkit/list-event-rsvps',
		'producerkit/cancel-rsvp',
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
		foreach ( [ 'producerkit-products', 'producerkit-availability', 'producerkit-locations', 'producerkit-events', 'producerkit-preorders', 'producerkit-commissions' ] as $slug ) {
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

	/* ── Commissions and RSVPs were invisible to agents ───────── */

	/**
	 * Pre-orders had list + create + update-status from the start. Commissions
	 * arrived from a merge that predated the abilities work and had none, so a
	 * maker could ask about one request type and not the other.
	 */
	public function test_commissions_are_addressable(): void {
		foreach (
			[
				'producerkit/list-commissions',
				'producerkit/count-commissions-by-status',
				'producerkit/send-commission-quote',
				'producerkit/update-commission-status',
			] as $name
		) {
			$this->assertNotNull( wp_get_ability( $name ), "{$name} should be registered." );
		}
	}

	public function test_a_guest_list_is_addressable(): void {
		$this->assertNotNull( wp_get_ability( 'producerkit/list-event-rsvps' ) );
		$this->assertNotNull( wp_get_ability( 'producerkit/cancel-rsvp' ) );
	}

	/* ── The capability must match the screen guarding the same data ── */

	/**
	 * These rows hold a customer's name, email, phone and request, and quoting
	 * sets a binding price. An ability looser than its own admin screen is the
	 * same leak with a wider reach — and that drift has already happened once
	 * here, when a manage_cap() lived in an is_admin()-gated file.
	 *
	 * @dataProvider staff_ability_provider
	 */
	public function test_staff_abilities_refuse_a_contributor( string $name ): void {
		$ability = wp_get_ability( $name );
		$this->assertNotNull( $ability );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'contributor' ] ) );
		$this->assertNotTrue( $ability->check_permissions(), "{$name} must not be open to Contributor." );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$this->assertTrue( $ability->check_permissions(), "{$name} should be open to Editor." );

		wp_set_current_user( 0 );
	}

	public function staff_ability_provider(): array {
		return [
			'list commissions'  => [ 'producerkit/list-commissions' ],
			'count commissions' => [ 'producerkit/count-commissions-by-status' ],
			'send quote'        => [ 'producerkit/send-commission-quote' ],
			'update status'     => [ 'producerkit/update-commission-status' ],
			'list rsvps'        => [ 'producerkit/list-event-rsvps' ],
			'cancel rsvp'       => [ 'producerkit/cancel-rsvp' ],
		];
	}

	/**
	 * The visitor-facing one has to stay open — a guest has no account.
	 */
	public function test_rsvping_stays_open_to_a_visitor(): void {
		wp_set_current_user( 0 );

		$this->assertTrue( wp_get_ability( 'producerkit/rsvp-to-event' )->check_permissions() );
	}

	/* ── Tokens are capabilities, not fields ──────────────────── */

	/**
	 * A quote token lets its holder accept a binding price, and an RSVP token
	 * lets its holder cancel a booking. Both reach the person by email; neither
	 * belongs in a response an agent can read back.
	 */
	public function test_no_ability_schema_exposes_a_token(): void {
		foreach ( [ 'producerkit/list-commissions', 'producerkit/send-commission-quote', 'producerkit/list-event-rsvps' ] as $name ) {
			$schema = wp_json_encode( wp_get_ability( $name )->get_output_schema() );

			$this->assertStringNotContainsString( 'quote_token', (string) $schema, "{$name} leaks the quote token." );
			$this->assertStringNotContainsString( '"token"', (string) $schema, "{$name} leaks a token." );
		}
	}

	/**
	 * Quoting needs a price and a fresh token, which only send_quote()
	 * produces. Offering it as a plain status would let an agent create the
	 * same unrecoverable row one admin click used to.
	 */
	public function test_status_updates_cannot_reach_quoted(): void {
		$schema = wp_get_ability( 'producerkit/update-commission-status' )->get_input_schema();
		$enum   = $schema['properties']['status']['enum'] ?? [];

		$this->assertNotContains( 'quoted', $enum );
		$this->assertContains( 'accepted', $enum );
		$this->assertContains( 'cancelled', $enum );
	}
}
