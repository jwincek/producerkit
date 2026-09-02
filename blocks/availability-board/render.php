<?php
/**
 * Server-side render for producerkit.
 *
 * activeStatuses is now an object map { "abundant": true, "available": true }
 * instead of an array, because the Interactivity API reactive proxy
 * reliably tracks object property changes but not array reassignment.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$show_filters        = (bool) ( $attributes['showFilters'] ?? true );
$show_images         = (bool) ( $attributes['showImages'] ?? true );
$show_prices         = (bool) ( $attributes['showPrices'] ?? true );
$show_quantity_notes = (bool) ( $attributes['showQuantityNotes'] ?? true );
$default_status      = $attributes['defaultStatusFilter'] ?? 'abundant,available,limited';
$location_id         = (int) ( $attributes['locationId'] ?? 0 );
$layout              = $attributes['layout'] ?? 'grid';
$empty_message       = $attributes['emptyMessage'] ?? __( 'Check back soon — we\'re updating what\'s available this week!', 'producerkit' );

$request = new \WP_REST_Request( 'GET', '/producerkit/v1/board' );
$request->set_param( 'location', $location_id );
$response = \ProducerKit\AvailabilityBoard\REST\get_board( $request );
$board    = $response->get_data();

$groups       = $board['groups'] ?? [];
$filter_types = $board['filter_types'] ?? [];
$statuses     = $board['statuses'] ?? [];
$total        = $board['total_items'] ?? 0;

// Build activeStatuses as an object map: { "abundant": true, "available": true, ... }
$active_list = array_filter( array_map( 'trim', explode( ',', $default_status ) ) );
$status_map  = new \stdClass();
foreach ( $statuses as $s ) {
	$status_map->$s = in_array( $s, $active_list, true );
}

// Build flat item list for pure-state footer counting (avoids DOM queries in view.js).
$all_items = [];
foreach ( $groups as $group ) {
	foreach ( $group['items'] as $item ) {
		$all_items[] = [
			'status' => $item['status'],
			'type'   => $item['product_slugs'][0] ?? '',
		];
	}
}

wp_interactivity_state(
	'producerkit',
	[
		'activeStatuses' => $status_map,
		'allStatuses'    => array_values( $statuses ),
		'activeType'     => '',
		'totalItems'     => $total,
		'allItems'       => $all_items,
	]
);

$context = [
	'layout'   => $layout,
	'restBase' => esc_url_raw( rest_url( 'producerkit/v1' ) ),
];

$wrapper_attrs = get_block_wrapper_attributes(
	[
		'class' => 'pkit-avail-board pkit-avail-board--' . esc_attr( $layout ),
	]
);
?>

<section
	<?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes(). ?>
	aria-label="<?php esc_attr_e( 'Product Availability', 'producerkit' ); ?>"
	data-wp-interactive="producerkit"
	<?php echo wp_interactivity_data_wp_context( $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- returns a pre-escaped data-wp-context attribute. ?>
>
	<?php if ( $total === 0 ) : ?>
		<p class="pkit-avail-board__empty">
			<?php echo esc_html( $empty_message ); ?>
		</p>
	<?php else : ?>

		<?php if ( $show_filters ) : ?>
			<div class="pkit-avail-board__filters">
				<div
					class="pkit-avail-board__filter-group"
					role="toolbar"
					aria-label="<?php esc_attr_e( 'Filter by availability status', 'producerkit' ); ?>"
				>
					<span class="pkit-avail-board__filter-label">
						<?php esc_html_e( 'Show:', 'producerkit' ); ?>
					</span>
					<?php
					foreach ( $statuses as $status ) :
						$is_active = in_array( $status, $active_list, true );
						?>
						<button
							type="button"
							class="pkit-avail-board__filter-btn pkit-availability-badge pkit-availability-badge--<?php echo esc_attr( $status ); ?><?php echo $is_active ? ' pkit-avail-board__filter-btn--active' : ''; ?>"
							data-wp-on--click="actions.toggleStatus"
							data-wp-context='<?php echo esc_attr( wp_json_encode( [ 'filterStatus' => $status ] ) ); ?>'
							data-wp-class--pkit-avail-board__filter-btn--active="state.isCurrentStatusActive"
							data-wp-bind--aria-pressed="state.isCurrentStatusActive"
							data-status="<?php echo esc_attr( $status ); ?>"
							aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>"
						><?php echo esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ); ?></button>
					<?php endforeach; ?>
				</div>

				<?php if ( count( $filter_types ) > 1 ) : ?>
					<div
						class="pkit-avail-board__filter-group"
						role="toolbar"
						aria-label="<?php esc_attr_e( 'Filter by product type', 'producerkit' ); ?>"
					>
						<span class="pkit-avail-board__filter-label">
							<?php esc_html_e( 'Type:', 'producerkit' ); ?>
						</span>
						<button
							type="button"
							class="pkit-avail-board__filter-btn pkit-avail-board__filter-btn--active"
							data-wp-on--click="actions.setProductTypeFilter"
							data-wp-context='<?php echo esc_attr( wp_json_encode( [ 'filterType' => '' ] ) ); ?>'
							data-wp-class--pkit-avail-board__filter-btn--active="state.isProductTypeActive"
							data-wp-bind--aria-pressed="state.isProductTypeActive"
							data-type-slug=""
							aria-pressed="true"
						><?php esc_html_e( 'All', 'producerkit' ); ?></button>
						<?php foreach ( $filter_types as $ft ) : ?>
							<button
								type="button"
								class="pkit-avail-board__filter-btn"
								data-wp-on--click="actions.setProductTypeFilter"
								data-wp-context='<?php echo esc_attr( wp_json_encode( [ 'filterType' => $ft['slug'] ] ) ); ?>'
								data-wp-class--pkit-avail-board__filter-btn--active="state.isProductTypeActive"
								data-wp-bind--aria-pressed="state.isProductTypeActive"
								data-type-slug="<?php echo esc_attr( $ft['slug'] ); ?>"
								aria-pressed="false"
							><?php echo esc_html( $ft['label'] ); ?></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php
		foreach ( $groups as $group ) :
			// Collect all item statuses in this group for the getter.
			$group_item_statuses = array_map(
				fn ( $item ) => $item['status'],
				$group['items'],
			);
			?>
			<div
				class="pkit-avail-board__group"
				data-type-slug="<?php echo esc_attr( $group['slug'] ); ?>"
				data-wp-context='
				<?php
				echo esc_attr(
					wp_json_encode(
						[
							'groupSlug'    => $group['slug'],
							'itemStatuses' => array_values( $group_item_statuses ),
							'itemCount'    => count( $group['items'] ),
						]
					)
				);
				?>
				'
				data-wp-bind--hidden="state.isCurrentGroupHidden"
			>
				<h3 class="pkit-avail-board__group-title">
					<?php echo esc_html( $group['label'] ); ?>
					<span
						class="pkit-avail-board__group-count"
						aria-hidden="true"
						data-wp-text="state.currentGroupCount"
					><?php echo count( $group['items'] ); ?></span>
				</h3>

				<div class="pkit-avail-board__items pkit-avail-board__items--<?php echo esc_attr( $layout ); ?>">
					<?php
					foreach ( $group['items'] as $item ) :
						$status_text = ucfirst( str_replace( '_', ' ', $item['status'] ) );
						$aria_parts  = [ $item['product_name'], $status_text ];
						if ( $show_prices && $item['price'] ) {
							$price_str = $item['price'];
							if ( $item['unit'] ) {
								$price_str .= '/' . $item['unit'];
							}
							$aria_parts[] = $price_str;
						}
						if ( $show_quantity_notes && $item['quantity_note'] ) {
							$aria_parts[] = $item['quantity_note'];
						}
						$item_aria_label = implode( ' — ', $aria_parts );

						$item_context = [
							'itemStatus' => $item['status'],
							'itemType'   => $item['product_slugs'][0] ?? '',
						];
						?>
						<article
							class="pkit-avail-board__item"
							data-status="<?php echo esc_attr( $item['status'] ); ?>"
							data-type-slug="<?php echo esc_attr( $item['product_slugs'][0] ?? '' ); ?>"
							data-wp-context='<?php echo esc_attr( wp_json_encode( $item_context ) ); ?>'
							data-wp-bind--hidden="state.isCurrentItemHidden"
							data-product-id="<?php echo (int) $item['product_id']; ?>"
							aria-label="<?php echo esc_attr( $item_aria_label ); ?>"
						>
							<?php if ( $show_images && $item['thumbnail_url'] ) : ?>
								<div class="pkit-avail-board__item-image">
									<img
										src="<?php echo esc_url( $item['thumbnail_url'] ); ?>"
										alt=""
										loading="lazy"
										width="80"
										height="80"
									>
								</div>
							<?php endif; ?>

							<div class="pkit-avail-board__item-body">
								<div class="pkit-avail-board__item-header">
									<a href="<?php echo esc_url( $item['permalink'] ); ?>" class="pkit-avail-board__item-name">
										<?php echo esc_html( $item['product_name'] ); ?>
									</a>
									<span class="pkit-availability-badge pkit-availability-badge--<?php echo esc_attr( $item['status'] ); ?>">
										<?php echo esc_html( $status_text ); ?>
									</span>
								</div>

								<?php if ( $show_prices && $item['price'] ) : ?>
									<span class="pkit-avail-board__item-price">
										<span class="screen-reader-text"><?php esc_html_e( 'Price:', 'producerkit' ); ?> </span>
										<?php echo esc_html( $item['price'] ); ?>
										<?php if ( $item['unit'] ) : ?>
											<span class="pkit-avail-board__item-unit">/ <?php echo esc_html( $item['unit'] ); ?></span>
										<?php endif; ?>
									</span>
								<?php endif; ?>

								<?php if ( $show_quantity_notes && $item['quantity_note'] ) : ?>
									<span class="pkit-avail-board__item-note">
										<?php echo esc_html( $item['quantity_note'] ); ?>
									</span>
								<?php endif; ?>

								<?php if ( $item['seasons'] ) : ?>
									<div class="pkit-avail-board__item-seasons" aria-label="<?php esc_attr_e( 'Available seasons', 'producerkit' ); ?>">
										<?php foreach ( $item['seasons'] as $season ) : ?>
											<span class="pkit-avail-board__season-tag"><?php echo esc_html( $season ); ?></span>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>

		<p class="pkit-avail-board__footer" aria-live="polite" aria-atomic="true">
			<span data-wp-text="state.footerText">
				<?php
				printf(
					/* translators: %d: number of items shown on the availability board. */
					esc_html__( 'Showing %d items', 'producerkit' ),
					(int) $total,
				);
				?>
			</span>
			<?php if ( $board['generated_at'] ?? false ) : ?>
				<span class="pkit-avail-board__timestamp">
					<span class="screen-reader-text"><?php esc_html_e( 'Last updated:', 'producerkit' ); ?> </span>
					<?php echo esc_html( date_i18n( 'M j, g:i A', strtotime( $board['generated_at'] ) ) ); ?>
				</span>
			<?php endif; ?>
		</p>

	<?php endif; ?>
</section>