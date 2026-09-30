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

// One row per trade field the active producer profile switched on, built from
// the terms actually present on this board. Empty for a farm, which asks for
// none of them.
$filter_traits = $board['filter_traits'] ?? [];
$statuses      = $board['statuses'] ?? [];
$total         = $board['total_items'] ?? 0;

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
			// Traits travel with the item now: the count is the headline of
			// the state sentence, and it was previously computed from status
			// and type alone, so a trade-field filter left it overstating.
			'traits' => (object) ( $item['traits'] ?? [] ),
		];
	}
}

wp_interactivity_state(
	'producerkit',
	[
		'activeStatuses'  => $status_map,
		'allStatuses'     => array_values( $statuses ),
		'activeType'      => '',
		// One selection per trade field, seeded empty. Declared here rather
		// than in the store's `state:` block, which would overwrite whatever
		// the server sent.
		'activeTraits'    => (object) [],
		'totalItems'      => $total,
		'allItems'        => $all_items,
		// The drawer starts shut. C's whole argument is that the sentence is
		// enough most of the time.
		'filtersOpen'     => false,
		// What the producer configured, kept so Clear everything can put the
		// board back to it. Restoring all-statuses-on instead would quietly
		// discard the defaultStatusFilter attribute — a visitor pressing
		// "clear" would end up seeing more than the board was set up to show.
		'defaultStatuses' => clone $status_map,
	]
);

$context = [
	'layout'   => $layout,
	'restBase' => esc_url_raw( rest_url( 'producerkit/v1' ) ),
];

// Unique per instance: two boards on one page must not share a drawer id.
$drawer_id = wp_unique_id( 'pkit-board-filters-' );

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

		<?php
		// The view, stated in a sentence. This replaces the old footer rather
		// than joining it: one live region, above the board, where someone
		// looks before they start hunting for why a thing is missing.
		?>
		<p class="pkit-avail-board__state" aria-live="polite" aria-atomic="true">
			<span class="pkit-avail-board__count" data-wp-text="state.summaryText">
				<?php
				printf(
					/* translators: %d: number of items shown on the availability board. */
					esc_html( _n( 'Showing %d item', 'Showing %d items', (int) $total, 'producerkit' ) ),
					(int) $total,
				);
				?>
			</span>

			<?php if ( $show_filters ) : ?>
				<span class="pkit-avail-board__state-note" data-wp-bind--hidden="!state.isShowingEverything">
					<?php esc_html_e( '· everything on the board', 'producerkit' ); ?>
				</span>

				<?php
				// Every pill that could ever apply is rendered once and hidden
				// until it does. A bounded set — five statuses, one per type,
				// one per trait term — so this costs less than templating a
				// list client-side, and every word stays translated on the
				// server where the plural rules already work.
				?>
				<?php foreach ( $statuses as $status ) : ?>
					<?php $status_label = \ProducerKit\Core\Availability\status_label( (string) $status ); ?>
					<span
						class="pkit-avail-board__pill"
						data-wp-context='<?php echo esc_attr( (string) wp_json_encode( [ 'filterStatus' => $status ] ) ); ?>'
						data-wp-bind--hidden="state.isCurrentStatusActive"
					>
						<span>
							<?php
							printf(
								/* translators: %s: an availability status, e.g. "Sold out". */
								esc_html__( 'hiding %s', 'producerkit' ),
								esc_html( $status_label ),
							);
							?>
						</span>
						<button
							type="button"
							data-wp-on--click="actions.restoreStatus"
							aria-label="
							<?php
							printf(
								/* translators: %s: an availability status, e.g. "Sold out". */
								esc_attr__( 'Show %s again', 'producerkit' ),
								esc_attr( $status_label ),
							);
							?>
							"
						>&times;</button>
					</span>
				<?php endforeach; ?>

				<?php foreach ( $filter_types as $ft ) : ?>
					<span
						class="pkit-avail-board__pill"
						data-wp-context='<?php echo esc_attr( (string) wp_json_encode( [ 'filterType' => $ft['slug'] ] ) ); ?>'
						data-wp-bind--hidden="!state.isProductTypeActive"
					>
						<span>
							<?php
							printf(
								/* translators: %s: a product type, e.g. "Bread". */
								esc_html__( '%s only', 'producerkit' ),
								esc_html( $ft['label'] ),
							);
							?>
						</span>
						<button
							type="button"
							data-wp-on--click="actions.clearProductType"
							aria-label="<?php esc_attr_e( 'Show all types', 'producerkit' ); ?>"
						>&times;</button>
					</span>
				<?php endforeach; ?>

				<?php foreach ( $filter_traits as $trait ) : ?>
					<?php foreach ( $trait['terms'] as $term ) : ?>
						<span
							class="pkit-avail-board__pill"
							data-wp-context='
							<?php
							echo esc_attr(
								(string) wp_json_encode(
									[
										'filterTaxonomy'  => $trait['taxonomy'],
										'filterTraitSlug' => $term['slug'],
									]
								)
							);
							?>
							'
							data-wp-bind--hidden="!state.isCurrentTraitActive"
						>
							<span><?php echo esc_html( $term['label'] ); ?></span>
							<button
								type="button"
								data-wp-on--click="actions.clearTrait"
								aria-label="
								<?php
								printf(
									/* translators: %s: a trade field value, e.g. "Stoneware". */
									esc_attr__( 'Stop filtering by %s', 'producerkit' ),
									esc_attr( $term['label'] ),
								);
								?>
								"
							>&times;</button>
						</span>
					<?php endforeach; ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</p>

		<?php if ( $show_filters ) : ?>
			<button
				type="button"
				class="pkit-avail-board__disclosure"
				data-wp-on--click="actions.toggleFilters"
				data-wp-bind--aria-expanded="state.filtersOpen"
				aria-expanded="false"
				aria-controls="<?php echo esc_attr( $drawer_id ); ?>"
			>
				<span class="pkit-avail-board__caret" aria-hidden="true">&rsaquo;</span>
				<span data-wp-text="state.filterToggleLabel"><?php esc_html_e( 'Filter', 'producerkit' ); ?></span>
			</button>

			<div
				class="pkit-avail-board__drawer"
				id="<?php echo esc_attr( $drawer_id ); ?>"
				data-wp-bind--hidden="!state.filtersOpen"
				hidden
			>
				<?php
				// Status is an include-set, so it is a set of checkboxes and
				// says so — to the eye and to a screen reader. The type and
				// trait rows below are choose-one and are radios. Those two
				// models used to be the same button with the same
				// aria-pressed, two inches apart, doing opposite things.
				?>
				<div class="pkit-avail-board__checkset" role="group" aria-label="<?php esc_attr_e( 'Show these statuses', 'producerkit' ); ?>">
					<span class="pkit-avail-board__legend"><?php esc_html_e( 'Show these', 'producerkit' ); ?></span>
					<?php foreach ( $statuses as $status ) : ?>
						<?php $is_active = in_array( $status, $active_list, true ); ?>
						<button
							type="button"
							role="checkbox"
							class="pkit-avail-board__check"
							data-wp-on--click="actions.toggleStatus"
							data-wp-context='<?php echo esc_attr( (string) wp_json_encode( [ 'filterStatus' => $status ] ) ); ?>'
							data-wp-bind--aria-checked="state.isCurrentStatusActive"
							aria-checked="<?php echo $is_active ? 'true' : 'false'; ?>"
						>
							<span class="pkit-avail-board__box" aria-hidden="true">&check;</span>
							<span class="pkit-avail-board__swatch pkit-availability-badge--<?php echo esc_attr( $status ); ?>" aria-hidden="true"></span>
							<span class="pkit-avail-board__checkname"><?php echo esc_html( \ProducerKit\Core\Availability\status_label( (string) $status ) ); ?></span>
							<span class="pkit-avail-board__n" data-wp-text="state.currentStatusCount"></span>
						</button>
					<?php endforeach; ?>
				</div>

				<?php if ( count( $filter_types ) > 1 ) : ?>
					<div class="pkit-avail-board__radiorow">
						<span class="pkit-avail-board__legend"><?php esc_html_e( 'Type', 'producerkit' ); ?></span>
						<div class="pkit-avail-board__radios" role="radiogroup" aria-label="<?php esc_attr_e( 'Filter by product type', 'producerkit' ); ?>">
							<button
								type="button"
								role="radio"
								data-wp-on--click="actions.setProductTypeFilter"
								data-wp-on--keydown="actions.moveWithinRadioGroup"
								data-wp-context='<?php echo esc_attr( (string) wp_json_encode( [ 'filterType' => '' ] ) ); ?>'
								data-wp-bind--aria-checked="state.isProductTypeActive"
								data-wp-bind--tabindex="state.radioTabIndex"
								aria-checked="true"
							><?php esc_html_e( 'All', 'producerkit' ); ?></button>
							<?php foreach ( $filter_types as $ft ) : ?>
								<button
									type="button"
									role="radio"
									data-wp-on--click="actions.setProductTypeFilter"
									data-wp-on--keydown="actions.moveWithinRadioGroup"
									data-wp-context='<?php echo esc_attr( (string) wp_json_encode( [ 'filterType' => $ft['slug'] ] ) ); ?>'
									data-wp-bind--aria-checked="state.isProductTypeActive"
									data-wp-bind--tabindex="state.radioTabIndex"
									data-wp-class--pkit-avail-board__radio--empty="state.isCurrentTypeEmpty"
									aria-checked="false"
								><?php echo esc_html( $ft['label'] ); ?></button>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php foreach ( $filter_traits as $trait ) : ?>
					<div class="pkit-avail-board__radiorow">
						<span class="pkit-avail-board__legend"><?php echo esc_html( $trait['label'] ); ?></span>
						<div
							class="pkit-avail-board__radios"
							role="radiogroup"
							aria-label="
							<?php
							printf(
								/* translators: %s: trade field name, e.g. Clay Body. */
								esc_attr__( 'Filter by %s', 'producerkit' ),
								esc_attr( $trait['label'] ),
							);
							?>
							"
						>
							<button
								type="button"
								role="radio"
								data-wp-on--click="actions.setTraitFilter"
								data-wp-on--keydown="actions.moveWithinRadioGroup"
								data-wp-context='
								<?php
								echo esc_attr(
									(string) wp_json_encode(
										[
											'filterTaxonomy'  => $trait['taxonomy'],
											'filterTraitSlug' => '',
										]
									)
								);
								?>
								'
								data-wp-bind--aria-checked="state.isCurrentTraitActive"
								data-wp-bind--tabindex="state.radioTabIndex"
								aria-checked="true"
							><?php esc_html_e( 'Any', 'producerkit' ); ?></button>
							<?php foreach ( $trait['terms'] as $term ) : ?>
								<button
									type="button"
									role="radio"
									data-wp-on--click="actions.setTraitFilter"
									data-wp-on--keydown="actions.moveWithinRadioGroup"
									data-wp-context='
									<?php
									echo esc_attr(
										(string) wp_json_encode(
											[
												'filterTaxonomy'  => $trait['taxonomy'],
												'filterTraitSlug' => $term['slug'],
											]
										)
									);
									?>
									'
									data-wp-bind--aria-checked="state.isCurrentTraitActive"
									data-wp-bind--tabindex="state.radioTabIndex"
									aria-checked="false"
								><?php echo esc_html( $term['label'] ); ?></button>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>

				<div>
					<button
						type="button"
						class="pkit-avail-board__clear"
						data-wp-on--click="actions.clearAllFilters"
						data-wp-bind--disabled="!state.isFiltered"
						disabled
					><?php esc_html_e( 'Clear everything', 'producerkit' ); ?></button>
				</div>
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
						$status_text = \ProducerKit\Core\Availability\status_label( (string) $item['status'] );
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
							// Keyed by taxonomy so the store can test each
							// trade field independently without knowing which
							// fields this site has.
							'itemTraits' => (object) ( $item['traits'] ?? [] ),
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


	<?php endif; ?>
</section>