<?php
/**
 * Producer profile: Bakery.
 *
 * Frequently runs alongside the farm profile rather than instead of it — a
 * grower who mills and bakes, or two businesses sharing one site. The Source
 * post type already models that join: a loaf links to the grain it came from,
 * and `_lfuf_milling_notes` records the step between them.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'label'           => __( 'Bakery', 'producerkit' ),
	'description'     => __( 'Bread and pastry, graded by flour and method.', 'producerkit' ),
	'taxonomies'      => [ 'lfuf_material', 'lfuf_finish', 'lfuf_component' ],
	'names'           => [
		'lfuf_material'  => [ __( 'Flour', 'producerkit' ), __( 'Flours', 'producerkit' ) ],
		'lfuf_finish'    => [ __( 'Method', 'producerkit' ), __( 'Methods', 'producerkit' ) ],
		'lfuf_component' => [ __( 'Add-in', 'producerkit' ), __( 'Add-ins', 'producerkit' ) ],
	],
	'post_type_names' => [
		'lfuf_product' => [ '', '', __( 'Bakery', 'producerkit' ) ],
	],
	'terms'           => [
		'lfuf_product_type' => [ 'Sourdough', 'Baguette', 'Focaccia', 'Bagel', 'Croissant', 'Rye Loaf', 'Brioche', 'Pastry', 'Cookie', 'Scone' ],
		'lfuf_material'     => [ 'Bread Flour', 'Whole Wheat', 'Rye', 'Spelt', 'Einkorn', 'Semolina', 'All-Purpose' ],
		'lfuf_finish'       => [ 'Naturally Leavened', 'Yeasted', 'Enriched', 'Laminated', 'Par-Baked' ],
		'lfuf_component'    => [ 'Seeded', 'Olive', 'Walnut', 'Raisin', 'Chocolate', 'Cheese', 'Herb', 'Plain' ],
		'lfuf_event_type'   => [ 'Bake Day', 'Bread Class', 'Market', 'Pop-Up' ],
	],
];
