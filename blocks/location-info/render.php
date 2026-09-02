<?php
/**
 * Server-side render for producerkit/location-info.
 *
 * Accessibility: <section> with aria-label, screen-reader labels
 * on address/hours, new-tab warning on Venmo link, role="status"
 * on open/closed badge.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$location_id = (int) ( $attributes['locationId'] ?? 0 );
$show_venmo  = (bool) ( $attributes['showVenmo'] ?? true );
$show_status = (bool) ( $attributes['showStatus'] ?? true );
$show_qr     = (bool) ( $attributes['showQR'] ?? false );

if ( $location_id < 1 ) {
	return;
}

$location = get_post( $location_id );
if ( ! $location || $location->post_type !== 'pkit_location' || $location->post_status !== 'publish' ) {
	return;
}

$address         = get_post_meta( $location_id, '_pkit_address', true );
$location_type   = get_post_meta( $location_id, '_pkit_location_type', true );
$payment_methods = \ProducerKit\Core\Payments\get_payment_methods( $location_id );
$hours           = get_post_meta( $location_id, '_pkit_hours', true );
$is_open         = (bool) get_post_meta( $location_id, '_pkit_is_open', true );

// Compute effective status from schedule + season (matches stand-status-banner logic).
$auto_toggle  = (bool) get_post_meta( $location_id, '_pkit_ss_auto_toggle', true );
$schedule     = get_post_meta( $location_id, '_pkit_ss_schedule', true );
$season_start = get_post_meta( $location_id, '_pkit_ss_season_start', true );
$season_end   = get_post_meta( $location_id, '_pkit_ss_season_end', true );

if ( $auto_toggle && $schedule && function_exists( '\\ProducerKit\\StandStatus\\REST\\compute_schedule_status' ) ) {
	$is_open = \ProducerKit\StandStatus\REST\compute_schedule_status( $schedule );
}
if ( $season_start && $season_end && function_exists( '\\ProducerKit\\StandStatus\\REST\\is_in_season' ) ) {
	if ( ! \ProducerKit\StandStatus\REST\is_in_season( $season_start, $season_end ) ) {
		$is_open = false;
	}
}

$wrapper_attrs = get_block_wrapper_attributes(
	[
		'class' => 'pkit-location-info',
	]
);

$section_label = sprintf(
	/* translators: %s = location name */
	__( '%s — Location Details', 'producerkit' ),
	$location->post_title,
);
?>

<section <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes(). ?> aria-label="<?php echo esc_attr( $section_label ); ?>">
	<div class="pkit-location-info__header">
		<h3 class="pkit-location-info__title">
			<?php echo esc_html( $location->post_title ); ?>
		</h3>

		<?php if ( $show_status ) : ?>
			<span
				class="pkit-location-info__status pkit-location-info__status--<?php echo $is_open ? 'open' : 'closed'; ?>"
				role="status"
			>
				<?php echo $is_open ? esc_html__( 'Open Now', 'producerkit' ) : esc_html__( 'Closed', 'producerkit' ); ?>
			</span>
		<?php endif; ?>
	</div>

	<?php if ( $location_type ) : ?>
		<span class="pkit-location-info__type">
			<?php echo esc_html( ucfirst( $location_type ) ); ?>
		</span>
	<?php endif; ?>

	<?php if ( $address ) : ?>
		<p class="pkit-location-info__address">
			<span class="screen-reader-text"><?php esc_html_e( 'Address:', 'producerkit' ); ?> </span>
			<?php echo esc_html( $address ); ?>
			<?php
			// Directions destination: coordinates when set, else the address.
			$lat         = (float) get_post_meta( $location_id, '_pkit_lat', true );
			$lng         = (float) get_post_meta( $location_id, '_pkit_lng', true );
			$destination = ( $lat !== 0.0 || $lng !== 0.0 ) ? $lat . ',' . $lng : $address;
			?>
			<a
				class="pkit-location-info__directions"
				href="<?php echo esc_url( 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $destination ) ); ?>"
				target="_blank"
				rel="noopener noreferrer"
			>
				<?php esc_html_e( 'Get directions', 'producerkit' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'producerkit' ); ?></span>
			</a>
		</p>
	<?php endif; ?>

	<?php if ( $hours ) : ?>
		<p class="pkit-location-info__hours">
			<span class="screen-reader-text"><?php esc_html_e( 'Hours:', 'producerkit' ); ?> </span>
			<?php echo esc_html( $hours ); ?>
		</p>
	<?php endif; ?>

	<?php // showVenmo is the stored attribute key (pre-payment-methods content); it now means "show payment options". ?>
	<?php if ( $show_venmo && $payment_methods ) : ?>
		<div class="pkit-location-info__payments">
			<span class="pkit-location-info__payments-label"><?php esc_html_e( 'Payment options:', 'producerkit' ); ?></span>
			<ul class="pkit-location-info__payments-list">
				<?php foreach ( $payment_methods as $method ) : ?>
					<li class="pkit-location-info__payment pkit-location-info__payment--<?php echo esc_attr( $method['type'] ); ?>">
						<?php if ( $method['is_link'] ) : ?>
							<a
								class="pkit-location-info__payment-link"
								href="<?php echo esc_url( $method['url'] ); ?>"
								target="_blank"
								rel="noopener noreferrer"
							>
								<?php
								if ( in_array( $method['type'], [ 'venmo', 'cashapp', 'paypal' ], true ) ) {
									printf(
										/* translators: 1: payment service name, 2: account handle. */
										esc_html__( '%1$s (@%2$s)', 'producerkit' ),
										esc_html( $method['label'] ),
										esc_html( $method['value'] ),
									);
								} else {
									echo esc_html( $method['label'] );
								}
								?>
								<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'producerkit' ); ?></span>
							</a>
						<?php else : ?>
							<span class="pkit-location-info__payment-badge"><?php echo esc_html( $method['label'] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php
			// QR code for the first payment link (rendered client-side by pkit-qr).
			$qr_link = null;
			if ( $show_qr ) {
				foreach ( $payment_methods as $method ) {
					if ( $method['is_link'] ) {
						$qr_link = $method;
						break;
					}
				}
			}
			?>
			<?php if ( $qr_link ) : ?>
				<?php wp_enqueue_script( 'pkit-qr' ); ?>
				<div class="pkit-location-info__qr">
					<div
						class="pkit-location-info__qr-code"
						data-pkit-qr="<?php echo esc_attr( $qr_link['url'] ); ?>"
						data-pkit-qr-label="
						<?php
							printf(
								/* translators: %s: payment method label. */
								esc_attr__( 'QR code: pay with %s', 'producerkit' ),
								esc_attr( $qr_link['label'] ),
							);
						?>
						"
					></div>
					<span class="pkit-location-info__qr-caption">
						<?php
						printf(
							/* translators: %s: payment method label. */
							esc_html__( 'Scan to pay with %s', 'producerkit' ),
							esc_html( $qr_link['label'] ),
						);
						?>
					</span>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>