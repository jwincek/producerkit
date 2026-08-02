<?php
/**
 * Stand Status module bootstrap.
 *
 * Real-time open/closed status for the farm stand.
 * Toggle from the field, display on the site.
 */

declare(strict_types=1);

namespace Leftfield\StandStatus;

defined( 'ABSPATH' ) || exit;

$module_dir = __DIR__;

require_once $module_dir . '/includes/meta-extensions.php';
require_once $module_dir . '/includes/rest-extensions.php';
require_once $module_dir . '/includes/admin-bar.php';
require_once $module_dir . '/includes/abilities.php';
