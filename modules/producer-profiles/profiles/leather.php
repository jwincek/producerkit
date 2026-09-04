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
	'taxonomies'  => [ 'pkit_material', 'pkit_finish', 'pkit_component' ],
	'names'       => [
		'pkit_material'  => [ __( 'Leather Type', 'producerkit' ), __( 'Leather Types', 'producerkit' ) ],
		'pkit_component' => [ __( 'Hardware', 'producerkit' ), __( 'Hardware', 'producerkit' ) ],
	],
	'meta_labels'     => [
		'_pkit_source_farm_name' => [ __( 'Tannery', 'producerkit' ), __( 'Who supplied the hide. Falls back to the post title on the front end if left empty.', 'producerkit' ) ],
		'_pkit_source_history' => [ __( 'Background', 'producerkit' ), __( 'The story behind this hide or supplier.', 'producerkit' ) ],
		'_pkit_milling_notes' => [ __( 'Tanning Notes', 'producerkit' ), __( 'How it was tanned and finished — veg, chrome, drum, hand-rubbed.', 'producerkit' ) ],
		'_pkit_growing_notes' => [ __( 'Making Notes', 'producerkit' ), __( 'Shown on the product page. Free-form.', 'producerkit' ) ],
	],
	'terms'       => [
		'pkit_product_type' => [ 'Wallet', 'Belt', 'Bag', 'Journal Cover', 'Key Fob', 'Card Holder', 'Watch Strap', 'Holster', 'Dog Collar', 'Sheath' ],
		'pkit_material'     => [ 'Vegetable Tanned', 'Chrome Tanned', 'Bridle Leather', 'Latigo', 'Shell Cordovan', 'Horween Chromexcel', 'Bison', 'Kangaroo', 'Suede' ],
		'pkit_finish'       => [ 'Natural', 'Hand Dyed', 'Burnished', 'Oil Rubbed', 'Waxed', 'Antique', 'Matte' ],
		'pkit_component'    => [ 'Solid Brass', 'Nickel', 'Stainless Steel', 'Copper Rivet', 'Antique Brass', 'No Hardware' ],
	],
];
