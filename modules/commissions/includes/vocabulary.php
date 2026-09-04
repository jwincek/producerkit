<?php
/**
 * What this trade calls a quote-then-make job.
 *
 * "Commission" is the right word for a potter, a painter or a taxidermist,
 * and the wrong one for most other people the plugin serves. A beekeeper
 * asked about bulk honey, mated queens or wax does not take commissions; they
 * answer an enquiry. A baker quoting a wedding cake takes a special order.
 * Showing a beekeeper's customer the word "commission" makes the form read as
 * though it belongs to someone else's business.
 *
 * Post types and taxonomies already re-label themselves per profile through
 * pkit_post_type_names and pkit_taxonomy_names. Commissions are rows in a
 * custom table rather than posts, so they flow through neither, and were left
 * speaking one trade's language to everybody.
 *
 * Only the wording moves. The type string 'commission', the table name, the
 * REST routes, the hook names and the ability names are all frozen
 * identifiers, and renaming any of them would break every integration built
 * on them for the sake of a label.
 */

declare(strict_types=1);

namespace ProducerKit\Commissions\Vocabulary;

defined( 'ABSPATH' ) || exit;

/**
 * The words this site uses, with the craft defaults as the fallback.
 *
 * Four slots rather than the [singular, plural, menu] triple the post types
 * use, because this concept also needs a verb — the thing the button on the
 * form actually says. "Commission a piece" and "Ask about bulk orders" are
 * not the same sentence with a noun swapped.
 *
 * @return array{singular: string, plural: string, menu: string, action: string}
 */
function words(): array {
	$defaults = [
		'singular' => __( 'Commission', 'producerkit' ),
		'plural'   => __( 'Commissions', 'producerkit' ),
		'menu'     => __( 'Commissions', 'producerkit' ),
		'action'   => __( 'Commission a piece', 'producerkit' ),
	];

	/**
	 * Filters what this site calls a quote-then-make job.
	 *
	 * @param array{singular: string, plural: string, menu: string, action: string} $words
	 */
	$filtered = (array) apply_filters( 'pkit_commission_names', $defaults );

	// Rebuilt from the known slots rather than returned as handed back, so the
	// documented shape is the actual shape. A filter that returns a partial
	// array, blanks a slot, or invents one it made up gets the defaults for
	// what it left out and no extra keys for what it added — callers index
	// this array directly.
	$words = [];
	foreach ( $defaults as $slot => $fallback ) {
		$value = isset( $filtered[ $slot ] ) ? trim( (string) $filtered[ $slot ] ) : '';

		$words[ $slot ] = '' !== $value ? $value : $fallback;
	}

	return $words;
}

/** One of them, e.g. "Enquiry". */
function singular(): string {
	return words()['singular'];
}

/** Several, e.g. "Enquiries". */
function plural(): string {
	return words()['plural'];
}

/** The admin menu label. */
function menu(): string {
	return words()['menu'];
}

/** What the form's button says, e.g. "Ask about bulk orders". */
function action(): string {
	return words()['action'];
}

/**
 * The singular in the middle of a sentence, e.g. "your enquiry is confirmed".
 *
 * Lowercased only when the word is not a proper noun already — a profile that
 * genuinely wants "Special Order" mid-sentence gets "special order", but one
 * that has not overridden anything is unaffected by the transformation.
 */
function singular_lower(): string {
	$word = singular();

	// mb_strtolower keeps accented and non-Latin overrides intact; strtolower
	// would corrupt a multibyte first character.
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $word, 'UTF-8' ) : strtolower( $word );
}

/** The plural in the middle of a sentence. */
function plural_lower(): string {
	$word = plural();

	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $word, 'UTF-8' ) : strtolower( $word );
}
