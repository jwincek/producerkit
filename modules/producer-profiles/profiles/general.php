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
	'taxonomies'  => [ 'lfuf_material', 'lfuf_finish', 'lfuf_component' ],
	'names'       => [],
	'terms'       => [
		'lfuf_product_type' => [],
		'lfuf_material'     => [],
		'lfuf_finish'       => [],
		'lfuf_component'    => [],
	],
];
