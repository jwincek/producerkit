<?php
/**
 * WooCommerce module bootstrap.
 *
 * The optional compatibility layer. Everything else in this plugin works with
 * no store at all — a pre-order is paid at the stand, a commission is
 * invoiced by the maker — and this module adds the option of settling either
 * one through WooCommerce checkout instead.
 *
 * It is the only module that touches WooCommerce, and it does nothing unless
 * WooCommerce is actually loaded. That is the whole point of the arrangement:
 * a farm stand with no store is not carrying a dependency it never uses, and
 * a maker who already sells online does not need a second checkout.
 *
 * Loading is gated on the class rather than on is_plugin_active(): the latter
 * needs an admin include, reads an option that lies on multisite, and would
 * be true during the request in which WooCommerce is being deactivated.
 */

declare(strict_types=1);

namespace ProducerKit\WooCommerce;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WooCommerce' ) ) {
	if ( is_admin() ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\missing_notice' );
	}
	return;
}

require_once __DIR__ . '/includes/settlement.php';
require_once __DIR__ . '/includes/checkout.php';
require_once __DIR__ . '/includes/order-sync.php';

/**
 * Explain the module's inertness, but only to someone who could act on it,
 * and only on our own screens — an unrelated admin page is not the place.
 */
function missing_notice(): void {
	$screen = get_current_screen();

	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	if ( ! $screen || ! str_contains( (string) $screen->id, 'farm-stand' ) ) {
		return;
	}

	echo '<div class="notice notice-info is-dismissible"><p>';
	echo esc_html__(
		'The WooCommerce module is enabled but WooCommerce is not active, so requests settle directly (cash, Venmo, or an invoice you send). Everything else works as normal.',
		'producerkit'
	);
	echo '</p></div>';
}
