<?php
/**
 * Product image resolution.
 *
 * A product with no featured image falls back to a muted illustration chosen
 * by its pkit_product_type term, so the availability board and product cards
 * stay visually even on a site that has not uploaded photos yet.
 *
 * The placeholders are deliberately flat and low-contrast: they should read as
 * "no photo yet" rather than compete with a real photograph in the next card,
 * and the admin dashboard still nudges the site owner to upload real ones.
 *
 * They carry no text, because text baked into a shipped SVG cannot be
 * translated.
 */

declare(strict_types=1);

namespace ProducerKit\Core\Product_Images;

defined( 'ABSPATH' ) || exit;

/**
 * Base URL of the bundled placeholder directory.
 *
 * plugin_dir_url() only needs a path *inside* the plugin — the file itself
 * need not exist — so this avoids naming the main plugin file, which is not
 * reliably derivable from the directory (CI clones into a folder named after
 * the repository).
 */
function assets_url(): string {
	return plugin_dir_url( \ProducerKit\PLUGIN_DIR . '/.' ) . 'assets/img/';
}

/**
 * The product type slug used to pick a placeholder, or '' if none applies.
 */
function type_slug( int $product_id ): string {
	$terms = get_the_terms( $product_id, 'pkit_product_type' );

	if ( ! is_array( $terms ) || ! $terms ) {
		return '';
	}

	// A product may carry several types; the first is the one displayed on the
	// board, so it is the one the image should agree with.
	return (string) ( $terms[0]->slug ?? '' );
}

/**
 * Placeholder URL for a product, or '' when there is nothing suitable.
 *
 * Product types are a user-editable taxonomy, so a site may well have types
 * this plugin ships no art for. That is not an error — the callers simply
 * render no image, exactly as they did before placeholders existed.
 *
 * @param int $product_id Product post ID.
 * @return string Absolute URL, or '' to render no image.
 */
function placeholder_url( int $product_id ): string {
	$slug = type_slug( $product_id );
	$url  = '';

	if ( $slug ) {
		$file = 'assets/img/placeholder-' . $slug . '.svg';

		if ( is_file( \ProducerKit\PLUGIN_DIR . '/' . $file ) ) {
			$url = assets_url() . 'placeholder-' . $slug . '.svg';
		}
	}

	/**
	 * Filters the placeholder image used for a product with no featured image.
	 *
	 * Return '' to render no image at all.
	 *
	 * @param string $url        Placeholder URL, or '' if none was found.
	 * @param int    $product_id Product post ID.
	 * @param string $slug       Resolved product type slug, or ''.
	 */
	return (string) apply_filters( 'pkit_product_placeholder_url', $url, $product_id, $slug );
}

/**
 * A product's featured image if it has one, otherwise its type placeholder.
 *
 * @param int    $product_id Product post ID.
 * @param string $size       Registered image size for the featured image.
 * @return string Absolute URL, or '' when neither is available.
 */
function thumbnail_url( int $product_id, string $size = 'thumbnail' ): string {
	$thumb_id = get_post_thumbnail_id( $product_id );

	if ( $thumb_id ) {
		$url = wp_get_attachment_image_url( $thumb_id, $size );

		if ( $url ) {
			return $url;
		}
	}

	return placeholder_url( $product_id );
}
