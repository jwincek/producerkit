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
 * plural. WooCommerce also registers a top-level menu called "Products", and
 * two identical entries in one sidebar is a support question waiting to
 * happen — so this plugin's catalogue says "Catalog" in the menu while
 * remaining "Products" everywhere the word appears in context.
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
			'menu_icon'      => 'dashicons-archive',
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
	$labels = [
		'name'          => __( 'Events', 'producerkit' ),
		'singular_name' => __( 'Event', 'producerkit' ),
		'add_new_item'  => __( 'Add New Event', 'producerkit' ),
		'edit_item'     => __( 'Edit Event', 'producerkit' ),
		'new_item'      => __( 'New Event', 'producerkit' ),
		'view_item'     => __( 'View Event', 'producerkit' ),
		'search_items'  => __( 'Search Events', 'producerkit' ),
		'not_found'     => __( 'No events found.', 'producerkit' ),
		'all_items'     => __( 'All Events', 'producerkit' ),
		'menu_name'     => __( 'Events', 'producerkit' ),
	];

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
			'menu_position'  => 29,
			'supports'       => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
			'show_in_rest'   => true,
			'rest_base'      => 'events',
			'rest_namespace' => 'lfuf/v1',
		]
	);
}
