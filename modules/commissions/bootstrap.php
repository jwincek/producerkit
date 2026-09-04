<?php
/**
 * Commissions module bootstrap.
 *
 * Made-to-order requests: a customer describes something that does not exist,
 * the maker quotes a price and a date, and the customer accepts or declines.
 * Ported from wc-artisan-tools, but onto this plugin's own storage and the
 * shared request substrate rather than WooCommerce.
 *
 * The settlement leg — taking the money once a quote is accepted — belongs to
 * the woocommerce module, so nothing here assumes WooCommerce is installed.
 * Without it an accepted commission is simply one the maker arranges payment
 * for directly, which is how most makers already work.
 */

declare(strict_types=1);

namespace ProducerKit\Commissions;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/vocabulary.php';
require_once __DIR__ . '/includes/commissions-table.php';
require_once __DIR__ . '/includes/rest-extensions.php';
require_once __DIR__ . '/includes/quote-response.php';
require_once __DIR__ . '/includes/abilities.php';

if ( is_admin() ) {
	require_once __DIR__ . '/includes/admin-commissions.php';
}
