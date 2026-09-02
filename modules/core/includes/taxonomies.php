<?php
/**
 * Shared Taxonomies.
 *
 * lfuf_product_type — Produce, Bread, Pantry Good, Seedling, etc.
 * lfuf_season       — Spring, Summer, Fall, Winter (shared across products & events).
 * lfuf_event_type   — Pizza Night, Potluck, Farm Dinner, Workshop, Tour, Market, etc.
 *
 * The display names and the seeded default terms both run through filters so
 * that the producer-profiles module can re-label a taxonomy for a different
 * trade — "Material" becomes "Floral Source" for a beekeeper, "Wood Species"
 * for a woodworker — without core knowing that module exists. With no profile
 * active the defaults below are what ships.
 */

declare(strict_types=1);

namespace ProducerKit\Core\Taxonomies;

defined( 'ABSPATH' ) || exit;

function register(): void {
	register_product_type();
	register_season();
	register_event_type();
}

/**
 * Build a full WordPress label set from a singular/plural pair.
 *
 * Filterable as a pair rather than as a finished label array: a profile
 * declaring `[ 'Floral Source', 'Floral Sources' ]` gets all eleven labels
 * derived for it, instead of having to restate each one.
 *
 * @param string $taxonomy Taxonomy slug, passed to the filter for context.
 * @param string $singular Default singular name.
 * @param string $plural   Default plural name.
 * @return array<string, string>
 */
function build_labels( string $taxonomy, string $singular, string $plural ): array {
	/**
	 * Filters the singular/plural display names for one of the plugin's taxonomies.
	 *
	 * @param array{0: string, 1: string} $names    [ singular, plural ].
	 * @param string                      $taxonomy Taxonomy slug.
	 */
	[ $singular, $plural ] = apply_filters( 'lfuf_taxonomy_names', [ $singular, $plural ], $taxonomy );

	return [
		'name'              => $plural,
		'singular_name'     => $singular,
		/* translators: %s: plural taxonomy name. */
		'search_items'      => sprintf( __( 'Search %s', 'producerkit' ), $plural ),
		/* translators: %s: plural taxonomy name. */
		'all_items'         => sprintf( __( 'All %s', 'producerkit' ), $plural ),
		/* translators: %s: singular taxonomy name. */
		'parent_item'       => sprintf( __( 'Parent %s', 'producerkit' ), $singular ),
		/* translators: %s: singular taxonomy name. */
		'parent_item_colon' => sprintf( __( 'Parent %s:', 'producerkit' ), $singular ),
		/* translators: %s: singular taxonomy name. */
		'edit_item'         => sprintf( __( 'Edit %s', 'producerkit' ), $singular ),
		/* translators: %s: singular taxonomy name. */
		'update_item'       => sprintf( __( 'Update %s', 'producerkit' ), $singular ),
		/* translators: %s: singular taxonomy name. */
		'add_new_item'      => sprintf( __( 'Add New %s', 'producerkit' ), $singular ),
		/* translators: %s: singular taxonomy name. */
		'new_item_name'     => sprintf( __( 'New %s Name', 'producerkit' ), $singular ),
		'menu_name'         => $plural,
	];
}

/**
 * Seed a taxonomy's default terms, once, without ever removing existing ones.
 *
 * Admin-only and self-healing: a term deleted on purpose stays deleted until
 * the next profile switch re-seeds, and a term added by hand is never touched.
 *
 * @param string   $taxonomy Taxonomy slug.
 * @param string[] $defaults Term names to ensure exist.
 */
function seed_terms( string $taxonomy, array $defaults ): void {
	/**
	 * Filters the default terms seeded into one of the plugin's taxonomies.
	 *
	 * @param string[] $defaults Term names.
	 * @param string   $taxonomy Taxonomy slug.
	 */
	$defaults = (array) apply_filters( 'lfuf_taxonomy_default_terms', $defaults, $taxonomy );

	foreach ( $defaults as $term ) {
		$term = trim( (string) $term );
		if ( '' !== $term && ! term_exists( $term, $taxonomy ) ) {
			wp_insert_term( $term, $taxonomy );
		}
	}
}

/**
 * The trade-specific terms a product carries, keyed by their current label.
 *
 * Product type and season have their own places in every template. These are
 * the optional ones a producer profile switches on — a potter's Clay Body and
 * Glaze, a printer's Substrate and Ink — which otherwise had somewhere to be
 * entered and nowhere to be seen.
 *
 * Core asks rather than knows: the list arrives by filter, so with the
 * producer-profiles module off this returns nothing and every caller carries
 * on unchanged.
 *
 * @param int $post_id Product id.
 * @return array<string, string[]> Display label => term names.
 */
function detail_terms( int $post_id ): array {
	/**
	 * Filters which taxonomies count as trade detail on a product.
	 *
	 * @param string[] $taxonomies Taxonomy slugs, in display order.
	 * @param int      $post_id    Product id.
	 */
	$taxonomies = (array) apply_filters( 'lfuf_detail_taxonomies', [], $post_id );

	$out = [];

	foreach ( $taxonomies as $taxonomy ) {
		if ( ! is_string( $taxonomy ) || ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}

		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! $terms || is_wp_error( $terms ) ) {
			continue;
		}

		// Keyed by the label the current viewer sees, which under
		// multi-profile is not the same for everyone.
		$out[ get_taxonomy( $taxonomy )->labels->singular_name ] = wp_list_pluck( $terms, 'name' );
	}

	return $out;
}

/* ───────────────────────────────────────────────
 * Product Type
 * ─────────────────────────────────────────────── */
function register_product_type(): void {
	register_taxonomy(
		'lfuf_product_type',
		[ 'lfuf_product' ],
		[
			'labels'            => build_labels(
				'lfuf_product_type',
				__( 'Product Type', 'producerkit' ),
				__( 'Product Types', 'producerkit' )
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'rest_base'         => 'product-types',
			'rest_namespace'    => 'lfuf/v1',
			'rewrite'           => [
				'slug'       => 'product-type',
				'with_front' => false,
			],
			'show_admin_column' => true,
		]
	);

	// Seed default terms (self-healing, admin only).
	if ( is_admin() ) {
		seed_terms( 'lfuf_product_type', [ 'Produce', 'Bread', 'Baked Good', 'Pantry Good', 'Seedling' ] );
	}
}

/* ───────────────────────────────────────────────
 * Season (shared: products + events)
 * ─────────────────────────────────────────────── */
function register_season(): void {
	register_taxonomy(
		'lfuf_season',
		[ 'lfuf_product', 'lfuf_event' ],
		[
			'labels'            => build_labels(
				'lfuf_season',
				__( 'Season', 'producerkit' ),
				__( 'Seasons', 'producerkit' )
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'rest_base'         => 'seasons',
			'rest_namespace'    => 'lfuf/v1',
			'rewrite'           => [
				'slug'       => 'season',
				'with_front' => false,
			],
			'show_admin_column' => true,
		]
	);

	if ( is_admin() ) {
		seed_terms( 'lfuf_season', [ 'Spring', 'Summer', 'Fall', 'Winter' ] );
	}
}

/* ───────────────────────────────────────────────
 * Event Type
 * ─────────────────────────────────────────────── */
function register_event_type(): void {
	register_taxonomy(
		'lfuf_event_type',
		[ 'lfuf_event' ],
		[
			'labels'            => build_labels(
				'lfuf_event_type',
				__( 'Event Type', 'producerkit' ),
				__( 'Event Types', 'producerkit' )
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'rest_base'         => 'event-types',
			'rest_namespace'    => 'lfuf/v1',
			'rewrite'           => [
				'slug'       => 'event-type',
				'with_front' => false,
			],
			'show_admin_column' => true,
		]
	);

	if ( is_admin() ) {
		seed_terms(
			'lfuf_event_type',
			[
				'Pizza Night',
				'Potluck',
				'Farm Dinner',
				'Workshop',
				'Farm Tour',
				'Seed Exchange',
				'Mini Market',
			]
		);
	}
}
