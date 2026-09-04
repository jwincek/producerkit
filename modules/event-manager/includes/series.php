<?php
/**
 * Turning a recurrence rule into real events.
 *
 * The occurrences of a recurring event are posts, not computed rows. That is
 * the expensive choice and the right one here: RSVPs, capacity, guest lists,
 * cancellation and the whole event-manager module already work on a post, so
 * a generated occurrence needs no new concepts to be bookable. The
 * alternative — occurrences as data expanded at read time — would have meant
 * one shared capacity pool across fifty-two Saturdays.
 *
 * The shape:
 *
 *   Series      The post a producer creates and edits. Carries the rule.
 *               Never shown publicly: it is a description of events, not one.
 *   Occurrence  A generated post, parented to the series, stamped with the
 *               date it stands for. Shown publicly. Editable, cancellable,
 *               bookable, exactly like a one-off event.
 *
 * The hard part is not generating them; it is generating them again. A
 * producer who moves one Saturday's start time, or cancels the one that falls
 * on a holiday, must not have that undone the next time the series is saved.
 * So an occurrence a human has touched is marked, and the generator does not
 * write to a marked occurrence again.
 */

declare(strict_types=1);

namespace ProducerKit\EventManager\Series;

use ProducerKit\EventManager\Recurrence;

defined( 'ABSPATH' ) || exit;

/** The date an occurrence stands for, as Y-m-d H:i:s in site time. */
const OCCURRENCE_DATE = '_pkit_occurrence_date';

/** Set once a person edits an occurrence, after which the generator leaves it alone. */
const OCCURRENCE_EDITED = '_pkit_occurrence_edited';

/**
 * Meta copied from the series onto each occurrence it creates.
 *
 * Deliberately not everything. The rule itself must not be copied, or each
 * occurrence would look like a series of its own and generate again. The
 * start and end datetimes are computed per occurrence rather than copied.
 */
function inherited_meta(): array {
	return (array) apply_filters(
		'pkit_series_inherited_meta',
		[
			'_pkit_event_location_id',
			'_pkit_featured_product_ids',
			'_pkit_rsvp_cap',
			'_pkit_donation_link',
			'_pkit_em_cost_note',
			'_pkit_em_what_to_bring',
			'_pkit_em_rsvp_enabled',
			'_pkit_em_rsvp_label',
		]
	);
}

/**
 * Is this post a series — something with a rule to expand?
 */
function is_series( int $post_id ): bool {
	return '' !== trim( (string) get_post_meta( $post_id, '_pkit_recurrence_rule', true ) )
		&& ! is_occurrence( $post_id );
}

/**
 * Is this post one occurrence of a series?
 */
function is_occurrence( int $post_id ): bool {
	$post = get_post( $post_id );

	return $post instanceof \WP_Post
		&& 'pkit_event' === $post->post_type
		&& $post->post_parent > 0;
}

/**
 * Every occurrence of a series, whatever its status.
 *
 * @return \WP_Post[]
 */
function occurrences( int $series_id ): array {
	if ( $series_id < 1 ) {
		return [];
	}

	return get_posts(
		[
			'post_type'      => 'pkit_event',
			'post_parent'    => $series_id,
			'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
			'numberposts'    => -1,
			'orderby'        => 'meta_value',
			'meta_key'       => OCCURRENCE_DATE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- The column is what these are ordered by; there is no other key.
			'order'          => 'ASC',
			'suppress_filters' => false,
		]
	);
}

/**
 * Generate or refresh the occurrences of one series.
 *
 * Idempotent by design: running it twice changes nothing the second time.
 * That matters because it runs on every save of the series and on a schedule,
 * and a generator that produced duplicates would be discovered as a hundred
 * Saturdays.
 *
 * @return array{created: int, updated: int, removed: int, kept: int}|\WP_Error
 */
function generate( int $series_id ): array|\WP_Error {
	$series = get_post( $series_id );

	if ( ! $series instanceof \WP_Post || 'pkit_event' !== $series->post_type ) {
		return new \WP_Error( 'not_an_event', __( 'That is not an event.', 'producerkit' ) );
	}

	if ( ! is_series( $series_id ) ) {
		return new \WP_Error( 'not_a_series', __( 'That event has no recurrence rule.', 'producerkit' ) );
	}

	$rule  = (string) get_post_meta( $series_id, '_pkit_recurrence_rule', true );
	$start = occurrence_start( $series_id );

	if ( null === $start ) {
		return new \WP_Error(
			'no_start',
			__( 'A recurring event needs a start date and time before its occurrences can be worked out.', 'producerkit' )
		);
	}

	$dates = Recurrence\expand( $rule, $start );

	if ( is_wp_error( $dates ) ) {
		return $dates;
	}

	$wanted = [];
	foreach ( $dates as $date ) {
		$wanted[ $date->format( 'Y-m-d H:i:s' ) ] = $date;
	}

	$existing = [];
	foreach ( occurrences( $series_id ) as $post ) {
		$key = (string) get_post_meta( $post->ID, OCCURRENCE_DATE, true );

		// An occurrence with no stamp cannot be matched to a date, so it is
		// left entirely alone rather than guessed at or deleted.
		if ( '' !== $key ) {
			$existing[ $key ] = $post;
		}
	}

	$stats = [
		'created' => 0,
		'updated' => 0,
		'removed' => 0,
		'kept'    => 0,
	];

	foreach ( $wanted as $key => $date ) {
		if ( isset( $existing[ $key ] ) ) {
			$post = $existing[ $key ];

			if ( edited( $post->ID ) ) {
				// The producer moved this one's time, or cancelled it for a
				// holiday. Leaving it is the whole point.
				++$stats['kept'];
				continue;
			}

			refresh_occurrence( $post->ID, $series, $date );
			++$stats['updated'];
			continue;
		}

		create_occurrence( $series, $date );
		++$stats['created'];
	}

	// Dates the rule no longer produces — the producer shortened the season,
	// or changed the weekday.
	foreach ( $existing as $key => $post ) {
		if ( isset( $wanted[ $key ] ) ) {
			continue;
		}

		if ( edited( $post->ID ) ) {
			// Deleting an occurrence someone deliberately changed would throw
			// away their work and any bookings on it. It is detached from the
			// series instead and survives as a one-off event.
			detach( $post->ID );
			++$stats['kept'];
			continue;
		}

		delete_occurrence( $post->ID );
		++$stats['removed'];
	}

	return $stats;
}

/**
 * The series' own start, as a zoned datetime.
 */
function occurrence_start( int $series_id ): ?\DateTimeImmutable {
	$raw = trim( (string) get_post_meta( $series_id, '_pkit_start_datetime', true ) );

	if ( '' === $raw ) {
		return null;
	}

	try {
		return new \DateTimeImmutable( $raw, wp_timezone() );
	} catch ( \Exception ) {
		return null;
	}
}

/**
 * Has a person edited this occurrence?
 */
function edited( int $post_id ): bool {
	return (bool) get_post_meta( $post_id, OCCURRENCE_EDITED, true );
}

/**
 * How long the series runs for, so an occurrence can end the right distance
 * from where it starts.
 */
function duration( int $series_id ): ?\DateInterval {
	$start = occurrence_start( $series_id );
	$raw   = trim( (string) get_post_meta( $series_id, '_pkit_end_datetime', true ) );

	if ( null === $start || '' === $raw ) {
		return null;
	}

	try {
		$end = new \DateTimeImmutable( $raw, wp_timezone() );
	} catch ( \Exception ) {
		return null;
	}

	return $end > $start ? $start->diff( $end ) : null;
}

/**
 * Guard so the generator's own writes are not mistaken for a person's.
 *
 * wp_insert_post() fires save_post, and the listener below marks any saved
 * occurrence as edited. Without this flag the generator would mark everything
 * it created the moment it created it, and then refuse to ever touch it
 * again — the feature would appear to work once and never update.
 */
function generating( ?bool $set = null ): bool {
	static $active = false;

	if ( null !== $set ) {
		$active = $set;
	}

	return $active;
}

/**
 * Create one occurrence post from a series.
 */
function create_occurrence( \WP_Post $series, \DateTimeImmutable $date ): int {
	generating( true );

	$post_id = wp_insert_post(
		[
			'post_type'    => 'pkit_event',
			'post_status'  => $series->post_status,
			'post_title'   => $series->post_title,
			'post_content' => $series->post_content,
			'post_excerpt' => $series->post_excerpt,
			'post_author'  => $series->post_author,
			'post_parent'  => $series->ID,
			'post_date'    => $series->post_date,
		],
		true
	);

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		generating( false );

		return 0;
	}

	update_post_meta( $post_id, OCCURRENCE_DATE, $date->format( 'Y-m-d H:i:s' ) );
	apply_occurrence_meta( (int) $post_id, $series, $date );

	$thumbnail = get_post_thumbnail_id( $series->ID );
	if ( $thumbnail ) {
		set_post_thumbnail( $post_id, $thumbnail );
	}

	// Event types and any other taxonomy the series carries.
	foreach ( get_object_taxonomies( 'pkit_event' ) as $taxonomy ) {
		$terms = wp_get_object_terms( $series->ID, $taxonomy, [ 'fields' => 'ids' ] );

		if ( ! is_wp_error( $terms ) && $terms ) {
			wp_set_object_terms( $post_id, $terms, $taxonomy );
		}
	}

	generating( false );

	/**
	 * Fires after an occurrence is generated from a series.
	 *
	 * @param int                $post_id   The new occurrence.
	 * @param \WP_Post           $series    The series it came from.
	 * @param \DateTimeImmutable $date      The date it stands for.
	 */
	do_action( 'pkit_occurrence_created', (int) $post_id, $series, $date );

	return (int) $post_id;
}

/**
 * Bring an untouched occurrence back in line with its series.
 *
 * Only ever called for an occurrence nobody has edited, so overwriting is
 * safe: the producer changed the series and expects the change to land.
 */
function refresh_occurrence( int $post_id, \WP_Post $series, \DateTimeImmutable $date ): void {
	generating( true );

	wp_update_post(
		[
			'ID'           => $post_id,
			'post_title'   => $series->post_title,
			'post_content' => $series->post_content,
			'post_excerpt' => $series->post_excerpt,
			'post_status'  => $series->post_status,
		]
	);

	apply_occurrence_meta( $post_id, $series, $date );

	generating( false );
}

/**
 * Write the meta an occurrence takes from its series, plus its own times.
 */
function apply_occurrence_meta( int $post_id, \WP_Post $series, \DateTimeImmutable $date ): void {
	update_post_meta( $post_id, '_pkit_start_datetime', $date->format( 'Y-m-d\TH:i:s' ) );

	$duration = duration( $series->ID );

	if ( null !== $duration ) {
		update_post_meta( $post_id, '_pkit_end_datetime', $date->add( $duration )->format( 'Y-m-d\TH:i:s' ) );
	} else {
		delete_post_meta( $post_id, '_pkit_end_datetime' );
	}

	foreach ( inherited_meta() as $key ) {
		$value = get_post_meta( $series->ID, $key, true );

		if ( '' === $value || null === $value || [] === $value ) {
			delete_post_meta( $post_id, $key );
			continue;
		}

		update_post_meta( $post_id, $key, $value );
	}
}

/**
 * Remove an occurrence the rule no longer produces.
 */
function delete_occurrence( int $post_id ): void {
	generating( true );

	// Force, not trash: a trashed occurrence keeps its parent and its date
	// stamp, so the next run would find it, decide it exists, and never
	// recreate it — the date would silently vanish from the series.
	wp_delete_post( $post_id, true );

	generating( false );
}

/**
 * Cut an edited occurrence loose from a series that no longer wants it.
 *
 * It keeps its content, its meta and any bookings on it, and becomes an
 * ordinary one-off event. Better than deleting somebody's work because they
 * changed the rule.
 */
function detach( int $post_id ): void {
	generating( true );

	wp_update_post(
		[
			'ID'          => $post_id,
			'post_parent' => 0,
		]
	);

	delete_post_meta( $post_id, OCCURRENCE_DATE );
	delete_post_meta( $post_id, OCCURRENCE_EDITED );

	generating( false );

	/**
	 * Fires when an edited occurrence outlives the rule that made it.
	 *
	 * @param int $post_id The now-independent event.
	 */
	do_action( 'pkit_occurrence_detached', $post_id );
}

/* ───────────────────────────────────────────────
 * Wiring
 * ─────────────────────────────────────────────── */

add_action( 'save_post_pkit_event', __NAMESPACE__ . '\\on_save', 20, 2 );

/**
 * Keep a series' occurrences in step, and notice when a person edits one.
 */
function on_save( int $post_id, \WP_Post $post ): void {
	if ( generating() || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	if ( is_occurrence( $post_id ) ) {
		// A person saved one occurrence. From here the generator leaves it
		// alone, which is what makes cancelling a single holiday stick.
		update_post_meta( $post_id, OCCURRENCE_EDITED, 1 );

		return;
	}

	if ( ! is_series( $post_id ) ) {
		// The rule was removed. Anything still parented to this post is now
		// orphaned by intent rather than by accident.
		release_all( $post_id );

		return;
	}

	generate( $post_id );
}

/**
 * A post that has stopped being a series lets its occurrences go.
 *
 * Untouched ones are deleted — they only ever existed to express the rule.
 * Edited ones are detached and survive, for the same reason as in generate().
 */
function release_all( int $series_id ): void {
	foreach ( occurrences( $series_id ) as $post ) {
		if ( edited( $post->ID ) ) {
			detach( $post->ID );
			continue;
		}

		delete_occurrence( $post->ID );
	}
}

add_action( 'before_delete_post', __NAMESPACE__ . '\\on_delete', 10, 2 );

/**
 * Take a series' occurrences with it.
 *
 * wp_delete_post() only re-parents children for hierarchical post types, and
 * pkit_event is not one — so without this a deleted series leaves its
 * occurrences behind pointing at a post id that no longer exists. They would
 * keep appearing on the site with no way to manage them.
 */
function on_delete( int $post_id, ?\WP_Post $post = null ): void {
	if ( ! $post instanceof \WP_Post || 'pkit_event' !== $post->post_type ) {
		return;
	}

	foreach ( occurrences( $post_id ) as $occurrence ) {
		// Force-deleted rather than detached, edited or not: the series is
		// going, and an occurrence of a deleted event is not a thing. The
		// RSVP module's own before_delete_post listener removes the bookings.
		delete_occurrence( $occurrence->ID );
	}
}

add_action( 'pre_get_posts', __NAMESPACE__ . '\\hide_series_from_public_queries' );

/**
 * A series never appears on the site.
 *
 * It is a description of events, not one of them. Its own start date is the
 * first occurrence's, so leaving it in a feed would show the same market
 * twice on the same morning — once as the series and once as the occurrence
 * generated from it.
 */
function hide_series_from_public_queries( \WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$types = (array) $query->get( 'post_type' );

	if ( ! in_array( 'pkit_event', $types, true ) ) {
		return;
	}

	$meta_query = (array) $query->get( 'meta_query' );

	$meta_query[] = [
		'key'     => '_pkit_recurrence_rule',
		'compare' => 'NOT EXISTS',
	];

	$query->set( 'meta_query', $meta_query );
}

/* ───────────────────────────────────────────────
 * Admin list
 * ─────────────────────────────────────────────── */

/** Query var that reveals occurrences in the list table. */
const SHOW_OCCURRENCES = 'pkit_occurrences';

add_action( 'pre_get_posts', __NAMESPACE__ . '\\hide_occurrences_in_admin' );

/**
 * Keep generated occurrences out of the events list by default.
 *
 * A weekly market is fifty-two near-identical rows a year. Left in, they bury
 * every one-off event and make the screen useless by the second season. They
 * are not hidden away, though: a series' row links to its own occurrences,
 * because the whole point is being able to cancel one for a holiday.
 */
function hide_occurrences_in_admin( \WP_Query $query ): void {
	if ( ! is_admin() || ! $query->is_main_query() || 'pkit_event' !== $query->get( 'post_type' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading a list-table filter, not acting on one.
	if ( isset( $_GET[ SHOW_OCCURRENCES ] ) ) {
		$series_id = (int) $_GET[ SHOW_OCCURRENCES ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $series_id > 0 ) {
			$query->set( 'post_parent', $series_id );
		}

		return;
	}

	$query->set( 'post_parent', 0 );
}

add_filter( 'post_row_actions', __NAMESPACE__ . '\\series_row_action', 10, 2 );

/**
 * A way into a series' occurrences from the list.
 */
function series_row_action( array $actions, \WP_Post $post ): array {
	if ( 'pkit_event' !== $post->post_type || ! is_series( $post->ID ) ) {
		return $actions;
	}

	$count = count( occurrences( $post->ID ) );

	if ( 0 === $count ) {
		return $actions;
	}

	$actions['pkit_occurrences'] = sprintf(
		'<a href="%s">%s</a>',
		esc_url(
			add_query_arg(
				[
					'post_type'       => 'pkit_event',
					SHOW_OCCURRENCES  => $post->ID,
				],
				admin_url( 'edit.php' )
			)
		),
		esc_html(
			sprintf(
				/* translators: %d: number of occurrences. */
				_n( '%d occurrence', '%d occurrences', $count, 'producerkit' ),
				$count
			)
		)
	);

	return $actions;
}

add_filter( 'manage_pkit_event_posts_columns', __NAMESPACE__ . '\\add_series_column' );

/**
 * Say which rows are a series, and which occurrence you are looking at.
 */
function add_series_column( array $columns ): array {
	$out = [];

	foreach ( $columns as $key => $label ) {
		$out[ $key ] = $label;

		if ( 'title' === $key ) {
			$out['pkit_series'] = __( 'Recurrence', 'producerkit' );
		}
	}

	return $out;
}

add_action( 'manage_pkit_event_posts_custom_column', __NAMESPACE__ . '\\render_series_column', 10, 2 );

/**
 * @param string $column  Column key.
 * @param int    $post_id Row post.
 */
function render_series_column( string $column, int $post_id ): void {
	if ( 'pkit_series' !== $column ) {
		return;
	}

	if ( is_series( $post_id ) ) {
		echo '<strong>' . esc_html__( 'Series', 'producerkit' ) . '</strong><br>';
		echo esc_html( (string) get_post_meta( $post_id, '_pkit_recurrence_rule', true ) );

		return;
	}

	if ( ! is_occurrence( $post_id ) ) {
		echo '—';

		return;
	}

	$parent = get_post( (int) get_post( $post_id )->post_parent );

	printf(
		/* translators: %s: the series this occurrence belongs to. */
		esc_html__( 'One of %s', 'producerkit' ),
		'<a href="' . esc_url( (string) get_edit_post_link( $parent->ID ) ) . '">' . esc_html( get_the_title( $parent ) ) . '</a>'
	);

	if ( edited( $post_id ) ) {
		echo '<br><em>' . esc_html__( 'Edited — the series will not overwrite it', 'producerkit' ) . '</em>';
	}
}
