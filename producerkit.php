<?php
/**
 * Plugin Name:       ProducerKit
 * Plugin URI:        https://github.com/jwincek/producerkit
 * Description:       Catalog, sales locations, live availability, pickup pre-orders and events for small independent producers — farms, makers and beekeepers. Blocks and Abilities API support.
 * Version:           2.3.0
 * Requires at least: 6.9
 * Requires PHP:      8.1
 * WC requires at least: 8.2
 * WC tested up to:   11.1
 * Author:            Jerome Wincek
 * Author URI:        https://github.com/jwincek
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       producerkit
 */

declare(strict_types=1);

namespace ProducerKit;

defined( 'ABSPATH' ) || exit;

const VERSION    = '2.3.0';
const PLUGIN_DIR = __DIR__;
const PREFIX     = 'pkit';

/* ───────────────────────────────────────────────
 * Module registry
 *
 * Each module has a slug, a label (for future admin UI),
 * and a bootstrap file. The core module is always loaded.
 * Feature modules can be toggled via the
 * 'pkit_active_modules' filter.
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
		'core'               => __( 'Core Data Layer', 'producerkit' ),
		'producer-profiles'  => __( 'Producer Profiles', 'producerkit' ),
		'stand-status'       => __( 'Stand Status', 'producerkit' ),
		'availability-board' => __( 'Availability Board', 'producerkit' ),
		'event-manager'      => __( 'Event Manager', 'producerkit' ),
		'notifications'      => __( 'Notifications', 'producerkit' ),
		'pre-order'          => __( 'Pre-Orders', 'producerkit' ),
		'commissions'        => __( 'Commissions', 'producerkit' ),
		'woocommerce'        => __( 'WooCommerce Settlement', 'producerkit' ),
	];
}

function get_registered_modules(): array {
	return [
		'core'               => [
			'bootstrap' => PLUGIN_DIR . '/modules/core/bootstrap.php',
			'required'  => true,
		],
		'producer-profiles'  => [
			'bootstrap' => PLUGIN_DIR . '/modules/producer-profiles/bootstrap.php',
			'required'  => false,
		],
		'stand-status'       => [
			'bootstrap' => PLUGIN_DIR . '/modules/stand-status/bootstrap.php',
			'required'  => false,
		],
		'availability-board' => [
			'bootstrap' => PLUGIN_DIR . '/modules/availability-board/bootstrap.php',
			'required'  => false,
		],
		'event-manager'      => [
			'bootstrap' => PLUGIN_DIR . '/modules/event-manager/bootstrap.php',
			'required'  => false,
		],
		'notifications'      => [
			'bootstrap' => PLUGIN_DIR . '/modules/notifications/bootstrap.php',
			'required'  => false,
		],
		'pre-order'          => [
			'bootstrap' => PLUGIN_DIR . '/modules/pre-order/bootstrap.php',
			'required'  => false,
		],
		'commissions'        => [
			'bootstrap' => PLUGIN_DIR . '/modules/commissions/bootstrap.php',
			'required'  => false,
		],
		'woocommerce'        => [
			'bootstrap' => PLUGIN_DIR . '/modules/woocommerce/bootstrap.php',
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
	$defaults   = array_keys( $registered );

	/** @var string[] $active */
	$active = apply_filters( 'pkit_active_modules', $defaults );

	// Ensure required modules are always loaded.
	foreach ( $registered as $slug => $config ) {
		if ( $config['required'] && ! in_array( $slug, $active, true ) ) {
			array_unshift( $active, $slug );
		}
	}

	return $active;
}

/**
 * Check whether a module is active.
 */
function is_module_active( string $slug ): bool {
	return in_array( $slug, get_active_modules(), true );
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

	foreach ( $active as $slug ) {
		if ( isset( $registered[ $slug ] ) && file_exists( $registered[ $slug ]['bootstrap'] ) ) {
			require_once $registered[ $slug ]['bootstrap'];
		}
	}
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\boot', 5 );

/* ───────────────────────────────────────────────
 * Admin dashboard
 * ─────────────────────────────────────────────── */

if ( is_admin() ) {
	require_once PLUGIN_DIR . '/includes/admin-dashboard.php';
	require_once PLUGIN_DIR . '/includes/sample-data.php';
}

// Sample data markers load on both front and admin.
require_once PLUGIN_DIR . '/includes/sample-data-markers.php';

/* ───────────────────────────────────────────────
 * Block registration (all blocks, flat directory)
 * ─────────────────────────────────────────────── */

add_action(
	'init',
	function (): void {
		$blocks_dir = PLUGIN_DIR . '/blocks';

		foreach ( glob( $blocks_dir . '/*/block.json' ) as $block_json ) {
			register_block_type( dirname( $block_json ) );
		}
	}
);

/* ───────────────────────────────────────────────
 * Front-end QR support (bundled qrcode-generator, MIT)
 *
 * Registered here, enqueued on demand by blocks that render a
 * [data-pkit-qr] container (e.g. location-info with showQR on).
 * ─────────────────────────────────────────────── */

function register_qr_scripts(): void {
	wp_register_script(
		'pkit-qrcode-vendor',
		plugins_url( 'assets/js/vendor/qrcode.js', __FILE__ ),
		[],
		VERSION,
		true,
	);
	wp_register_script(
		'pkit-qr',
		plugins_url( 'assets/js/pkit-qr.js', __FILE__ ),
		[ 'pkit-qrcode-vendor' ],
		VERSION,
		true,
	);
}

add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\register_qr_scripts' );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\register_qr_scripts' );

/* ───────────────────────────────────────────────
 * WooCommerce feature compatibility
 * ─────────────────────────────────────────────── */

/**
 * Declare compatibility with WooCommerce's optional features.
 *
 * WooCommerce treats silence as incompatibility. An undeclared active plugin
 * does not merely earn a warning: FeaturesController disables the HPOS radio
 * outright, so the store cannot turn High-Performance Order Storage on at all
 * while this plugin is active. Both claims below are true today —
 *
 *   custom_order_tables  Nothing here reads or writes order storage directly.
 *                        The settlement module keeps its own order_id mapping
 *                        in its own table, and the checkout module goes
 *                        through $order CRUD, both of which are storage
 *                        agnostic. The one update_post_meta() call in that
 *                        module is on a product, which is still a post.
 *
 *   cart_checkout_blocks The four woocommerce_order_status_* transitions are
 *                        the entire integration surface. Nothing hooks the
 *                        cart, registers a gateway, or adds checkout fields,
 *                        so there is nothing for the block checkout to break.
 *                        Payment goes through an order's own pay-for-order
 *                        URL rather than the cart, which sidesteps the
 *                        classic/blocks split entirely.
 *
 * Declared unconditionally rather than from the WooCommerce module, which is
 * optional: a site that has switched that module off integrates with nothing
 * and is trivially compatible, and must not be blocking HPOS either.
 *
 * declare_compatibility() insists on being called inside
 * before_woocommerce_init and returns false with a _doing_it_wrong() notice
 * anywhere else, so the hook is not decorative.
 */
add_action(
	'before_woocommerce_init',
	function (): void {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}

		foreach ( [ 'custom_order_tables', 'cart_checkout_blocks' ] as $feature ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( $feature, __FILE__, true );
		}
	}
);

/* ───────────────────────────────────────────────
 * Block category
 * ─────────────────────────────────────────────── */

add_filter(
	'block_categories_all',
	function ( array $categories ): array {
		array_unshift(
			$categories,
			[
				'slug'  => 'producerkit',
				'title' => __( 'ProducerKit', 'producerkit' ),
				'icon'  => 'store',
			]
		);
		return $categories;
	}
);

/* ───────────────────────────────────────────────
 * Editor settings (shared across all blocks)
 * ─────────────────────────────────────────────── */

add_action(
	'enqueue_block_editor_assets',
	function (): void {
		wp_add_inline_script(
			'wp-blocks',
			sprintf(
				'window.pkitSettings = %s;',
				wp_json_encode(
					[
						'restBase'      => esc_url_raw( rest_url( 'producerkit/v1' ) ),
						'nonce'         => wp_create_nonce( 'wp_rest' ),
						'pluginUrl'     => plugins_url( '', __FILE__ ),
						'activeModules' => get_active_modules(),
						// Distinct from the module being active: a site can have
						// the module switched on with WooCommerce uninstalled,
						// in which case its bootstrap returns early and any
						// payment control would be inert.
						'hasWooCommerce' => class_exists( '\\WooCommerce' ),
						// What this trade calls a quote-then-make job, so the
						// request form's placeholder does not tell a beekeeper
						// to "commission a piece".
						'requestWords'   => function_exists( '\\ProducerKit\\Commissions\\Vocabulary\\words' )
							? \ProducerKit\Commissions\Vocabulary\words()
							: null,
					]
				),
			),
			'before',
		);

		// CPT-specific editor sidebar panels.
		$screen    = get_current_screen();
		$post_type = $screen->post_type ?? '';

		$scripts = [
			'pkit_location' => 'editor-location.js',
			'pkit_product'  => 'editor-product.js',
			'pkit_event'    => 'editor-event.js',
		];

		if ( isset( $scripts[ $post_type ] ) ) {
			wp_enqueue_script(
				'pkit-editor-' . $post_type,
				plugins_url( 'assets/js/' . $scripts[ $post_type ], __FILE__ ),
				[ 'wp-plugins', 'wp-editor', 'wp-components', 'wp-data', 'wp-core-data', 'wp-element' ],
				filemtime( PLUGIN_DIR . '/assets/js/' . $scripts[ $post_type ] ),
				true,
			);
		}
	}
);

/* ───────────────────────────────────────────────
 * Activation / Deactivation
 * ─────────────────────────────────────────────── */

function activate(): void {
	// Core module handles table creation.
	require_once PLUGIN_DIR . '/modules/core/bootstrap.php';
	\ProducerKit\Core\Availability\create_table();
	\ProducerKit\Core\Post_Types\register();
	\ProducerKit\Core\Taxonomies\register();

	// Event manager RSVP table.
	require_once PLUGIN_DIR . '/modules/event-manager/includes/rsvp-table.php';
	\ProducerKit\EventManager\RSVP\create_table();

	// Pre-order table.
	require_once PLUGIN_DIR . '/modules/pre-order/includes/orders-table.php';
	\ProducerKit\PreOrder\Orders\create_table();

	// Schedule daily availability cleanup.
	\ProducerKit\Core\Availability\schedule_cleanup();

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );

function deactivate(): void {
	\ProducerKit\Core\Availability\unschedule_cleanup();
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );
