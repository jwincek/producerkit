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
	'taxonomies'  => [ 'lfuf_material', 'lfuf_finish', 'lfuf_component' ],
	'names'       => [
		'lfuf_material'  => [ __( 'Clay Body', 'producerkit' ), __( 'Clay Bodies', 'producerkit' ) ],
		'lfuf_finish'    => [ __( 'Glaze', 'producerkit' ), __( 'Glazes', 'producerkit' ) ],
		'lfuf_component' => [ __( 'Firing Method', 'producerkit' ), __( 'Firing Methods', 'producerkit' ) ],
	],
	'terms'       => [
		'lfuf_product_type' => [ 'Mug', 'Bowl', 'Vase', 'Plate', 'Platter', 'Pitcher', 'Teapot', 'Cup', 'Planter', 'Sculpture' ],
		'lfuf_material'     => [ 'Stoneware', 'Porcelain', 'Earthenware', 'Raku', 'Terracotta' ],
		'lfuf_finish'       => [ 'Matte Glaze', 'Glossy Glaze', 'Satin Glaze', 'Ash Glaze', 'Salt Glaze', 'Unglazed', 'Slip Decorated', 'Celadon' ],
		'lfuf_component'    => [ 'Cone 6 Electric', 'Cone 10 Gas', 'Wood Fired', 'Raku Fired', 'Pit Fired', 'Soda Fired' ],
	],
];
