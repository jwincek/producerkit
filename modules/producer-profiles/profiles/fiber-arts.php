<?php
/**
 * Producer profile: Fiber Arts.
 *
 * Ported from WC Artisan Tools' craft profiles.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'label'       => __( 'Fiber Arts', 'producerkit' ),
	'description' => __( 'Knitted, woven and stitched textiles.', 'producerkit' ),
	'taxonomies'  => [ 'pkit_material', 'pkit_finish', 'pkit_component' ],
	'names'       => [
		'pkit_material'  => [ __( 'Fiber', 'producerkit' ), __( 'Fibers', 'producerkit' ) ],
		'pkit_component' => [ __( 'Technique', 'producerkit' ), __( 'Techniques', 'producerkit' ) ],
	],
	'terms'       => [
		'pkit_product_type' => [ 'Scarf', 'Blanket', 'Bag', 'Hat', 'Mittens', 'Shawl', 'Wall Hanging', 'Table Runner', 'Pillow', 'Garment' ],
		'pkit_material'     => [ 'Merino Wool', 'Alpaca', 'Cotton', 'Linen', 'Silk', 'Bamboo', 'Hemp', 'Cashmere', 'Mohair', 'Wool Blend' ],
		'pkit_finish'       => [ 'Natural', 'Hand Dyed', 'Plant Dyed', 'Felted', 'Blocked', 'Raw' ],
		'pkit_component'    => [ 'Hand Knit', 'Hand Woven', 'Crocheted', 'Macrame', 'Punch Needle', 'Embroidered', 'Quilted', 'Hand Sewn' ],
	],
];
