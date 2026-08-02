<?php
/**
 * Fresh Sheet: a print-ready one-pager of today's availability.
 *
 * Farm name, date, the availability board grouped by product type,
 * plus the chosen location's hours, payment options, and a payment QR
 * code — the sign a farmer prints and tapes to the stand each morning.
 */

declare(strict_types=1);

namespace Leftfield\AvailabilityBoard\FreshSheet;

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function (): void {
		add_submenu_page(
			'farm-stand-dashboard',
			__( 'Fresh Sheet', 'farm-stand-manager' ),
			__( 'Fresh Sheet', 'farm-stand-manager' ),
			'edit_posts',
			'farm-stand-fresh-sheet',
			__NAMESPACE__ . '\\render_page',
		);
	}
);

function status_label( string $status ): string {
	return match ( $status ) {
		'abundant'    => __( 'Abundant', 'farm-stand-manager' ),
		'available'   => __( 'Available', 'farm-stand-manager' ),
		'limited'     => __( 'Limited', 'farm-stand-manager' ),
		'sold_out'    => __( 'Sold out', 'farm-stand-manager' ),
		'unavailable' => __( 'Unavailable', 'farm-stand-manager' ),
		default       => $status,
	};
}

function render_page(): void {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	$locations = get_posts(
		[
			'post_type'   => 'lfuf_location',
			'post_status' => 'publish',
			'numberposts' => 50,
			'orderby'     => 'title',
			'order'       => 'ASC',
		]
	);

	// Read-only location picker; no state change happens on GET.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$location_id = (int) ( $_GET['location_id'] ?? 0 );
	if ( ! $location_id && $locations ) {
		$location_id = $locations[0]->ID;
	}

	// Board data via the same code path the block and ability use.
	$request = new \WP_REST_Request( 'GET', '/lfuf/v1/board' );
	$request->set_param( 'status', 'abundant,available,limited' );
	$request->set_param( 'location', $location_id );
	$board = \Leftfield\AvailabilityBoard\REST\get_board( $request )->get_data();

	$hours           = $location_id ? (string) get_post_meta( $location_id, '_lfuf_hours', true ) : '';
	$address         = $location_id ? (string) get_post_meta( $location_id, '_lfuf_address', true ) : '';
	$payment_methods = $location_id ? \Leftfield\Core\Payments\get_payment_methods( $location_id ) : [];

	$qr_link = null;
	foreach ( $payment_methods as $method ) {
		if ( $method['is_link'] ) {
			$qr_link = $method;
			break;
		}
	}
	if ( $qr_link ) {
		wp_enqueue_script( 'lfuf-qr' );
	}
	?>
	<div class="wrap lfuf-fresh-sheet-admin">
		<h1>
			<?php esc_html_e( 'Fresh Sheet', 'farm-stand-manager' ); ?>
			<button type="button" class="page-title-action lfuf-fresh-sheet__print" onclick="window.print()">
				<?php esc_html_e( 'Print', 'farm-stand-manager' ); ?>
			</button>
		</h1>

		<?php if ( count( $locations ) > 1 ) : ?>
			<form method="get" class="lfuf-fresh-sheet__picker">
				<input type="hidden" name="page" value="farm-stand-fresh-sheet">
				<label for="lfuf-fs-location"><?php esc_html_e( 'Location:', 'farm-stand-manager' ); ?></label>
				<select name="location_id" id="lfuf-fs-location" onchange="this.form.submit()">
					<?php foreach ( $locations as $location ) : ?>
						<option value="<?php echo (int) $location->ID; ?>" <?php selected( $location_id, $location->ID ); ?>>
							<?php echo esc_html( $location->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</form>
		<?php endif; ?>

		<div class="lfuf-fresh-sheet" id="lfuf-fresh-sheet">
			<header class="lfuf-fresh-sheet__header">
				<h2 class="lfuf-fresh-sheet__site"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h2>
				<p class="lfuf-fresh-sheet__date">
					<?php echo esc_html( date_i18n( get_option( 'date_format' ), current_time( 'timestamp' ) ) ); ?>
					<?php if ( $location_id ) : ?>
						— <?php echo esc_html( get_the_title( $location_id ) ); ?>
					<?php endif; ?>
				</p>
				<?php if ( $hours ) : ?>
					<p class="lfuf-fresh-sheet__hours"><?php echo esc_html( $hours ); ?></p>
				<?php endif; ?>
				<?php if ( $address ) : ?>
					<p class="lfuf-fresh-sheet__address"><?php echo esc_html( $address ); ?></p>
				<?php endif; ?>
			</header>

			<?php if ( empty( $board['groups'] ) ) : ?>
				<p><?php esc_html_e( 'Nothing is marked available right now — update availability first (Farm Stand → Availability).', 'farm-stand-manager' ); ?></p>
			<?php endif; ?>

			<?php foreach ( $board['groups'] ?? [] as $group ) : ?>
				<section class="lfuf-fresh-sheet__group">
					<h3><?php echo esc_html( $group['label'] ); ?></h3>
					<table>
						<tbody>
							<?php foreach ( $group['items'] as $item ) : ?>
								<tr>
									<td class="lfuf-fresh-sheet__product"><?php echo esc_html( $item['product_name'] ); ?></td>
									<td class="lfuf-fresh-sheet__price">
										<?php echo esc_html( $item['price'] ); ?><?php echo $item['unit'] ? esc_html( ' / ' . $item['unit'] ) : ''; ?>
									</td>
									<td class="lfuf-fresh-sheet__status lfuf-fresh-sheet__status--<?php echo esc_attr( $item['status'] ); ?>">
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
				<footer class="lfuf-fresh-sheet__footer">
					<div class="lfuf-fresh-sheet__payments">
						<h3><?php esc_html_e( 'We accept', 'farm-stand-manager' ); ?></h3>
						<p>
							<?php echo esc_html( implode( ' · ', array_column( $payment_methods, 'label' ) ) ); ?>
						</p>
					</div>
					<?php if ( $qr_link ) : ?>
						<div class="lfuf-fresh-sheet__qr">
							<div
								class="lfuf-fresh-sheet__qr-code"
								data-lfuf-qr="<?php echo esc_attr( $qr_link['url'] ); ?>"
								data-lfuf-qr-label="
								<?php
									printf(
										/* translators: %s: payment method label. */
										esc_attr__( 'QR code: pay with %s', 'farm-stand-manager' ),
										esc_attr( $qr_link['label'] ),
									);
								?>
								"
							></div>
							<p class="lfuf-fresh-sheet__qr-caption">
								<?php
								printf(
									/* translators: %s: payment method label. */
									esc_html__( 'Scan to pay with %s', 'farm-stand-manager' ),
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
	.lfuf-fresh-sheet {
		background: #fff;
		border: 1px solid #e5e7eb;
		border-radius: 8px;
		max-width: 46rem;
		margin-top: 1rem;
		padding: 2rem 2.5rem;
	}
	.lfuf-fresh-sheet__header { text-align: center; margin-bottom: 1.5rem; }
	.lfuf-fresh-sheet__site { font-size: 1.75rem; margin: 0 0 0.25rem; }
	.lfuf-fresh-sheet__date { font-size: 1.05rem; margin: 0 0 0.25rem; }
	.lfuf-fresh-sheet__hours,
	.lfuf-fresh-sheet__address { color: #4b5563; margin: 0; }
	.lfuf-fresh-sheet__group h3 {
		border-bottom: 2px solid #111827;
		padding-bottom: 0.25rem;
		margin: 1.25rem 0 0.25rem;
	}
	.lfuf-fresh-sheet__group table { width: 100%; border-collapse: collapse; }
	.lfuf-fresh-sheet__group td { padding: 0.3rem 0.25rem; border-bottom: 1px solid #f3f4f6; }
	.lfuf-fresh-sheet__product { font-weight: 600; }
	.lfuf-fresh-sheet__price { text-align: right; white-space: nowrap; }
	.lfuf-fresh-sheet__status { text-align: right; color: #4b5563; white-space: nowrap; }
	.lfuf-fresh-sheet__status--limited { color: #92400e; }
	.lfuf-fresh-sheet__footer {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 1.5rem;
		margin-top: 1.75rem;
		border-top: 2px solid #111827;
		padding-top: 1rem;
	}
	.lfuf-fresh-sheet__footer h3 { margin: 0 0 0.25rem; }
	.lfuf-fresh-sheet__qr { text-align: center; }
	.lfuf-fresh-sheet__qr-code { width: 8rem; }
	.lfuf-fresh-sheet__qr-code svg { display: block; width: 100%; height: auto; }
	.lfuf-fresh-sheet__qr-caption { font-size: 0.8125rem; color: #4b5563; margin: 0.25rem 0 0; }

	@media print {
		#adminmenumain, #wpadminbar, #wpfooter, .notice,
		.lfuf-fresh-sheet-admin > h1, .lfuf-fresh-sheet__picker { display: none !important; }
		#wpcontent, #wpbody-content { margin: 0 !important; padding: 0 !important; }
		.lfuf-fresh-sheet { border: none; border-radius: 0; max-width: none; padding: 0.5in; }
		.lfuf-fresh-sheet__qr-code { width: 11rem; }
	}
	</style>
	<?php
}
