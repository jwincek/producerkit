<?php
/**
 * Switching optional modules off (#89).
 *
 * Three of the four own tables holding a customer's name, email and phone, and
 * each owns the only screen that shows them. The control has to be honest
 * about that and must never delete anything — those are the properties worth
 * asserting, more than the mechanics of the option.
 *
 * The boot-with-each-off cases are the reason #70 had to land first: these
 * files are only visible to the suite now that the admin is loaded
 * unconditionally.
 */

declare(strict_types=1);

namespace ProducerKit\Tests\Integration;

use WP_UnitTestCase;

use ProducerKit\Modules;

class ModuleTogglesTest extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( Modules\OPTION );
		parent::tear_down();
	}

	/**
	 * Only the four. Producer Profiles especially is a vocabulary switch, not
	 * a feature: turning it off reverts every word on a pottery site.
	 */
	public function test_only_low_coupling_modules_are_offered(): void {
		$this->assertSame(
			[ 'pre-order', 'commissions', 'notifications', 'woocommerce' ],
			Modules\toggleable()
		);

		foreach ( [ 'core', 'producer-profiles', 'availability-board', 'event-manager', 'stand-status' ] as $slug ) {
			$this->assertNotContains( $slug, Modules\toggleable(), "{$slug} must not be toggleable." );
		}
	}

	public function test_a_disabled_module_drops_out_of_the_active_list(): void {
		$this->assertContains( 'pre-order', \ProducerKit\get_active_modules() );

		update_option( Modules\OPTION, [ 'pre-order' ] );

		$this->assertNotContains( 'pre-order', \ProducerKit\get_active_modules() );
		$this->assertFalse( \ProducerKit\is_module_active( 'pre-order' ) );
	}

	/**
	 * Code beats a click: a site with its own filter is not overridden by the
	 * dashboard, so the documented extension point keeps working.
	 */
	public function test_a_later_filter_still_wins_over_the_option(): void {
		update_option( Modules\OPTION, [ 'commissions' ] );

		$restore = static function ( array $active ): array {
			$active[] = 'commissions';
			return array_values( array_unique( $active ) );
		};

		add_filter( 'pkit_active_modules', $restore, 20 );

		$this->assertContains( 'commissions', \ProducerKit\get_active_modules() );

		remove_filter( 'pkit_active_modules', $restore, 20 );
	}

	/**
	 * A required module can never be switched off, whatever is in the option.
	 */
	public function test_core_survives_a_tampered_option(): void {
		update_option( Modules\OPTION, [ 'core', 'producer-profiles', 'pre-order' ] );

		$active = \ProducerKit\get_active_modules();

		$this->assertContains( 'core', $active );
		// Not toggleable, so the option must not reach it either.
		$this->assertContains( 'producer-profiles', $active );
		$this->assertNotContains( 'pre-order', $active );
	}

	/**
	 * The warning has to be built from real data, or it is decoration.
	 */
	public function test_usage_reports_a_page_that_would_go_blank(): void {
		self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:producerkit/preorder-form /-->',
			]
		);

		$usage = Modules\usage( 'pre-order' );

		$this->assertNotEmpty( $usage['lines'] );
		$this->assertStringContainsString( 'render nothing', implode( ' ', $usage['lines'] ) );
	}

	public function test_usage_is_quiet_when_nothing_depends_on_it(): void {
		$usage = Modules\usage( 'pre-order' );

		$this->assertSame( [], $usage['lines'] );
		$this->assertFalse( $usage['holds_data'] );
	}

	/**
	 * Switching off must not touch the data. This is the promise the notice
	 * makes in as many words.
	 */
	public function test_switching_off_deletes_nothing(): void {
		global $wpdb;

		if ( ! function_exists( '\\ProducerKit\\PreOrder\\Orders\\table_name' ) ) {
			$this->markTestSkipped( 'Pre-order module not loaded.' );
		}

		$table  = \ProducerKit\PreOrder\Orders\table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table name, counted in a test.
		$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		update_option( Modules\OPTION, [ 'pre-order' ] );

		$after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->assertSame( $before, $after, 'Switching a module off changed its table.' );
		$this->assertNotFalse(
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ),
			'Switching a module off dropped its table.'
		);
	}

	/**
	 * The reason #70 came first: with each of the four off, the plugin still
	 * has to boot and its screens still have to render.
	 *
	 * @dataProvider toggleable_modules
	 */
	public function test_the_dashboard_survives_each_module_being_off( string $slug ): void {
		update_option( Modules\OPTION, [ $slug ] );

		$this->assertNotContains( $slug, \ProducerKit\get_active_modules() );

		// The dashboard reads every module's usage while rendering.
		foreach ( Modules\toggleable() as $other ) {
			$usage = Modules\usage( $other );
			$this->assertIsArray( $usage['lines'], "usage({$other}) broke with {$slug} off." );
		}

		// And the guide, which resolves vocabulary from several modules.
		$this->assertNotSame( '', \ProducerKit\Guide\html(), "The guide broke with {$slug} off." );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function toggleable_modules(): array {
		return [
			'pre-order'     => [ 'pre-order' ],
			'commissions'   => [ 'commissions' ],
			'notifications' => [ 'notifications' ],
			'woocommerce'   => [ 'woocommerce' ],
		];
	}
}
