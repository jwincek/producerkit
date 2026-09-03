<?php
/**
 * The page a guest reaches from their RSVP confirmation email.
 *
 * Before this, an RSVP could be submitted and then reached by nobody. The
 * guest received no email at all — every notification went to the site admin —
 * and "Cancel my RSVP" only worked in the browser tab they had submitted from,
 * because the token lived in that page's Interactivity context and nowhere
 * else. Close the tab and the booking was permanent.
 *
 * For a capped event that is not cosmetic: a guest who cannot cancel silently
 * holds a seat, and the organiser has no way to know.
 */

declare(strict_types=1);

namespace ProducerKit\EventManager\RSVPResponse;

use ProducerKit\Core\TokenPage;
use ProducerKit\EventManager\RSVP;

defined( 'ABSPATH' ) || exit;

/** Query var carrying the RSVP token. */
const QUERY_VAR = 'pkit_rsvp';

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
 * The address to put in a confirmation email.
 */
function url_for( string $token ): string {
	return TokenPage\url_for( QUERY_VAR, $token );
}

/**
 * Intercept the request when an RSVP token is present.
 */
function maybe_render(): void {
	$token = get_query_var( QUERY_VAR );

	if ( ! is_string( $token ) || '' === $token ) {
		return;
	}

	$rsvp = RSVP\find_by_token( $token );

	if ( null === $rsvp ) {
		TokenPage\render_unknown(
			__( 'We could not find that booking', 'producerkit' ),
			__( 'It may already have been cancelled. If you think it should still be here, get in touch and we will check.', 'producerkit' )
		);
	}

	$event = get_post( (int) $rsvp['event_id'] );

	if ( TokenPage\is_submission( 'pkit_cancel_rsvp' ) ) {
		if ( ! TokenPage\verify( 'pkit_rsvp_' . $token ) ) {
			TokenPage\render_unknown(
				__( 'That form had expired', 'producerkit' ),
				__( 'Please open the link from your email again.', 'producerkit' )
			);
		}

		RSVP\cancel_rsvp( $token );

		TokenPage\render_unknown(
			__( 'Your RSVP is cancelled', 'producerkit' ),
			__( 'Thank you for letting us know — your place has been given back. You are welcome to book again if your plans change.', 'producerkit' )
		);
	}

	render_booking( $token, $rsvp, $event );
}

/**
 * Show the booking, with a way out of it.
 */
function render_booking( string $token, array $rsvp, ?\WP_Post $event ): void {
	$start = $event ? (string) get_post_meta( $event->ID, '_pkit_start_datetime', true ) : '';

	ob_start();
	?>
	<p>
		<?php
		printf(
			/* translators: %s: guest name. */
			esc_html__( 'Hello %s — here is your booking.', 'producerkit' ),
			esc_html( (string) $rsvp['name'] )
		);
		?>
	</p>

	<dl class="pkit-rsvp__details">
		<?php if ( $event ) : ?>
			<dt><?php esc_html_e( 'Event', 'producerkit' ); ?></dt>
			<dd><strong><?php echo esc_html( get_the_title( $event ) ); ?></strong></dd>
		<?php endif; ?>
		<?php if ( '' !== $start ) : ?>
			<dt><?php esc_html_e( 'When', 'producerkit' ); ?></dt>
			<dd>
				<?php
				echo esc_html(
					wp_date(
						(string) get_option( 'date_format' ) . ', ' . (string) get_option( 'time_format' ),
						(int) strtotime( $start )
					)
				);
				?>
			</dd>
		<?php endif; ?>
		<dt><?php esc_html_e( 'Party size', 'producerkit' ); ?></dt>
		<dd><?php echo esc_html( (string) (int) $rsvp['party_size'] ); ?></dd>
		<?php if ( ! empty( $rsvp['note'] ) ) : ?>
			<dt><?php esc_html_e( 'Your note', 'producerkit' ); ?></dt>
			<dd><?php echo esc_html( (string) $rsvp['note'] ); ?></dd>
		<?php endif; ?>
	</dl>

	<?php if ( $event ) : ?>
		<p><a href="<?php echo esc_url( (string) get_permalink( $event ) ); ?>"><?php esc_html_e( 'See the event page', 'producerkit' ); ?></a></p>
	<?php endif; ?>

	<form method="post" class="pkit-rsvp__actions">
		<?php wp_nonce_field( 'pkit_rsvp_' . $token, 'pkit_token_nonce' ); ?>
		<button type="submit" name="pkit_cancel_rsvp" value="1" class="wp-element-button is-style-outline">
			<?php esc_html_e( 'Cancel my RSVP', 'producerkit' ); ?>
		</button>
	</form>

	<p style="opacity:.75;font-size:.9em;">
		<?php esc_html_e( 'Cancelling frees your place for someone else. There is no charge either way.', 'producerkit' ); ?>
	</p>
	<?php

	TokenPage\render( __( 'Your booking', 'producerkit' ), (string) ob_get_clean() );
}
