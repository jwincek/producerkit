<?php
/**
 * Event Manager module bootstrap.
 *
 * Adds RSVP tracking, event listing/filtering REST endpoints,
 * front-end blocks for upcoming events, and Abilities API abilities.
 */

declare(strict_types=1);

namespace Leftfield\EventManager;

defined( 'ABSPATH' ) || exit;

$module_dir = __DIR__;

require_once $module_dir . '/includes/meta-extensions.php';
require_once $module_dir . '/includes/rsvp-table.php';
require_once $module_dir . '/includes/rest-extensions.php';
require_once $module_dir . '/includes/render-helpers.php';
require_once $module_dir . '/includes/abilities.php';
