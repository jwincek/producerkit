<?php
/**
 * Availability Board module bootstrap.
 *
 * Weekly "what's available" board for the farm website.
 * Reads from the shared lfuf_availability table, adds a
 * grouped REST endpoint, an admin quick-entry page, and
 * a front-end board block with Interactivity API filtering.
 */

declare(strict_types=1);

namespace Leftfield\AvailabilityBoard;

defined('ABSPATH') || exit;

$module_dir = __DIR__;

require_once $module_dir . '/includes/rest-extensions.php';
require_once $module_dir . '/includes/admin-quick-entry.php';
require_once $module_dir . '/includes/fresh-sheet.php';
require_once $module_dir . '/includes/abilities.php';
