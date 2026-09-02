<?php
/**
 * Stand Status module bootstrap.
 *
 * Real-time open/closed status for the farm stand.
 * Toggle from the field, display on the site.
 */

declare(strict_types=1);

namespace ProducerKit\StandStatus;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/meta-extensions.php';
require_once __DIR__ . '/includes/rest-extensions.php';
require_once __DIR__ . '/includes/admin-bar.php';
require_once __DIR__ . '/includes/abilities.php';
