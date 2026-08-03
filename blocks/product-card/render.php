<?php
/**
 * Server-side render for lfuf/product-card.
 *
 * Accessibility: <article> with aria-label, decorative image,
 * screen-reader labels on price and availability, focus-visible
 * on links handled in CSS.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$product_id        = (int) ( $attributes['productId'] ?? 0 );
$show_availability = (bool) ( $attributes['showAvailability'] ?? true );
$show_source       = (bool) ( $attributes['showSource'] ?? false );

if ( $product_id < 1 ) {
	return;
}

$product = get_post( $product_id );
if ( ! $product || $product->post_type !== 'lfuf_product' || $product->post_status !== 'publish' ) {
	return;
}

$price         = get_post_meta( $product_id, '_lfuf_price', true );
$unit          = get_post_meta( $product_id, '_lfuf_unit', true );
$growing_notes = get_post_meta( $product_id, '_lfuf_growing_notes', true );
$thumbnail     = get_the_post_thumbnail( $product_id, 'medium', [ 'alt' => '' ] );

// No featured image: fall back to the product type's placeholder. Still empty
// when the type has no bundled art, in which case no image renders at all.
$placeholder = $thumbnail ? '' : \Leftfield\Core\Product_Images\placeholder_url( $product_id );
$types         = get_the_terms( $product_id, 'lfuf_product_type' );
$seasons       = get_the_terms( $product_id, 'lfuf_season' );

$availability_rows = [];
if ( $show_availability ) {
	$availability_rows = \Leftfield\Core\Availability\get_current( $product_id );
}

$sources = [];
if ( $show_source ) {
	$source_ids = get_post_meta( $product_id, '_lfuf_source_ids', true );
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
}

// Build aria-label.
$aria_parts = [ $product->post_title ];
if ( $price ) {
	$aria_parts[] = $price . ( $unit ? '/' . $unit : '' );
}
if ( ! empty( $availability_rows ) ) {
	$aria_parts[] = ucfirst( str_replace( '_', ' ', $availability_rows[0]->status ) );
}

$wrapper_attrs = get_block_wrapper_attributes(
	[
		'class' => 'lfuf-product-card',
	]
);
?>

<article <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes(). ?> aria-label="<?php echo esc_attr( implode( ' — ', $aria_parts ) ); ?>">
	<?php if ( $thumbnail ) : ?>
		<div class="lfuf-product-card__image">
			<?php echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core get_the_post_thumbnail() output. ?>
		</div>
	<?php elseif ( $placeholder ) : ?>
		<div class="lfuf-product-card__image lfuf-product-card__image--placeholder">
			<img src="<?php echo esc_url( $placeholder ); ?>" alt="" loading="lazy" width="400" height="400">
		</div>
	<?php endif; ?>

	<div class="lfuf-product-card__body">
		<h3 class="lfuf-product-card__title">
			<a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>">
				<?php echo esc_html( $product->post_title ); ?>
			</a>
		</h3>

		<?php if ( $types && ! is_wp_error( $types ) ) : ?>
			<span class="lfuf-product-card__type">
				<?php echo esc_html( implode( ', ', wp_list_pluck( $types, 'name' ) ) ); ?>
			</span>
		<?php endif; ?>

		<?php if ( $price ) : ?>
			<span class="lfuf-product-card__price">
				<span class="screen-reader-text"><?php esc_html_e( 'Price:', 'farm-stand-manager' ); ?> </span>
				<?php echo esc_html( $price ); ?>
				<?php if ( $unit ) : ?>
					<span class="lfuf-product-card__unit"> / <?php echo esc_html( $unit ); ?></span>
				<?php endif; ?>
			</span>
		<?php endif; ?>

		<?php if ( $seasons && ! is_wp_error( $seasons ) ) : ?>
			<div class="lfuf-product-card__seasons" aria-label="<?php esc_attr_e( 'Available seasons', 'farm-stand-manager' ); ?>">
				<?php foreach ( $seasons as $season ) : ?>
					<span class="lfuf-product-card__season-badge"><?php echo esc_html( $season->name ); ?></span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( $growing_notes ) : ?>
			<p class="lfuf-product-card__notes"><?php echo esc_html( $growing_notes ); ?></p>
		<?php endif; ?>

		<?php if ( $show_availability && ! empty( $availability_rows ) ) : ?>
			<div class="lfuf-product-card__availability" aria-label="<?php esc_attr_e( 'Current availability', 'farm-stand-manager' ); ?>">
				<?php foreach ( $availability_rows as $row ) : ?>
					<span class="lfuf-availability-badge lfuf-availability-badge--<?php echo esc_attr( $row->status ); ?>">
						<?php echo esc_html( ucfirst( str_replace( '_', ' ', $row->status ) ) ); ?>
					</span>
					<?php if ( $row->quantity_note ) : ?>
						<span class="lfuf-product-card__quantity-note"><?php echo esc_html( $row->quantity_note ); ?></span>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( $show_source && ! empty( $sources ) ) : ?>
			<div class="lfuf-product-card__sources">
				<strong><?php esc_html_e( 'Sourced from:', 'farm-stand-manager' ); ?></strong>
				<?php foreach ( $sources as $source ) : ?>
					<div class="lfuf-product-card__source">
						<a href="<?php echo esc_url( get_permalink( $source->ID ) ); ?>">
							<?php echo esc_html( get_post_meta( $source->ID, '_lfuf_source_farm_name', true ) ?: $source->post_title ); ?>
						</a>
						<?php $loc = get_post_meta( $source->ID, '_lfuf_source_location', true ); ?>
						<?php if ( $loc ) : ?>
							<span class="lfuf-product-card__source-location">(<?php echo esc_html( $loc ); ?>)</span>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</article>