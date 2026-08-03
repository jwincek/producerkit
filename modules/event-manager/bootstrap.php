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

require_once __DIR__ . '/includes/meta-extensions.php';
require_once __DIR__ . '/includes/rsvp-table.php';
require_once __DIR__ . '/includes/rest-extensions.php';
require_once __DIR__ . '/includes/render-helpers.php';
require_once __DIR__ . '/includes/abilities.php';
