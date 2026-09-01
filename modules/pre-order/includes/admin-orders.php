<?php
/**
 * Pre-Orders admin screen.
 *
 * Submenu under the Farm Stand dashboard: list with status filter and
 * one-click status transitions. Actions go through the plugin's own
 * REST endpoints with a wp_rest nonce (same pattern as Quick Entry).
 */

declare(strict_types=1);

namespace Leftfield\PreOrder\Admin;

use Leftfield\PreOrder\Orders;

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function (): void {
		add_submenu_page(
			'farm-stand-dashboard',
			__( 'Pre-Orders', 'producerkit' ),
			__( 'Pre-Orders', 'producerkit' ),
			'edit_posts',
			'farm-stand-preorders',
			__NAMESPACE__ . '\\render_page',
		);
	}
);

/**
 * Status → next-step actions offered in the list.
 *
 * @return array<string, string[]>
 */
function next_actions(): array {
	return [
		'pending'   => [ 'confirmed', 'cancelled' ],
		'confirmed' => [ 'ready', 'cancelled' ],
		'ready'     => [ 'picked_up' ],
		'picked_up' => [],
		'cancelled' => [],
	];
}

function status_label( string $status ): string {
	return match ( $status ) {
		'pending'   => __( 'Pending', 'producerkit' ),
		'confirmed' => __( 'Confirmed', 'producerkit' ),
		'ready'     => __( 'Ready for pickup', 'producerkit' ),
		'picked_up' => __( 'Picked up', 'producerkit' ),
		'cancelled' => __( 'Cancelled', 'producerkit' ),
		default     => $status,
	};
}

/**
 * Harvest List view: per pickup date, the total of each product to have
 * ready, aggregated from active orders. Print-friendly.
 */
function render_harvest_view(): void {
	$groups = Orders\get_harvest_list();
	?>
	<div class="wrap lfuf-harvest">
		<h1>
			<?php esc_html_e( 'Harvest List', 'producerkit' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=farm-stand-preorders' ) ); ?>" class="page-title-action lfuf-harvest__back">
				<?php esc_html_e( 'All Pre-Orders', 'producerkit' ); ?>
			</a>
			<button type="button" class="page-title-action lfuf-harvest__print" onclick="window.print()">
				<?php esc_html_e( 'Print', 'producerkit' ); ?>
			</button>
		</h1>
		<p class="lfuf-harvest__meta">
			<?php
			printf(
				/* translators: %s: today's date. */
				esc_html__( 'Active pre-orders (pending, confirmed, ready) as of %s.', 'producerkit' ),
				esc_html( current_time( 'Y-m-d' ) ),
			);
			?>
		</p>

		<?php if ( ! $groups ) : ?>
			<p><?php esc_html_e( 'Nothing to harvest — no active pre-orders.', 'producerkit' ); ?></p>
		<?php endif; ?>

		<?php foreach ( $groups as $group ) : ?>
			<h2 class="lfuf-harvest__date">
				<?php
				echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $group['pickup_date'] ) ) );
				if ( $group['location_name'] ) {
					echo ' — ' . esc_html( $group['location_name'] );
				}
				?>
				<span class="lfuf-harvest__count">
					<?php
					printf(
						/* translators: %d: number of orders. */
						esc_html( _n( '(%d order)', '(%d orders)', $group['order_count'], 'producerkit' ) ),
						(int) $group['order_count'],
					);
					?>
				</span>
			</h2>
			<table class="widefat striped" style="max-width: 40em; margin-bottom: 1.5em;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'producerkit' ); ?></th>
						<th style="width: 8em;"><?php esc_html_e( 'Quantity', 'producerkit' ); ?></th>
						<th style="width: 8em;"><?php esc_html_e( 'Orders', 'producerkit' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $group['items'] as $item ) : ?>
						<tr>
							<td><?php echo esc_html( $item['title'] ); ?></td>
							<td><strong><?php echo (int) $item['total_qty']; ?></strong><?php echo $item['unit'] ? esc_html( ' ' . $item['unit'] ) : ''; ?></td>
							<td><?php echo (int) $item['order_count']; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endforeach; ?>
	</div>

	<style>
	@media print {
		#adminmenumain, #wpadminbar, #wpfooter,
		.lfuf-harvest__back, .lfuf-harvest__print, .notice { display: none !important; }
		#wpcontent, #wpbody-content { margin: 0 !important; padding: 0 !important; }
		.lfuf-harvest table { border: 1px solid #000; }
	}
	</style>
	<?php
}

function render_page(): void {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	// Read-only view switch; no state change happens on GET.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$view = sanitize_key( wp_unslash( $_GET['view'] ?? '' ) );
	if ( $view === 'harvest' ) {
		render_harvest_view();
		return;
	}

	// Read-only view filter; no state change happens on GET.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$status_filter = sanitize_key( wp_unslash( $_GET['status'] ?? '' ) );
	if ( ! in_array( $status_filter, Orders\valid_statuses(), true ) ) {
		$status_filter = '';
	}

	$result = Orders\get_orders(
		[
			'status' => $status_filter,
			'limit'  => 100,
		]
	);
	$counts = [];
	foreach ( Orders\valid_statuses() as $s ) {
		$counts[ $s ] = Orders\get_orders(
			[
				'status' => $s,
				'limit'  => 1,
			]
		)['total'];
	}

	$rest_base = esc_url_raw( rest_url( 'lfuf/v1' ) );
	$nonce     = wp_create_nonce( 'wp_rest' );
	?>
	<div class="wrap">
		<h1>
			<?php esc_html_e( 'Pre-Orders', 'producerkit' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=farm-stand-preorders&view=harvest' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Harvest List', 'producerkit' ); ?>
			</a>
		</h1>

		<ul class="subsubsub">
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=farm-stand-preorders' ) ); ?>"
					<?php echo $status_filter === '' ? 'class="current"' : ''; ?>>
					<?php esc_html_e( 'All', 'producerkit' ); ?>
				</a> |
			</li>
			<?php
			$i = 0;
			foreach ( $counts as $s => $count ) :
				++$i;
				?>
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=farm-stand-preorders&status=' . $s ) ); ?>"
						<?php echo $status_filter === $s ? 'class="current"' : ''; ?>>
						<?php echo esc_html( status_label( $s ) ); ?>
						<span class="count">(<?php echo (int) $count; ?>)</span>
					</a><?php echo $i < count( $counts ) ? ' |' : ''; ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<table class="widefat striped" style="margin-top: 2.5em;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Pickup', 'producerkit' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'producerkit' ); ?></th>
					<th><?php esc_html_e( 'Items', 'producerkit' ); ?></th>
					<th><?php esc_html_e( 'Location', 'producerkit' ); ?></th>
					<th><?php esc_html_e( 'Status', 'producerkit' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'producerkit' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $result['orders'] ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No pre-orders yet.', 'producerkit' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $result['orders'] as $order ) : ?>
					<tr data-lfuf-order="<?php echo (int) $order['id']; ?>">
						<td><strong><?php echo esc_html( $order['pickup_date'] ); ?></strong></td>
						<td>
							<?php echo esc_html( $order['name'] ); ?>
							<?php if ( $order['email'] ) : ?>
								<br><a href="mailto:<?php echo esc_attr( $order['email'] ); ?>"><?php echo esc_html( $order['email'] ); ?></a>
							<?php endif; ?>
							<?php if ( $order['phone'] ) : ?>
								<br><?php echo esc_html( $order['phone'] ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php foreach ( $order['items'] as $item ) : ?>
								<?php
								printf(
									/* translators: 1: quantity, 2: product title, 3: unit. */
									esc_html__( '%1$d × %2$s %3$s', 'producerkit' ),
									(int) $item['qty'],
									esc_html( $item['title'] ),
									$item['unit'] ? esc_html( '(' . $item['unit'] . ')' ) : '',
								);
								?>
								<br>
							<?php endforeach; ?>
							<?php if ( $order['note'] ) : ?>
								<em><?php echo esc_html( $order['note'] ); ?></em>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $order['location_name'] ?: '—' ); ?></td>
						<td class="lfuf-preorder-status"><?php echo esc_html( status_label( $order['status'] ) ); ?></td>
						<td>
							<?php foreach ( next_actions()[ $order['status'] ] ?? [] as $next ) : ?>
								<button
									type="button"
									class="button button-small lfuf-preorder-action"
									data-order-id="<?php echo (int) $order['id']; ?>"
									data-next-status="<?php echo esc_attr( $next ); ?>"
								>
									<?php echo esc_html( status_label( $next ) ); ?>
								</button>
							<?php endforeach; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<script>
	( function () {
		var restBase = <?php echo wp_json_encode( $rest_base ); ?>;
		var nonce    = <?php echo wp_json_encode( $nonce ); ?>;

		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '.lfuf-preorder-action' );
			if ( ! button ) return;

			button.disabled = true;
			fetch( restBase + '/preorders/' + button.dataset.orderId + '/status', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				body: JSON.stringify( { status: button.dataset.nextStatus } ),
			} )
				.then( function ( r ) { return r.json().then( function ( d ) { return { ok: r.ok, data: d }; } ); } )
				.then( function ( result ) {
					if ( result.ok ) {
						window.location.reload();
					} else {
						alert( result.data.message || 'Could not update the pre-order.' );
						button.disabled = false;
					}
				} )
				.catch( function () {
					alert( 'Could not update the pre-order.' );
					button.disabled = false;
				} );
		} );
	} )();
	</script>
	<?php
}
