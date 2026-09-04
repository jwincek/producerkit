<?php
/**
 * Producer profile: Screen Printing.
 *
 * Substrate, ink and colour count are the three things a print shop quotes
 * from, so they take the three optional axes directly. Often runs alongside
 * the Musician profile — a band printing its own shirts is both trades at
 * once, which is what multi-profile is for.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'label'       => __( 'Screen Printing', 'producerkit' ),
	'description' => __( 'Printed shirts, totes and posters, priced by substrate, ink and colour count.', 'producerkit' ),
	'taxonomies'  => [ 'pkit_material', 'pkit_finish', 'pkit_component' ],
	'names'       => [
		'pkit_material'  => [ __( 'Substrate', 'producerkit' ), __( 'Substrates', 'producerkit' ) ],
		'pkit_finish'    => [ __( 'Ink', 'producerkit' ), __( 'Inks', 'producerkit' ) ],
		'pkit_component' => [ __( 'Colours', 'producerkit' ), __( 'Colours', 'producerkit' ) ],
	],
	'meta_labels'     => [
		'_pkit_source_farm_name' => [ __( 'Blank Supplier', 'producerkit' ), __( 'Who supplied the blanks. Falls back to the post title on the front end if left empty.', 'producerkit' ) ],
		'_pkit_source_history' => [ __( 'Background', 'producerkit' ), __( 'The story behind this blank or supplier.', 'producerkit' ) ],
		'_pkit_milling_notes' => [ __( 'Printing Notes', 'producerkit' ), __( 'Inks, mesh, cure — how it was printed.', 'producerkit' ) ],
		'_pkit_growing_notes' => [ __( 'Printing Notes', 'producerkit' ), __( 'Shown on the product page. Free-form.', 'producerkit' ) ],
	],
	'terms'       => [
		'pkit_product_type' => [ 'T-Shirt', 'Long Sleeve', 'Hoodie', 'Crewneck', 'Tote Bag', 'Poster', 'Art Print', 'Tea Towel', 'Patch', 'Sticker' ],
		'pkit_material'     => [ 'Ring-Spun Cotton', 'Heavyweight Cotton', 'Tri-Blend', 'Poly-Cotton', 'Organic Cotton', 'Canvas', 'Cover Stock', 'French Paper', 'Newsprint' ],
		'pkit_finish'       => [ 'Water-Based', 'Discharge', 'Plastisol', 'Puff', 'Metallic', 'Glow in the Dark', 'Soft Hand' ],
		'pkit_component'    => [ '1 Colour', '2 Colour', '3 Colour', '4 Colour', 'Halftone', 'Split Fountain', 'Full Bleed' ],
		'pkit_event_type'   => [ 'Print Sale', 'Studio Sale', 'Market', 'Workshop', 'Live Printing' ],
	],
];
