<?php
/**
 * Notifications module bootstrap.
 *
 * Sends email notifications to the site admin for key farm events:
 *   - RSVP added / cancelled
 *   - Pre-order created / ready for pickup
 *   - Stand status toggled
 *   - Availability rows expired (daily summary)
 *
 * All notifications can be filtered or disabled via hooks.
 */

declare(strict_types=1);

namespace Leftfield\Notifications;

defined( 'ABSPATH' ) || exit;

$module_dir = __DIR__;

require_once $module_dir . '/includes/email-notifications.php';
require_once $module_dir . '/includes/preorder-notifications.php';
