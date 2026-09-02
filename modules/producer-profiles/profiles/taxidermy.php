<?php
/**
 * Producer profile: Taxidermy.
 *
 * Shaped differently from every other profile here, deliberately. A
 * taxidermist mostly does not hold stock — the customer brings the animal —
 * so the catalogue and availability board matter far less than the
 * commissions module, and the vocabulary below describes work taken on
 * rather than things on a shelf.
 *
 * Kept to common game species and standard mount styles. Anyone working in
 * fish, birds of prey or protected species will want to edit the Species
 * list, which is one file.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'label'           => __( 'Taxidermy', 'producerkit' ),
	'description'     => __( 'Mounts taken in as commissions, catalogued by species, style and base.', 'producerkit' ),
	'taxonomies'      => [ 'lfuf_material', 'lfuf_finish', 'lfuf_component' ],
	'names'           => [
		'lfuf_material'  => [ __( 'Species', 'producerkit' ), __( 'Species', 'producerkit' ) ],
		'lfuf_finish'    => [ __( 'Mount Style', 'producerkit' ), __( 'Mount Styles', 'producerkit' ) ],
		'lfuf_component' => [ __( 'Base', 'producerkit' ), __( 'Bases', 'producerkit' ) ],
	],
	'post_type_names' => [
		'lfuf_product' => [ __( 'Mount', 'producerkit' ), __( 'Mounts', 'producerkit' ), __( 'Mounts', 'producerkit' ) ],
	],
	'terms'           => [
		'lfuf_product_type' => [ 'Shoulder Mount', 'Full Body Mount', 'European Mount', 'Pedestal Mount', 'Bird Mount', 'Fish Mount', 'Hide or Rug', 'Habitat Scene', 'Repair or Restoration' ],
		'lfuf_material'     => [ 'Whitetail Deer', 'Mule Deer', 'Elk', 'Black Bear', 'Pronghorn', 'Turkey', 'Pheasant', 'Waterfowl', 'Trout', 'Bass', 'Coyote', 'Fox' ],
		'lfuf_finish'       => [ 'Upright', 'Semi-Sneak', 'Sneak', 'Open Mouth', 'Wall Pedestal', 'Standing', 'Flying' ],
		'lfuf_component'    => [ 'Walnut Panel', 'Oak Panel', 'Driftwood', 'Rock Base', 'Habitat Base', 'No Base' ],
		'lfuf_event_type'   => [ 'Sportsmans Show', 'Open Shop', 'Drop-Off Day', 'Workshop' ],
	],
];
