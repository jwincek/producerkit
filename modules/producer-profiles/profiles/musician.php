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
	'label'       => __( 'Musician', 'producerkit' ),
	'description' => __( 'Records, tapes and merch for a band or solo artist, with shows as events.', 'producerkit' ),
	'taxonomies'  => [ 'lfuf_material', 'lfuf_finish', 'lfuf_component' ],
	'names'       => [
		'lfuf_material'  => [ __( 'Format', 'producerkit' ), __( 'Formats', 'producerkit' ) ],
		'lfuf_finish'    => [ __( 'Edition', 'producerkit' ), __( 'Editions', 'producerkit' ) ],
		'lfuf_component' => [ __( 'Packaging', 'producerkit' ), __( 'Packaging', 'producerkit' ) ],
	],
	'terms'       => [
		'lfuf_product_type' => [ 'Vinyl LP', 'Vinyl 7"', 'Cassette', 'CD', 'Digital Download', 'T-Shirt', 'Hoodie', 'Poster', 'Sticker', 'Patch', 'Tote Bag', 'Songbook' ],
		'lfuf_material'     => [ 'Black Vinyl', '180g Black Vinyl', 'Colored Vinyl', 'Splatter Vinyl', 'Picture Disc', 'Cassette', 'Compact Disc', 'Digital' ],
		'lfuf_finish'       => [ 'First Pressing', 'Repress', 'Limited Edition', 'Numbered', 'Signed', 'Test Pressing', 'Tour Exclusive', 'Standard' ],
		'lfuf_component'    => [ 'Gatefold', 'Single Sleeve', 'Hand-Screened Sleeve', 'Digipak', 'Jewel Case', 'Obi Strip', 'Download Card Included', 'Poly Bag' ],
		'lfuf_event_type'   => [ 'Show', 'Album Release', 'Tour Date', 'Festival', 'Residency', 'House Show', 'In-Store', 'Livestream', 'Record Fair' ],
		// Growing seasons mean nothing on a tour schedule. Seeding nothing
		// leaves the taxonomy registered but empty, so anyone who does want
		// to group by tour leg can add their own terms.
		'lfuf_season'       => [],
	],
];
