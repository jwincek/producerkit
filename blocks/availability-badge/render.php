<?php
/**
 * Server-side render for producerkit/availability-badge.
 *
 * Accessibility: screen-reader label "Availability:" before the badge,
 * role="status" on the wrapper for context.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$product_id  = (int) ( $attributes['productId'] ?? 0 );
$location_id = (int) ( $attributes['locationId'] ?? 0 );

if ( $product_id < 1 ) {
	return;
}

$rows = \ProducerKit\Core\Availability\get_current( $product_id, $location_id );

if ( empty( $rows ) ) {
	return;
}

$row          = $rows[0];
$status_text  = ucfirst( str_replace( '_', ' ', $row->status ) );
$product      = get_post( $product_id );
$product_name = $product ? $product->post_title : '';

$wrapper_attrs = get_block_wrapper_attributes(
	[
		'class' => 'pkit-availability-badge-wrapper',
	]
);
?>

<span <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes(). ?> role="status">
	<span class="screen-reader-text">
		<?php
		if ( $product_name ) {
			/* translators: %s: product name. */
			printf( esc_html__( '%s availability:', 'producerkit' ), esc_html( $product_name ) );
		} else {
			esc_html_e( 'Availability:', 'producerkit' );
		}
		?>
	</span>
	<span class="pkit-availability-badge pkit-availability-badge--<?php echo esc_attr( $row->status ); ?>">
		<?php echo esc_html( $status_text ); ?>
	</span>
	<?php if ( $row->quantity_note ) : ?>
		<span class="pkit-availability-badge__note"><?php echo esc_html( $row->quantity_note ); ?></span>
	<?php endif; ?>
</span>