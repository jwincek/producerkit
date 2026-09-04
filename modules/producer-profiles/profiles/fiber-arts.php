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
	'meta_labels'     => [
		'_pkit_source_farm_name' => [ __( 'Flock / Mill', 'producerkit' ), __( 'Where the fibre came from. Falls back to the post title on the front end if left empty.', 'producerkit' ) ],
		'_pkit_source_history' => [ __( 'Background', 'producerkit' ), __( 'The story behind this flock or fibre.', 'producerkit' ) ],
		'_pkit_milling_notes' => [ __( 'Processing Notes', 'producerkit' ), __( 'How it was scoured, carded, spun and plied.', 'producerkit' ) ],
		'_pkit_growing_notes' => [ __( 'Making Notes', 'producerkit' ), __( 'Shown on the product page. Free-form.', 'producerkit' ) ],
	],
	'terms'       => [
		'pkit_product_type' => [ 'Scarf', 'Blanket', 'Bag', 'Hat', 'Mittens', 'Shawl', 'Wall Hanging', 'Table Runner', 'Pillow', 'Garment' ],
		'pkit_material'     => [ 'Merino Wool', 'Alpaca', 'Cotton', 'Linen', 'Silk', 'Bamboo', 'Hemp', 'Cashmere', 'Mohair', 'Wool Blend' ],
		'pkit_finish'       => [ 'Natural', 'Hand Dyed', 'Plant Dyed', 'Felted', 'Blocked', 'Raw' ],
		'pkit_component'    => [ 'Hand Knit', 'Hand Woven', 'Crocheted', 'Macrame', 'Punch Needle', 'Embroidered', 'Quilted', 'Hand Sewn' ],
	],
];
