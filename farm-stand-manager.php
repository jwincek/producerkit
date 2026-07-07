<?php
/**
 * Plugin Name:       Farm Stand Manager
 * Plugin URI:        https://github.com/jwincek/farm-stand-manager
 * Description:       Products, sales locations, real-time availability, stand status, and events for small farms and farm stands — with blocks and Abilities API support.
 * Version:           1.0.0
 * Requires at least: 6.9
 * Requires PHP:      8.1
 * Author:            Jerome Wincek
 * Author URI:        https://github.com/jwincek
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       farm-stand-manager
 */

declare(strict_types=1);

namespace Leftfield;

defined('ABSPATH') || exit;

const VERSION    = '1.0.0';
const PLUGIN_DIR = __DIR__;
const PREFIX     = 'lfuf';

/* ───────────────────────────────────────────────
 * Module registry
 *
 * Each module has a slug, a label (for future admin UI),
 * and a bootstrap file. The core module is always loaded.
 * Feature modules can be toggled via the
 * 'lfuf_active_modules' filter.
 * ─────────────────────────────────────────────── */

/**
 * Human-readable module labels, translated on demand.
 *
 * Kept separate from get_registered_modules() so that boot() — which
 * runs at plugins_loaded, before translations may load — never triggers
 * WordPress 6.7's just-in-time textdomain notice.
 *
 * @return array<string, string>
 */
function get_module_labels(): array {
    return [
        'core'               => __('Core Data Layer', 'farm-stand-manager'),
        'stand-status'       => __('Stand Status', 'farm-stand-manager'),
        'availability-board' => __('Availability Board', 'farm-stand-manager'),
        'event-manager'      => __('Event Manager', 'farm-stand-manager'),
        'notifications'      => __('Notifications', 'farm-stand-manager'),
        'pre-order'          => __('Pre-Orders', 'farm-stand-manager'),
    ];
}

function get_registered_modules(): array {
    return [
        'core' => [
            'bootstrap' => PLUGIN_DIR . '/modules/core/bootstrap.php',
            'required'  => true,
        ],
        'stand-status' => [
            'bootstrap' => PLUGIN_DIR . '/modules/stand-status/bootstrap.php',
            'required'  => false,
        ],
        'availability-board' => [
            'bootstrap' => PLUGIN_DIR . '/modules/availability-board/bootstrap.php',
            'required'  => false,
        ],
        'event-manager' => [
            'bootstrap' => PLUGIN_DIR . '/modules/event-manager/bootstrap.php',
            'required'  => false,
        ],
        'notifications' => [
            'bootstrap' => PLUGIN_DIR . '/modules/notifications/bootstrap.php',
            'required'  => false,
        ],
        'pre-order' => [
            'bootstrap' => PLUGIN_DIR . '/modules/pre-order/bootstrap.php',
            'required'  => false,
        ],
        // Future modules:
        // 'grain-stories' => [ ... ],
    ];
}

/**
 * Get the list of currently active module slugs.
 *
 * Defaults to all registered modules. Can be filtered to disable
 * specific features without deactivating the plugin.
 *
 * @return string[]
 */
function get_active_modules(): array {
    $registered = get_registered_modules();
    $defaults   = array_keys($registered);

    /** @var string[] $active */
    $active = apply_filters('lfuf_active_modules', $defaults);

    // Ensure required modules are always loaded.
    foreach ($registered as $slug => $config) {
        if ($config['required'] && ! in_array($slug, $active, true)) {
            array_unshift($active, $slug);
        }
    }

    return $active;
}

/**
 * Check whether a module is active.
 */
function is_module_active(string $slug): bool {
    return in_array($slug, get_active_modules(), true);
}

/* ───────────────────────────────────────────────
 * Boot
 * ─────────────────────────────────────────────── */

/**
 * Load active modules.
 */
function boot(): void {
    $registered = get_registered_modules();
    $active     = get_active_modules();

    foreach ($active as $slug) {
        if (isset($registered[$slug]) && file_exists($registered[$slug]['bootstrap'])) {
            require_once $registered[$slug]['bootstrap'];
        }
    }
}

add_action('plugins_loaded', __NAMESPACE__ . '\\boot', 5);

/* ───────────────────────────────────────────────
 * Admin dashboard
 * ─────────────────────────────────────────────── */

if (is_admin()) {
    require_once PLUGIN_DIR . '/includes/admin-dashboard.php';
    require_once PLUGIN_DIR . '/includes/sample-data.php';
}

// Sample data markers load on both front and admin.
require_once PLUGIN_DIR . '/includes/sample-data-markers.php';

/* ───────────────────────────────────────────────
 * Block registration (all blocks, flat directory)
 * ─────────────────────────────────────────────── */

add_action('init', function (): void {
    $blocks_dir = PLUGIN_DIR . '/blocks';

    foreach (glob($blocks_dir . '/*/block.json') as $block_json) {
        register_block_type(dirname($block_json));
    }
});

/* ───────────────────────────────────────────────
 * Front-end QR support (bundled qrcode-generator, MIT)
 *
 * Registered here, enqueued on demand by blocks that render a
 * [data-lfuf-qr] container (e.g. location-info with showQR on).
 * ─────────────────────────────────────────────── */

add_action('wp_enqueue_scripts', function (): void {
    wp_register_script(
        'lfuf-qrcode-vendor',
        plugins_url('assets/js/vendor/qrcode.js', __FILE__),
        [],
        VERSION,
        true,
    );
    wp_register_script(
        'lfuf-qr',
        plugins_url('assets/js/lfuf-qr.js', __FILE__),
        ['lfuf-qrcode-vendor'],
        VERSION,
        true,
    );
});

/* ───────────────────────────────────────────────
 * Block category
 * ─────────────────────────────────────────────── */

add_filter('block_categories_all', function (array $categories): array {
    array_unshift($categories, [
        'slug'  => 'leftfield',
        'title' => __('Farm Stand Manager', 'farm-stand-manager'),
        'icon'  => 'carrot',
    ]);
    return $categories;
});

/* ───────────────────────────────────────────────
 * Editor settings (shared across all blocks)
 * ─────────────────────────────────────────────── */

add_action('enqueue_block_editor_assets', function (): void {
    wp_add_inline_script(
        'wp-blocks',
        sprintf(
            'window.lfufSettings = %s;',
            wp_json_encode([
                'restBase'      => esc_url_raw(rest_url('lfuf/v1')),
                'nonce'         => wp_create_nonce('wp_rest'),
                'pluginUrl'     => plugins_url('', __FILE__),
                'activeModules' => get_active_modules(),
            ]),
        ),
        'before',
    );

    // CPT-specific editor sidebar panels.
    $screen = get_current_screen();
    $post_type = $screen->post_type ?? '';

    $scripts = [
        'lfuf_location' => 'editor-location.js',
        'lfuf_product'  => 'editor-product.js',
        'lfuf_event'    => 'editor-event.js',
    ];

    if (isset($scripts[$post_type])) {
        wp_enqueue_script(
            'lfuf-editor-' . $post_type,
            plugins_url('assets/js/' . $scripts[$post_type], __FILE__),
            ['wp-plugins', 'wp-editor', 'wp-components', 'wp-data', 'wp-core-data', 'wp-element'],
            filemtime(PLUGIN_DIR . '/assets/js/' . $scripts[$post_type]),
            true,
        );
    }
});

/* ───────────────────────────────────────────────
 * Activation / Deactivation
 * ─────────────────────────────────────────────── */

function activate(): void {
    // Core module handles table creation.
    require_once PLUGIN_DIR . '/modules/core/bootstrap.php';
    \Leftfield\Core\Availability\create_table();
    \Leftfield\Core\Post_Types\register();
    \Leftfield\Core\Taxonomies\register();

    // Event manager RSVP table.
    require_once PLUGIN_DIR . '/modules/event-manager/includes/rsvp-table.php';
    \Leftfield\EventManager\RSVP\create_table();

    // Pre-order table.
    require_once PLUGIN_DIR . '/modules/pre-order/includes/orders-table.php';
    \Leftfield\PreOrder\Orders\create_table();

    // Schedule daily availability cleanup.
    \Leftfield\Core\Availability\schedule_cleanup();

    flush_rewrite_rules();
}
register_activation_hook(__FILE__, __NAMESPACE__ . '\\activate');

function deactivate(): void {
    \Leftfield\Core\Availability\unschedule_cleanup();
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\\deactivate');