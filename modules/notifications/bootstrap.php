<?php
/**
 * Notifications module bootstrap.
 *
 * Sends email notifications for the events a producer needs to know about:
 *   - RSVP added / cancelled
 *   - Pre-order created / ready for pickup
 *   - Commission requested / quoted / accepted / declined / finished
 *   - Stand status toggled
 *   - Availability rows expired (daily summary)
 *
 * All notifications can be filtered or disabled via hooks.
 */

declare(strict_types=1);

namespace ProducerKit\Notifications;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/email-notifications.php';
require_once __DIR__ . '/includes/preorder-notifications.php';
require_once __DIR__ . '/includes/commission-notifications.php';
