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
	'taxonomies'  => [ 'lfuf_material', 'lfuf_finish', 'lfuf_component' ],
	'names'       => [
		'lfuf_material'  => [ __( 'Wood Species', 'producerkit' ), __( 'Wood Species', 'producerkit' ) ],
		'lfuf_component' => [ __( 'Hardware', 'producerkit' ), __( 'Hardware', 'producerkit' ) ],
	],
	'terms'       => [
		'lfuf_product_type' => [ 'Pen', 'Bowl', 'Razor', 'Coffee Scoop', 'Bottle Stopper', 'Letter Opener', 'Cutting Board', 'Rolling Pin', 'Utensil' ],
		'lfuf_material'     => [ 'Black Walnut', 'Cherry', 'Maple', 'Spalted Maple', 'Oak', 'Olive', 'Padauk', 'Purple Heart', 'Bocote', 'Zebrawood', 'Cedar', 'Hickory', 'Ash', 'Ebony', 'Cocobolo' ],
		'lfuf_finish'       => [ 'CA Glue', 'Tung Oil', 'Beeswax', 'Danish Oil', 'Lacquer', 'Food Safe', 'Raw' ],
		'lfuf_component'    => [ 'Slimline', 'Cigar', 'Bolt Action', 'Tactical', 'Click', 'Fountain', 'Rollerball', 'Double Edge Razor', 'Mach 3 Razor' ],
	],
];
