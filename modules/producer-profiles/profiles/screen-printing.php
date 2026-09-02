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
	'taxonomies'  => [ 'lfuf_material', 'lfuf_finish', 'lfuf_component' ],
	'names'       => [
		'lfuf_material'  => [ __( 'Substrate', 'producerkit' ), __( 'Substrates', 'producerkit' ) ],
		'lfuf_finish'    => [ __( 'Ink', 'producerkit' ), __( 'Inks', 'producerkit' ) ],
		'lfuf_component' => [ __( 'Colours', 'producerkit' ), __( 'Colours', 'producerkit' ) ],
	],
	'terms'       => [
		'lfuf_product_type' => [ 'T-Shirt', 'Long Sleeve', 'Hoodie', 'Crewneck', 'Tote Bag', 'Poster', 'Art Print', 'Tea Towel', 'Patch', 'Sticker' ],
		'lfuf_material'     => [ 'Ring-Spun Cotton', 'Heavyweight Cotton', 'Tri-Blend', 'Poly-Cotton', 'Organic Cotton', 'Canvas', 'Cover Stock', 'French Paper', 'Newsprint' ],
		'lfuf_finish'       => [ 'Water-Based', 'Discharge', 'Plastisol', 'Puff', 'Metallic', 'Glow in the Dark', 'Soft Hand' ],
		'lfuf_component'    => [ '1 Colour', '2 Colour', '3 Colour', '4 Colour', 'Halftone', 'Split Fountain', 'Full Bleed' ],
		'lfuf_event_type'   => [ 'Print Sale', 'Studio Sale', 'Market', 'Workshop', 'Live Printing' ],
	],
];
