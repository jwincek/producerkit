<?php
/**
 * Single post content enhancement for plugin CPTs.
 *
 * When viewing a single product, source, location, or event,
 * this appends the structured meta data below the post content.
 * Works with any theme — no custom template files needed.
 *
 * Only runs on singular views of our CPTs, not in feeds,
 * REST responses, or admin.
 */

declare(strict_types=1);

namespace Leftfield\Core\SingleContent;

defined( 'ABSPATH' ) || exit;

add_filter( 'the_content', __NAMESPACE__ . '\\enhance_single_content', 20 );

function enhance_single_content( string $content ): string {
	// Only on singular front-end views.
	if ( ! is_singular() || is_admin() || wp_doing_ajax() ) {
		return $content;
	}

	$post = get_post();
	if ( ! $post ) {
		return $content;
	}

	// Only run once per page load (avoid nested calls from apply_filters).
	static $running = false;
	if ( $running ) {
		return $content;
	}
	$running = true;

	$extra = match ( $post->post_type ) {
		'lfuf_product'  => render_product_details( $post ),
		'lfuf_source'   => render_source_details( $post ),
		'lfuf_location' => render_location_details( $post ),
		'lfuf_event'    => render_event_details( $post ),
		default         => '',
	};

	$running = false;

	return $content . $extra;
}

/* ───────────────────────────────────────────────
 * Product single
 * ─────────────────────────────────────────────── */

function render_product_details( \WP_Post $post ): string {
	$id            = $post->ID;
	$price         = get_post_meta( $id, '_lfuf_price', true );
	$unit          = get_post_meta( $id, '_lfuf_unit', true );
	$growing_notes = get_post_meta( $id, '_lfuf_growing_notes', true );
	$types         = get_the_terms( $id, 'lfuf_product_type' );
	$seasons       = get_the_terms( $id, 'lfuf_season' );

	// Availability.
	$availability = \Leftfield\Core\Availability\get_current( $id );

	// Sources.
	$source_ids = get_post_meta( $id, '_lfuf_source_ids', true );
	$sources    = [];
	if ( is_array( $source_ids ) && ! empty( $source_ids ) ) {
		$sources = get_posts(
			[
				'post_type'   => 'lfuf_source',
				'post__in'    => $source_ids,
				'numberposts' => 10,
				'post_status' => 'publish',
			]
		);
	}

	ob_start();
	?>
	<div class="lfuf-single-details lfuf-single-details--product">
		<?php if ( $price || $unit ) : ?>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'Price', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value">
					<?php echo esc_html( $price ); ?>
					<?php if ( $unit ) : ?>
						<span class="lfuf-single-details__unit">/ <?php echo esc_html( $unit ); ?></span>
					<?php endif; ?>
				</span>
			</div>
		<?php endif; ?>

		<?php if ( $types && ! is_wp_error( $types ) ) : ?>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'Type', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value">
					<?php echo esc_html( implode( ', ', wp_list_pluck( $types, 'name' ) ) ); ?>
				</span>
			</div>
		<?php endif; ?>

		<?php if ( $seasons && ! is_wp_error( $seasons ) ) : ?>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'Season', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value">
					<?php echo esc_html( implode( ', ', wp_list_pluck( $seasons, 'name' ) ) ); ?>
				</span>
			</div>
		<?php endif; ?>

		<?php
		if ( ! empty( $availability ) ) :
			$row         = $availability[0];
			$status_text = ucfirst( str_replace( '_', ' ', $row->status ) );
			?>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'Availability', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value">
					<span class="lfuf-availability-badge lfuf-availability-badge--<?php echo esc_attr( $row->status ); ?>">
						<?php echo esc_html( $status_text ); ?>
					</span>
					<?php if ( $row->quantity_note ) : ?>
						<span class="lfuf-single-details__note"><?php echo esc_html( $row->quantity_note ); ?></span>
					<?php endif; ?>
				</span>
			</div>
		<?php endif; ?>

		<?php if ( $growing_notes ) : ?>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'Notes', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value"><?php echo esc_html( $growing_notes ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $sources ) ) : ?>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'Sourced from', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value">
					<?php
					foreach ( $sources as $source ) :
						$farm_name = get_post_meta( $source->ID, '_lfuf_source_farm_name', true ) ?: $source->post_title;
						$location  = get_post_meta( $source->ID, '_lfuf_source_location', true );
						?>
						<a href="<?php echo esc_url( get_permalink( $source->ID ) ); ?>">
							<?php echo esc_html( $farm_name ); ?>
						</a>
						<?php if ( $location ) : ?>
							<span class="lfuf-single-details__note">(<?php echo esc_html( $location ); ?>)</span>
						<?php endif; ?>
					<?php endforeach; ?>
				</span>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/* ───────────────────────────────────────────────
 * Source single
 * ─────────────────────────────────────────────── */

function render_source_details( \WP_Post $post ): string {
	$id            = $post->ID;
	$farm_name     = get_post_meta( $id, '_lfuf_source_farm_name', true );
	$location      = get_post_meta( $id, '_lfuf_source_location', true );
	$history       = get_post_meta( $id, '_lfuf_source_history', true );
	$milling_notes = get_post_meta( $id, '_lfuf_milling_notes', true );

	// Find products that use this source.
	$products = get_posts(
		[
			'post_type'   => 'lfuf_product',
			'post_status' => 'publish',
			'numberposts' => 20,
			'meta_query'  => [
				[
					'key'     => '_lfuf_source_ids',
					'value'   => sprintf( ':"%d"', $id ),
					'compare' => 'LIKE',
				],
			],
		]
	);

	ob_start();
	?>
	<div class="lfuf-single-details lfuf-single-details--source">
		<?php if ( $farm_name ) : ?>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'Farm', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value"><?php echo esc_html( $farm_name ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( $location ) : ?>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'Location', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value"><?php echo esc_html( $location ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( $history ) : ?>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'History', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value"><?php echo esc_html( $history ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( $milling_notes ) : ?>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'Milling Notes', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value"><?php echo esc_html( $milling_notes ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $products ) ) : ?>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'Used in', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value lfuf-single-details__links">
					<?php foreach ( $products as $product ) : ?>
						<a href="<?php echo esc_url( get_permalink( $product->ID ) ); ?>">
							<?php echo esc_html( $product->post_title ); ?>
						</a>
					<?php endforeach; ?>
				</span>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/* ───────────────────────────────────────────────
 * Location single
 * ─────────────────────────────────────────────── */

function render_location_details( \WP_Post $post ): string {
	$id              = $post->ID;
	$address         = get_post_meta( $id, '_lfuf_address', true );
	$location_type   = get_post_meta( $id, '_lfuf_location_type', true );
	$hours           = get_post_meta( $id, '_lfuf_hours', true );
	$payment_methods = \Leftfield\Core\Payments\get_payment_methods( $id );
	$is_open         = (bool) get_post_meta( $id, '_lfuf_is_open', true );

	ob_start();
	?>
	<div class="lfuf-single-details lfuf-single-details--location">
		<div class="lfuf-single-details__row">
			<span class="lfuf-single-details__label"><?php esc_html_e( 'Status', 'producerkit' ); ?></span>
			<span class="lfuf-single-details__value">
				<span class="lfuf-location-info__status lfuf-location-info__status--<?php echo $is_open ? 'open' : 'closed'; ?>">
					<?php echo $is_open ? esc_html__( 'Open Now', 'producerkit' ) : esc_html__( 'Closed', 'producerkit' ); ?>
				</span>
			</span>
		</div>

		<?php if ( $location_type ) : ?>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'Type', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value"><?php echo esc_html( ucfirst( $location_type ) ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( $address ) : ?>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'Address', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value"><?php echo esc_html( $address ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( $hours ) : ?>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'Hours', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value"><?php echo esc_html( $hours ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( $payment_methods ) : ?>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'Payment', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value">
					<?php foreach ( $payment_methods as $i => $method ) : ?>
						<?php
						if ( $i > 0 ) {
							echo ' · '; }
						?>
						<?php if ( $method['is_link'] ) : ?>
							<a href="<?php echo esc_url( $method['url'] ); ?>"
								target="_blank" rel="noopener noreferrer">
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
							<?php echo esc_html( $method['label'] ); ?>
						<?php endif; ?>
					<?php endforeach; ?>
				</span>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/* ───────────────────────────────────────────────
 * Event single
 * ─────────────────────────────────────────────── */

function render_event_details( \WP_Post $post ): string {
	$id = $post->ID;

	$start     = get_post_meta( $id, '_lfuf_start_datetime', true );
	$end       = get_post_meta( $id, '_lfuf_end_datetime', true );
	$cost_note = get_post_meta( $id, '_lfuf_em_cost_note', true );
	$bring     = get_post_meta( $id, '_lfuf_em_what_to_bring', true );
	$donation  = get_post_meta( $id, '_lfuf_donation_link', true );
	$cancelled = (bool) get_post_meta( $id, '_lfuf_em_cancelled', true );

	// Location.
	$location_id = (int) get_post_meta( $id, '_lfuf_event_location_id', true );
	$location    = $location_id > 0 ? get_post( $location_id ) : null;

	// Event types.
	$types = get_the_terms( $id, 'lfuf_event_type' );

	// RSVP.
	$rsvp_enabled = (bool) get_post_meta( $id, '_lfuf_em_rsvp_enabled', true );
	$rsvp_summary = null;
	if ( $rsvp_enabled && function_exists( 'Leftfield\\EventManager\\RSVP\\get_event_rsvp_summary' ) ) {
		$rsvp_summary = \Leftfield\EventManager\RSVP\get_event_rsvp_summary( $id );
	}

	$start_ts = $start ? strtotime( $start ) : 0;
	$end_ts   = $end ? strtotime( $end ) : 0;

	ob_start();
	?>
	<div class="lfuf-single-details lfuf-single-details--event">
		<?php if ( $cancelled ) : ?>
			<div class="lfuf-single-details__alert">
				<?php esc_html_e( 'This event has been cancelled.', 'producerkit' ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $start_ts ) : ?>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'Date', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value"><?php echo esc_html( date_i18n( 'l, F j, Y', $start_ts ) ); ?></span>
			</div>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'Time', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value">
					<?php echo esc_html( date_i18n( 'g:i A', $start_ts ) ); ?>
					<?php if ( $end_ts ) : ?>
						– <?php echo esc_html( date_i18n( 'g:i A', $end_ts ) ); ?>
					<?php endif; ?>
				</span>
			</div>
		<?php endif; ?>

		<?php if ( $types && ! is_wp_error( $types ) ) : ?>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'Type', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value">
					<?php echo esc_html( implode( ', ', wp_list_pluck( $types, 'name' ) ) ); ?>
				</span>
			</div>
		<?php endif; ?>

		<?php if ( $location ) : ?>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'Location', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value">
					<a href="<?php echo esc_url( get_permalink( $location->ID ) ); ?>">
						<?php echo esc_html( $location->post_title ); ?>
					</a>
					<?php
					$addr = get_post_meta( $location->ID, '_lfuf_address', true );
					if ( $addr ) :
						?>
						<span class="lfuf-single-details__note">— <?php echo esc_html( $addr ); ?></span>
					<?php endif; ?>
				</span>
			</div>
		<?php endif; ?>

		<?php if ( $cost_note ) : ?>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'Cost', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value"><?php echo esc_html( $cost_note ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( $bring ) : ?>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'What to bring', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value"><?php echo esc_html( $bring ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( $rsvp_summary && $rsvp_summary['enabled'] ) : ?>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'RSVPs', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value">
					<?php
					/* translators: %d: number of people who have RSVPed. */
					printf( esc_html__( '%d people coming', 'producerkit' ), (int) $rsvp_summary['headcount'] );
					?>
					<?php if ( $rsvp_summary['cap'] > 0 ) : ?>
						<?php
						/* translators: %d: total number of RSVP spots. */
						printf( esc_html__( '(%d spots total)', 'producerkit' ), (int) $rsvp_summary['cap'] );
						?>
					<?php endif; ?>
					<?php if ( $rsvp_summary['is_full'] ) : ?>
						— <strong><?php esc_html_e( 'Full', 'producerkit' ); ?></strong>
					<?php endif; ?>
				</span>
			</div>
		<?php endif; ?>

		<?php if ( $donation && ! $cancelled ) : ?>
			<div class="lfuf-single-details__row">
				<span class="lfuf-single-details__label"><?php esc_html_e( 'Payment', 'producerkit' ); ?></span>
				<span class="lfuf-single-details__value">
					<a href="<?php echo esc_url( $donation ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Donate / Pay', 'producerkit' ); ?>
						<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'producerkit' ); ?></span>
					</a>
				</span>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}