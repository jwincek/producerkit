<?php
/**
 * Server-side render for lfuf/preorder-form.
 *
 * Lists orderable products with quantity steppers, contact fields, and a
 * pickup date, submitting to POST /lfuf/v1/preorders via the Interactivity
 * API view module. Payment stays at pickup — the confirmation panel shows
 * the pickup location's accepted payment methods.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// The block is registered even when the module is toggled off; bail quietly.
if ( ! function_exists( 'Leftfield\\PreOrder\\Orders\\create_order' ) ) {
	return;
}

$location_id    = (int) ( $attributes['locationId'] ?? 0 );
$only_available = (bool) ( $attributes['onlyAvailable'] ?? true );

// ── Build the orderable product list. ──
$products = get_posts(
	[
		'post_type'   => 'lfuf_product',
		'post_status' => 'publish',
		'numberposts' => 100,
		'orderby'     => 'title',
		'order'       => 'ASC',
	]
);

// Current availability status per product (0 = any location).
$status_by_product = [];
foreach ( \Leftfield\Core\Availability\get_all_current() as $row ) {
	$pid = (int) $row->product_id;
	if ( ! isset( $status_by_product[ $pid ] ) ) {
		$status_by_product[ $pid ] = (string) $row->status;
	}
}

$orderable = [];
foreach ( $products as $product ) {
	$status = $status_by_product[ $product->ID ] ?? '';
	if ( $only_available && in_array( $status, [ 'sold_out', 'unavailable' ], true ) ) {
		continue;
	}
	$orderable[] = [
		'id'     => $product->ID,
		'title'  => $product->post_title,
		'price'  => (string) get_post_meta( $product->ID, '_lfuf_price', true ),
		'unit'   => (string) get_post_meta( $product->ID, '_lfuf_unit', true ),
		'status' => $status,
	];
}

if ( ! $orderable ) {
	return;
}

$payment_methods = $location_id
	? \Leftfield\Core\Payments\get_payment_methods( $location_id )
	: [];

$today    = current_time( 'Y-m-d' );
$max_date = gmdate( 'Y-m-d', strtotime( $today . ' +' . \Leftfield\PreOrder\Orders\MAX_PICKUP_DAYS . ' days' ) );
$form_id  = wp_unique_id( 'lfuf-preorder-' );

// Pickup constraints for the chosen location (open days, season, blackouts).
$constraints = \Leftfield\PreOrder\Orders\pickup_constraints( $location_id );
$days_label  = $constraints['allowed_days'] !== null
	? implode( ', ', \Leftfield\PreOrder\Orders\weekday_names( $constraints['allowed_days'] ) )
	: '';

$context = [
	'restBase'     => esc_url_raw( rest_url( 'lfuf/v1' ) ),
	'locationId'   => $location_id,
	'items'        => (object) [],
	'name'         => '',
	'email'        => '',
	'phone'        => '',
	'pickupDate'   => '',
	'allowedDays'  => $constraints['allowed_days'],
	'allowedLabel' => $days_label,
	'blackouts'    => $constraints['blackouts'],
	'note'         => '',
	'_hp'          => '',
	'submitting'   => false,
	'submitted'    => false,
	'error'        => '',
	'token'        => '',
];

$wrapper_attrs = get_block_wrapper_attributes(
	[
		'class' => 'lfuf-preorder-form',
	]
);
?>

<section
	<?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes(). ?>
	aria-label="<?php esc_attr_e( 'Pre-order form', 'producerkit' ); ?>"
	data-wp-interactive="leftfield/preorder-form"
	<?php echo wp_interactivity_data_wp_context( $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- returns a pre-escaped data-wp-context attribute. ?>
>
	<form data-wp-bind--hidden="context.submitted" data-wp-on--submit="actions.submit">

		<fieldset class="lfuf-preorder-form__products">
			<legend><?php esc_html_e( 'What would you like to reserve?', 'producerkit' ); ?></legend>

			<?php foreach ( $orderable as $product ) : ?>
				<div class="lfuf-preorder-form__product">
					<label class="lfuf-preorder-form__product-label" for="<?php echo esc_attr( $form_id . '-p' . $product['id'] ); ?>">
						<span class="lfuf-preorder-form__product-title"><?php echo esc_html( $product['title'] ); ?></span>
						<?php if ( $product['price'] ) : ?>
							<span class="lfuf-preorder-form__product-price">
								<?php echo esc_html( $product['price'] ); ?><?php echo $product['unit'] ? esc_html( ' / ' . $product['unit'] ) : ''; ?>
							</span>
						<?php endif; ?>
						<?php if ( $product['status'] === 'limited' ) : ?>
							<span class="lfuf-preorder-form__product-status"><?php esc_html_e( 'Limited', 'producerkit' ); ?></span>
						<?php endif; ?>
					</label>
					<input
						type="number"
						id="<?php echo esc_attr( $form_id . '-p' . $product['id'] ); ?>"
						class="lfuf-preorder-form__qty"
						min="0"
						max="99"
						step="1"
						placeholder="0"
						data-product-id="<?php echo (int) $product['id']; ?>"
						data-wp-on--input="actions.updateQty"
					>
				</div>
			<?php endforeach; ?>
		</fieldset>

		<div class="lfuf-preorder-form__fields">
			<p>
				<label for="<?php echo esc_attr( $form_id ); ?>-name"><?php esc_html_e( 'Name', 'producerkit' ); ?> <span aria-hidden="true">*</span></label>
				<input type="text" id="<?php echo esc_attr( $form_id ); ?>-name" required
						data-field="name" data-wp-on--input="actions.updateField">
			</p>
			<p>
				<label for="<?php echo esc_attr( $form_id ); ?>-email"><?php esc_html_e( 'Email (for confirmation)', 'producerkit' ); ?></label>
				<input type="email" id="<?php echo esc_attr( $form_id ); ?>-email"
						data-field="email" data-wp-on--input="actions.updateField">
			</p>
			<p>
				<label for="<?php echo esc_attr( $form_id ); ?>-phone"><?php esc_html_e( 'Phone', 'producerkit' ); ?></label>
				<input type="tel" id="<?php echo esc_attr( $form_id ); ?>-phone"
						data-field="phone" data-wp-on--input="actions.updateField">
			</p>
			<p>
				<label for="<?php echo esc_attr( $form_id ); ?>-date"><?php esc_html_e( 'Pickup date', 'producerkit' ); ?> <span aria-hidden="true">*</span></label>
				<input type="date" id="<?php echo esc_attr( $form_id ); ?>-date" required
						min="<?php echo esc_attr( $today ); ?>" max="<?php echo esc_attr( $max_date ); ?>"
						data-field="pickupDate" data-wp-on--input="actions.updateField">
				<?php if ( $days_label ) : ?>
					<span class="lfuf-preorder-form__date-hint">
						<?php
						printf(
							/* translators: %s: comma-separated list of weekday names. */
							esc_html__( 'Pickup days: %s.', 'producerkit' ),
							esc_html( $days_label ),
						);
						?>
					</span>
				<?php endif; ?>
			</p>
			<p>
				<label for="<?php echo esc_attr( $form_id ); ?>-note"><?php esc_html_e( 'Note (optional)', 'producerkit' ); ?></label>
				<textarea id="<?php echo esc_attr( $form_id ); ?>-note" rows="2" maxlength="500"
							data-field="note" data-wp-on--input="actions.updateField"></textarea>
			</p>
			<p class="lfuf-preorder-form__hp" aria-hidden="true">
				<label for="<?php echo esc_attr( $form_id ); ?>-website"><?php esc_html_e( 'Website', 'producerkit' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $form_id ); ?>-website" tabindex="-1" autocomplete="off"
						data-field="_hp" data-wp-on--input="actions.updateField">
			</p>
		</div>

		<p class="lfuf-preorder-form__error" role="alert" data-wp-bind--hidden="!context.error" data-wp-text="context.error" hidden></p>

		<button type="submit" class="lfuf-preorder-form__submit wp-element-button" data-wp-bind--disabled="context.submitting">
			<span data-wp-bind--hidden="context.submitting"><?php esc_html_e( 'Place Pre-Order', 'producerkit' ); ?></span>
			<span data-wp-bind--hidden="!context.submitting" hidden><?php esc_html_e( 'Sending…', 'producerkit' ); ?></span>
		</button>

		<p class="lfuf-preorder-form__disclaimer">
			<?php esc_html_e( 'No payment now — pay when you pick up.', 'producerkit' ); ?>
		</p>
	</form>

	<div class="lfuf-preorder-form__success" role="status" data-wp-bind--hidden="!context.submitted" hidden>
		<h3><?php esc_html_e( 'Pre-order received!', 'producerkit' ); ?></h3>
		<p>
			<?php esc_html_e( 'We\'ll have it ready on your pickup date. Keep this cancellation code in case your plans change:', 'producerkit' ); ?>
			<code data-wp-text="context.token"></code>
		</p>

		<?php if ( $payment_methods ) : ?>
			<p class="lfuf-preorder-form__pay-note">
				<?php esc_html_e( 'Payment is at pickup. We accept:', 'producerkit' ); ?>
				<?php foreach ( $payment_methods as $i => $method ) : ?>
					<?php
					if ( $i > 0 ) {
						echo ' · '; }
					?>
					<?php if ( $method['is_link'] ) : ?>
						<a href="<?php echo esc_url( $method['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $method['label'] ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $method['label'] ); ?>
					<?php endif; ?>
				<?php endforeach; ?>
			</p>
		<?php else : ?>
			<p class="lfuf-preorder-form__pay-note"><?php esc_html_e( 'Payment is at pickup.', 'producerkit' ); ?></p>
		<?php endif; ?>
	</div>
</section>
