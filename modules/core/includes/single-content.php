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

namespace ProducerKit\Core\SingleContent;

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
		'pkit_product'  => render_product_details( $post ),
		'pkit_source'   => render_source_details( $post ),
		'pkit_location' => render_location_details( $post ),
		'pkit_event'    => render_event_details( $post ),
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
	$price         = get_post_meta( $id, '_pkit_price', true );
	$unit          = get_post_meta( $id, '_pkit_unit', true );
	$growing_notes = get_post_meta( $id, '_pkit_growing_notes', true );
	$types         = get_the_terms( $id, 'pkit_product_type' );
	$seasons       = get_the_terms( $id, 'pkit_season' );
	$detail_terms  = \ProducerKit\Core\Taxonomies\detail_terms( $id );

	// Availability.
	$availability = \ProducerKit\Core\Availability\get_current( $id );

	// Sources.
	$source_ids = get_post_meta( $id, '_pkit_source_ids', true );
	$sources    = [];
	if ( is_array( $source_ids ) && ! empty( $source_ids ) ) {
		$sources = get_posts(
			[
				'post_type'   => 'pkit_source',
				'post__in'    => $source_ids,
				'numberposts' => 10,
				'post_status' => 'publish',
			]
		);
	}

	ob_start();
	?>
	<div class="pkit-single-details pkit-single-details--product">
		<?php if ( $price || $unit ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php esc_html_e( 'Price', 'producerkit' ); ?></span>
				<span class="pkit-single-details__value">
					<?php echo esc_html( $price ); ?>
					<?php if ( $unit ) : ?>
						<span class="pkit-single-details__unit">/ <?php echo esc_html( $unit ); ?></span>
					<?php endif; ?>
				</span>
			</div>
		<?php endif; ?>

		<?php if ( $types && ! is_wp_error( $types ) ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php esc_html_e( 'Type', 'producerkit' ); ?></span>
				<span class="pkit-single-details__value">
					<?php echo esc_html( implode( ', ', wp_list_pluck( $types, 'name' ) ) ); ?>
				</span>
			</div>
		<?php endif; ?>

		<?php
		// Which shops currently have this — the question a customer asks
		// standing in town wanting a jar today, which a combined board cannot
		// answer.
		$stocked_by = \ProducerKit\Core\Availability\get_locations_for_product( $id );
		?>
		<?php if ( $stocked_by ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php esc_html_e( 'Where to find it', 'producerkit' ); ?></span>
				<span class="pkit-single-details__value">
					<?php
					$stock_links = [];
					foreach ( $stocked_by as $stock_row ) {
						$stock_loc = get_post( (int) $stock_row->location_id );
						if ( ! $stock_loc || 'publish' !== $stock_loc->post_status ) {
							continue;
						}

						$stock_label = esc_html( get_the_title( $stock_loc ) );
						if ( ! empty( $stock_row->quantity_note ) ) {
							$stock_label .= ' <span class="pkit-stocked-item__note">(' . esc_html( (string) $stock_row->quantity_note ) . ')</span>';
						}

						$stock_links[] = '<a href="' . esc_url( (string) get_permalink( $stock_loc ) ) . '">' . $stock_label . '</a>';
					}

					echo wp_kses_post( implode( ', ', $stock_links ) );
					?>
				</span>
			</div>
		<?php endif; ?>

		<?php if ( $seasons && ! is_wp_error( $seasons ) ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php esc_html_e( 'Season', 'producerkit' ); ?></span>
				<span class="pkit-single-details__value">
					<?php echo esc_html( implode( ', ', wp_list_pluck( $seasons, 'name' ) ) ); ?>
				</span>
			</div>
		<?php endif; ?>

		<?php foreach ( $detail_terms as $detail_label => $detail_values ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php echo esc_html( $detail_label ); ?></span>
				<span class="pkit-single-details__value">
					<?php echo esc_html( implode( ', ', $detail_values ) ); ?>
				</span>
			</div>
		<?php endforeach; ?>

		<?php
		if ( ! empty( $availability ) ) :
			$row         = $availability[0];
			$status_text = ucfirst( str_replace( '_', ' ', $row->status ) );
			?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php esc_html_e( 'Availability', 'producerkit' ); ?></span>
				<span class="pkit-single-details__value">
					<span class="pkit-availability-badge pkit-availability-badge--<?php echo esc_attr( $row->status ); ?>">
						<?php echo esc_html( $status_text ); ?>
					</span>
					<?php if ( $row->quantity_note ) : ?>
						<span class="pkit-single-details__note"><?php echo esc_html( $row->quantity_note ); ?></span>
					<?php endif; ?>
				</span>
			</div>
		<?php endif; ?>

		<?php if ( $growing_notes ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php echo esc_html( \ProducerKit\Core\MetaLabels\label( '_pkit_growing_notes' ) ); ?></span>
				<span class="pkit-single-details__value"><?php echo esc_html( $growing_notes ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $sources ) ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php esc_html_e( 'Sourced from', 'producerkit' ); ?></span>
				<span class="pkit-single-details__value">
					<?php
					foreach ( $sources as $source ) :
						$farm_name = get_post_meta( $source->ID, '_pkit_source_farm_name', true ) ?: $source->post_title;
						$location  = get_post_meta( $source->ID, '_pkit_source_location', true );
						?>
						<a href="<?php echo esc_url( get_permalink( $source->ID ) ); ?>">
							<?php echo esc_html( $farm_name ); ?>
						</a>
						<?php if ( $location ) : ?>
							<span class="pkit-single-details__note">(<?php echo esc_html( $location ); ?>)</span>
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
	$farm_name     = get_post_meta( $id, '_pkit_source_farm_name', true );
	$location      = get_post_meta( $id, '_pkit_source_location', true );
	$history       = get_post_meta( $id, '_pkit_source_history', true );
	$milling_notes = get_post_meta( $id, '_pkit_milling_notes', true );

	// Find products that use this source.
	$products = get_posts(
		[
			'post_type'   => 'pkit_product',
			'post_status' => 'publish',
			'numberposts' => 20,
			'meta_query'  => [
				[
					'key'     => '_pkit_source_ids',
					'value'   => sprintf( ':"%d"', $id ),
					'compare' => 'LIKE',
				],
			],
		]
	);

	ob_start();
	?>
	<div class="pkit-single-details pkit-single-details--source">
		<?php if ( $farm_name ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php echo esc_html( \ProducerKit\Core\MetaLabels\label( '_pkit_source_farm_name' ) ); ?></span>
				<span class="pkit-single-details__value"><?php echo esc_html( $farm_name ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( $location ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php echo esc_html( \ProducerKit\Core\MetaLabels\label( '_pkit_source_location' ) ); ?></span>
				<span class="pkit-single-details__value"><?php echo esc_html( $location ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( $history ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php echo esc_html( \ProducerKit\Core\MetaLabels\label( '_pkit_source_history' ) ); ?></span>
				<span class="pkit-single-details__value"><?php echo esc_html( $history ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( $milling_notes ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php echo esc_html( \ProducerKit\Core\MetaLabels\label( '_pkit_milling_notes' ) ); ?></span>
				<span class="pkit-single-details__value"><?php echo esc_html( $milling_notes ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $products ) ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php esc_html_e( 'Used in', 'producerkit' ); ?></span>
				<span class="pkit-single-details__value pkit-single-details__links">
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
	$address         = get_post_meta( $id, '_pkit_address', true );
	$location_type   = get_post_meta( $id, '_pkit_location_type', true );
	$hours           = get_post_meta( $id, '_pkit_hours', true );
	$payment_methods = \ProducerKit\Core\Payments\get_payment_methods( $id );
	$is_open         = (bool) get_post_meta( $id, '_pkit_is_open', true );

	ob_start();
	?>
	<div class="pkit-single-details pkit-single-details--location">
		<div class="pkit-single-details__row">
			<span class="pkit-single-details__label"><?php esc_html_e( 'Status', 'producerkit' ); ?></span>
			<span class="pkit-single-details__value">
				<span class="pkit-location-info__status pkit-location-info__status--<?php echo $is_open ? 'open' : 'closed'; ?>">
					<?php echo $is_open ? esc_html__( 'Open Now', 'producerkit' ) : esc_html__( 'Closed', 'producerkit' ); ?>
				</span>
			</span>
		</div>

		<?php if ( $location_type ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php esc_html_e( 'Type', 'producerkit' ); ?></span>
				<span class="pkit-single-details__value"><?php echo esc_html( ucfirst( $location_type ) ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( $address ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php esc_html_e( 'Address', 'producerkit' ); ?></span>
				<span class="pkit-single-details__value"><?php echo esc_html( $address ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( $hours ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php esc_html_e( 'Hours', 'producerkit' ); ?></span>
				<span class="pkit-single-details__value"><?php echo esc_html( $hours ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( $payment_methods ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php esc_html_e( 'Payment', 'producerkit' ); ?></span>
				<span class="pkit-single-details__value">
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

		<?php
		// What is on the shelf here. A shop's page is where someone checks
		// before driving over, so the answer belongs on it rather than only in
		// a combined board that lists every location at once.
		$here = \ProducerKit\Core\Availability\get_for_location( $id );
		?>
		<?php if ( $here ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php esc_html_e( 'Available here', 'producerkit' ); ?></span>
				<span class="pkit-single-details__value pkit-single-details__value--stack">
					<?php foreach ( $here as $here_row ) : ?>
						<span class="pkit-stocked-item">
							<a href="<?php echo esc_url( (string) get_permalink( (int) $here_row->product_post_id ) ); ?>">
								<?php echo esc_html( (string) $here_row->product_name ); ?>
							</a>
							<?php if ( ! empty( $here_row->quantity_note ) ) : ?>
								<span class="pkit-stocked-item__note"><?php echo esc_html( (string) $here_row->quantity_note ); ?></span>
							<?php endif; ?>
							<?php if ( 'limited' === $here_row->status ) : ?>
								<span class="pkit-stocked-item__flag"><?php esc_html_e( 'Running low', 'producerkit' ); ?></span>
							<?php endif; ?>
						</span>
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

	$start     = get_post_meta( $id, '_pkit_start_datetime', true );
	$end       = get_post_meta( $id, '_pkit_end_datetime', true );
	$cost_note = get_post_meta( $id, '_pkit_em_cost_note', true );
	$bring     = get_post_meta( $id, '_pkit_em_what_to_bring', true );
	$donation  = get_post_meta( $id, '_pkit_donation_link', true );
	$cancelled = (bool) get_post_meta( $id, '_pkit_em_cancelled', true );

	// Location.
	$location_id = (int) get_post_meta( $id, '_pkit_event_location_id', true );
	$location    = $location_id > 0 ? get_post( $location_id ) : null;

	// Event types.
	$types = get_the_terms( $id, 'pkit_event_type' );

	// RSVP.
	$rsvp_enabled = (bool) get_post_meta( $id, '_pkit_em_rsvp_enabled', true );
	$rsvp_summary = null;
	if ( $rsvp_enabled && function_exists( 'ProducerKit\\EventManager\\RSVP\\get_event_rsvp_summary' ) ) {
		$rsvp_summary = \ProducerKit\EventManager\RSVP\get_event_rsvp_summary( $id );
	}

	$start_ts = $start ? strtotime( $start ) : 0;
	$end_ts   = $end ? strtotime( $end ) : 0;

	ob_start();
	?>
	<div class="pkit-single-details pkit-single-details--event">
		<?php if ( $cancelled ) : ?>
			<div class="pkit-single-details__alert">
				<?php esc_html_e( 'This event has been cancelled.', 'producerkit' ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $start_ts ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php esc_html_e( 'Date', 'producerkit' ); ?></span>
				<span class="pkit-single-details__value"><?php echo esc_html( date_i18n( 'l, F j, Y', $start_ts ) ); ?></span>
			</div>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php esc_html_e( 'Time', 'producerkit' ); ?></span>
				<span class="pkit-single-details__value">
					<?php echo esc_html( date_i18n( 'g:i A', $start_ts ) ); ?>
					<?php if ( $end_ts ) : ?>
						– <?php echo esc_html( date_i18n( 'g:i A', $end_ts ) ); ?>
					<?php endif; ?>
				</span>
			</div>
		<?php endif; ?>

		<?php if ( $types && ! is_wp_error( $types ) ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php esc_html_e( 'Type', 'producerkit' ); ?></span>
				<span class="pkit-single-details__value">
					<?php echo esc_html( implode( ', ', wp_list_pluck( $types, 'name' ) ) ); ?>
				</span>
			</div>
		<?php endif; ?>

		<?php if ( $location ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php esc_html_e( 'Location', 'producerkit' ); ?></span>
				<span class="pkit-single-details__value">
					<a href="<?php echo esc_url( get_permalink( $location->ID ) ); ?>">
						<?php echo esc_html( $location->post_title ); ?>
					</a>
					<?php
					$addr = get_post_meta( $location->ID, '_pkit_address', true );
					if ( $addr ) :
						?>
						<span class="pkit-single-details__note">— <?php echo esc_html( $addr ); ?></span>
					<?php endif; ?>
				</span>
			</div>
		<?php endif; ?>

		<?php if ( $cost_note ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php esc_html_e( 'Cost', 'producerkit' ); ?></span>
				<span class="pkit-single-details__value"><?php echo esc_html( $cost_note ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( $bring ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php esc_html_e( 'What to bring', 'producerkit' ); ?></span>
				<span class="pkit-single-details__value"><?php echo esc_html( $bring ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( $rsvp_summary && $rsvp_summary['enabled'] ) : ?>
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php esc_html_e( 'RSVPs', 'producerkit' ); ?></span>
				<span class="pkit-single-details__value">
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
			<div class="pkit-single-details__row">
				<span class="pkit-single-details__label"><?php esc_html_e( 'Payment', 'producerkit' ); ?></span>
				<span class="pkit-single-details__value">
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