<?php
/**
 * Abilities API registration for the core module.
 *
 * Registers discoverable abilities for products, availability,
 * and locations. These wrap the existing REST/PHP logic in the
 * standard WP Abilities format so AI agents, MCP adapters, and
 * automation tools can discover and execute them.
 *
 * Requires WordPress 6.9+ (Abilities API in core).
 * Gracefully skips registration on older versions.
 *
 * @see https://developer.wordpress.org/apis/abilities-api/
 */

declare(strict_types=1);

namespace Leftfield\Core\Abilities;

defined('ABSPATH') || exit;

/**
 * Guard: only register if the Abilities API functions exist (WP 6.9+).
 */
function is_abilities_api_available(): bool {
    return function_exists('wp_register_ability_category')
        && function_exists('wp_register_ability');
}

/* ───────────────────────────────────────────────
 * Categories
 * ─────────────────────────────────────────────── */

add_action('wp_abilities_api_categories_init', function (): void {
    if (! is_abilities_api_available()) {
        return;
    }

    wp_register_ability_category('farm-products', [
        'label'       => __('Farm Products', 'farm-stand-manager'),
        'description' => __('Abilities for managing farm products — produce, bread, pantry goods.', 'farm-stand-manager'),
    ]);

    wp_register_ability_category('farm-availability', [
        'label'       => __('Farm Availability', 'farm-stand-manager'),
        'description' => __('Abilities for querying and updating product availability at locations.', 'farm-stand-manager'),
    ]);

    wp_register_ability_category('farm-locations', [
        'label'       => __('Farm Locations', 'farm-stand-manager'),
        'description' => __('Abilities for managing sales locations — stand, market, on-farm.', 'farm-stand-manager'),
    ]);
});

/* ───────────────────────────────────────────────
 * Abilities
 * ─────────────────────────────────────────────── */

add_action('wp_abilities_api_init', function (): void {
    if (! is_abilities_api_available()) {
        return;
    }

    register_product_abilities();
    register_availability_abilities();
    register_location_abilities();
});

/* ── Products ──────────────────────────────────── */

function register_product_abilities(): void {

    wp_register_ability('farm-stand-manager/list-products', [
        'label'       => __('List Products', 'farm-stand-manager'),
        'description' => __('Retrieve a list of all published farm products with their type, season, price, and unit.', 'farm-stand-manager'),
        'category'    => 'farm-products',
        'execute_callback' => function (): array {
            $products = get_posts([
                'post_type'   => 'lfuf_product',
                'post_status' => 'publish',
                'numberposts' => 100,
            ]);

            return array_map(function (\WP_Post $p): array {
                $types   = get_the_terms($p->ID, 'lfuf_product_type');
                $seasons = get_the_terms($p->ID, 'lfuf_season');

                return [
                    'id'      => $p->ID,
                    'title'   => $p->post_title,
                    'excerpt' => $p->post_excerpt,
                    'types'   => $types && ! is_wp_error($types)
                        ? wp_list_pluck($types, 'name')
                        : [],
                    'seasons' => $seasons && ! is_wp_error($seasons)
                        ? wp_list_pluck($seasons, 'name')
                        : [],
                    'price'   => get_post_meta($p->ID, '_lfuf_price', true),
                    'unit'    => get_post_meta($p->ID, '_lfuf_unit', true),
                ];
            }, $products);
        },
        'output_schema' => [
            'type'  => 'array',
            'items' => [
                'type'       => 'object',
                'properties' => [
                    'id'      => ['type' => 'integer', 'description' => 'Product post ID.'],
                    'title'   => ['type' => 'string',  'description' => 'Product name.'],
                    'excerpt' => ['type' => 'string',  'description' => 'Short description.'],
                    'types'   => ['type' => 'array',   'items' => ['type' => 'string'], 'description' => 'Product type terms.'],
                    'seasons' => ['type' => 'array',   'items' => ['type' => 'string'], 'description' => 'Season terms.'],
                    'price'   => ['type' => 'string',  'description' => 'Display price.'],
                    'unit'    => ['type' => 'string',  'description' => 'Unit of sale.'],
                ],
            ],
        ],
        'permission_callback' => '__return_true',
        'meta' => [
            'show_in_rest' => true,
            'annotations'  => [
                'readonly' => true,
            ],
        ],
    ]);

    wp_register_ability('farm-stand-manager/get-product-sources', [
        'label'       => __('Get Product Sources', 'farm-stand-manager'),
        'description' => __('Retrieve the grain origins and partner farms linked to a product.', 'farm-stand-manager'),
        'category'    => 'farm-products',
        'execute_callback' => function (array $input): array {
            $source_ids = get_post_meta($input['product_id'], '_lfuf_source_ids', true);
            if (empty($source_ids) || ! is_array($source_ids)) {
                return [];
            }

            $sources = get_posts([
                'post_type'   => 'lfuf_source',
                'post__in'    => $source_ids,
                'numberposts' => 20,
                'post_status' => 'publish',
            ]);

            return array_map(fn (\WP_Post $s) => [
                'id'            => $s->ID,
                'title'         => $s->post_title,
                'farm_name'     => get_post_meta($s->ID, '_lfuf_source_farm_name', true),
                'location'      => get_post_meta($s->ID, '_lfuf_source_location', true),
                'history'       => get_post_meta($s->ID, '_lfuf_source_history', true),
                'milling_notes' => get_post_meta($s->ID, '_lfuf_milling_notes', true),
            ], $sources);
        },
        'input_schema' => [
            'type'       => 'object',
            'properties' => [
                'product_id' => ['type' => 'integer', 'description' => 'Product post ID.'],
            ],
            'required' => ['product_id'],
        ],
        'output_schema' => [
            'type'  => 'array',
            'items' => [
                'type'       => 'object',
                'properties' => [
                    'id'            => ['type' => 'integer'],
                    'title'         => ['type' => 'string'],
                    'farm_name'     => ['type' => 'string'],
                    'location'      => ['type' => 'string'],
                    'history'       => ['type' => 'string'],
                    'milling_notes' => ['type' => 'string'],
                ],
            ],
        ],
        'permission_callback' => '__return_true',
        'meta' => [
            'show_in_rest' => true,
            'annotations'  => ['readonly' => true],
        ],
    ]);
}

/* ── Availability ──────────────────────────────── */

function register_availability_abilities(): void {

    wp_register_ability('farm-stand-manager/get-availability', [
        'label'       => __('Get Current Availability', 'farm-stand-manager'),
        'description' => __('Retrieve the current availability status of all products, optionally filtered by product or location.', 'farm-stand-manager'),
        'category'    => 'farm-availability',
        'execute_callback' => function (array $input = []): array {
            $product_id  = (int) ($input['product_id'] ?? 0);
            $location_id = (int) ($input['location_id'] ?? 0);

            if ($product_id > 0) {
                $rows = \Leftfield\Core\Availability\get_current($product_id, $location_id);
            } else {
                $rows = \Leftfield\Core\Availability\get_all_current();
            }

            // wpdb returns every column as a string; cast to match the output schema.
            return array_map(function ($row): array {
                $row                = (array) $row;
                $row['id']          = (int) $row['id'];
                $row['product_id']  = (int) $row['product_id'];
                $row['location_id'] = (int) $row['location_id'];
                return $row;
            }, $rows);
        },
        'input_schema' => [
            'type'       => 'object',
            'properties' => [
                'product_id'  => ['type' => 'integer', 'description' => 'Filter by product ID (0 = all).'],
                'location_id' => ['type' => 'integer', 'description' => 'Filter by location ID (0 = all).'],
            ],
            'default'    => [],
        ],
        'output_schema' => [
            'type'  => 'array',
            'items' => [
                'type'       => 'object',
                'properties' => [
                    'id'             => ['type' => 'integer'],
                    'product_id'     => ['type' => 'integer'],
                    'location_id'    => ['type' => 'integer'],
                    'status'         => ['type' => 'string'],
                    'quantity_note'  => ['type' => 'string'],
                    'effective_date' => ['type' => 'string'],
                    'notes'          => ['type' => 'string'],
                    'product_name'   => ['type' => 'string'],
                ],
            ],
        ],
        'permission_callback' => '__return_true',
        'meta' => [
            'show_in_rest' => true,
            'annotations'  => ['readonly' => true],
        ],
    ]);

    wp_register_ability('farm-stand-manager/update-availability', [
        'label'       => __('Update Product Availability', 'farm-stand-manager'),
        'description' => __('Set the availability status of a product at a location for a given date.', 'farm-stand-manager'),
        'category'    => 'farm-availability',
        'execute_callback' => function (array $input): array {
            $id = \Leftfield\Core\Availability\upsert($input);
            if ($id === false) {
                return ['success' => false, 'message' => 'Invalid data.'];
            }
            return ['success' => true, 'id' => $id];
        },
        'input_schema' => [
            'type'       => 'object',
            'properties' => [
                'product_id'     => ['type' => 'integer', 'description' => 'Product post ID.'],
                'location_id'    => ['type' => 'integer', 'description' => 'Location post ID (0 = all).'],
                'status'         => ['type' => 'string',  'description' => 'One of: abundant, available, limited, sold_out, unavailable.', 'enum' => ['abundant', 'available', 'limited', 'sold_out', 'unavailable']],
                'quantity_note'  => ['type' => 'string',  'description' => 'Optional note like "~3 bunches left".'],
                'effective_date' => ['type' => 'string',  'description' => 'Date this status takes effect (YYYY-MM-DD).'],
                'expires_date'   => ['type' => 'string',  'description' => 'Optional expiry date (YYYY-MM-DD).'],
                'notes'          => ['type' => 'string',  'description' => 'Internal notes.'],
            ],
            'required' => ['product_id', 'status', 'effective_date'],
        ],
        'output_schema' => [
            'type'       => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'id'      => ['type' => 'integer'],
                'message' => ['type' => 'string'],
            ],
        ],
        'permission_callback' => fn () => current_user_can('edit_posts'),
        'meta' => [
            'show_in_rest' => true,
            'annotations'  => ['idempotent' => true],
        ],
    ]);
}

/* ── Locations ─────────────────────────────────── */

function register_location_abilities(): void {

    wp_register_ability('farm-stand-manager/list-locations', [
        'label'       => __('List Locations', 'farm-stand-manager'),
        'description' => __('Retrieve all published sales locations with address, type, hours, and open/closed status.', 'farm-stand-manager'),
        'category'    => 'farm-locations',
        'execute_callback' => function (): array {
            $locations = get_posts([
                'post_type'   => 'lfuf_location',
                'post_status' => 'publish',
                'numberposts' => 50,
            ]);

            return array_map(fn (\WP_Post $p) => [
                'id'      => $p->ID,
                'title'   => $p->post_title,
                'type'    => get_post_meta($p->ID, '_lfuf_location_type', true),
                'address' => get_post_meta($p->ID, '_lfuf_address', true),
                'hours'   => get_post_meta($p->ID, '_lfuf_hours', true),
                'is_open' => (bool) get_post_meta($p->ID, '_lfuf_is_open', true),
                'venmo'   => get_post_meta($p->ID, '_lfuf_venmo_handle', true),
                'payment_methods' => \Leftfield\Core\Payments\get_payment_methods($p->ID),
            ], $locations);
        },
        'output_schema' => [
            'type'  => 'array',
            'items' => [
                'type'       => 'object',
                'properties' => [
                    'id'      => ['type' => 'integer'],
                    'title'   => ['type' => 'string'],
                    'type'    => ['type' => 'string'],
                    'address' => ['type' => 'string'],
                    'hours'   => ['type' => 'string'],
                    'is_open' => ['type' => 'boolean'],
                    'venmo'   => ['type' => 'string', 'description' => 'Legacy Venmo handle; prefer payment_methods.'],
                    'payment_methods' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'type'    => ['type' => 'string'],
                                'label'   => ['type' => 'string'],
                                'value'   => ['type' => 'string'],
                                'url'     => ['type' => 'string'],
                                'is_link' => ['type' => 'boolean'],
                            ],
                        ],
                        'description' => 'Accepted payment methods: links (Venmo, Cash App, PayPal, custom) and badges (cash, check, SNAP/EBT, market vouchers).',
                    ],
                ],
            ],
        ],
        'permission_callback' => '__return_true',
        'meta' => [
            'show_in_rest' => true,
            'annotations'  => ['readonly' => true],
        ],
    ]);
}
