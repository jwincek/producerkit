<?php
/**
 * Pre-Order module bootstrap.
 *
 * Cartless pay-at-pickup pre-orders: custom table, public REST
 * endpoints, Abilities, and an admin management screen. The
 * lfuf/preorder-form block is registered by the main plugin file
 * alongside the other blocks; its render checks this module is active.
 */

declare(strict_types=1);

namespace Leftfield\PreOrder;

defined( 'ABSPATH' ) || exit;

$module_dir = __DIR__;

require_once $module_dir . '/includes/orders-table.php';
require_once $module_dir . '/includes/rest-extensions.php';
require_once $module_dir . '/includes/abilities.php';

if ( is_admin() ) {
	require_once $module_dir . '/includes/admin-orders.php';
}
