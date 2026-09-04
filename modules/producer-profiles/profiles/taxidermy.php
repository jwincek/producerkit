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
	'taxonomies'      => [ 'pkit_material', 'pkit_finish', 'pkit_component' ],
	'names'           => [
		'pkit_material'  => [ __( 'Species', 'producerkit' ), __( 'Species', 'producerkit' ) ],
		'pkit_finish'    => [ __( 'Mount Style', 'producerkit' ), __( 'Mount Styles', 'producerkit' ) ],
		'pkit_component' => [ __( 'Base', 'producerkit' ), __( 'Bases', 'producerkit' ) ],
	],
	'post_type_names' => [
		'pkit_product' => [ __( 'Mount', 'producerkit' ), __( 'Mounts', 'producerkit' ), __( 'Mounts', 'producerkit' ) ],
	],
	'meta_labels'     => [
		'_pkit_source_farm_name' => [ __( 'Source', 'producerkit' ), __( 'Where the specimen came from. Falls back to the post title on the front end if left empty.', 'producerkit' ) ],
		'_pkit_milling_notes' => [ __( 'Preparation Notes', 'producerkit' ), __( 'How it was prepared and mounted.', 'producerkit' ) ],
		'_pkit_growing_notes' => [ __( 'Mount Notes', 'producerkit' ), __( 'Shown on the product page. Free-form.', 'producerkit' ) ],
	],
	'terms'           => [
		'pkit_product_type' => [ 'Shoulder Mount', 'Full Body Mount', 'European Mount', 'Pedestal Mount', 'Bird Mount', 'Fish Mount', 'Hide or Rug', 'Habitat Scene', 'Repair or Restoration' ],
		'pkit_material'     => [ 'Whitetail Deer', 'Mule Deer', 'Elk', 'Black Bear', 'Pronghorn', 'Turkey', 'Pheasant', 'Waterfowl', 'Trout', 'Bass', 'Coyote', 'Fox' ],
		'pkit_finish'       => [ 'Upright', 'Semi-Sneak', 'Sneak', 'Open Mouth', 'Wall Pedestal', 'Standing', 'Flying' ],
		'pkit_component'    => [ 'Walnut Panel', 'Oak Panel', 'Driftwood', 'Rock Base', 'Habitat Base', 'No Base' ],
		'pkit_event_type'   => [ 'Sportsmans Show', 'Open Shop', 'Drop-Off Day', 'Workshop' ],
	],
];
