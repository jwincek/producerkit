<?php
/**
 * Profile-driven taxonomy behaviour.
 *
 * Two jobs:
 *
 *   1. Register the optional product taxonomies (material / finish /
 *      component) when the active profile asks for them. A farm gets none of
 *      them; a jeweller gets all three, labelled "Metal", "Finish" and
 *      "Stone / Setting".
 *   2. Feed the active profile's names and default terms into the filters
 *      core exposes, so core's own taxonomies re-label themselves too.
 *
 * Switching profiles never deletes anything. An unregistered taxonomy keeps
 * its rows in term_taxonomy, so switching back brings the terms with it.
 */

declare(strict_types=1);

namespace ProducerKit\ProducerProfiles\Taxonomies;

use ProducerKit\Core\Taxonomies as Core;
use ProducerKit\ProducerProfiles\Profiles;

defined( 'ABSPATH' ) || exit;

/**
 * Register the optional taxonomies the active profile switches on.
 */
function register(): void {
	$optional = Profiles\optional_taxonomies();

	foreach ( Profiles\active_taxonomies() as $taxonomy ) {
		[ $singular, $plural ] = $optional[ $taxonomy ];

		register_taxonomy(
			$taxonomy,
			[ 'lfuf_product' ],
			[
				// Runs the same lfuf_taxonomy_names filter core uses, so the
				// profile's own re-labelling applies here for free.
				'labels'            => Core\build_labels( $taxonomy, $singular, $plural ),
				'hierarchical'      => false,
				'public'            => true,
				'show_in_rest'      => true,
				'rest_base'         => str_replace( [ 'lfuf_', '_' ], [ '', '-' ], $taxonomy ) . 's',
				'rest_namespace'    => 'lfuf/v1',
				'rewrite'           => [
					'slug'       => str_replace( 'lfuf_', '', $taxonomy ),
					'with_front' => false,
				],
				'show_admin_column' => true,
			]
		);

		if ( is_admin() ) {
			Core\seed_terms( $taxonomy, [] );
		}
	}
}

/**
 * Re-label a taxonomy from the active profile.
 *
 * @param array{0: string, 1: string} $names    [ singular, plural ].
 * @param string                      $taxonomy Taxonomy slug.
 * @return array{0: string, 1: string}
 */
function filter_names( array $names, string $taxonomy ): array {
	$profile = Profiles\active();

	if ( null === $profile || ! array_key_exists( $taxonomy, $profile['names'] ) ) {
		return $names;
	}

	$override = (array) $profile['names'][ $taxonomy ];

	// Both halves are required; a half-declared override keeps the default.
	if ( 2 !== count( $override ) || '' === (string) $override[0] || '' === (string) $override[1] ) {
		return $names;
	}

	return [ (string) $override[0], (string) $override[1] ];
}

/**
 * Replace a taxonomy's seeded default terms with the active profile's.
 *
 * A profile that does not mention a taxonomy leaves core's defaults alone.
 * A profile that mentions it with an empty list seeds nothing — that is how
 * the "General" blank-slate profile works.
 *
 * @param string[] $defaults Term names.
 * @param string   $taxonomy Taxonomy slug.
 * @return string[]
 */
function filter_default_terms( array $defaults, string $taxonomy ): array {
	$profile = Profiles\active();

	if ( null === $profile || ! array_key_exists( $taxonomy, $profile['terms'] ) ) {
		return $defaults;
	}

	return array_values( array_map( 'strval', (array) $profile['terms'][ $taxonomy ] ) );
}
