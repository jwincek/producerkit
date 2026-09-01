<?php
/**
 * Producer profile: Beekeeping.
 *
 * An apiary sells three quite different things — honey graded by floral
 * source, hive products made from wax, and live bees sold months ahead of a
 * spring pickup. The taxonomy set covers the first two; the pre-order module
 * covers the third.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'label'       => __( 'Beekeeping', 'producerkit' ),
	'description' => __( 'Honey by floral source, hive products, nucs and queens.', 'producerkit' ),
	'taxonomies'  => [ 'lfuf_material', 'lfuf_finish', 'lfuf_component' ],
	'names'       => [
		'lfuf_material'  => [ __( 'Floral Source', 'producerkit' ), __( 'Floral Sources', 'producerkit' ) ],
		'lfuf_finish'    => [ __( 'Style', 'producerkit' ), __( 'Styles', 'producerkit' ) ],
		'lfuf_component' => [ __( 'Container', 'producerkit' ), __( 'Containers', 'producerkit' ) ],
	],
	'terms'       => [
		'lfuf_product_type' => [ 'Honey', 'Comb Honey', 'Creamed Honey', 'Infused Honey', 'Beeswax', 'Candle', 'Soap', 'Lip Balm', 'Pollen', 'Propolis', 'Nucleus Colony', 'Mated Queen', 'Package Bees' ],
		'lfuf_material'     => [ 'Wildflower', 'Clover', 'Orange Blossom', 'Buckwheat', 'Tulip Poplar', 'Sourwood', 'Goldenrod', 'Basswood', 'Alfalfa', 'Knotweed' ],
		'lfuf_finish'       => [ 'Raw', 'Unfiltered', 'Strained', 'Creamed', 'Chunk', 'Infused' ],
		'lfuf_component'    => [ '8 oz Queenline', '1 lb Jar', '2 lb Jar', 'Quart', 'Half Gallon', 'Gallon', '5 Gallon Bucket', 'Honey Bear', 'Muth Jar' ],
		'lfuf_event_type'   => [ 'Hive Tour', 'Beekeeping Class', 'Extraction Day', 'Bee Pickup', 'Market' ],
	],
];
