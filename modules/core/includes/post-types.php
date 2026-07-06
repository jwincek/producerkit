<?php
/**
 * Custom Post Types: lfuf_product, lfuf_source, lfuf_location, lfuf_event
 */

declare(strict_types=1);

namespace Leftfield\Core\Post_Types;

defined('ABSPATH') || exit;

function register(): void {
    register_product();
    register_source();
    register_location();
    register_event();
}

/* ───────────────────────────────────────────────
 * Product — anything grown, baked, or sold.
 * ─────────────────────────────────────────────── */
function register_product(): void {
    $labels = [
        'name'                  => __('Products', 'farm-stand-manager'),
        'singular_name'         => __('Product', 'farm-stand-manager'),
        'add_new_item'          => __('Add New Product', 'farm-stand-manager'),
        'edit_item'             => __('Edit Product', 'farm-stand-manager'),
        'new_item'              => __('New Product', 'farm-stand-manager'),
        'view_item'             => __('View Product', 'farm-stand-manager'),
        'search_items'          => __('Search Products', 'farm-stand-manager'),
        'not_found'             => __('No products found.', 'farm-stand-manager'),
        'not_found_in_trash'    => __('No products found in Trash.', 'farm-stand-manager'),
        'all_items'             => __('All Products', 'farm-stand-manager'),
        'menu_name'             => __('Products', 'farm-stand-manager'),
    ];

    register_post_type('lfuf_product', [
        'labels'              => $labels,
        'public'              => true,
        'has_archive'         => false,
        'rewrite'             => ['slug' => 'products', 'with_front' => false],
        'menu_icon'           => 'dashicons-carrot',
        'menu_position'       => 26,
        'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
        'show_in_rest'        => true,
        'rest_base'           => 'products',
        'rest_namespace'      => 'lfuf/v1',
        'template'            => [],
        'template_lock'       => false,
    ]);
}

/* ───────────────────────────────────────────────
 * Source — a grain origin, partner farm, etc.
 * ─────────────────────────────────────────────── */
function register_source(): void {
    $labels = [
        'name'                  => __('Sources', 'farm-stand-manager'),
        'singular_name'         => __('Source', 'farm-stand-manager'),
        'add_new_item'          => __('Add New Source', 'farm-stand-manager'),
        'edit_item'             => __('Edit Source', 'farm-stand-manager'),
        'new_item'              => __('New Source', 'farm-stand-manager'),
        'view_item'             => __('View Source', 'farm-stand-manager'),
        'search_items'          => __('Search Sources', 'farm-stand-manager'),
        'not_found'             => __('No sources found.', 'farm-stand-manager'),
        'all_items'             => __('All Sources', 'farm-stand-manager'),
        'menu_name'             => __('Sources', 'farm-stand-manager'),
    ];

    register_post_type('lfuf_source', [
        'labels'              => $labels,
        'public'              => true,
        'has_archive'         => false,
        'rewrite'             => ['slug' => 'sources', 'with_front' => false],
        'menu_icon'           => 'dashicons-location-alt',
        'menu_position'       => 27,
        'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
        'show_in_rest'        => true,
        'rest_base'           => 'sources',
        'rest_namespace'      => 'lfuf/v1',
    ]);
}

/* ───────────────────────────────────────────────
 * Location — sales channels (stand, market, farm).
 * ─────────────────────────────────────────────── */
function register_location(): void {
    $labels = [
        'name'                  => __('Locations', 'farm-stand-manager'),
        'singular_name'         => __('Location', 'farm-stand-manager'),
        'add_new_item'          => __('Add New Location', 'farm-stand-manager'),
        'edit_item'             => __('Edit Location', 'farm-stand-manager'),
        'new_item'              => __('New Location', 'farm-stand-manager'),
        'view_item'             => __('View Location', 'farm-stand-manager'),
        'search_items'          => __('Search Locations', 'farm-stand-manager'),
        'not_found'             => __('No locations found.', 'farm-stand-manager'),
        'all_items'             => __('All Locations', 'farm-stand-manager'),
        'menu_name'             => __('Locations', 'farm-stand-manager'),
    ];

    register_post_type('lfuf_location', [
        'labels'              => $labels,
        'public'              => true,
        'has_archive'         => false,
        'rewrite'             => ['slug' => 'locations', 'with_front' => false],
        'menu_icon'           => 'dashicons-store',
        'menu_position'       => 28,
        'supports'            => ['title', 'editor', 'thumbnail', 'custom-fields'],
        'show_in_rest'        => true,
        'rest_base'           => 'locations',
        'rest_namespace'      => 'lfuf/v1',
    ]);
}

/* ───────────────────────────────────────────────
 * Event — pizza nights, potlucks, farm dinners.
 * ─────────────────────────────────────────────── */
function register_event(): void {
    $labels = [
        'name'                  => __('Events', 'farm-stand-manager'),
        'singular_name'         => __('Event', 'farm-stand-manager'),
        'add_new_item'          => __('Add New Event', 'farm-stand-manager'),
        'edit_item'             => __('Edit Event', 'farm-stand-manager'),
        'new_item'              => __('New Event', 'farm-stand-manager'),
        'view_item'             => __('View Event', 'farm-stand-manager'),
        'search_items'          => __('Search Events', 'farm-stand-manager'),
        'not_found'             => __('No events found.', 'farm-stand-manager'),
        'all_items'             => __('All Events', 'farm-stand-manager'),
        'menu_name'             => __('Events', 'farm-stand-manager'),
    ];

    register_post_type('lfuf_event', [
        'labels'              => $labels,
        'public'              => true,
        'has_archive'         => false,
        'rewrite'             => ['slug' => 'events', 'with_front' => false],
        'menu_icon'           => 'dashicons-calendar-alt',
        'menu_position'       => 29,
        'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
        'show_in_rest'        => true,
        'rest_base'           => 'events',
        'rest_namespace'      => 'lfuf/v1',
    ]);
}
