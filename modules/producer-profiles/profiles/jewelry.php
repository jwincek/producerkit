<?php
/**
 * Producer profile: Jewelry.
 *
 * Ported from WC Artisan Tools' craft profiles.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'label'       => __( 'Jewelry', 'producerkit' ),
	'description' => __( 'Fabricated and cast jewellery in precious and base metals.', 'producerkit' ),
	'taxonomies'  => [ 'pkit_material', 'pkit_finish', 'pkit_component' ],
	'names'       => [
		'pkit_material'  => [ __( 'Metal', 'producerkit' ), __( 'Metals', 'producerkit' ) ],
		'pkit_component' => [ __( 'Stone / Setting', 'producerkit' ), __( 'Stones / Settings', 'producerkit' ) ],
	],
	'terms'       => [
		'pkit_product_type' => [ 'Ring', 'Necklace', 'Bracelet', 'Earrings', 'Pendant', 'Brooch', 'Cuff', 'Anklet', 'Tie Clip', 'Money Clip' ],
		'pkit_material'     => [ 'Sterling Silver', 'Gold Fill', '14K Gold', 'Brass', 'Copper', 'Titanium', 'Stainless Steel', 'Bronze' ],
		'pkit_finish'       => [ 'Polished', 'Brushed', 'Hammered', 'Oxidized', 'Patina', 'Matte', 'Mirror' ],
		'pkit_component'    => [ 'Bezel Set', 'Prong Set', 'Channel Set', 'Turquoise', 'Garnet', 'Amethyst', 'Moonstone', 'Opal', 'Pearl', 'Lab-Grown Diamond', 'No Stone' ],
	],
];
