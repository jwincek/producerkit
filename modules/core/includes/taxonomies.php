<?php
/**
 * Shared Taxonomies.
 *
 * lfuf_product_type — Produce, Bread, Pantry Good, Seedling, etc.
 * lfuf_season       — Spring, Summer, Fall, Winter (shared across products & events).
 * lfuf_event_type   — Pizza Night, Potluck, Farm Dinner, Workshop, Tour, Market, etc.
 */

declare(strict_types=1);

namespace Leftfield\Core\Taxonomies;

defined('ABSPATH') || exit;

function register(): void {
    register_product_type();
    register_season();
    register_event_type();
}

/* ───────────────────────────────────────────────
 * Product Type
 * ─────────────────────────────────────────────── */
function register_product_type(): void {
    $labels = [
        'name'              => __('Product Types', 'farm-stand-manager'),
        'singular_name'     => __('Product Type', 'farm-stand-manager'),
        'search_items'      => __('Search Product Types', 'farm-stand-manager'),
        'all_items'         => __('All Product Types', 'farm-stand-manager'),
        'parent_item'       => __('Parent Product Type', 'farm-stand-manager'),
        'parent_item_colon' => __('Parent Product Type:', 'farm-stand-manager'),
        'edit_item'         => __('Edit Product Type', 'farm-stand-manager'),
        'update_item'       => __('Update Product Type', 'farm-stand-manager'),
        'add_new_item'      => __('Add New Product Type', 'farm-stand-manager'),
        'new_item_name'     => __('New Product Type Name', 'farm-stand-manager'),
        'menu_name'         => __('Product Types', 'farm-stand-manager'),
    ];

    register_taxonomy('lfuf_product_type', ['lfuf_product'], [
        'labels'            => $labels,
        'hierarchical'      => true,
        'public'            => true,
        'show_in_rest'      => true,
        'rest_base'         => 'product-types',
        'rest_namespace'    => 'lfuf/v1',
        'rewrite'           => ['slug' => 'product-type', 'with_front' => false],
        'show_admin_column' => true,
    ]);

    // Seed default terms (self-healing, admin only).
    if (is_admin()) {
        $defaults = ['Produce', 'Bread', 'Baked Good', 'Pantry Good', 'Seedling'];
        foreach ($defaults as $term) {
            if (! term_exists($term, 'lfuf_product_type')) {
                wp_insert_term($term, 'lfuf_product_type');
            }
        }
    }
}

/* ───────────────────────────────────────────────
 * Season (shared: products + events)
 * ─────────────────────────────────────────────── */
function register_season(): void {
    $labels = [
        'name'              => __('Seasons', 'farm-stand-manager'),
        'singular_name'     => __('Season', 'farm-stand-manager'),
        'search_items'      => __('Search Seasons', 'farm-stand-manager'),
        'all_items'         => __('All Seasons', 'farm-stand-manager'),
        'edit_item'         => __('Edit Season', 'farm-stand-manager'),
        'update_item'       => __('Update Season', 'farm-stand-manager'),
        'add_new_item'      => __('Add New Season', 'farm-stand-manager'),
        'new_item_name'     => __('New Season Name', 'farm-stand-manager'),
        'menu_name'         => __('Seasons', 'farm-stand-manager'),
    ];

    register_taxonomy('lfuf_season', ['lfuf_product', 'lfuf_event'], [
        'labels'            => $labels,
        'hierarchical'      => true,
        'public'            => true,
        'show_in_rest'      => true,
        'rest_base'         => 'seasons',
        'rest_namespace'    => 'lfuf/v1',
        'rewrite'           => ['slug' => 'season', 'with_front' => false],
        'show_admin_column' => true,
    ]);

    if (is_admin()) {
        $defaults = ['Spring', 'Summer', 'Fall', 'Winter'];
        foreach ($defaults as $term) {
            if (! term_exists($term, 'lfuf_season')) {
                wp_insert_term($term, 'lfuf_season');
            }
        }
    }
}

/* ───────────────────────────────────────────────
 * Event Type
 * ─────────────────────────────────────────────── */
function register_event_type(): void {
    $labels = [
        'name'              => __('Event Types', 'farm-stand-manager'),
        'singular_name'     => __('Event Type', 'farm-stand-manager'),
        'search_items'      => __('Search Event Types', 'farm-stand-manager'),
        'all_items'         => __('All Event Types', 'farm-stand-manager'),
        'edit_item'         => __('Edit Event Type', 'farm-stand-manager'),
        'update_item'       => __('Update Event Type', 'farm-stand-manager'),
        'add_new_item'      => __('Add New Event Type', 'farm-stand-manager'),
        'new_item_name'     => __('New Event Type Name', 'farm-stand-manager'),
        'menu_name'         => __('Event Types', 'farm-stand-manager'),
    ];

    register_taxonomy('lfuf_event_type', ['lfuf_event'], [
        'labels'            => $labels,
        'hierarchical'      => true,
        'public'            => true,
        'show_in_rest'      => true,
        'rest_base'         => 'event-types',
        'rest_namespace'    => 'lfuf/v1',
        'rewrite'           => ['slug' => 'event-type', 'with_front' => false],
        'show_admin_column' => true,
    ]);

    if (is_admin()) {
        $defaults = [
            'Pizza Night',
            'Potluck',
            'Farm Dinner',
            'Workshop',
            'Farm Tour',
            'Seed Exchange',
            'Mini Market',
        ];
        foreach ($defaults as $term) {
            if (! term_exists($term, 'lfuf_event_type')) {
                wp_insert_term($term, 'lfuf_event_type');
            }
        }
    }
}