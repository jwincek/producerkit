<?php
/**
 * Producer profile: Woodworking.
 *
 * Ported from WC Artisan Tools' craft profiles.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'label'       => __( 'Woodworking', 'producerkit' ),
	'description' => __( 'Hand-turned and hand-crafted wood items.', 'producerkit' ),
	'taxonomies'  => [ 'pkit_material', 'pkit_finish', 'pkit_component' ],
	'names'       => [
		'pkit_material'  => [ __( 'Wood Species', 'producerkit' ), __( 'Wood Species', 'producerkit' ) ],
		'pkit_component' => [ __( 'Hardware', 'producerkit' ), __( 'Hardware', 'producerkit' ) ],
	],
	'meta_labels'     => [
		'_pkit_source_farm_name' => [ __( 'Woodlot / Supplier', 'producerkit' ), __( 'Where the timber came from. Falls back to the post title on the front end if left empty.', 'producerkit' ) ],
		'_pkit_source_history' => [ __( 'Background', 'producerkit' ), __( 'The story behind this tree or stand of timber.', 'producerkit' ) ],
		'_pkit_milling_notes' => [ __( 'Milling & Drying Notes', 'producerkit' ), __( 'How it was cut and seasoned — quarter-sawn, air-dried, kiln.', 'producerkit' ) ],
		'_pkit_growing_notes' => [ __( 'Making Notes', 'producerkit' ), __( 'Shown on the product page. Free-form.', 'producerkit' ) ],
	],
	'terms'       => [
		'pkit_product_type' => [ 'Pen', 'Bowl', 'Razor', 'Coffee Scoop', 'Bottle Stopper', 'Letter Opener', 'Cutting Board', 'Rolling Pin', 'Utensil' ],
		'pkit_material'     => [ 'Black Walnut', 'Cherry', 'Maple', 'Spalted Maple', 'Oak', 'Olive', 'Padauk', 'Purple Heart', 'Bocote', 'Zebrawood', 'Cedar', 'Hickory', 'Ash', 'Ebony', 'Cocobolo' ],
		'pkit_finish'       => [ 'CA Glue', 'Tung Oil', 'Beeswax', 'Danish Oil', 'Lacquer', 'Food Safe', 'Raw' ],
		'pkit_component'    => [ 'Slimline', 'Cigar', 'Bolt Action', 'Tactical', 'Click', 'Fountain', 'Rollerball', 'Double Edge Razor', 'Mach 3 Razor' ],
	],
];
