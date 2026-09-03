<?php
/**
 * Pre-Order module bootstrap.
 *
 * Cartless pay-at-pickup pre-orders: custom table, public REST
 * endpoints, Abilities, and an admin management screen. The
 * producerkit/preorder-form block is registered by the main plugin file
 * alongside the other blocks; its render checks this module is active.
 */

declare(strict_types=1);

namespace ProducerKit\PreOrder;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/orders-table.php';
require_once __DIR__ . '/includes/rest-extensions.php';
require_once __DIR__ . '/includes/order-response.php';
require_once __DIR__ . '/includes/abilities.php';

if ( is_admin() ) {
	require_once __DIR__ . '/includes/admin-orders.php';
}
