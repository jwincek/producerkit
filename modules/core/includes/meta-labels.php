<?php
/**
 * What this trade calls its own fields.
 *
 * Taxonomies and post types already re-label themselves per profile through
 * pkit_taxonomy_names and pkit_post_type_names. Post meta did not, so a
 * musician cataloguing a release saw "Farm / Origin Name", "Location",
 * "History" and "Milling / Process Notes" — when what they mean is a label, a
 * studio, some background and mastering notes.
 *
 * The data model was already right. `_pkit_source_farm_name` is "who this came
 * from", `_pkit_source_location` is "where", `_pkit_milling_notes` is "what was
 * done to it in between". Those are the right three questions for a record
 * label, a tannery or a mill. Only the words were wrong, which is why this is
 * a labelling problem and not a schema one — the meta keys are in the
 * database and stay exactly as they are.
 *
 * Scope is the trade-specific labels, not every field. "Price" and "Location"
 * are the same word in every trade; "Growing / Baking Notes" is not.
 */

declare(strict_types=1);

namespace ProducerKit\Core\MetaLabels;

defined( 'ABSPATH' ) || exit;

/**
 * The labels this site uses, with the farm-and-mill wording as the fallback.
 *
 * Each entry is [ label, help ]. The help text is as trade-specific as the
 * label — telling a jeweller their notes are about "grind, cure, age" is the
 * same mistake one line further down.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function labels(): array {
	$defaults = [
		'_pkit_source_farm_name' => [
			__( 'Farm / Origin Name', 'producerkit' ),
			__( 'Who this came from. Falls back to the post title on the front end if left empty.', 'producerkit' ),
		],
		'_pkit_source_location'  => [
			__( 'Location', 'producerkit' ),
			__( 'County and state, or however you would say where.', 'producerkit' ),
		],
		'_pkit_source_history'   => [
			__( 'History', 'producerkit' ),
			__( 'Heritage notes — the story behind this ingredient or variety.', 'producerkit' ),
		],
		'_pkit_milling_notes'    => [
			__( 'Milling / Process Notes', 'producerkit' ),
			__( 'What was done to it in between — grind, cure, age, finish.', 'producerkit' ),
		],
		'_pkit_growing_notes'    => [
			__( 'Growing / Baking Notes', 'producerkit' ),
			__( 'Shown on the product page. Free-form.', 'producerkit' ),
		],
	];

	/**
	 * Filters the labels for trade-specific meta fields.
	 *
	 * @param array<string, array{0: string, 1: string}> $labels Key => [ label, help ].
	 */
	$filtered = (array) apply_filters( 'pkit_meta_labels', $defaults );

	// Rebuilt from the known keys rather than returned as handed back, so the
	// documented shape is the actual shape and a filter cannot blank a label,
	// half-declare a pair, or add a key nothing reads. Same rule as
	// Commissions\Vocabulary\words(), and for the same reason: callers index
	// this directly.
	$labels = [];
	foreach ( $defaults as $key => $fallback ) {
		$pair = isset( $filtered[ $key ] ) ? (array) $filtered[ $key ] : [];

		$labels[ $key ] = [
			isset( $pair[0] ) && '' !== trim( (string) $pair[0] ) ? (string) $pair[0] : $fallback[0],
			isset( $pair[1] ) && '' !== trim( (string) $pair[1] ) ? (string) $pair[1] : $fallback[1],
		];
	}

	return $labels;
}

/**
 * The label for one field.
 */
function label( string $key ): string {
	$labels = labels();

	return $labels[ $key ][0] ?? $key;
}

/**
 * The help text for one field.
 */
function help( string $key ): string {
	$labels = labels();

	return $labels[ $key ][1] ?? '';
}

/**
 * Flattened for the editor, which wants a plain key => { label, help } object.
 *
 * @return array<string, array{label: string, help: string}>
 */
function for_editor(): array {
	$out = [];

	foreach ( labels() as $key => $pair ) {
		$out[ $key ] = [
			'label' => $pair[0],
			'help'  => $pair[1],
		];
	}

	return $out;
}
