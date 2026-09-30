<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Two kinds of thing get left behind, and they deserve different defaults.
 *
 * Plugin bookkeeping — schema version options, the cron hook, rate-limit
 * transients — is meaningless without the plugin and is always removed.
 *
 * Everything a person actually made — products, locations, events, sources,
 * and the RSVPs, pre-orders and commissions in the custom tables — is only
 * removed when the site has explicitly asked for that, because deleting a
 * plugin to troubleshoot something should not destroy a catalogue. The
 * setting lives under ProducerKit → Producer Profile and is off by default.
 *
 * @package ProducerKit
 */

declare(strict_types=1);

// Only ever reached through WordPress's own uninstall path.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Do the work.
 *
 * Wrapped rather than run at file scope: variables at the top level of an
 * uninstall script are globals as far as WordPress coding standards are
 * concerned, and $post_type and $taxonomy are real WordPress globals that
 * assigning to would clobber.
 */
function pkit_uninstall(): void {
	global $wpdb;

	/* ── Always: bookkeeping that means nothing without the plugin ────────────── */

	$options = [
		'pkit_availability_db_version',
		'pkit_preorder_db_version',
		'pkit_rsvp_db_version',
		'pkit_commissions_db_version',
		'pkit_wc_settlement_db_version',
		'pkit_meta_key_version',
		'pkit_sample_data_loaded',
		'pkit_producer_profile_flush',
		'pkit_profile_prompt_dismissed',
	];

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// The plugin's taxonomies, including the three a producer profile may have
	// switched on.
	$taxonomies = [
		'pkit_product_type',
		'pkit_season',
		'pkit_event_type',
		'pkit_material',
		'pkit_finish',
		'pkit_component',
	];

	// WordPress caches each hierarchical taxonomy's parent/child map in an
	// option of its own. It rebuilds that on demand, so it goes even when the
	// terms are kept.
	foreach ( $taxonomies as $taxonomy ) {
		delete_option( "{$taxonomy}_children" );
	}

	wp_clear_scheduled_hook( 'pkit_availability_cleanup' );
	wp_clear_scheduled_hook( 'pkit_series_extend' );

	// Transients are per-visitor and short-lived; the LIKE covers the per-IP rate
	// limit keys and the per-request payment links, which are generated names.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE '\_transient\_pkit\_%'
		    OR option_name LIKE '\_transient\_timeout\_pkit\_%'"
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	/* ── Only on request: the content itself ─────────────────────────────────── */

	if ( ! get_option( 'pkit_delete_data_on_uninstall' ) ) {
		// The choice itself is the last thing to go, so re-installing does not
		// silently inherit a decision made a year ago.
		delete_option( 'pkit_delete_data_on_uninstall' );
		return;
	}

	// Custom tables. Dropped rather than emptied — nothing else reads them.
	foreach ( [ 'pkit_availability', 'pkit_rsvps', 'pkit_preorders', 'pkit_commissions' ] as $suffix ) {
		$table = $wpdb->prefix . $suffix;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dropping this plugin's own tables at uninstall is the entire point; a table name is an identifier and cannot be a placeholder.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// Posts, with their meta and term relationships. wp_delete_post() rather than
	// a DELETE, so attachments and taxonomy counts are handled properly.
	$post_types = [ 'pkit_product', 'pkit_source', 'pkit_location', 'pkit_event' ];

	foreach ( $post_types as $post_type ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One query per type at uninstall; get_posts() cannot see an unregistered type here.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", $post_type ) );

		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}

	// Terms in the plugin's taxonomies. The plugin is not loaded during
	// uninstall, and wp_delete_term() refuses a taxonomy that is not registered,
	// so each one is registered just long enough to empty it. Flat on purpose:
	// a hierarchical registration would have WordPress rebuild the
	// {taxonomy}_children cache cleared above.
	foreach ( $taxonomies as $taxonomy ) {
		$borrowed = ! taxonomy_exists( $taxonomy );

		if ( $borrowed ) {
			register_taxonomy( $taxonomy, [] );
		}

		$term_ids = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			]
		);

		foreach ( is_array( $term_ids ) ? $term_ids : [] as $term_id ) {
			wp_delete_term( (int) $term_id, $taxonomy );
		}

		if ( $borrowed ) {
			unregister_taxonomy( $taxonomy );
		}
	}

	// The pages the dashboard generated are kept — they are ordinary pages by
	// now, likely edited — but the marker saying ProducerKit made them goes.
	delete_post_meta_by_key( '_pkit_generated_page' );

	// Per-person settings.
	delete_metadata( 'user', 0, 'pkit_producer_profile', '', true );
	delete_metadata( 'user', 0, 'pkit_producer_name', '', true );

	delete_option( 'pkit_producer_profile' );
	delete_option( 'pkit_disabled_modules' );
	delete_option( 'pkit_delete_data_on_uninstall' );
}

pkit_uninstall();
