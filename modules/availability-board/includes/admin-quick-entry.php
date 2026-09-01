<?php
/**
 * Availability Board admin quick-entry page.
 *
 * A dedicated admin page for the weekly "what's available" workflow.
 * Shows all published products in a table with status dropdowns
 * and quantity note fields. Saves via AJAX to the existing
 * REST availability endpoint.
 *
 * Accessible under the Farm Stand menu.
 */

declare(strict_types=1);

namespace Leftfield\AvailabilityBoard\Admin;

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', __NAMESPACE__ . '\\register_page' );

function register_page(): void {
	add_submenu_page(
		'farm-stand-dashboard',
		__( 'Update Availability', 'producerkit' ),
		__( 'Availability', 'producerkit' ),
		'edit_posts',
		'farm-stand-availability',
		__NAMESPACE__ . '\\render_page',
	);
}

function render_page(): void {
	// Get all published products grouped by type.
	$products = get_posts(
		[
			'post_type'   => 'lfuf_product',
			'post_status' => 'publish',
			'numberposts' => 200,
			'orderby'     => 'title',
			'order'       => 'ASC',
		]
	);

	// Get current availability.
	$current    = \Leftfield\Core\Availability\get_all_current();
	$by_product = [];
	foreach ( $current as $row ) {
		$by_product[ (int) $row->product_id ] = $row;
	}

	// Get locations for the location selector.
	$locations = get_posts(
		[
			'post_type'   => 'lfuf_location',
			'post_status' => 'publish',
			'numberposts' => 50,
			'orderby'     => 'title',
			'order'       => 'ASC',
		]
	);

	$statuses  = \Leftfield\Core\Availability\valid_statuses();
	$today     = current_time( 'Y-m-d' );
	$rest_base = esc_url_raw( rest_url( 'lfuf/v1' ) );
	$nonce     = wp_create_nonce( 'wp_rest' );

	// Build a JSON map of last-known statuses for the copy-last-week feature.
	$last_week_data = [];
	foreach ( $by_product as $pid => $row ) {
		$last_week_data[ $pid ] = [
			'status' => $row->status,
			'note'   => $row->quantity_note ?? '',
		];
	}

	?>
	<div class="wrap lfuf-quick-entry">
		<h1><?php esc_html_e( 'Update Availability', 'producerkit' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'Set what\'s available this week. Changes take effect immediately on the site.', 'producerkit' ); ?>
		</p>

		<div class="lfuf-quick-entry__toolbar">
			<label for="lfuf-qe-location">
				<?php esc_html_e( 'Location:', 'producerkit' ); ?>
			</label>
			<select id="lfuf-qe-location">
				<option value="0"><?php esc_html_e( 'All locations', 'producerkit' ); ?></option>
				<?php foreach ( $locations as $loc ) : ?>
					<option value="<?php echo (int) $loc->ID; ?>">
						<?php echo esc_html( $loc->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<label for="lfuf-qe-date">
				<?php esc_html_e( 'Effective date:', 'producerkit' ); ?>
			</label>
			<input type="date" id="lfuf-qe-date" value="<?php echo esc_attr( $today ); ?>">

			<button type="button" id="lfuf-qe-copy-last" class="button">
				<?php esc_html_e( 'Copy Last Week', 'producerkit' ); ?>
			</button>

			<button type="button" id="lfuf-qe-save-all" class="button button-primary" disabled>
				<?php esc_html_e( 'Save All Changes', 'producerkit' ); ?>
			</button>
			<span id="lfuf-qe-status" class="lfuf-quick-entry__save-status"></span>
		</div>

		<table class="wp-list-table widefat striped lfuf-quick-entry__table">
			<thead>
				<tr>
					<th class="lfuf-qe-col-thumb"></th>
					<th class="lfuf-qe-col-product"><?php esc_html_e( 'Product', 'producerkit' ); ?></th>
					<th class="lfuf-qe-col-type"><?php esc_html_e( 'Type', 'producerkit' ); ?></th>
					<th class="lfuf-qe-col-status"><?php esc_html_e( 'Status', 'producerkit' ); ?></th>
					<th class="lfuf-qe-col-note"><?php esc_html_e( 'Quantity Note', 'producerkit' ); ?></th>
					<th class="lfuf-qe-col-current"><?php esc_html_e( 'Current', 'producerkit' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $products as $product ) :
					$pid      = $product->ID;
					$existing = $by_product[ $pid ] ?? null;
					$types    = get_the_terms( $pid, 'lfuf_product_type' );
					$type_str = ( $types && ! is_wp_error( $types ) )
						? implode( ', ', wp_list_pluck( $types, 'name' ) )
						: '—';
					$thumb    = \Leftfield\Core\Product_Images\thumbnail_url( $pid, 'thumbnail' );
					?>
					<tr class="lfuf-qe-row" data-product-id="<?php echo (int) $pid; ?>">
						<td class="lfuf-qe-col-thumb">
							<?php if ( $thumb ) : ?>
								<img src="<?php echo esc_url( $thumb ); ?>" alt="" class="lfuf-qe-thumb">
							<?php else : ?>
								<span class="lfuf-qe-thumb-placeholder">📷</span>
							<?php endif; ?>
						</td>
						<td class="lfuf-qe-col-product">
							<strong><?php echo esc_html( $product->post_title ); ?></strong>
							<?php
								$price = get_post_meta( $pid, '_lfuf_price', true );
								$unit  = get_post_meta( $pid, '_lfuf_unit', true );
							if ( $price ) :
								?>
								<span class="lfuf-qe-price"><?php echo esc_html( $price ); ?>
								<?php
								if ( $unit ) {
									echo ' / ' . esc_html( $unit );
								}
								?>
								</span>
							<?php endif; ?>
						</td>
						<td class="lfuf-qe-col-type">
							<span class="lfuf-qe-type-label"><?php echo esc_html( $type_str ); ?></span>
						</td>
						<td class="lfuf-qe-col-status">
							<select class="lfuf-qe-status-select" data-original="<?php echo esc_attr( $existing->status ?? '' ); ?>">
								<option value=""><?php esc_html_e( '— Not listed —', 'producerkit' ); ?></option>
								<?php foreach ( $statuses as $s ) : ?>
									<option
										value="<?php echo esc_attr( $s ); ?>"
										<?php selected( $existing->status ?? '', $s ); ?>
									>
										<?php echo esc_html( ucfirst( str_replace( '_', ' ', $s ) ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td class="lfuf-qe-col-note">
							<input
								type="text"
								class="lfuf-qe-note-input"
								value="<?php echo esc_attr( $existing->quantity_note ?? '' ); ?>"
								data-original="<?php echo esc_attr( $existing->quantity_note ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'e.g. ~3 bunches left', 'producerkit' ); ?>"
							>
						</td>
						<td class="lfuf-qe-col-current">
							<?php if ( $existing ) : ?>
								<span class="lfuf-availability-badge lfuf-availability-badge--<?php echo esc_attr( $existing->status ); ?>">
									<?php echo esc_html( ucfirst( str_replace( '_', ' ', $existing->status ) ) ); ?>
								</span>
							<?php else : ?>
								<span class="lfuf-qe-not-listed"><?php esc_html_e( 'Not listed', 'producerkit' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( empty( $products ) ) : ?>
			<p class="lfuf-quick-entry__empty">
				<?php
				printf(
					wp_kses(
						/* translators: %s: URL of the "Add New Product" admin screen. */
						__( 'No products found. <a href="%s">Add your first product</a> to get started.', 'producerkit' ),
						[ 'a' => [ 'href' => [] ] ],
					),
					esc_url( admin_url( 'post-new.php?post_type=lfuf_product' ) ),
				);
				?>
			</p>
		<?php endif; ?>
	</div>

	<style>
		.lfuf-quick-entry__toolbar {
			display: flex;
			align-items: center;
			gap: 0.75rem;
			margin: 1rem 0;
			padding: 0.75rem 1rem;
			background: #fff;
			border: 1px solid #dcdcde;
			border-radius: 0.25rem;
			flex-wrap: wrap;
		}
		.lfuf-quick-entry__toolbar label { font-weight: 600; font-size: 0.85rem; }
		.lfuf-quick-entry__save-status { font-size: 0.85rem; color: #16a34a; font-weight: 600; }
		.lfuf-quick-entry__save-status.error { color: #dc2626; }

		.lfuf-quick-entry__table .lfuf-qe-col-thumb { width: 48px; padding: 4px 8px; }
		.lfuf-qe-thumb {
			width: 40px; height: 40px; object-fit: cover; border-radius: 4px;
			display: block;
		}
		.lfuf-qe-thumb-placeholder {
			display: flex; align-items: center; justify-content: center;
			width: 40px; height: 40px; background: #f3f4f6; border-radius: 4px;
			font-size: 16px; opacity: 0.5;
		}
		.lfuf-quick-entry__table .lfuf-qe-col-product { min-width: 160px; }
		.lfuf-qe-price { display: block; font-size: 0.8rem; color: #6b7280; margin-top: 2px; }
		.lfuf-quick-entry__table .lfuf-qe-col-type { min-width: 90px; }
		.lfuf-quick-entry__table .lfuf-qe-col-status { min-width: 140px; }
		.lfuf-quick-entry__table .lfuf-qe-col-note { min-width: 180px; }
		.lfuf-quick-entry__table .lfuf-qe-col-note input { width: 100%; }
		.lfuf-qe-type-label { font-size: 0.8rem; color: #6b7280; }
		.lfuf-qe-not-listed { font-size: 0.8rem; color: #9ca3af; font-style: italic; }
		.lfuf-qe-row.lfuf-qe-changed { background: #fffbeb !important; }
		.lfuf-qe-row.lfuf-qe-saved { background: #f0fdf4 !important; }
		.lfuf-qe-row.lfuf-qe-error { background: #fef2f2 !important; }

		/* Larger touch targets for mobile */
		.lfuf-qe-status-select {
			min-height: 36px; font-size: 14px; padding: 4px 8px;
		}
		.lfuf-qe-note-input {
			min-height: 36px; font-size: 14px; padding: 4px 8px;
		}

		.lfuf-availability-badge {
			display: inline-block; font-size: 0.7rem; font-weight: 600;
			text-transform: uppercase; letter-spacing: 0.04em;
			padding: 0.2rem 0.5rem; border-radius: 0.25rem;
		}
		.lfuf-availability-badge--abundant   { background: #d1fae5; color: #065f46; }
		.lfuf-availability-badge--available  { background: #dbeafe; color: #1e40af; }
		.lfuf-availability-badge--limited    { background: #fef3c7; color: #92400e; }
		.lfuf-availability-badge--sold_out   { background: #fee2e2; color: #991b1b; }
		.lfuf-availability-badge--unavailable { background: #f3f4f6; color: #6b7280; }

		@media (max-width: 782px) {
			.lfuf-qe-col-type, .lfuf-qe-col-current { display: none; }
			.lfuf-qe-col-product { min-width: 120px; }
		}
	</style>

	<script>
	( function () {
		'use strict';

		var restBase    = <?php echo wp_json_encode( $rest_base ); ?>;
		var nonce       = <?php echo wp_json_encode( $nonce ); ?>;
		var lastWeek    = <?php echo wp_json_encode( $last_week_data ); ?>;
		var saveBtn     = document.getElementById( 'lfuf-qe-save-all' );
		var copyBtn     = document.getElementById( 'lfuf-qe-copy-last' );
		var statusEl    = document.getElementById( 'lfuf-qe-status' );
		var rows        = document.querySelectorAll( '.lfuf-qe-row' );

		// Track changes.
		rows.forEach( function ( row ) {
			var select = row.querySelector( '.lfuf-qe-status-select' );
			var input  = row.querySelector( '.lfuf-qe-note-input' );

			function markChanged() {
				var changed = select.value !== select.dataset.original ||
								input.value !== input.dataset.original;
				row.classList.toggle( 'lfuf-qe-changed', changed );
				row.classList.remove( 'lfuf-qe-saved', 'lfuf-qe-error' );
				updateSaveBtn();
			}

			select.addEventListener( 'change', markChanged );
			input.addEventListener( 'input', markChanged );
		} );

		function updateSaveBtn() {
			var changed = document.querySelectorAll( '.lfuf-qe-changed' );
			saveBtn.disabled = changed.length === 0;
			saveBtn.textContent = changed.length > 0
				? 'Save ' + changed.length + ' Change' + ( changed.length > 1 ? 's' : '' )
				: 'Save All Changes';
		}

		// Copy last week's values into the form.
		copyBtn.addEventListener( 'click', function () {
			var filled = 0;
			rows.forEach( function ( row ) {
				var pid    = row.dataset.productId;
				var prev   = lastWeek[ pid ];
				if ( ! prev ) return;

				var select = row.querySelector( '.lfuf-qe-status-select' );
				var input  = row.querySelector( '.lfuf-qe-note-input' );

				if ( select.value !== prev.status || input.value !== prev.note ) {
					select.value = prev.status;
					input.value  = prev.note;
					filled++;
				}

				var changed = select.value !== select.dataset.original ||
								input.value !== input.dataset.original;
				row.classList.toggle( 'lfuf-qe-changed', changed );
				row.classList.remove( 'lfuf-qe-saved', 'lfuf-qe-error' );
			} );

			updateSaveBtn();
			statusEl.textContent = filled > 0
				? 'Copied ' + filled + ' items from current availability. Review and save.'
				: 'No changes — already matches current availability.';
			statusEl.className = 'lfuf-quick-entry__save-status';
		} );

		// Batch save.
		saveBtn.addEventListener( 'click', function () {
			var changedRows  = document.querySelectorAll( '.lfuf-qe-changed' );
			var locationId   = parseInt( document.getElementById( 'lfuf-qe-location' ).value ) || 0;
			var effectiveDate = document.getElementById( 'lfuf-qe-date' ).value;
			var total        = changedRows.length;
			var completed    = 0;
			var errors       = 0;

			saveBtn.disabled = true;
			statusEl.textContent = 'Saving…';
			statusEl.className = 'lfuf-quick-entry__save-status';

			changedRows.forEach( function ( row ) {
				var productId = parseInt( row.dataset.productId );
				var status    = row.querySelector( '.lfuf-qe-status-select' ).value;
				var note      = row.querySelector( '.lfuf-qe-note-input' ).value;

				if ( ! status ) {
					// Empty = skip (not listed).
					row.classList.remove( 'lfuf-qe-changed' );
					row.classList.add( 'lfuf-qe-saved' );
					completed++;
					checkDone();
					return;
				}

				fetch( restBase + '/availability', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': nonce,
					},
					body: JSON.stringify( {
						product_id: productId,
						location_id: locationId,
						status: status,
						quantity_note: note,
						effective_date: effectiveDate,
					} ),
				} )
				.then( function ( r ) {
					if ( ! r.ok ) throw new Error( 'Failed' );
					return r.json();
				} )
				.then( function () {
					row.classList.remove( 'lfuf-qe-changed' );
					row.classList.add( 'lfuf-qe-saved' );

					// Update "original" values so re-editing works.
					var select = row.querySelector( '.lfuf-qe-status-select' );
					var input  = row.querySelector( '.lfuf-qe-note-input' );
					select.dataset.original = select.value;
					input.dataset.original  = input.value;

					completed++;
					checkDone();
				} )
				.catch( function () {
					row.classList.remove( 'lfuf-qe-changed' );
					row.classList.add( 'lfuf-qe-error' );
					errors++;
					completed++;
					checkDone();
				} );
			} );

			function checkDone() {
				if ( completed < total ) return;
				updateSaveBtn();
				if ( errors > 0 ) {
					statusEl.textContent = errors + ' error(s). Some items may not have saved.';
					statusEl.className = 'lfuf-quick-entry__save-status error';
				} else {
					statusEl.textContent = 'All changes saved.';
					statusEl.className = 'lfuf-quick-entry__save-status';
					setTimeout( function () { statusEl.textContent = ''; }, 4000 );
				}
			}
		} );
	} )();
	</script>
	<?php
}