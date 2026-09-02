<?php
/**
 * Payment methods for locations.
 *
 * A location can accept any number of payment methods, stored as a JSON
 * array in the `_pkit_payment_methods` meta (same JSON-in-string pattern
 * as the stand schedule). Each entry is {type, value, label}:
 *
 *   - type:  a key from method_types() — venmo, cashapp, paypal,
 *            link, cash, check, snap_ebt, market_voucher
 *   - value: the handle or URL for link-kind methods, '' for badges
 *   - label: optional display label override
 *
 * The legacy `_pkit_venmo_handle` meta is still honored: if it is set and
 * the methods list has no venmo entry, it is merged in on read, so
 * existing sites keep working without migration.
 */

declare(strict_types=1);

namespace ProducerKit\Core\Payments;

defined( 'ABSPATH' ) || exit;

/** Max stored methods per location. */
const MAX_METHODS = 10;

/**
 * Registry of supported payment method types.
 *
 * kind: 'handle' (username → URL), 'url' (raw link), or 'badge'
 * (accepted-at-the-stand marker with no link — cash, SNAP/EBT, …).
 *
 * @return array<string, array{label: string, kind: string, url?: string}>
 */
function method_types(): array {
	$types = [
		'venmo'          => [
			'label' => __( 'Venmo', 'producerkit' ),
			'kind'  => 'handle',
			'url'   => 'https://venmo.com/%s',
		],
		'cashapp'        => [
			'label' => __( 'Cash App', 'producerkit' ),
			'kind'  => 'handle',
			'url'   => 'https://cash.app/$%s',
		],
		'paypal'         => [
			'label' => __( 'PayPal', 'producerkit' ),
			'kind'  => 'handle',
			'url'   => 'https://paypal.me/%s',
		],
		'link'           => [
			'label' => __( 'Payment Link', 'producerkit' ),
			'kind'  => 'url',
		],
		'cash'           => [
			'label' => __( 'Cash', 'producerkit' ),
			'kind'  => 'badge',
		],
		'check'          => [
			'label' => __( 'Check', 'producerkit' ),
			'kind'  => 'badge',
		],
		'snap_ebt'       => [
			'label' => __( 'SNAP/EBT', 'producerkit' ),
			'kind'  => 'badge',
		],
		'market_voucher' => [
			'label' => __( 'Market Vouchers (WIC/Senior FMNP)', 'producerkit' ),
			'kind'  => 'badge',
		],
	];

	/**
	 * Filters the supported payment method types.
	 *
	 * @param array $types Type key => ['label', 'kind', 'url'?].
	 */
	return apply_filters( 'pkit_payment_method_types', $types );
}

/**
 * Sanitize one method value according to its type's kind.
 *
 * Handles get the same charset treatment as the Venmo handle meta;
 * URLs must be http(s). Returns null if the value is unusable.
 */
function sanitize_method_value( string $type, mixed $value ): ?string {
	$types = method_types();
	if ( ! isset( $types[ $type ] ) ) {
		return null;
	}

	$kind  = $types[ $type ]['kind'];
	$value = is_string( $value ) ? trim( $value ) : '';

	return match ( $kind ) {
		'badge'  => '',
		'handle' => \ProducerKit\Core\Meta_Fields\sanitize_payment_handle( $value ) ?: null,
		'url'    => \ProducerKit\Core\Meta_Fields\sanitize_url_field( $value ) ?: null,
		default  => null,
	};
}

/**
 * Sanitize callback for the `_pkit_payment_methods` meta.
 *
 * Accepts a JSON string (from the editor) or an array; stores a JSON
 * string of validated rows. Unknown types and link/handle entries whose
 * value doesn't survive sanitization are dropped entirely.
 */
function sanitize_payment_methods( mixed $value ): string {
	if ( is_string( $value ) ) {
		$value = json_decode( $value, true );
	}
	if ( ! is_array( $value ) ) {
		return '';
	}

	$clean = [];
	foreach ( array_slice( $value, 0, MAX_METHODS ) as $row ) {
		if ( ! is_array( $row ) || empty( $row['type'] ) || ! is_string( $row['type'] ) ) {
			continue;
		}
		$type      = sanitize_key( $row['type'] );
		$sanitized = sanitize_method_value( $type, $row['value'] ?? '' );
		if ( $sanitized === null ) {
			continue;
		}
		$clean[] = [
			'type'  => $type,
			'value' => $sanitized,
			'label' => sanitize_text_field( $row['label'] ?? '' ),
		];
	}

	return $clean ? (string) wp_json_encode( $clean ) : '';
}

/**
 * Build the public URL for a method, or '' for badges/invalid values.
 */
function method_url( string $type, string $value ): string {
	$types = method_types();
	if ( ! isset( $types[ $type ] ) || $value === '' ) {
		return '';
	}

	return match ( $types[ $type ]['kind'] ) {
		'handle' => isset( $types[ $type ]['url'] ) ? sprintf( $types[ $type ]['url'], rawurlencode( $value ) ) : '',
		'url'    => $value,
		default  => '',
	};
}

/**
 * Get the enriched payment methods for a location.
 *
 * Merges the legacy `_pkit_venmo_handle` meta (as a venmo entry) when the
 * stored list has none, so pre-existing sites need no migration. This is
 * the single read point for blocks, REST, and abilities.
 *
 * @return array<int, array{type: string, label: string, value: string, url: string, is_link: bool}>
 */
function get_payment_methods( int $location_id ): array {
	$types  = method_types();
	$stored = json_decode( (string) get_post_meta( $location_id, '_pkit_payment_methods', true ), true );
	$rows   = is_array( $stored ) ? $stored : [];

	// Legacy single-Venmo fallback.
	$has_venmo = (bool) array_filter( $rows, fn ( $r ) => ( $r['type'] ?? '' ) === 'venmo' );
	if ( ! $has_venmo ) {
		$legacy = (string) get_post_meta( $location_id, '_pkit_venmo_handle', true );
		if ( $legacy !== '' ) {
			array_unshift(
				$rows,
				[
					'type'  => 'venmo',
					'value' => $legacy,
					'label' => '',
				]
			);
		}
	}

	$methods = [];
	foreach ( $rows as $row ) {
		$type = $row['type'] ?? '';
		if ( ! isset( $types[ $type ] ) ) {
			continue;
		}
		$value = is_string( $row['value'] ?? null ) ? $row['value'] : '';
		$url   = method_url( $type, $value );
		$kind  = $types[ $type ]['kind'];

		// A link-kind entry with no usable URL renders nothing — skip it.
		if ( $kind !== 'badge' && $url === '' ) {
			continue;
		}

		$methods[] = [
			'type'    => $type,
			'label'   => ( $row['label'] ?? '' ) !== '' ? $row['label'] : $types[ $type ]['label'],
			'value'   => $value,
			'url'     => $url,
			'is_link' => $url !== '',
		];
	}

	return $methods;
}
