<?php
/**
 * Producer profile: Bakery.
 *
 * Frequently runs alongside the farm profile rather than instead of it — a
 * grower who mills and bakes, or two businesses sharing one site. The Source
 * post type already models that join: a loaf links to the grain it came from,
 * and `_pkit_milling_notes` records the step between them.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'label'           => __( 'Bakery', 'producerkit' ),
	'description'     => __( 'Bread and pastry, graded by flour and method.', 'producerkit' ),
	'taxonomies'      => [ 'pkit_material', 'pkit_finish', 'pkit_component' ],
	'names'           => [
		'pkit_material'  => [ __( 'Flour', 'producerkit' ), __( 'Flours', 'producerkit' ) ],
		'pkit_finish'    => [ __( 'Method', 'producerkit' ), __( 'Methods', 'producerkit' ) ],
		'pkit_component' => [ __( 'Add-in', 'producerkit' ), __( 'Add-ins', 'producerkit' ) ],
	],
	'post_type_names' => [
		'pkit_product' => [ '', '', __( 'Bakery', 'producerkit' ) ],
	],
	'terms'           => [
		'pkit_product_type' => [ 'Sourdough', 'Baguette', 'Focaccia', 'Bagel', 'Croissant', 'Rye Loaf', 'Brioche', 'Pastry', 'Cookie', 'Scone' ],
		'pkit_material'     => [ 'Bread Flour', 'Whole Wheat', 'Rye', 'Spelt', 'Einkorn', 'Semolina', 'All-Purpose' ],
		'pkit_finish'       => [ 'Naturally Leavened', 'Yeasted', 'Enriched', 'Laminated', 'Par-Baked' ],
		'pkit_component'    => [ 'Seeded', 'Olive', 'Walnut', 'Raisin', 'Chocolate', 'Cheese', 'Herb', 'Plain' ],
		'pkit_event_type'   => [ 'Bake Day', 'Bread Class', 'Market', 'Pop-Up' ],
	],
];
