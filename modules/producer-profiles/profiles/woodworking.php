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
	'terms'       => [
		'pkit_product_type' => [ 'Pen', 'Bowl', 'Razor', 'Coffee Scoop', 'Bottle Stopper', 'Letter Opener', 'Cutting Board', 'Rolling Pin', 'Utensil' ],
		'pkit_material'     => [ 'Black Walnut', 'Cherry', 'Maple', 'Spalted Maple', 'Oak', 'Olive', 'Padauk', 'Purple Heart', 'Bocote', 'Zebrawood', 'Cedar', 'Hickory', 'Ash', 'Ebony', 'Cocobolo' ],
		'pkit_finish'       => [ 'CA Glue', 'Tung Oil', 'Beeswax', 'Danish Oil', 'Lacquer', 'Food Safe', 'Raw' ],
		'pkit_component'    => [ 'Slimline', 'Cigar', 'Bolt Action', 'Tactical', 'Click', 'Fountain', 'Rollerball', 'Double Edge Razor', 'Mach 3 Razor' ],
	],
];
