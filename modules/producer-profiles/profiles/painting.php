<?php
/**
 * Producer profile: Painting & Drawing.
 *
 * Medium and surface are how a painter's work is actually catalogued, and
 * framing is the option that changes what ships. Pairs naturally with the
 * commissions module — a portrait or a pet painting is the archetypal
 * quote-then-make job.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'label'           => __( 'Painting & Drawing', 'producerkit' ),
	'description'     => __( 'Original work on canvas and paper, plus prints and commissions.', 'producerkit' ),
	'taxonomies'      => [ 'lfuf_material', 'lfuf_finish', 'lfuf_component' ],
	'names'           => [
		'lfuf_material'  => [ __( 'Medium', 'producerkit' ), __( 'Media', 'producerkit' ) ],
		'lfuf_finish'    => [ __( 'Surface', 'producerkit' ), __( 'Surfaces', 'producerkit' ) ],
		'lfuf_component' => [ __( 'Framing', 'producerkit' ), __( 'Framing', 'producerkit' ) ],
	],
	'post_type_names' => [
		'lfuf_product' => [ __( 'Work', 'producerkit' ), __( 'Works', 'producerkit' ), __( 'Works', 'producerkit' ) ],
	],
	'terms'           => [
		'lfuf_product_type' => [ 'Painting', 'Drawing', 'Study', 'Sketch', 'Giclee Print', 'Screen Print', 'Miniature', 'Diptych', 'Sketchbook' ],
		'lfuf_material'     => [ 'Oil', 'Acrylic', 'Watercolour', 'Gouache', 'Graphite', 'Charcoal', 'Ink', 'Soft Pastel', 'Coloured Pencil', 'Mixed Media' ],
		'lfuf_finish'       => [ 'Stretched Canvas', 'Canvas Panel', 'Linen', 'Cradled Panel', 'Cold Press Paper', 'Hot Press Paper', 'Toned Paper', 'Illustration Board' ],
		'lfuf_component'    => [ 'Unframed', 'Float Frame', 'Gallery Wrap', 'Matted', 'Framed & Glazed', 'Rolled' ],
		'lfuf_event_type'   => [ 'Open Studio', 'Gallery Show', 'Art Fair', 'Workshop', 'Life Drawing', 'Live Painting' ],
	],
];
