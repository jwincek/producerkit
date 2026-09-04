<?php
/**
 * Producer profile: Musician.
 *
 * A band's merch table is a stand: a short catalogue, stock that differs by
 * night, cash and Venmo, and a pile of records that has to be carried in and
 * counted out. The event/location model covers the shows themselves — a gig
 * is an event at a venue with a capacity and a set of records being sold at
 * it — so this profile only has to supply the vocabulary.
 *
 * Format is deliberately scoped to recordings rather than trying to cover
 * apparel too: a t-shirt simply leaves the field empty, which is tidier than
 * one axis that means "vinyl weight" and "cotton blend" by turns.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'label'           => __( 'Musician', 'producerkit' ),
	'description'     => __( 'Records, tapes and merch for a band or solo artist, with shows as events.', 'producerkit' ),
	'taxonomies'      => [ 'pkit_material', 'pkit_finish', 'pkit_component' ],
	// "Merch" is what a band calls the table; "Catalog" is what everyone else
	// calls it. Only the sidebar word changes — it is still a Product on the
	// edit screen, where the generic word reads better.
	'post_type_names' => [
		'pkit_product' => [ '', '', __( 'Merch', 'producerkit' ) ],
		'pkit_event'   => [ __( 'Show', 'producerkit' ), __( 'Shows', 'producerkit' ), __( 'Shows', 'producerkit' ) ],
	],
	'names'           => [
		'pkit_material'  => [ __( 'Format', 'producerkit' ), __( 'Formats', 'producerkit' ) ],
		'pkit_finish'    => [ __( 'Edition', 'producerkit' ), __( 'Editions', 'producerkit' ) ],
		'pkit_component' => [ __( 'Packaging', 'producerkit' ), __( 'Packaging', 'producerkit' ) ],
	],
	'request_names'   => [
		'singular' => __( 'Booking', 'producerkit' ),
		'plural'   => __( 'Bookings', 'producerkit' ),
		'menu'     => __( 'Bookings', 'producerkit' ),
		'action'   => __( 'Enquire about a booking', 'producerkit' ),
	],
	'meta_labels'     => [
		'_pkit_source_farm_name' => [ __( 'Label', 'producerkit' ), __( 'Who released it. Falls back to the post title on the front end if left empty.', 'producerkit' ) ],
		'_pkit_source_location' => [ __( 'Studio', 'producerkit' ), __( 'Where it was recorded.', 'producerkit' ) ],
		'_pkit_source_history' => [ __( 'Background', 'producerkit' ), __( 'The story behind the session or the songs.', 'producerkit' ) ],
		'_pkit_milling_notes' => [ __( 'Mastering Notes', 'producerkit' ), __( 'What was done in post — mixing, mastering, the cut.', 'producerkit' ) ],
		'_pkit_growing_notes' => [ __( 'Release Notes', 'producerkit' ), __( 'Shown on the release page. Free-form.', 'producerkit' ) ],
	],
	'terms'           => [
		'pkit_product_type' => [ 'Vinyl LP', 'Vinyl 7"', 'Cassette', 'CD', 'Digital Download', 'T-Shirt', 'Hoodie', 'Poster', 'Sticker', 'Patch', 'Tote Bag', 'Songbook' ],
		'pkit_material'     => [ 'Black Vinyl', '180g Black Vinyl', 'Colored Vinyl', 'Splatter Vinyl', 'Picture Disc', 'Cassette', 'Compact Disc', 'Digital' ],
		'pkit_finish'       => [ 'First Pressing', 'Repress', 'Limited Edition', 'Numbered', 'Signed', 'Test Pressing', 'Tour Exclusive', 'Standard' ],
		'pkit_component'    => [ 'Gatefold', 'Single Sleeve', 'Hand-Screened Sleeve', 'Digipak', 'Jewel Case', 'Obi Strip', 'Download Card Included', 'Poly Bag' ],
		'pkit_event_type'   => [ 'Show', 'Album Release', 'Tour Date', 'Festival', 'Residency', 'House Show', 'In-Store', 'Livestream', 'Record Fair' ],
		// Growing seasons mean nothing on a tour schedule. Seeding nothing
		// leaves the taxonomy registered but empty, so anyone who does want
		// to group by tour leg can add their own terms.
		'pkit_season'       => [],
	],
];
