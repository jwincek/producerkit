<?php
/**
 * Producer profile: Farm.
 *
 * The plugin's original vocabulary, and the default. Mirrors the terms core
 * seeds on its own so that a site with this profile active behaves exactly as
 * it did before profiles existed.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'label'       => __( 'Farm', 'producerkit' ),
	'description' => __( 'Produce, bread and pantry goods for a farm stand or market garden.', 'producerkit' ),
	// A farm sells what it grew; material/finish/component add nothing.
	'taxonomies'  => [],
	'names'       => [],
	'request_names'   => [
		'singular' => __( 'Special Order', 'producerkit' ),
		'plural'   => __( 'Special Orders', 'producerkit' ),
		'menu'     => __( 'Special Orders', 'producerkit' ),
		'action'   => __( 'Request a special order', 'producerkit' ),
	],
	'terms'       => [
		'pkit_product_type' => [ 'Produce', 'Bread', 'Baked Good', 'Pantry Good', 'Seedling' ],
		'pkit_season'       => [ 'Spring', 'Summer', 'Fall', 'Winter' ],
		'pkit_event_type'   => [ 'Pizza Night', 'Potluck', 'Farm Dinner', 'Workshop', 'Farm Tour', 'Seed Exchange', 'Mini Market' ],
	],
];
