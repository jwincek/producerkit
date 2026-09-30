<?php
/**
 * The sources behind a product, as the public may see them.
 *
 * Two public paths answer "where does this product come from?" for any product
 * ID a caller supplies: GET /products/{id}/sources and the get-product-sources
 * ability. Both filtered the sources they returned to published ones, and
 * neither checked the product. So a draft or private product's sources could
 * be read by anyone counting upward through IDs, and neither path even
 * confirmed the ID was a product (#102). Both now ask this.
 */

declare(strict_types=1);

namespace ProducerKit\Core\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Published sources for a published product.
 *
 * Anything else — a draft, a private product, a trashed one, an ID that is not
 * a product at all — gets the same empty answer as a product with no sources.
 * Distinguishing "no such product" from "no sources" would tell a guesser what
 * exists, which is the thing being withheld.
 *
 * @return array<int, \WP_Post>
 */
function for_product( int $product_id ): array {
	$product = get_post( $product_id );

	if ( ! $product instanceof \WP_Post
		|| 'pkit_product' !== $product->post_type
		|| 'publish' !== $product->post_status
		|| '' !== (string) $product->post_password
	) {
		return [];
	}

	$source_ids = get_post_meta( $product_id, '_pkit_source_ids', true );

	if ( ! is_array( $source_ids ) || [] === $source_ids ) {
		return [];
	}

	return get_posts(
		[
			'post_type'    => 'pkit_source',
			'post__in'     => array_map( 'intval', $source_ids ),
			'numberposts'  => 20,
			'post_status'  => 'publish',
			'has_password' => false,
		]
	);
}
