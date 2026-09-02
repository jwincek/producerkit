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

/** Option holding the site's active profile slugs (an array). */
const OPTION = 'pkit_producer_profile';

/** User meta holding which active profile that person reads the admin in. */
const USER_META = 'pkit_producer_profile';

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
		'pkit_material'  => [ __( 'Material', 'producerkit' ), __( 'Materials', 'producerkit' ) ],
		'pkit_finish'    => [ __( 'Finish', 'producerkit' ), __( 'Finishes', 'producerkit' ) ],
		'pkit_component' => [ __( 'Component', 'producerkit' ), __( 'Components', 'producerkit' ) ],
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
	return (array) apply_filters( 'pkit_producer_profile_slugs', $slugs );
}

/**
 * Load one profile by slug.
 *
 * @param string $slug Profile slug.
 * @return array{label: string, description: string, taxonomies: string[], names: array<string, array{0: string, 1: string}>, terms: array<string, string[]>, post_type_names: array<string, array>}|null
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
			'label'           => $slug,
			'description'     => '',
			'taxonomies'      => [],
			'names'           => [],
			'terms'           => [],
			'post_type_names' => [],
		]
	);

	return $cache[ $slug ];
}

/**
 * The profiles this site runs, in stored order.
 *
 * A site can practise more than one trade — a farm that also runs a bakery,
 * two people sharing one install. What that decides is site-wide and
 * physical: which optional taxonomies get registered, and which vocabulary
 * gets seeded. Both of those union cleanly, because registration is a set and
 * seeding only ever inserts.
 *
 * Never returns empty: a site with no valid selection falls back to the
 * default rather than losing its fields.
 *
 * @return string[]
 */
function active_slugs(): array {
	$stored = get_option( OPTION, DEFAULT_SLUG );

	// Stored as a single string before multi-profile support; read either.
	$slugs = array_values(
		array_filter(
			array_map( 'strval', (array) $stored ),
			static fn ( string $slug ): bool => '' !== $slug && null !== get( $slug )
		)
	);

	return $slugs ?: [ DEFAULT_SLUG ];
}

/**
 * The loaded profiles this site runs.
 *
 * @return array<int, array>
 */
function active_profiles(): array {
	return array_values( array_filter( array_map( __NAMESPACE__ . '\\get', active_slugs() ) ) );
}

/**
 * Slug of the profile whose wording applies to this request.
 *
 * Labels are the one thing that cannot union — there is a single
 * `pkit_material` field with a single label, and "Wood Species" and "Flour"
 * have no sensible merge. But a label is display, not structure, so it can be
 * resolved per viewer instead of per site: two people sharing an install each
 * see their own trade's words over the same underlying fields.
 *
 * Falls back to the first site-active profile — for logged-out visitors, for
 * cron, and for anyone who has not chosen. That keeps the front end
 * deterministic rather than varying by whoever happens to be signed in.
 */
function labelling_slug(): string {
	$active = active_slugs();
	$user   = get_current_user_id();

	if ( $user > 0 ) {
		$chosen = (string) get_user_meta( $user, USER_META, true );

		// Only honoured while the site still runs that profile, so turning one
		// off does not leave someone stranded on vocabulary nothing else uses.
		if ( '' !== $chosen && in_array( $chosen, $active, true ) ) {
			return $chosen;
		}
	}

	/**
	 * Filters the profile whose wording applies to this request.
	 *
	 * @param string   $slug   Resolved profile slug.
	 * @param string[] $active Profiles the site runs.
	 */
	return (string) apply_filters( 'pkit_labelling_profile', $active[0], $active );
}

/**
 * The profile whose wording applies to this request.
 *
 * @return array{label: string, description: string, taxonomies: string[], names: array<string, array{0: string, 1: string}>, terms: array<string, string[]>, post_type_names: array<string, array>}|null
 */
function labelling_profile(): ?array {
	return get( labelling_slug() );
}

/**
 * The profile a given user reads the admin in, if they have chosen one that
 * the site still runs.
 */
function user_slug( int $user_id ): string {
	$chosen = (string) get_user_meta( $user_id, USER_META, true );

	return in_array( $chosen, active_slugs(), true ) ? $chosen : '';
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
 * Which optional taxonomies the site's profiles switch on, combined.
 *
 * A union rather than a choice: registering a taxonomy is a physical fact
 * about the site, so if any active trade needs Material, the field exists.
 * A trade that does not use it simply leaves it blank.
 *
 * @return string[]
 */
function active_taxonomies(): array {
	$wanted = [];

	foreach ( active_profiles() as $profile ) {
		$wanted = array_merge( $wanted, (array) $profile['taxonomies'] );
	}

	return array_values(
		array_intersect( array_unique( $wanted ), array_keys( optional_taxonomies() ) )
	);
}
