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
	'taxonomies'      => [ 'pkit_material', 'pkit_finish', 'pkit_component' ],
	'names'           => [
		'pkit_material'  => [ __( 'Medium', 'producerkit' ), __( 'Media', 'producerkit' ) ],
		'pkit_finish'    => [ __( 'Surface', 'producerkit' ), __( 'Surfaces', 'producerkit' ) ],
		'pkit_component' => [ __( 'Framing', 'producerkit' ), __( 'Framing', 'producerkit' ) ],
	],
	'post_type_names' => [
		'pkit_product' => [ __( 'Work', 'producerkit' ), __( 'Works', 'producerkit' ), __( 'Works', 'producerkit' ) ],
	],
	'meta_labels'     => [
		'_pkit_growing_notes' => [ __( 'Making Notes', 'producerkit' ), __( 'Shown on the product page. Free-form.', 'producerkit' ) ],
	],
	'terms'           => [
		'pkit_product_type' => [ 'Painting', 'Drawing', 'Study', 'Sketch', 'Giclee Print', 'Screen Print', 'Miniature', 'Diptych', 'Sketchbook' ],
		'pkit_material'     => [ 'Oil', 'Acrylic', 'Watercolour', 'Gouache', 'Graphite', 'Charcoal', 'Ink', 'Soft Pastel', 'Coloured Pencil', 'Mixed Media' ],
		'pkit_finish'       => [ 'Stretched Canvas', 'Canvas Panel', 'Linen', 'Cradled Panel', 'Cold Press Paper', 'Hot Press Paper', 'Toned Paper', 'Illustration Board' ],
		'pkit_component'    => [ 'Unframed', 'Float Frame', 'Gallery Wrap', 'Matted', 'Framed & Glazed', 'Rolled' ],
		'pkit_event_type'   => [ 'Open Studio', 'Gallery Show', 'Art Fair', 'Workshop', 'Life Drawing', 'Live Painting' ],
	],
];
