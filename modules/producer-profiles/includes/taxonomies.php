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
			[ 'pkit_product' ],
			[
				// Runs the same pkit_taxonomy_names filter core uses, so the
				// profile's own re-labelling applies here for free.
				'labels'            => Core\build_labels( $taxonomy, $singular, $plural ),
				'hierarchical'      => false,
				'public'            => true,
				'show_in_rest'      => true,
				'rest_base'         => rest_base_for( $taxonomy ),
				'rest_namespace'    => 'producerkit/v1',
				'rewrite'           => [
					'slug'       => str_replace( 'pkit_', '', $taxonomy ),
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
	$profile = Profiles\labelling_profile();

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
 * The REST base for one of the optional taxonomies.
 *
 * Appending "s" gave `finishs`, which would have been a public route name and
 * awkward to change once anything consumed it. Spelled out rather than
 * pluralised by rule: there are three of them, and a rule that handles
 * "finish" correctly will meet "batch" or "wash" next.
 */
function rest_base_for( string $taxonomy ): string {
	return [
		'pkit_material'  => 'materials',
		'pkit_finish'    => 'finishes',
		'pkit_component' => 'components',
	][ $taxonomy ] ?? str_replace( [ 'pkit_', '_' ], [ '', '-' ], $taxonomy );
}

/**
 * Re-word a post type from the active profile.
 *
 * Same shape as filter_names(), but a triple: a trade may want a different
 * word in the sidebar from the one it uses in a sentence. A musician's
 * catalogue is "Merch" in the menu and still a "Product" on the edit screen.
 *
 * @param array{0: string, 1: string, 2: string} $names     [ singular, plural, menu ].
 * @param string                                 $post_type Post type slug.
 * @return array{0: string, 1: string, 2: string}
 */
function filter_post_type_names( array $names, string $post_type ): array {
	$profile = Profiles\labelling_profile();

	if ( null === $profile || ! array_key_exists( $post_type, $profile['post_type_names'] ) ) {
		return $names;
	}

	$override = (array) $profile['post_type_names'][ $post_type ];

	// Any slot the profile leaves out keeps the default.
	foreach ( [ 0, 1, 2 ] as $i ) {
		if ( isset( $override[ $i ] ) && '' !== (string) $override[ $i ] ) {
			$names[ $i ] = (string) $override[ $i ];
		}
	}

	return $names;
}

/**
 * Re-word the commission concept from the active profile.
 *
 * Unlike taxonomies and post types, this is not per-object: a site has one
 * word for the job regardless of how many profiles it runs. Where profiles
 * disagree, the labelling profile wins, which is the same rule the admin
 * screens already use for every other name.
 *
 * @param array{singular: string, plural: string, menu: string, action: string} $words
 * @return array{singular: string, plural: string, menu: string, action: string}
 */
function filter_commission_names( array $words ): array {
	$profile = Profiles\labelling_profile();

	if ( null === $profile || ! isset( $profile['request_names'] ) ) {
		return $words;
	}

	foreach ( (array) $profile['request_names'] as $slot => $value ) {
		// An unknown slot is ignored rather than added: a typo in a profile
		// should not invent a word nothing reads.
		if ( array_key_exists( $slot, $words ) && '' !== (string) $value ) {
			$words[ $slot ] = (string) $value;
		}
	}

	return $words;
}

/**
 * Replace a taxonomy's seeded default terms with the active profiles'.
 *
 * Unions across every profile the site runs, because seeding is additive by
 * construction — it only ever inserts a term that does not exist — so a farm
 * that also bakes gets both vocabularies and no conflict arises.
 *
 * A profile that does not mention a taxonomy contributes nothing. If none of
 * them mention it, core's own defaults stand. A profile that mentions it with
 * an empty list still counts as mentioning it, which is how the "General"
 * blank-slate profile suppresses seeding.
 *
 * @param string[] $defaults Term names.
 * @param string   $taxonomy Taxonomy slug.
 * @return string[]
 */
function filter_default_terms( array $defaults, string $taxonomy ): array {
	$mentioned = false;
	$terms     = [];

	foreach ( Profiles\active_profiles() as $profile ) {
		if ( ! array_key_exists( $taxonomy, $profile['terms'] ) ) {
			continue;
		}

		$mentioned = true;
		$terms     = array_merge( $terms, array_map( 'strval', (array) $profile['terms'][ $taxonomy ] ) );
	}

	if ( ! $mentioned ) {
		return $defaults;
	}

	return array_values( array_unique( $terms ) );
}
