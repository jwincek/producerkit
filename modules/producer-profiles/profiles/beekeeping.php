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
	'taxonomies'  => [ 'pkit_material', 'pkit_finish', 'pkit_component' ],
	'names'       => [
		'pkit_material'  => [ __( 'Floral Source', 'producerkit' ), __( 'Floral Sources', 'producerkit' ) ],
		'pkit_finish'    => [ __( 'Style', 'producerkit' ), __( 'Styles', 'producerkit' ) ],
		'pkit_component' => [ __( 'Container', 'producerkit' ), __( 'Containers', 'producerkit' ) ],
	],
	'request_names'   => [
		'singular' => __( 'Enquiry', 'producerkit' ),
		'plural'   => __( 'Enquiries', 'producerkit' ),
		'menu'     => __( 'Enquiries', 'producerkit' ),
		'action'   => __( 'Ask about bulk orders', 'producerkit' ),
	],
	'meta_labels'     => [
		'_pkit_source_farm_name' => [ __( 'Apiary', 'producerkit' ), __( 'Which yard this came from. Falls back to the post title on the front end if left empty.', 'producerkit' ) ],
		'_pkit_source_location' => [ __( 'Yard Location', 'producerkit' ), __( 'County and state, or however you would say where.', 'producerkit' ) ],
		'_pkit_source_history' => [ __( 'Forage Notes', 'producerkit' ), __( 'What the bees were working — the floral sources behind this crop.', 'producerkit' ) ],
		'_pkit_milling_notes' => [ __( 'Extraction Notes', 'producerkit' ), __( 'How it was taken off and handled — crush, spin, strain, settle.', 'producerkit' ) ],
		'_pkit_growing_notes' => [ __( 'Hive Notes', 'producerkit' ), __( 'Shown on the product page. Free-form.', 'producerkit' ) ],
	],
	'terms'       => [
		'pkit_product_type' => [ 'Honey', 'Comb Honey', 'Creamed Honey', 'Infused Honey', 'Beeswax', 'Candle', 'Soap', 'Lip Balm', 'Pollen', 'Propolis', 'Nucleus Colony', 'Mated Queen', 'Package Bees' ],
		'pkit_material'     => [ 'Wildflower', 'Clover', 'Orange Blossom', 'Buckwheat', 'Tulip Poplar', 'Sourwood', 'Goldenrod', 'Basswood', 'Alfalfa', 'Knotweed' ],
		'pkit_finish'       => [ 'Raw', 'Unfiltered', 'Strained', 'Creamed', 'Chunk', 'Infused' ],
		'pkit_component'    => [ '8 oz Queenline', '1 lb Jar', '2 lb Jar', 'Quart', 'Half Gallon', 'Gallon', '5 Gallon Bucket', 'Honey Bear', 'Muth Jar' ],
		'pkit_event_type'   => [ 'Hive Tour', 'Beekeeping Class', 'Extraction Day', 'Bee Pickup', 'Market' ],
	],
];
