<?php
/**
 * Commissions admin screen.
 *
 * Submenu under the ProducerKit dashboard: the request queue, a quote form,
 * and one-click moves along the status machine. Actions go through the
 * plugin's own REST endpoints with a wp_rest nonce — the same pattern as
 * Pre-Orders and Quick Entry — so the transition rules are enforced in one
 * place rather than re-implemented for the admin.
 */

declare(strict_types=1);

namespace ProducerKit\Commissions\Admin;

use ProducerKit\Commissions\Store;
use ProducerKit\Commissions\Vocabulary;

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', __NAMESPACE__ . '\\register_page' );

function register_page(): void {
	$open  = Store\count_by_status()['new'] ?? 0;
	$badge = $open > 0
		? sprintf( ' <span class="awaiting-mod"><span class="pending-count">%d</span></span>', $open )
		: '';

	add_submenu_page(
		'producerkit',
		Vocabulary\menu(),
		Vocabulary\menu() . $badge,
		Store\manage_cap(),
		'producerkit-commissions',
		__NAMESPACE__ . '\\render_page'
	);
}

/**
 * @return array<string, string>
 */
function status_labels(): array {
	return [
		'new'         => __( 'New', 'producerkit' ),
		'quoted'      => __( 'Quoted', 'producerkit' ),
		'accepted'    => __( 'Accepted', 'producerkit' ),
		'in_progress' => __( 'In progress', 'producerkit' ),
		'complete'    => __( 'Complete', 'producerkit' ),
		'declined'    => __( 'Declined', 'producerkit' ),
		'cancelled'   => __( 'Cancelled', 'producerkit' ),
	];
}

function status_label( string $status ): string {
	return status_labels()[ $status ] ?? $status;
}

/**
 * Colour per status, mirroring the admin's own palette.
 */
function status_color( string $status ): string {
	return match ( $status ) {
		'new'         => '#2271b1',
		'quoted'      => '#dba617',
		'accepted'    => '#00a32a',
		'in_progress' => '#8c5db0',
		'complete'    => '#1a6b1a',
		default       => '#787c82',
	};
}

function render_page(): void {
	if ( ! current_user_can( Store\manage_cap() ) ) {
		wp_die(
			esc_html(
				sprintf(
					/* translators: %s: this trade's word for commissions, lowercase plural. */
					__( 'You do not have permission to view %s.', 'producerkit' ),
					Vocabulary\plural_lower()
				)
			),
			403
		);
	}

	// Read-only view selector; no state changes happen on this request.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
	if ( '' !== $filter && ! isset( status_labels()[ $filter ] ) ) {
		$filter = '';
	}

	// One grouped query for every filter count, rather than a COUNT plus a
	// discarded SELECT * per status — that was about twenty queries to draw a
	// row of links.
	$by_status = Store\count_by_status();

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only paging.
	$paged    = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
	$per_page = 50;

	$list  = Store\list_commissions(
		[
			'status' => $filter,
			'limit'  => $per_page,
			'offset' => ( $paged - 1 ) * $per_page,
		]
	);
	$base  = admin_url( 'admin.php?page=producerkit-commissions' );
	$money = html_entity_decode( get_woocommerce_currency_symbol_safe() );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( Vocabulary\plural() ); ?></h1>

		<ul class="subsubsub">
			<li>
				<a href="<?php echo esc_url( $base ); ?>" class="<?php echo '' === $filter ? 'current' : ''; ?>">
					<?php esc_html_e( 'All', 'producerkit' ); ?>
					<span class="count">(<?php echo (int) array_sum( $by_status ); ?>)</span>
				</a>
			</li>
			<?php
			$counts = [];
			foreach ( status_labels() as $slug => $label ) {
				$count = $by_status[ $slug ] ?? 0;
				if ( $count > 0 || $slug === $filter ) {
					$counts[ $slug ] = [ $label, $count ];
				}
			}
			?>
			<?php foreach ( $counts as $slug => list( $label, $count ) ) : ?>
				<li>
					| <a href="<?php echo esc_url( add_query_arg( 'status', $slug, $base ) ); ?>"
						class="<?php echo $slug === $filter ? 'current' : ''; ?>">
						<?php echo esc_html( $label ); ?> <span class="count">(<?php echo (int) $count; ?>)</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<p id="pkit-commission-status" style="min-height:1.5em;color:#2271b1;"></p>

		<?php if ( ! $list['commissions'] ) : ?>
			<div class="notice notice-info inline"><p>
				<?php
				printf(
					/* translators: %s: this trade's word for commissions, lowercase plural. */
					esc_html__( 'No %s yet. Add the request form to a page so customers can send one.', 'producerkit' ),
					esc_html( Vocabulary\plural_lower() )
				);
				?>
			</p></div>
		<?php else : ?>
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Request', 'producerkit' ); ?></th>
						<th scope="col" style="width:9em"><?php esc_html_e( 'Status', 'producerkit' ); ?></th>
						<th scope="col" style="width:11em"><?php esc_html_e( 'Quote', 'producerkit' ); ?></th>
						<th scope="col" style="width:22em"><?php esc_html_e( 'Actions', 'producerkit' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $list['commissions'] as $c ) : ?>
					<?php
					$id     = (int) $c['id'];
					$status = (string) $c['status'];
					// 'quoted' is deliberately not offered as a plain status
					// button: quoting needs a price, and the quote form below
					// is the only thing that can supply one. set_status()
					// refuses it too, but an unusable button is still a trap.
					$onward = array_values(
						array_diff( Store\transitions()[ $status ] ?? [], [ 'quoted' ] )
					);
					// Stored as slugs; shown as words. "live-edge-walnut" is not
					// what the customer typed and not what the maker reads.
					$details = array_filter(
						[
							Store\term_label( (string) ( $c['product_type'] ?? '' ), 'pkit_product_type' ),
							Store\term_label( (string) ( $c['material'] ?? '' ), 'pkit_material' ),
							(string) ( Store\budget_ranges()[ $c['budget_range'] ?? '' ] ?? '' ),
						]
					);
					?>
					<tr data-commission="<?php echo esc_attr( (string) $id ); ?>">
						<td>
							<strong><?php echo esc_html( (string) $c['name'] ); ?></strong><br>
							<a href="mailto:<?php echo esc_attr( (string) $c['email'] ); ?>"><?php echo esc_html( (string) $c['email'] ); ?></a>
							<?php if ( ! empty( $c['phone'] ) ) : ?>
								· <?php echo esc_html( (string) $c['phone'] ); ?>
							<?php endif; ?>
							<p style="margin:.5em 0 0;color:#3c434a;">
								<?php echo esc_html( wp_trim_words( (string) $c['description'], 40 ) ); ?>
							</p>
							<?php if ( $details ) : ?>
								<p style="margin:.25em 0 0;color:#646970;font-size:12px;">
									<?php echo esc_html( implode( ' · ', $details ) ); ?>
								</p>
							<?php endif; ?>
							<?php if ( ! empty( $c['deadline'] ) ) : ?>
								<p style="margin:.25em 0 0;color:#646970;font-size:12px;">
									<?php
									printf(
										/* translators: %s: date the customer needs the piece by. */
										esc_html__( 'Needed by %s', 'producerkit' ),
										esc_html( (string) $c['deadline'] )
									);
									?>
								</p>
							<?php endif; ?>
						</td>
						<td>
							<span style="display:inline-block;padding:2px 8px;border-radius:9px;color:#fff;font-size:12px;background:<?php echo esc_attr( status_color( $status ) ); ?>">
								<?php echo esc_html( status_label( $status ) ); ?>
							</span>
						</td>
						<td>
							<?php
							$pay_url   = get_transient( 'pkit_pay_url_' . $id );
							$pay_error = get_transient( 'pkit_settlement_error_' . $id );
							?>
							<?php if ( $pay_error ) : ?>
								<p style="margin:0 0 .4em;color:#d63638;font-size:12px;">
									<?php
									printf(
										/* translators: %s: reason the order could not be raised. */
										esc_html__( 'Payment link failed: %s', 'producerkit' ),
										esc_html( (string) $pay_error )
									);
									?>
								</p>
							<?php elseif ( $pay_url ) : ?>
								<p style="margin:0 0 .4em;font-size:12px;">
									<a href="<?php echo esc_url( (string) $pay_url ); ?>"><?php esc_html_e( 'Payment link', 'producerkit' ); ?></a>
								</p>
							<?php endif; ?>
							<?php if ( null !== ( $c['quoted_price'] ?? null ) ) : ?>
								<strong><?php echo esc_html( $money . number_format( (float) $c['quoted_price'], 2 ) ); ?></strong>
								<?php if ( ! empty( $c['estimated_date'] ) ) : ?>
									<br><span style="color:#646970;font-size:12px;"><?php echo esc_html( (string) $c['estimated_date'] ); ?></span>
								<?php endif; ?>
							<?php else : ?>
								<span style="color:#646970;">—</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( in_array( $status, [ 'new', 'quoted' ], true ) ) : ?>
								<div class="pkit-quote-form" style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;">
									<input type="number" step="0.01" min="0.01" class="pkit-price small-text"
										placeholder="<?php esc_attr_e( 'Price', 'producerkit' ); ?>"
										aria-label="<?php esc_attr_e( 'Quoted price', 'producerkit' ); ?>">
									<input type="date" class="pkit-date"
										aria-label="<?php esc_attr_e( 'Estimated ready date', 'producerkit' ); ?>">
									<button type="button" class="button button-primary pkit-send-quote">
										<?php
										echo 'quoted' === $status
											? esc_html__( 'Re-quote', 'producerkit' )
											: esc_html__( 'Send quote', 'producerkit' );
										?>
									</button>
								</div>
							<?php endif; ?>

							<?php foreach ( $onward as $next ) : ?>
								<button type="button" class="button pkit-set-status"
									data-status="<?php echo esc_attr( $next ); ?>">
									<?php echo esc_html( status_label( $next ) ); ?>
								</button>
							<?php endforeach; ?>

							<?php if ( ! $onward && 'new' !== $status ) : ?>
								<span style="color:#646970;"><?php esc_html_e( 'Closed', 'producerkit' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			$total_pages = (int) ceil( $list['total'] / $per_page );
			if ( $total_pages > 1 ) :
				?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php
							printf(
								/* translators: %d: number of commissions. */
								esc_html( _n( '%d item', '%d items', (int) $list['total'], 'producerkit' ) ),
								(int) $list['total']
							);
							?>
						</span>
						<?php
						echo wp_kses_post(
							paginate_links(
								[
									'base'      => add_query_arg( 'paged', '%#%' ),
									'format'    => '',
									'prev_text' => '&laquo;',
									'next_text' => '&raquo;',
									'total'     => $total_pages,
									'current'   => $paged,
								]
							)
						);
						?>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
	render_script();
}

/**
 * The currency symbol, without requiring WooCommerce.
 */
function get_woocommerce_currency_symbol_safe(): string {
	if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
		return (string) get_woocommerce_currency_symbol();
	}

	/**
	 * Filters the currency symbol shown beside quoted prices.
	 *
	 * @param string $symbol Defaults to a dollar sign.
	 */
	return (string) apply_filters( 'pkit_currency_symbol', '$' );
}

/**
 * Inline handler. Talks to the same REST routes a client would, so the
 * transition table is the single authority on what is allowed.
 */
function render_script(): void {
	$config = [
		'root'  => esc_url_raw( rest_url( 'producerkit/v1/commissions' ) ),
		'nonce' => wp_create_nonce( 'wp_rest' ),
		'i18n'  => [
			'needPrice' => __( 'Enter a price before sending the quote.', 'producerkit' ),
			'saving'    => __( 'Saving…', 'producerkit' ),
			'saved'     => __( 'Saved.', 'producerkit' ),
			'failed'    => __( 'Could not save:', 'producerkit' ),
		],
	];
	?>
	<script>
	( function () {
		var cfg = <?php echo wp_json_encode( $config ); ?>;
		var out = document.getElementById( 'pkit-commission-status' );

		function say( msg, isError ) {
			out.textContent = msg;
			out.style.color = isError ? '#d63638' : '#2271b1';
		}

		function post( url, body, done ) {
			say( cfg.i18n.saving, false );
			fetch( url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
				body: JSON.stringify( body || {} )
			} )
				.then( function ( r ) {
					return r.json().then( function ( data ) {
						return { ok: r.ok, data: data };
					} );
				} )
				.then( function ( res ) {
					if ( ! res.ok ) {
						say( cfg.i18n.failed + ' ' + ( res.data && res.data.message ? res.data.message : '' ), true );
						return;
					}
					say( cfg.i18n.saved, false );
					done();
				} )
				.catch( function () { say( cfg.i18n.failed, true ); } );
		}

		document.addEventListener( 'click', function ( e ) {
			var row = e.target.closest( '[data-commission]' );
			if ( ! row ) { return; }
			var id = row.getAttribute( 'data-commission' );

			if ( e.target.classList.contains( 'pkit-send-quote' ) ) {
				var price = row.querySelector( '.pkit-price' ).value;
				var date = row.querySelector( '.pkit-date' ).value;
				if ( ! price || parseFloat( price ) <= 0 ) {
					say( cfg.i18n.needPrice, true );
					return;
				}
				post( cfg.root + '/' + id + '/quote', { price: price, estimated_date: date }, function () {
					window.location.reload();
				} );
			}

			if ( e.target.classList.contains( 'pkit-set-status' ) ) {
				post( cfg.root + '/' + id + '/status', { status: e.target.getAttribute( 'data-status' ) }, function () {
					window.location.reload();
				} );
			}
		} );
	} )();
	</script>
	<?php
}
