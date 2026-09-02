<?php
/**
 * Producer profile: Pottery.
 *
 * Ported from WC Artisan Tools' craft profiles.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'label'       => __( 'Pottery', 'producerkit' ),
	'description' => __( 'Wheel-thrown and hand-built ceramics.', 'producerkit' ),
	'taxonomies'  => [ 'pkit_material', 'pkit_finish', 'pkit_component' ],
	'names'       => [
		'pkit_material'  => [ __( 'Clay Body', 'producerkit' ), __( 'Clay Bodies', 'producerkit' ) ],
		'pkit_finish'    => [ __( 'Glaze', 'producerkit' ), __( 'Glazes', 'producerkit' ) ],
		'pkit_component' => [ __( 'Firing Method', 'producerkit' ), __( 'Firing Methods', 'producerkit' ) ],
	],
	'terms'       => [
		'pkit_product_type' => [ 'Mug', 'Bowl', 'Vase', 'Plate', 'Platter', 'Pitcher', 'Teapot', 'Cup', 'Planter', 'Sculpture' ],
		'pkit_material'     => [ 'Stoneware', 'Porcelain', 'Earthenware', 'Raku', 'Terracotta' ],
		'pkit_finish'       => [ 'Matte Glaze', 'Glossy Glaze', 'Satin Glaze', 'Ash Glaze', 'Salt Glaze', 'Unglazed', 'Slip Decorated', 'Celadon' ],
		'pkit_component'    => [ 'Cone 6 Electric', 'Cone 10 Gas', 'Wood Fired', 'Raku Fired', 'Pit Fired', 'Soda Fired' ],
	],
];
