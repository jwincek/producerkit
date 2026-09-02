<?php
/**
 * Producer profile: Comics & Graphic Novels.
 *
 * Named for both because the same table holds both: a small-press creator
 * sells the long-form book next to single issues, minis and zines, and the
 * axes that matter are the same across all of them.
 *
 * Print process takes the Material axis rather than paper stock, because in
 * small-press comics it is the thing that decides the look and most of the
 * price — a risograph mini and an offset trade are different objects to a
 * buyer in a way that "uncoated vs coated" never is.
 *
 * Deliberately leaves the post type as Product/Products and re-words only the
 * menu. The catalogue also holds prints, original pages and pins, and "Add
 * New Comic" would be wrong on three of those.
 *
 * Pairs with Author for someone who writes prose too, and with Painting &
 * Drawing for someone selling original art alongside the printed book.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'label'           => __( 'Comics & Graphic Novels', 'producerkit' ),
	'description'     => __( 'Graphic novels, single issues, minis and zines, plus prints and original pages.', 'producerkit' ),
	'taxonomies'      => [ 'lfuf_material', 'lfuf_finish', 'lfuf_component' ],
	'names'           => [
		'lfuf_material'  => [ __( 'Printing', 'producerkit' ), __( 'Printing', 'producerkit' ) ],
		'lfuf_finish'    => [ __( 'Edition', 'producerkit' ), __( 'Editions', 'producerkit' ) ],
		'lfuf_component' => [ __( 'Binding', 'producerkit' ), __( 'Bindings', 'producerkit' ) ],
	],
	'post_type_names' => [
		'lfuf_product' => [ '', '', __( 'Comics', 'producerkit' ) ],
	],
	'terms'           => [
		'lfuf_product_type' => [ 'Graphic Novel', 'Single Issue', 'Trade Paperback', 'Mini-Comic', 'Ashcan', 'Zine', 'Anthology', 'Art Book', 'Sketchbook', 'Original Page', 'Print', 'Sticker', 'Pin' ],
		'lfuf_material'     => [ 'Offset', 'Risograph', 'Digital', 'Photocopy', 'Screen Printed', 'Letterpress' ],
		'lfuf_finish'       => [ 'First Printing', 'Second Printing', 'Signed', 'Numbered', 'Sketch Edition', 'Variant Cover', 'Convention Exclusive', 'Standard' ],
		'lfuf_component'    => [ 'Saddle Stitched', 'Perfect Bound', 'Hardcover', 'Digest', 'Ashcan', 'Accordion Fold', 'Hand Bound' ],
		'lfuf_event_type'   => [ 'Comic Con', 'Small Press Expo', 'Zine Fest', 'Artist Alley', 'Signing', 'Launch', 'Workshop' ],
	],
];
