<?php
/**
 * Producer profile: Author.
 *
 * A writer selling their own books — at readings, at fairs, and by post.
 * Binding and edition are the two axes that actually change the price, which
 * is why they take Material and Finish rather than anything about genre.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'label'           => __( 'Author', 'producerkit' ),
	'description'     => __( 'Self-published and small-press books, sold at readings and by post.', 'producerkit' ),
	'taxonomies'      => [ 'lfuf_material', 'lfuf_finish', 'lfuf_component' ],
	'names'           => [
		'lfuf_material'  => [ __( 'Binding', 'producerkit' ), __( 'Bindings', 'producerkit' ) ],
		'lfuf_finish'    => [ __( 'Edition', 'producerkit' ), __( 'Editions', 'producerkit' ) ],
		'lfuf_component' => [ __( 'Extra', 'producerkit' ), __( 'Extras', 'producerkit' ) ],
	],
	'post_type_names' => [
		'lfuf_product' => [ __( 'Title', 'producerkit' ), __( 'Titles', 'producerkit' ), __( 'Titles', 'producerkit' ) ],
	],
	'terms'           => [
		'lfuf_product_type' => [ 'Novel', 'Novella', 'Short Stories', 'Poetry', 'Chapbook', 'Zine', 'Anthology', 'Non-fiction', 'Illustrated', 'Childrens' ],
		'lfuf_material'     => [ 'Hardcover', 'Trade Paperback', 'Mass Market', 'Perfect Bound', 'Saddle Stitched', 'Hand Sewn', 'Spiral Bound' ],
		'lfuf_finish'       => [ 'First Edition', 'Second Printing', 'Signed', 'Numbered', 'Lettered', 'Advance Copy', 'Standard' ],
		'lfuf_component'    => [ 'Dust Jacket', 'Bookplate', 'Slipcase', 'Broadside', 'Postcard', 'None' ],
		'lfuf_event_type'   => [ 'Reading', 'Book Launch', 'Signing', 'Workshop', 'Book Fair', 'Panel' ],
	],
];
