<?php
/**
 * The page a customer reaches from their pre-order confirmation.
 *
 * The form told them, in as many words, to "keep this cancellation code in
 * case your plans change" — and then gave them nowhere to use it. The code was
 * shown once on the success panel, the confirmation email left it out
 * entirely, and the only thing that accepted it was a REST route nobody
 * outside the plugin would think to call.
 *
 * So the promise was made and never kept: a customer who could not come had to
 * telephone, and an order nobody cancelled turned into produce picked for a
 * pickup that never happened.
 *
 * Third use of the token-page shape, after commission quotes and RSVPs, which
 * is why Core\TokenPage exists.
 */

declare(strict_types=1);

namespace ProducerKit\PreOrder\OrderResponse;

use ProducerKit\Core\TokenPage;
use ProducerKit\PreOrder\Orders;

defined( 'ABSPATH' ) || exit;

/** Query var carrying the pre-order token. */
const QUERY_VAR = 'pkit_preorder';

add_filter( 'query_vars', __NAMESPACE__ . '\\register_query_var' );
add_action( 'template_redirect', __NAMESPACE__ . '\\maybe_render' );

/**
 * @param string[] $vars
 * @return string[]
 */
function register_query_var( array $vars ): array {
	$vars[] = QUERY_VAR;
	return $vars;
}

/**
 * The address to put in a confirmation email, or behind the success panel.
 */
function url_for( string $token ): string {
	return TokenPage\url_for( QUERY_VAR, $token );
}

/**
 * Intercept the request when a pre-order token is present.
 */
function maybe_render(): void {
	$token = get_query_var( QUERY_VAR );

	if ( ! is_string( $token ) || '' === $token ) {
		return;
	}

	$order = Orders\get_order_by_token( $token );

	if ( null === $order ) {
		TokenPage\render_unknown(
			__( 'We could not find that order', 'producerkit' ),
			__( 'The link may be incomplete, or the order may already have been collected. Please get in touch and we will check.', 'producerkit' )
		);
	}

	if ( TokenPage\is_submission( 'pkit_cancel_preorder' ) ) {
		if ( ! TokenPage\verify( 'pkit_preorder_' . $token ) ) {
			TokenPage\render_unknown(
				__( 'That form had expired', 'producerkit' ),
				__( 'Please open the link from your confirmation again.', 'producerkit' )
			);
		}

		$result = Orders\cancel_order_by_token( $token );

		if ( is_wp_error( $result ) ) {
			// Most often "no longer cancellable" — the order is already packed
			// or collected. Say so plainly rather than pretending it worked.
			TokenPage\render_unknown(
				__( 'We could not cancel that', 'producerkit' ),
				$result->get_error_message()
			);
		}

		TokenPage\render_unknown(
			__( 'Your pre-order is cancelled', 'producerkit' ),
			__( 'Thank you for letting us know — nothing will be set aside. You are welcome to order again any time.', 'producerkit' )
		);
	}

	render_order( $token, (array) $order );
}

/**
 * Show the order, and offer a way out of it while that is still possible.
 */
function render_order( string $token, array $order ): void {
	$cancellable = in_array( (string) $order['status'], [ 'pending', 'confirmed' ], true );
	$location    = (int) ( $order['location_id'] ?? 0 ) > 0 ? get_post( (int) $order['location_id'] ) : null;

	ob_start();
	?>
	<p>
		<?php
		printf(
			/* translators: %s: customer name. */
			esc_html__( 'Hello %s — here is your order.', 'producerkit' ),
			esc_html( (string) $order['name'] )
		);
		?>
	</p>

	<dl class="pkit-preorder__details">
		<dt><?php esc_html_e( 'Pickup', 'producerkit' ); ?></dt>
		<dd>
			<strong>
				<?php
				echo esc_html(
					wp_date( (string) get_option( 'date_format' ), (int) strtotime( (string) $order['pickup_date'] ) )
				);
				?>
			</strong>
			<?php if ( $location ) : ?>
				— <?php echo esc_html( get_the_title( $location ) ); ?>
			<?php endif; ?>
		</dd>

		<dt><?php esc_html_e( 'Status', 'producerkit' ); ?></dt>
		<dd><?php echo esc_html( status_label( (string) $order['status'] ) ); ?></dd>
	</dl>

	<?php if ( ! empty( $order['items'] ) ) : ?>
		<table class="pkit-preorder__items" style="width:100%;border-collapse:collapse;margin:1em 0;">
			<tbody>
			<?php foreach ( (array) $order['items'] as $item ) : ?>
				<tr>
					<td style="padding:.4em 0;border-bottom:1px solid rgba(0,0,0,.1);">
						<?php echo esc_html( (string) $item['qty'] ); ?> ×
						<?php echo esc_html( (string) $item['title'] ); ?>
						<?php if ( ! empty( $item['unit'] ) ) : ?>
							<span style="opacity:.7;">(<?php echo esc_html( (string) $item['unit'] ); ?>)</span>
						<?php endif; ?>
					</td>
					<td style="padding:.4em 0;border-bottom:1px solid rgba(0,0,0,.1);text-align:right;">
						<?php echo esc_html( (string) $item['price'] ); ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( $cancellable ) : ?>
		<form method="post" class="pkit-preorder__actions">
			<?php wp_nonce_field( 'pkit_preorder_' . $token, 'pkit_token_nonce' ); ?>
			<button type="submit" name="pkit_cancel_preorder" value="1" class="wp-element-button is-style-outline">
				<?php esc_html_e( 'Cancel this order', 'producerkit' ); ?>
			</button>
		</form>
		<p style="opacity:.75;font-size:.9em;">
			<?php esc_html_e( 'Cancelling early means nothing is picked or set aside for you. There is nothing to pay either way.', 'producerkit' ); ?>
		</p>
	<?php else : ?>
		<p style="opacity:.75;">
			<?php esc_html_e( 'This order can no longer be cancelled online. Please get in touch if something has changed.', 'producerkit' ); ?>
		</p>
	<?php endif; ?>
	<?php

	TokenPage\render( __( 'Your pre-order', 'producerkit' ), (string) ob_get_clean() );
}

/**
 * Customer-facing wording for a status.
 *
 * Deliberately not the admin's vocabulary: "picked_up" is a database value and
 * "Collected" is what a person says.
 */
function status_label( string $status ): string {
	return match ( $status ) {
		'pending'   => __( 'Received — we will confirm shortly', 'producerkit' ),
		'confirmed' => __( 'Confirmed', 'producerkit' ),
		'ready'     => __( 'Ready for collection', 'producerkit' ),
		'picked_up' => __( 'Collected', 'producerkit' ),
		'cancelled' => __( 'Cancelled', 'producerkit' ),
		default     => $status,
	};
}
