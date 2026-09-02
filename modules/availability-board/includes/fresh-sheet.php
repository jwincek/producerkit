<?php
/**
 * Fresh Sheet: a print-ready one-pager of today's availability.
 *
 * Farm name, date, the availability board grouped by product type,
 * plus the chosen location's hours, payment options, and a payment QR
 * code — the sign a farmer prints and tapes to the stand each morning.
 */

declare(strict_types=1);

namespace ProducerKit\AvailabilityBoard\FreshSheet;

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function (): void {
		add_submenu_page(
			'producerkit',
			__( 'Fresh Sheet', 'producerkit' ),
			__( 'Fresh Sheet', 'producerkit' ),
			'edit_posts',
			'producerkit-fresh-sheet',
			__NAMESPACE__ . '\\render_page',
		);
	}
);

function status_label( string $status ): string {
	return match ( $status ) {
		'abundant'    => __( 'Abundant', 'producerkit' ),
		'available'   => __( 'Available', 'producerkit' ),
		'limited'     => __( 'Limited', 'producerkit' ),
		'sold_out'    => __( 'Sold out', 'producerkit' ),
		'unavailable' => __( 'Unavailable', 'producerkit' ),
		default       => $status,
	};
}

function render_page(): void {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	$locations = get_posts(
		[
			'post_type'   => 'pkit_location',
			'post_status' => 'publish',
			'numberposts' => 50,
			'orderby'     => 'title',
			'order'       => 'ASC',
		]
	);

	// Read-only location picker; no state change happens on GET.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$location_id = isset( $_GET['location_id'] ) ? (int) sanitize_text_field( wp_unslash( $_GET['location_id'] ) ) : 0;

	// Not absint(): that would turn a negative id into a real, unrelated post.
	if ( $location_id < 1 ) {
		$location_id = $locations ? (int) $locations[0]->ID : 0;
	}

	// Board data via the same code path the block and ability use.
	$request = new \WP_REST_Request( 'GET', '/producerkit/v1/board' );
	$request->set_param( 'status', 'abundant,available,limited' );
	$request->set_param( 'location', $location_id );
	$board = \ProducerKit\AvailabilityBoard\REST\get_board( $request )->get_data();

	$hours           = $location_id ? (string) get_post_meta( $location_id, '_pkit_hours', true ) : '';
	$address         = $location_id ? (string) get_post_meta( $location_id, '_pkit_address', true ) : '';
	$payment_methods = $location_id ? \ProducerKit\Core\Payments\get_payment_methods( $location_id ) : [];

	$qr_link = null;
	foreach ( $payment_methods as $method ) {
		if ( $method['is_link'] ) {
			$qr_link = $method;
			break;
		}
	}
	if ( $qr_link ) {
		wp_enqueue_script( 'pkit-qr' );
	}
	?>
	<div class="wrap pkit-fresh-sheet-admin">
		<h1>
			<?php esc_html_e( 'Fresh Sheet', 'producerkit' ); ?>
			<button type="button" class="page-title-action pkit-fresh-sheet__print" onclick="window.print()">
				<?php esc_html_e( 'Print', 'producerkit' ); ?>
			</button>
		</h1>

		<?php if ( count( $locations ) > 1 ) : ?>
			<form method="get" class="pkit-fresh-sheet__picker">
				<input type="hidden" name="page" value="producerkit-fresh-sheet">
				<label for="pkit-fs-location"><?php esc_html_e( 'Location:', 'producerkit' ); ?></label>
				<select name="location_id" id="pkit-fs-location" onchange="this.form.submit()">
					<?php foreach ( $locations as $location ) : ?>
						<option value="<?php echo (int) $location->ID; ?>" <?php selected( $location_id, $location->ID ); ?>>
							<?php echo esc_html( $location->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</form>
		<?php endif; ?>

		<div class="pkit-fresh-sheet" id="pkit-fresh-sheet">
			<header class="pkit-fresh-sheet__header">
				<h2 class="pkit-fresh-sheet__site"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h2>
				<p class="pkit-fresh-sheet__date">
					<?php echo esc_html( date_i18n( get_option( 'date_format' ), current_time( 'timestamp' ) ) ); ?>
					<?php if ( $location_id ) : ?>
						— <?php echo esc_html( get_the_title( $location_id ) ); ?>
					<?php endif; ?>
				</p>
				<?php if ( $hours ) : ?>
					<p class="pkit-fresh-sheet__hours"><?php echo esc_html( $hours ); ?></p>
				<?php endif; ?>
				<?php if ( $address ) : ?>
					<p class="pkit-fresh-sheet__address"><?php echo esc_html( $address ); ?></p>
				<?php endif; ?>
			</header>

			<?php if ( empty( $board['groups'] ) ) : ?>
				<p><?php esc_html_e( 'Nothing is marked available right now — update availability first (Farm Stand → Availability).', 'producerkit' ); ?></p>
			<?php endif; ?>

			<?php foreach ( $board['groups'] ?? [] as $group ) : ?>
				<section class="pkit-fresh-sheet__group">
					<h3><?php echo esc_html( $group['label'] ); ?></h3>
					<table>
						<tbody>
							<?php foreach ( $group['items'] as $item ) : ?>
								<tr>
									<td class="pkit-fresh-sheet__product"><?php echo esc_html( $item['product_name'] ); ?></td>
									<td class="pkit-fresh-sheet__price">
										<?php echo esc_html( $item['price'] ); ?><?php echo $item['unit'] ? esc_html( ' / ' . $item['unit'] ) : ''; ?>
									</td>
									<td class="pkit-fresh-sheet__status pkit-fresh-sheet__status--<?php echo esc_attr( $item['status'] ); ?>">
										<?php
										echo esc_html( status_label( $item['status'] ) );
										if ( $item['quantity_note'] ) {
											echo esc_html( ' — ' . $item['quantity_note'] );
										}
										?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</section>
			<?php endforeach; ?>

			<?php if ( $payment_methods ) : ?>
				<footer class="pkit-fresh-sheet__footer">
					<div class="pkit-fresh-sheet__payments">
						<h3><?php esc_html_e( 'We accept', 'producerkit' ); ?></h3>
						<p>
							<?php echo esc_html( implode( ' · ', array_column( $payment_methods, 'label' ) ) ); ?>
						</p>
					</div>
					<?php if ( $qr_link ) : ?>
						<div class="pkit-fresh-sheet__qr">
							<div
								class="pkit-fresh-sheet__qr-code"
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
							<p class="pkit-fresh-sheet__qr-caption">
								<?php
								printf(
									/* translators: %s: payment method label. */
									esc_html__( 'Scan to pay with %s', 'producerkit' ),
									esc_html( $qr_link['label'] ),
								);
								?>
							</p>
						</div>
					<?php endif; ?>
				</footer>
			<?php endif; ?>
		</div>
	</div>

	<style>
	.pkit-fresh-sheet {
		background: #fff;
		border: 1px solid #e5e7eb;
		border-radius: 8px;
		max-width: 46rem;
		margin-top: 1rem;
		padding: 2rem 2.5rem;
	}
	.pkit-fresh-sheet__header { text-align: center; margin-bottom: 1.5rem; }
	.pkit-fresh-sheet__site { font-size: 1.75rem; margin: 0 0 0.25rem; }
	.pkit-fresh-sheet__date { font-size: 1.05rem; margin: 0 0 0.25rem; }
	.pkit-fresh-sheet__hours,
	.pkit-fresh-sheet__address { color: #4b5563; margin: 0; }
	.pkit-fresh-sheet__group h3 {
		border-bottom: 2px solid #111827;
		padding-bottom: 0.25rem;
		margin: 1.25rem 0 0.25rem;
	}
	.pkit-fresh-sheet__group table { width: 100%; border-collapse: collapse; }
	.pkit-fresh-sheet__group td { padding: 0.3rem 0.25rem; border-bottom: 1px solid #f3f4f6; }
	.pkit-fresh-sheet__product { font-weight: 600; }
	.pkit-fresh-sheet__price { text-align: right; white-space: nowrap; }
	.pkit-fresh-sheet__status { text-align: right; color: #4b5563; white-space: nowrap; }
	.pkit-fresh-sheet__status--limited { color: #92400e; }
	.pkit-fresh-sheet__footer {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 1.5rem;
		margin-top: 1.75rem;
		border-top: 2px solid #111827;
		padding-top: 1rem;
	}
	.pkit-fresh-sheet__footer h3 { margin: 0 0 0.25rem; }
	.pkit-fresh-sheet__qr { text-align: center; }
	.pkit-fresh-sheet__qr-code { width: 8rem; }
	.pkit-fresh-sheet__qr-code svg { display: block; width: 100%; height: auto; }
	.pkit-fresh-sheet__qr-caption { font-size: 0.8125rem; color: #4b5563; margin: 0.25rem 0 0; }

	@media print {
		#adminmenumain, #wpadminbar, #wpfooter, .notice,
		.pkit-fresh-sheet-admin > h1, .pkit-fresh-sheet__picker { display: none !important; }
		#wpcontent, #wpbody-content { margin: 0 !important; padding: 0 !important; }
		.pkit-fresh-sheet { border: none; border-radius: 0; max-width: none; padding: 0.5in; }
		.pkit-fresh-sheet__qr-code { width: 11rem; }
	}
	</style>
	<?php
}
