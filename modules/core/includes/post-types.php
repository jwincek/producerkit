<?php
/**
 * Custom Post Types: lfuf_product, lfuf_source, lfuf_location, lfuf_event
 */

declare(strict_types=1);

namespace ProducerKit\Core\Post_Types;

defined( 'ABSPATH' ) || exit;

/**
 * Post types shown as submenus of the ProducerKit menu rather than as their
 * own top-level item.
 *
 * Only types with no taxonomies belong here. Nesting makes core skip building
 * the type's menu entirely — no "Add New", and no taxonomy submenus — so a
 * nested type that owns taxonomies would put them out of reach.
 *
 * @return string[]
 */
function nested_post_types(): array {
	return [ 'lfuf_source', 'lfuf_location' ];
}

// Registered unconditionally: parent_file only ever runs in the admin, so
// gating it behind is_admin() would buy nothing and cost a test.
add_filter( 'parent_file', __NAMESPACE__ . '\\keep_parent_open' );

add_filter( 'custom_menu_order', '__return_true' );
add_filter( 'menu_order', __NAMESPACE__ . '\\group_menu_items' );

/**
 * The plugin's top-level menu items, in the order they should appear.
 *
 * @return string[]
 */
function grouped_menu_items(): array {
	return [
		'farm-stand-dashboard',
		'edit.php?post_type=lfuf_product',
		'edit.php?post_type=lfuf_event',
	];
}

/**
 * Keep this plugin's top-level items together.
 *
 * menu_position cannot do this. A plugin that does not set one is placed at
 * ++$_wp_last_object_menu, which starts at 25 — the same range any sensible
 * choice for a content plugin lands in — so on a busy site a neighbour drops
 * between Catalog and Calendar and there is nothing a number can do about it.
 * Reordering after the fact is the only approach that actually holds.
 *
 * Additive: items not ours keep their relative order, and anything we cannot
 * find (a module switched off, a post type unregistered) is simply skipped.
 *
 * @param string[] $order Top-level menu slugs.
 * @return string[]
 */
function group_menu_items( array $order ): array {
	$ours = array_values( array_intersect( grouped_menu_items(), $order ) );

	// Nothing to do if the parent is absent — without an anchor there is no
	// meaningful place to gather them.
	if ( count( $ours ) < 2 || ! in_array( 'farm-stand-dashboard', $ours, true ) ) {
		return $order;
	}

	$rest   = array_values( array_diff( $order, $ours ) );
	$anchor = array_search( 'farm-stand-dashboard', $order, true );

	// Re-insert the group where the parent already sat, so this respects the
	// parent's menu_position rather than overriding it.
	$before = array_slice( $rest, 0, count( array_filter( array_slice( $order, 0, (int) $anchor ), static fn ( $slug ) => ! in_array( $slug, $ours, true ) ) ) );
	$after  = array_slice( $rest, count( $before ) );

	return array_merge( $before, $ours, $after );
}

/**
 * Keep the ProducerKit menu highlighted on a nested type's add/edit screens.
 *
 * get_admin_page_parent() resolves the parent by looking for a submenu entry
 * matching "$pagenow?post_type=$typenow". Nesting a post type creates exactly
 * one such entry — the list screen — so edit.php resolves correctly while
 * post-new.php and post.php match nothing and leave the menu closed.
 */
function keep_parent_open( string $parent_file ): string {
	global $typenow, $pagenow;

	if (
		in_array( $typenow, nested_post_types(), true )
		&& in_array( $pagenow, [ 'post-new.php', 'post.php' ], true )
	) {
		return 'farm-stand-dashboard';
	}

	return $parent_file;
}

function register(): void {
	register_product();
	register_source();
	register_location();
	register_event();
}

/**
 * Build a post type's label set from a singular/plural pair, plus the
 * separate word that goes in the sidebar.
 *
 * menu_name is deliberately its own value rather than defaulting to the
 * plural, because the two obvious names for this plugin's content are both
 * taken by plugins it is likely to sit beside. WooCommerce registers a
 * top-level "Products"; The Events Calendar registers a top-level "Events".
 * Two identical entries in one sidebar is a support question waiting to
 * happen, so the menu says "Catalog" and "Calendar" while the content stays
 * Products and Events everywhere the word appears in a sentence.
 *
 * Filterable so the producer-profiles module can re-word it per trade, the
 * same way it re-words the taxonomies.
 *
 * @param string $post_type Slug, passed to the filter for context.
 * @param string $singular  Default singular name.
 * @param string $plural    Default plural name.
 * @param string $menu      Default sidebar label.
 * @return array<string, string>
 */
function build_labels( string $post_type, string $singular, string $plural, string $menu ): array {
	/**
	 * Filters the display names for one of the plugin's post types.
	 *
	 * @param array{0: string, 1: string, 2: string} $names     [ singular, plural, menu_name ].
	 * @param string                                 $post_type Post type slug.
	 */
	$names = apply_filters( 'lfuf_post_type_names', [ $singular, $plural, $menu ], $post_type );

	$singular = (string) ( $names[0] ?? $singular );
	$plural   = (string) ( $names[1] ?? $plural );
	$menu     = (string) ( $names[2] ?? $menu );

	return [
		'name'               => $plural,
		'singular_name'      => $singular,
		/* translators: %s: singular post type name. */
		'add_new_item'       => sprintf( __( 'Add New %s', 'producerkit' ), $singular ),
		/* translators: %s: singular post type name. */
		'edit_item'          => sprintf( __( 'Edit %s', 'producerkit' ), $singular ),
		/* translators: %s: singular post type name. */
		'new_item'           => sprintf( __( 'New %s', 'producerkit' ), $singular ),
		/* translators: %s: singular post type name. */
		'view_item'          => sprintf( __( 'View %s', 'producerkit' ), $singular ),
		/* translators: %s: plural post type name. */
		'search_items'       => sprintf( __( 'Search %s', 'producerkit' ), $plural ),
		/* translators: %s: plural post type name, lowercased. */
		'not_found'          => sprintf( __( 'No %s found.', 'producerkit' ), mb_strtolower( $plural ) ),
		/* translators: %s: plural post type name, lowercased. */
		'not_found_in_trash' => sprintf( __( 'No %s found in Trash.', 'producerkit' ), mb_strtolower( $plural ) ),
		/* translators: %s: plural post type name. */
		'all_items'          => sprintf( __( 'All %s', 'producerkit' ), $plural ),
		'menu_name'          => $menu,
	];
}

/* ───────────────────────────────────────────────
 * Product — anything grown, baked, or sold.
 * ─────────────────────────────────────────────── */
function register_product(): void {
	$labels = build_labels(
		'lfuf_product',
		__( 'Product', 'producerkit' ),
		__( 'Products', 'producerkit' ),
		__( 'Catalog', 'producerkit' )
	);

	register_post_type(
		'lfuf_product',
		[
			'labels'         => $labels,
			'public'         => true,
			'has_archive'    => false,
			'rewrite'        => [
				'slug'       => 'products',
				'with_front' => false,
			],
			'menu_icon'      => 'dashicons-tag',
			'menu_position'  => 26,
			'supports'       => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
			'show_in_rest'   => true,
			'rest_base'      => 'products',
			'rest_namespace' => 'lfuf/v1',
			'template'       => [],
			'template_lock'  => false,
		]
	);
}

/* ───────────────────────────────────────────────
 * Source — a grain origin, partner farm, etc.
 * ─────────────────────────────────────────────── */
function register_source(): void {
	$labels = [
		'name'          => __( 'Sources', 'producerkit' ),
		'singular_name' => __( 'Source', 'producerkit' ),
		'add_new_item'  => __( 'Add New Source', 'producerkit' ),
		'edit_item'     => __( 'Edit Source', 'producerkit' ),
		'new_item'      => __( 'New Source', 'producerkit' ),
		'view_item'     => __( 'View Source', 'producerkit' ),
		'search_items'  => __( 'Search Sources', 'producerkit' ),
		'not_found'     => __( 'No sources found.', 'producerkit' ),
		'all_items'     => __( 'All Sources', 'producerkit' ),
		'menu_name'     => __( 'Sources', 'producerkit' ),
	];

	register_post_type(
		'lfuf_source',
		[
			'labels'         => $labels,
			'public'         => true,
			'has_archive'    => false,
			'rewrite'        => [
				'slug'       => 'sources',
				'with_front' => false,
			],
			// Nested under the ProducerKit menu: no taxonomies to lose, and
			// these are configured once rather than worked in daily.
			'show_in_menu'   => 'farm-stand-dashboard',
			'supports'       => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
			'show_in_rest'   => true,
			'rest_base'      => 'sources',
			'rest_namespace' => 'lfuf/v1',
		]
	);
}

/* ───────────────────────────────────────────────
 * Location — sales channels (stand, market, farm).
 * ─────────────────────────────────────────────── */
function register_location(): void {
	$labels = [
		'name'          => __( 'Locations', 'producerkit' ),
		'singular_name' => __( 'Location', 'producerkit' ),
		'add_new_item'  => __( 'Add New Location', 'producerkit' ),
		'edit_item'     => __( 'Edit Location', 'producerkit' ),
		'new_item'      => __( 'New Location', 'producerkit' ),
		'view_item'     => __( 'View Location', 'producerkit' ),
		'search_items'  => __( 'Search Locations', 'producerkit' ),
		'not_found'     => __( 'No locations found.', 'producerkit' ),
		'all_items'     => __( 'All Locations', 'producerkit' ),
		'menu_name'     => __( 'Locations', 'producerkit' ),
	];

	register_post_type(
		'lfuf_location',
		[
			'labels'         => $labels,
			'public'         => true,
			'has_archive'    => false,
			'rewrite'        => [
				'slug'       => 'locations',
				'with_front' => false,
			],
			// Nested under the ProducerKit menu: no taxonomies to lose, and
			// these are configured once rather than worked in daily.
			'show_in_menu'   => 'farm-stand-dashboard',
			'supports'       => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
			'show_in_rest'   => true,
			'rest_base'      => 'locations',
			'rest_namespace' => 'lfuf/v1',
		]
	);
}

/* ───────────────────────────────────────────────
 * Event — pizza nights, potlucks, farm dinners.
 * ─────────────────────────────────────────────── */
function register_event(): void {
	$labels = build_labels(
		'lfuf_event',
		__( 'Event', 'producerkit' ),
		__( 'Events', 'producerkit' ),
		__( 'Calendar', 'producerkit' )
	);

	register_post_type(
		'lfuf_event',
		[
			'labels'         => $labels,
			'public'         => true,
			'has_archive'    => false,
			'rewrite'        => [
				'slug'       => 'events',
				'with_front' => false,
			],
			'menu_icon'      => 'dashicons-calendar-alt',
			'menu_position'  => 27,
			'supports'       => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
			'show_in_rest'   => true,
			'rest_base'      => 'events',
			'rest_namespace' => 'lfuf/v1',
		]
	);
}
