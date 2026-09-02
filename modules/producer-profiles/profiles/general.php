<?php
/**
 * Producer profile: General.
 *
 * Ported from WC Artisan Tools' craft profiles.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'label'       => __( 'General', 'producerkit' ),
	'description' => __( 'A blank slate: the full field set with no vocabulary seeded.', 'producerkit' ),
	'taxonomies'  => [ 'pkit_material', 'pkit_finish', 'pkit_component' ],
	'names'       => [],
	'terms'       => [
		'pkit_product_type' => [],
		'pkit_material'     => [],
		'pkit_finish'       => [],
		'pkit_component'    => [],
	],
];
