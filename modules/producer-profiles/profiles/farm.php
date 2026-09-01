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
	'terms'       => [
		'lfuf_product_type' => [ 'Produce', 'Bread', 'Baked Good', 'Pantry Good', 'Seedling' ],
		'lfuf_season'       => [ 'Spring', 'Summer', 'Fall', 'Winter' ],
		'lfuf_event_type'   => [ 'Pizza Night', 'Potluck', 'Farm Dinner', 'Workshop', 'Farm Tour', 'Seed Exchange', 'Mini Market' ],
	],
];
