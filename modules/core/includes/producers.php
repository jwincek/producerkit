<?php
/**
 * Who made this.
 *
 * Multi-profile lets a farm and a bakery share one install: the fields
 * combine and each person reads their own vocabulary. What it did not do is
 * record which business a given product came from, so a visitor could not
 * tell the loaf from the lettuce.
 *
 * This is built on `post_author` rather than a new taxonomy. Two businesses
 * sharing a site are two WordPress users, every post already records which
 * one saved it, and reassignment is a control WordPress ships. A
 * `pkit_producer` taxonomy would be a frozen identifier the moment anything
 * used it, and it would duplicate a relationship the database already stores.
 *
 * Deliberately NOT built here: archives, filtering by producer, or separate
 * storefronts. Those are a different product — their own pages, their own
 * boards, their own email — and quite possibly two WordPress installs. This
 * answers attribution only, which is the question a visitor is actually
 * asking, and it forecloses nothing.
 *
 * One trap worth naming. A byline's wording has to come from the profile of
 * the person who MADE the thing, not the person reading the page.
 * Profiles\labelling_slug() resolves per viewing user and falls back to the
 * first active profile — correct for admin labelling, wrong here, because on
 * a farm-and-bakery site it would label the bread "Grown by". So every
 * lookup below goes through the author's own profile.
 */

declare(strict_types=1);

namespace ProducerKit\Core\Producers;

defined( 'ABSPATH' ) || exit;

/**
 * User meta holding the business or project name.
 *
 * Distinct from display_name on purpose: a person may want to be "Jamie" in
 * a comment thread and their bakery's name on a loaf.
 */
const USER_META = 'pkit_producer_name';

/**
 * Post types a byline can appear on.
 *
 * Products and events are what a visitor browses. Sources describe an
 * upstream supplier, which is a different relationship, and a location's
 * owner is already evident from the page it sits on.
 *
 * @return array<int, string>
 */
function bylined_post_types(): array {
	return (array) apply_filters( 'pkit_bylined_post_types', [ 'pkit_product', 'pkit_event' ] );
}

/**
 * The words one trade uses for its own attribution.
 *
 * Two slots. `byline` prefixes the name on the front end — a farm grows, a
 * bakery bakes. `name_label` titles the field on the user's profile screen.
 *
 * @param int $user_id Whose profile decides the wording.
 *
 * @return array{byline: string, name_label: string}
 */
function words_for_user( int $user_id ): array {
	$defaults = [
		'byline'     => __( 'Made by', 'producerkit' ),
		'name_label' => __( 'Producer name', 'producerkit' ),
	];

	/**
	 * Filters the attribution wording for one producer.
	 *
	 * @param array{byline: string, name_label: string} $words   Defaults.
	 * @param int                                       $user_id The producer.
	 */
	$filtered = (array) apply_filters( 'pkit_producer_words', $defaults, $user_id );

	// Rebuilt from the known slots, like Commissions\Vocabulary\words() and
	// MetaLabels\labels(): callers index this directly, so a filter must not
	// be able to blank a slot or invent one nothing reads.
	$words = [];
	foreach ( $defaults as $slot => $fallback ) {
		$value = isset( $filtered[ $slot ] ) ? trim( (string) $filtered[ $slot ] ) : '';

		$words[ $slot ] = '' !== $value ? $value : $fallback;
	}

	return $words;
}

/**
 * What this producer is called.
 *
 * Falls back to the display name, so a site that never fills the field in
 * still gets a byline rather than a blank.
 */
function name_for( int $user_id ): string {
	if ( $user_id <= 0 ) {
		return '';
	}

	$name = trim( (string) get_user_meta( $user_id, USER_META, true ) );

	if ( '' === $name ) {
		$user = get_userdata( $user_id );
		$name = $user ? trim( (string) $user->display_name ) : '';
	}

	return $name;
}

/**
 * The name this producer actually declared, with no display-name fallback.
 *
 * Structured data uses this rather than name_for(): a search engine should be
 * told a real brand or nothing at all, never that a jar of honey is made by
 * "admin".
 */
function declared_name_for( int $user_id ): string {
	if ( $user_id <= 0 ) {
		return '';
	}

	return trim( (string) get_user_meta( $user_id, USER_META, true ) );
}

/**
 * The producer behind one post, or null if there is nothing to show.
 *
 * @return array{id: int, name: string, byline: string}|null
 */
function for_post( int $post_id ): ?array {
	$post = get_post( $post_id );

	if ( ! $post instanceof \WP_Post ) {
		return null;
	}

	$user_id = (int) $post->post_author;
	$name    = name_for( $user_id );

	if ( '' === $name ) {
		return null;
	}

	return [
		'id'     => $user_id,
		'name'   => $name,
		'byline' => byline_verb( $user_id, (string) $post->post_type ),
	];
}

/**
 * The verb introducing a producer's name, for one kind of post.
 *
 * A trade's own word describes what it makes — a farm grows, a bakery bakes —
 * and that is right on a product. It is wrong on an event: nobody grows a
 * Pizza Night. Hosting is the same act whatever you make, so events take one
 * neutral word and only products consult the trade.
 */
function byline_verb( int $user_id, string $post_type ): string {
	if ( 'pkit_event' === $post_type ) {
		return __( 'Hosted by', 'producerkit' );
	}

	return words_for_user( $user_id )['byline'];
}

/**
 * Cache group and key for the producer count.
 */
const COUNT_CACHE_GROUP = 'producerkit';
const COUNT_CACHE_KEY   = 'producer_count';

/**
 * Drop the cached count when the set of authors could have changed.
 *
 * Publishing, trashing, deleting or reassigning a post are all it takes to
 * cross the one-producer threshold in either direction.
 */
add_action( 'save_post', __NAMESPACE__ . '\\flush_count' );
add_action( 'deleted_post', __NAMESPACE__ . '\\flush_count' );
add_action( 'trashed_post', __NAMESPACE__ . '\\flush_count' );
add_action( 'untrashed_post', __NAMESPACE__ . '\\flush_count' );

/**
 * Forget the cached producer count.
 */
function flush_count(): void {
	wp_cache_delete( COUNT_CACHE_KEY, COUNT_CACHE_GROUP );
}

/**
 * How many distinct producers have published content on this site.
 *
 * Cached rather than memoised in a static: a product grid would otherwise run
 * the query once per card, but a static would also survive changes within the
 * same request and, in tests, leak between cases. The object cache is flushed
 * by the hooks above, and by the test suite between cases.
 */
function count_on_site(): int {
	$cached = wp_cache_get( COUNT_CACHE_KEY, COUNT_CACHE_GROUP );

	if ( false !== $cached ) {
		return (int) $cached;
	}

	global $wpdb;

	$types        = bylined_post_types();
	$placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );

	$count = (int) $wpdb->get_var(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is a generated list of %s.
			"SELECT COUNT(DISTINCT post_author) FROM {$wpdb->posts} WHERE post_type IN ({$placeholders}) AND post_status = 'publish'",
			...$types
		)
	);

	wp_cache_set( COUNT_CACHE_KEY, $count, COUNT_CACHE_GROUP );

	return $count;
}

/**
 * Whether a byline is worth showing at all.
 *
 * On a single-producer site — which is most of them — your own name on every
 * one of your own products is noise, so the byline stays hidden
 * until there is genuinely something to tell apart. This follows the
 * availability board's filter rows, which are built from what is on the
 * board rather than from the whole taxonomy: derive it from the data instead
 * of asking for a setting.
 */
function should_show_byline(): bool {
	/**
	 * Filters whether producer bylines are shown.
	 *
	 * @param bool $show Whether more than one producer has published content.
	 */
	return (bool) apply_filters( 'pkit_show_producer_byline', count_on_site() > 1 );
}

/**
 * The rendered byline for a post, or '' when there is nothing to say.
 */
function byline_for( int $post_id ): string {
	if ( ! should_show_byline() ) {
		return '';
	}

	$producer = for_post( $post_id );

	if ( null === $producer ) {
		return '';
	}

	return sprintf(
		/* translators: 1: attribution verb for the trade, e.g. "Grown by". 2: the producer's name. */
		_x( '%1$s %2$s', 'producer byline', 'producerkit' ),
		$producer['byline'],
		$producer['name']
	);
}
