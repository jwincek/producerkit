<?php
/**
 * Schema.org JSON-LD for product and location singles.
 *
 * Products emit Product (+ Offer when the free-text display price has a
 * parseable amount) with availability mapped from the availability table.
 * Locations emit LocalBusiness with address, geo, opening hours from the
 * weekly schedule, and accepted payment methods.
 *
 * Filter the final array (or return [] to suppress) via
 * 'lfuf_structured_data'.
 */

declare(strict_types=1);

namespace Leftfield\Core\StructuredData;

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', __NAMESPACE__ . '\\print_json_ld' );

function print_json_ld(): void {
	if ( ! is_singular( [ 'lfuf_product', 'lfuf_location' ] ) ) {
		return;
	}

	$post = get_queried_object();
	if ( ! $post instanceof \WP_Post ) {
		return;
	}

	$data = $post->post_type === 'lfuf_product'
		? product_data( $post )
		: location_data( $post );

	/**
	 * Filters the JSON-LD array before output. Return an empty array
	 * to suppress structured data for this post.
	 *
	 * @param array    $data Schema.org data.
	 * @param \WP_Post $post The queried post.
	 */
	$data = apply_filters( 'lfuf_structured_data', $data, $post );
	if ( ! $data ) {
		return;
	}

	// JSON_HEX_TAG/AMP prevent </script> breakouts from stored content.
	echo '<script type="application/ld+json">'
		. wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP )
		. '</script>' . "\n";
}

/**
 * Extract a numeric amount from the free-text display price
 * ("$5/loaf" → 5.00). Returns null when there's nothing parseable or
 * the price is donation-style.
 */
function parse_price( string $display_price ): ?string {
	if ( $display_price === '' || stripos( $display_price, 'donation' ) !== false ) {
		return null;
	}
	if ( ! preg_match( '/(\d+(?:\.\d{1,2})?)/', $display_price, $m ) ) {
		return null;
	}
	return number_format( (float) $m[1], 2, '.', '' );
}

/**
 * Map an availability status to a schema.org ItemAvailability URL.
 */
function availability_url( string $status ): ?string {
	return match ( $status ) {
		'abundant', 'available' => 'https://schema.org/InStock',
		'limited'               => 'https://schema.org/LimitedAvailability',
		'sold_out'              => 'https://schema.org/SoldOut',
		'unavailable'           => 'https://schema.org/OutOfStock',
		default                 => null,
	};
}

function product_data( \WP_Post $post ): array {
	$data = [
		'@context' => 'https://schema.org',
		'@type'    => 'Product',
		'name'     => $post->post_title,
		'url'      => get_permalink( $post ),
	];

	if ( $post->post_excerpt ) {
		$data['description'] = wp_strip_all_tags( $post->post_excerpt );
	}
	$image = get_the_post_thumbnail_url( $post, 'large' );
	if ( $image ) {
		$data['image'] = $image;
	}

	// Offer: only when the display price parses to a number.
	$price = parse_price( (string) get_post_meta( $post->ID, '_lfuf_price', true ) );
	if ( $price !== null ) {
		$offer = [
			'@type'         => 'Offer',
			'price'         => $price,
			/**
			 * Filters the currency used in structured-data offers.
			 *
			 * @param string $currency ISO 4217 code. Default 'USD'.
			 */
			'priceCurrency' => apply_filters( 'lfuf_structured_data_currency', 'USD' ),
			'url'           => get_permalink( $post ),
		];

		$rows = \Leftfield\Core\Availability\get_current( $post->ID );
		if ( $rows ) {
			$availability = availability_url( (string) $rows[0]->status );
			if ( $availability ) {
				$offer['availability'] = $availability;
			}
		}

		$data['offers'] = $offer;
	}

	return $data;
}

function location_data( \WP_Post $post ): array {
	$data = [
		'@context' => 'https://schema.org',
		'@type'    => 'LocalBusiness',
		'name'     => $post->post_title,
		'url'      => get_permalink( $post ),
	];

	$address = (string) get_post_meta( $post->ID, '_lfuf_address', true );
	if ( $address !== '' ) {
		$data['address'] = $address;
	}

	$lat = (float) get_post_meta( $post->ID, '_lfuf_lat', true );
	$lng = (float) get_post_meta( $post->ID, '_lfuf_lng', true );
	if ( $lat !== 0.0 || $lng !== 0.0 ) {
		$data['geo'] = [
			'@type'     => 'GeoCoordinates',
			'latitude'  => $lat,
			'longitude' => $lng,
		];
	}

	// Weekly schedule → schema openingHours ("Sa 09:00-16:00").
	$schedule = json_decode( (string) get_post_meta( $post->ID, '_lfuf_ss_schedule', true ), true );
	if ( is_array( $schedule ) && $schedule ) {
		$abbrev = [ 'Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa' ];
		$hours  = [];
		foreach ( $schedule as $entry ) {
			$day = (int) ( $entry['day'] ?? -1 );
			if ( $day >= 0 && $day <= 6 && ! empty( $entry['open'] ) && ! empty( $entry['close'] ) ) {
				$hours[] = $abbrev[ $day ] . ' ' . $entry['open'] . '-' . $entry['close'];
			}
		}
		if ( $hours ) {
			$data['openingHours'] = $hours;
		}
	}

	$methods = \Leftfield\Core\Payments\get_payment_methods( $post->ID );
	if ( $methods ) {
		$data['paymentAccepted'] = implode( ', ', array_column( $methods, 'label' ) );
	}

	$image = get_the_post_thumbnail_url( $post, 'large' );
	if ( $image ) {
		$data['image'] = $image;
	}

	return $data;
}
