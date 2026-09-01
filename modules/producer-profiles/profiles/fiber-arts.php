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
	'taxonomies'  => [ 'lfuf_material', 'lfuf_finish', 'lfuf_component' ],
	'names'       => [
		'lfuf_material'  => [ __( 'Fiber', 'producerkit' ), __( 'Fibers', 'producerkit' ) ],
		'lfuf_component' => [ __( 'Technique', 'producerkit' ), __( 'Techniques', 'producerkit' ) ],
	],
	'terms'       => [
		'lfuf_product_type' => [ 'Scarf', 'Blanket', 'Bag', 'Hat', 'Mittens', 'Shawl', 'Wall Hanging', 'Table Runner', 'Pillow', 'Garment' ],
		'lfuf_material'     => [ 'Merino Wool', 'Alpaca', 'Cotton', 'Linen', 'Silk', 'Bamboo', 'Hemp', 'Cashmere', 'Mohair', 'Wool Blend' ],
		'lfuf_finish'       => [ 'Natural', 'Hand Dyed', 'Plant Dyed', 'Felted', 'Blocked', 'Raw' ],
		'lfuf_component'    => [ 'Hand Knit', 'Hand Woven', 'Crocheted', 'Macrame', 'Punch Needle', 'Embroidered', 'Quilted', 'Hand Sewn' ],
	],
];
