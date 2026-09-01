<?php
/**
 * Producer profiles — registry and lookup.
 *
 * A profile re-labels the product taxonomies for one trade and seeds the
 * vocabulary that trade actually uses. A beekeeper's "Material" is a floral
 * source; a woodworker's is a wood species; a farm does not need the field at
 * all. Same data model underneath, different words on top.
 *
 * Profiles live one-per-file in ../profiles/ and are loaded lazily: building
 * the picker only needs each profile's label, and loading nine files at every
 * request to read nine strings is wasteful. It also keeps __() out of the
 * boot path, which is what WordPress 6.7's just-in-time textdomain notice
 * objects to.
 */

declare(strict_types=1);

namespace ProducerKit\ProducerProfiles\Profiles;

defined( 'ABSPATH' ) || exit;

/** Option holding the active profile slug. */
const OPTION = 'lfuf_producer_profile';

/** Profile used when none has been chosen. */
const DEFAULT_SLUG = 'farm';

/**
 * The optional product taxonomies a profile may switch on.
 *
 * Core registers product-type, season and event-type unconditionally. These
 * three are trade-specific enough that a farm should not have to look at
 * them, so they are registered only when the active profile asks for them.
 *
 * @return array<string, array{0: string, 1: string}> Slug => default [ singular, plural ].
 */
function optional_taxonomies(): array {
	return [
		'lfuf_material'  => [ __( 'Material', 'producerkit' ), __( 'Materials', 'producerkit' ) ],
		'lfuf_finish'    => [ __( 'Finish', 'producerkit' ), __( 'Finishes', 'producerkit' ) ],
		'lfuf_component' => [ __( 'Component', 'producerkit' ), __( 'Components', 'producerkit' ) ],
	];
}

/**
 * Absolute path to the directory holding the profile files.
 */
function profiles_dir(): string {
	return dirname( __DIR__ ) . '/profiles';
}

/**
 * Every registered profile slug, in directory order.
 *
 * @return string[]
 */
function get_slugs(): array {
	static $slugs = null;

	if ( null === $slugs ) {
		$slugs = [];
		foreach ( (array) glob( profiles_dir() . '/*.php' ) as $file ) {
			$slugs[] = basename( (string) $file, '.php' );
		}
		sort( $slugs );
	}

	/**
	 * Filters the list of available producer profile slugs.
	 *
	 * @param string[] $slugs Profile slugs.
	 */
	return (array) apply_filters( 'lfuf_producer_profile_slugs', $slugs );
}

/**
 * Load one profile by slug.
 *
 * @param string $slug Profile slug.
 * @return array{label: string, description: string, taxonomies: string[], names: array<string, array{0: string, 1: string}>, terms: array<string, string[]>}|null
 */
function get( string $slug ): ?array {
	static $cache = [];

	if ( array_key_exists( $slug, $cache ) ) {
		return $cache[ $slug ];
	}

	// Slug comes from an option, so never let it walk out of the directory.
	if ( ! preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
		return null;
	}

	$file = profiles_dir() . '/' . $slug . '.php';
	if ( ! is_readable( $file ) ) {
		$cache[ $slug ] = null;
		return null;
	}

	$profile = require $file;
	if ( ! is_array( $profile ) ) {
		$cache[ $slug ] = null;
		return null;
	}

	$cache[ $slug ] = wp_parse_args(
		$profile,
		[
			'label'       => $slug,
			'description' => '',
			'taxonomies'  => [],
			'names'       => [],
			'terms'       => [],
		]
	);

	return $cache[ $slug ];
}

/**
 * Slug of the active profile, falling back to the default if the stored one
 * has gone missing (a filter removed it, or a file was deleted).
 */
function active_slug(): string {
	$slug = (string) get_option( OPTION, DEFAULT_SLUG );

	if ( '' === $slug || null === get( $slug ) ) {
		$slug = DEFAULT_SLUG;
	}

	return $slug;
}

/**
 * The active profile.
 *
 * @return array{label: string, description: string, taxonomies: string[], names: array<string, array{0: string, 1: string}>, terms: array<string, string[]>}|null
 */
function active(): ?array {
	return get( active_slug() );
}

/**
 * Slug => label for every profile, for the settings picker.
 *
 * @return array<string, string>
 */
function choices(): array {
	$out = [];

	foreach ( get_slugs() as $slug ) {
		$profile = get( $slug );
		if ( null !== $profile ) {
			$out[ $slug ] = $profile['label'];
		}
	}

	return $out;
}

/**
 * Which optional taxonomies the active profile switches on.
 *
 * @return string[]
 */
function active_taxonomies(): array {
	$profile = active();
	if ( null === $profile ) {
		return [];
	}

	return array_values(
		array_intersect( (array) $profile['taxonomies'], array_keys( optional_taxonomies() ) )
	);
}
