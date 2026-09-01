<?php
/**
 * Producer profile: Metalwork.
 *
 * Ported from WC Artisan Tools' craft profiles.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'label'       => __( 'Metalwork', 'producerkit' ),
	'description' => __( 'Forged and fabricated steel, iron and non-ferrous metalwork.', 'producerkit' ),
	'taxonomies'  => [ 'lfuf_material', 'lfuf_finish', 'lfuf_component' ],
	'names'       => [
		'lfuf_material'  => [ __( 'Steel / Metal', 'producerkit' ), __( 'Steels / Metals', 'producerkit' ) ],
		'lfuf_component' => [ __( 'Handle / Accessory', 'producerkit' ), __( 'Handles / Accessories', 'producerkit' ) ],
	],
	'terms'       => [
		'lfuf_product_type' => [ 'Knife', 'Bottle Opener', 'Hook', 'Fire Poker', 'Candle Holder', 'Shelf Bracket', 'Letter Opener', 'Keychain', 'Sculpture', 'Tool' ],
		'lfuf_material'     => [ '1095 Carbon Steel', 'Damascus Steel', 'O1 Tool Steel', 'Wrought Iron', 'Mild Steel', 'Stainless Steel', 'Copper', 'Bronze', 'Brass' ],
		'lfuf_finish'       => [ 'Mirror Polish', 'Satin', 'Acid Etch', 'Patina', 'Beeswax', 'Clear Coat', 'Blued', 'Forge Scale' ],
		'lfuf_component'    => [ 'Walnut Handle', 'Micarta Handle', 'G10 Handle', 'Antler Handle', 'Leather Wrap', 'Paracord Wrap', 'Leather Sheath', 'Kydex Sheath', 'No Handle' ],
	],
];
