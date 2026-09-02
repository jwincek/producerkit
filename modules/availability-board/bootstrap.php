<?php
/**
 * Availability Board module bootstrap.
 *
 * Weekly "what's available" board for the farm website.
 * Reads from the shared pkit_availability table, adds a
 * grouped REST endpoint, an admin quick-entry page, and
 * a front-end board block with Interactivity API filtering.
 */

declare(strict_types=1);

namespace ProducerKit\AvailabilityBoard;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/rest-extensions.php';
require_once __DIR__ . '/includes/admin-quick-entry.php';
require_once __DIR__ . '/includes/fresh-sheet.php';
require_once __DIR__ . '/includes/abilities.php';
