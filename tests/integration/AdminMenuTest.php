<?php
/**
 * Where this plugin's post types appear in the admin menu, and what they are
 * called once WooCommerce is in the sidebar too.
 */

declare(strict_types=1);

use ProducerKit\Core\Post_Types;
use ProducerKit\ProducerProfiles\Profiles;

final class AdminMenuTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// WP_UnitTestCase::set_up() unregisters non-core post types.
		Post_Types\register();
	}

	public function tear_down(): void {
		delete_option( Profiles\OPTION );
		parent::tear_down();
	}

	/* ── Nesting ──────────────────────────────────────────────── */

	/**
	 * Nesting a post type costs its "Add New" entry and every one of its
	 * taxonomy submenus — core bails before building them. So only the types
	 * with no taxonomies are nested.
	 */
	public function test_only_the_taxonomy_free_types_are_nested(): void {
		foreach ( [ 'lfuf_source', 'lfuf_location' ] as $type ) {
			$this->assertSame(
				'farm-stand-dashboard',
				get_post_type_object( $type )->show_in_menu,
				"{$type} should sit under the ProducerKit menu."
			);

			$this->assertSame(
				[],
				get_object_taxonomies( $type ),
				"{$type} was nested, so it must not have taxonomies — nesting would hide them."
			);
		}
	}

	public function test_types_that_carry_taxonomies_stay_top_level(): void {
		foreach ( [ 'lfuf_product', 'lfuf_event' ] as $type ) {
			$this->assertTrue(
				get_post_type_object( $type )->show_in_menu,
				"{$type} carries taxonomies, which nesting would make unreachable from the menu."
			);

			$this->assertNotEmpty( get_object_taxonomies( $type ) );
		}
	}

	/**
	 * The list screen resolves its own parent, but post-new.php and post.php
	 * match no submenu entry and would leave the menu closed.
	 */
	public function test_the_parent_menu_stays_open_on_a_nested_add_screen(): void {
		global $typenow, $pagenow;

		$typenow = 'lfuf_location';
		$pagenow = 'post-new.php';
		$this->assertSame(
			'farm-stand-dashboard',
			apply_filters( 'parent_file', 'edit.php?post_type=lfuf_location' )
		);

		$pagenow = 'post.php';
		$this->assertSame(
			'farm-stand-dashboard',
			apply_filters( 'parent_file', 'edit.php?post_type=lfuf_location' )
		);

		// A type that was not nested must be left alone.
		$typenow = 'lfuf_product';
		$this->assertSame(
			'edit.php?post_type=lfuf_product',
			apply_filters( 'parent_file', 'edit.php?post_type=lfuf_product' )
		);

		$typenow = '';
		$pagenow = 'index.php';
	}

	/* ── Telling it apart from WooCommerce ────────────────────── */

	/**
	 * WooCommerce also registers a top-level menu called "Products". Two
	 * identical sidebar entries is a support question waiting to happen.
	 */
	public function test_the_catalogue_menu_label_differs_from_woocommerce(): void {
		$labels = get_post_type_object( 'lfuf_product' )->labels;

		$this->assertSame( 'Catalog', $labels->menu_name );
		$this->assertNotSame(
			'Products',
			$labels->menu_name,
			'This would collide with WooCommerce in the sidebar.'
		);
	}

	/**
	 * The Events Calendar also registers a top-level "Events". Both of the
	 * obvious names for this plugin's content are taken by plugins it is
	 * likely to sit beside.
	 */
	public function test_the_events_menu_label_differs_from_the_events_calendar(): void {
		$labels = get_post_type_object( 'lfuf_event' )->labels;

		$this->assertSame( 'Calendar', $labels->menu_name );
		$this->assertNotSame( 'Events', $labels->menu_name );
		$this->assertSame( 'Events', $labels->name, 'Still Events in a sentence.' );
		$this->assertSame( 'Add New Event', $labels->add_new_item );
	}

	/**
	 * The two top-level icons must not read as the same glyph as a neighbour's.
	 * A plain box collides with WooCommerce's Products icon.
	 */
	public function test_top_level_icons_are_distinct_from_each_other(): void {
		$icons = [
			get_post_type_object( 'lfuf_product' )->menu_icon,
			get_post_type_object( 'lfuf_event' )->menu_icon,
		];

		$this->assertSame( $icons, array_unique( $icons ) );
		$this->assertNotContains( 'dashicons-archive', $icons, 'Reads as WooCommerce’s Products box.' );
	}

	/**
	 * Only the sidebar word changes. In a sentence — "Add New Product",
	 * "No products found" — the ordinary word still reads better.
	 */
	public function test_the_word_product_survives_everywhere_else(): void {
		$labels = get_post_type_object( 'lfuf_product' )->labels;

		$this->assertSame( 'Products', $labels->name );
		$this->assertSame( 'Product', $labels->singular_name );
		$this->assertSame( 'Add New Product', $labels->add_new_item );
		$this->assertSame( 'All Products', $labels->all_items );
		$this->assertSame( 'No products found.', $labels->not_found );
	}

	/* ── Profile-driven wording ───────────────────────────────── */

	public function test_a_profile_can_reword_the_menu_without_touching_the_rest(): void {
		update_option( Profiles\OPTION, 'musician' );
		Post_Types\register();

		$labels = get_post_type_object( 'lfuf_product' )->labels;

		$this->assertSame( 'Merch', $labels->menu_name, 'A band calls the table merch.' );
		$this->assertSame( 'Product', $labels->singular_name, 'But it is still a product on the edit screen.' );
		$this->assertSame( 'Add New Product', $labels->add_new_item );

		// A profile may also override all three slots, not just the menu.
		$events = get_post_type_object( 'lfuf_event' )->labels;
		$this->assertSame( 'Shows', $events->menu_name );
		$this->assertSame( 'Show', $events->singular_name );
		$this->assertSame( 'Add New Show', $events->add_new_item );
	}

	public function test_a_profile_that_says_nothing_keeps_the_default(): void {
		update_option( Profiles\OPTION, 'pottery' );
		Post_Types\register();

		$this->assertSame( 'Catalog', get_post_type_object( 'lfuf_product' )->labels->menu_name );
	}

	public function test_labels_are_derived_from_the_filter(): void {
		$override = static fn ( array $names, string $type ): array =>
			'lfuf_product' === $type ? [ 'Widget', 'Widgets', 'Widgetry' ] : $names;

		add_filter( 'lfuf_post_type_names', $override, 10, 2 );
		Post_Types\register();

		$labels = get_post_type_object( 'lfuf_product' )->labels;
		$this->assertSame( 'Widgetry', $labels->menu_name );
		$this->assertSame( 'Add New Widget', $labels->add_new_item );
		$this->assertSame( 'No widgets found.', $labels->not_found );

		remove_filter( 'lfuf_post_type_names', $override, 10 );
	}
}
