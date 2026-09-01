<?php
/**
 * Producer profile: Leather.
 *
 * Ported from WC Artisan Tools' craft profiles.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'label'       => __( 'Leather', 'producerkit' ),
	'description' => __( 'Hand-cut and hand-stitched leather goods.', 'producerkit' ),
	'taxonomies'  => [ 'lfuf_material', 'lfuf_finish', 'lfuf_component' ],
	'names'       => [
		'lfuf_material'  => [ __( 'Leather Type', 'producerkit' ), __( 'Leather Types', 'producerkit' ) ],
		'lfuf_component' => [ __( 'Hardware', 'producerkit' ), __( 'Hardware', 'producerkit' ) ],
	],
	'terms'       => [
		'lfuf_product_type' => [ 'Wallet', 'Belt', 'Bag', 'Journal Cover', 'Key Fob', 'Card Holder', 'Watch Strap', 'Holster', 'Dog Collar', 'Sheath' ],
		'lfuf_material'     => [ 'Vegetable Tanned', 'Chrome Tanned', 'Bridle Leather', 'Latigo', 'Shell Cordovan', 'Horween Chromexcel', 'Bison', 'Kangaroo', 'Suede' ],
		'lfuf_finish'       => [ 'Natural', 'Hand Dyed', 'Burnished', 'Oil Rubbed', 'Waxed', 'Antique', 'Matte' ],
		'lfuf_component'    => [ 'Solid Brass', 'Nickel', 'Stainless Steel', 'Copper Rivet', 'Antique Brass', 'No Hardware' ],
	],
];
