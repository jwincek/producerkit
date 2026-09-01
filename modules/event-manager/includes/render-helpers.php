<?php
/**
 * Shared event card rendering helper.
 *
 * Accessibility improvements:
 *   - <article> with aria-label summarizing the event
 *   - Screen-reader labels on emoji-prefixed content
 *   - Donation link has "(opens in a new tab)" SR text
 *   - RSVP form inputs have proper <label> elements
 *   - RSVP section is aria-live for headcount announcements
 *   - Cancelled badge uses role="status" for prominence
 *   - Honeypot field for spam prevention
 *   - Image alt="" when decorative (name is in heading)
 */

declare(strict_types=1);

namespace ProducerKit\EventManager\Render;

defined( 'ABSPATH' ) || exit;

function render_event_card( array $event, bool $show_image, bool $show_rsvp, bool $show_location ): string {
	$id        = (int) $event['id'];
	$cancelled = (bool) $event['cancelled'];
	$start_ts  = $event['start'] ? strtotime( $event['start'] ) : 0;
	$end_ts    = $event['end'] ? strtotime( $event['end'] ) : 0;
	$type_slug = $event['event_slugs'][0] ?? '';
	$rsvp      = $event['rsvp'];

	// Format date/time.
	$date_str = $start_ts ? date_i18n( 'l, F j', $start_ts ) : '';
	$time_str = $start_ts ? date_i18n( 'g:i A', $start_ts ) : '';
	if ( $end_ts ) {
		$time_str .= ' – ' . date_i18n( 'g:i A', $end_ts );
	}

	// Build rich aria-label for the article.
	$aria_parts = [ $event['title'] ];
	if ( $cancelled ) {
		$aria_parts[] = __( 'Cancelled', 'producerkit' );
	}
	if ( $date_str ) {
		$aria_parts[] = $date_str;
	}
	if ( $time_str ) {
		$aria_parts[] = $time_str;
	}
	if ( $event['event_types'] ) {
		$aria_parts[] = $event['event_types'][0];
	}
	$article_aria_label = implode( ' — ', $aria_parts );

	// Unique ID prefix for form label associations.
	$uid = 'lfuf-rsvp-' . $id;

	// Interactivity API context for filtering + RSVP.
	$rsvp_context = wp_json_encode(
		[
			'eventType'     => $type_slug,
			'eventId'       => $id,
			'rsvpName'      => '',
			'rsvpEmail'     => '',
			'rsvpSize'      => 1,
			'rsvpNote'      => '',
			'rsvpSubmitted' => false,
			'rsvpMessage'   => '',
			'rsvpToken'     => '',
			'rsvpError'     => '',
			'submitting'    => false,
			'headcount'     => $rsvp ? (int) $rsvp['headcount'] : 0,
			'spotsLeft'     => $rsvp ? $rsvp['spots_left'] : null,
			'isFull'        => $rsvp ? (bool) $rsvp['is_full'] : false,
		]
	);

	ob_start();
	?>
	<article
		class="lfuf-event-card<?php echo $cancelled ? ' lfuf-event-card--cancelled' : ''; ?>"
		data-type-slug="<?php echo esc_attr( $type_slug ); ?>"
		data-wp-bind--hidden="state.isEventHidden"
		data-wp-context='<?php echo esc_attr( $rsvp_context ); ?>'
		aria-label="<?php echo esc_attr( $article_aria_label ); ?>"
	>
		<?php if ( $show_image && $event['thumbnail_url'] ) : ?>
			<div class="lfuf-event-card__image">
				<img src="<?php echo esc_url( $event['thumbnail_url'] ); ?>"
					alt=""
					loading="lazy">
			</div>
		<?php endif; ?>

		<div class="lfuf-event-card__body">
			<div class="lfuf-event-card__header">
				<?php if ( $event['event_types'] ) : ?>
					<span class="lfuf-event-card__type-badge">
						<?php echo esc_html( $event['event_types'][0] ); ?>
					</span>
				<?php endif; ?>

				<?php if ( $cancelled ) : ?>
					<span class="lfuf-event-card__cancelled-badge" role="status">
						<?php esc_html_e( 'Cancelled', 'producerkit' ); ?>
					</span>
				<?php endif; ?>
			</div>

			<h3 class="lfuf-event-card__title">
				<a href="<?php echo esc_url( $event['permalink'] ); ?>">
					<?php echo esc_html( $event['title'] ); ?>
				</a>
			</h3>

			<?php if ( $date_str ) : ?>
				<p class="lfuf-event-card__datetime">
					<span class="screen-reader-text"><?php esc_html_e( 'Date:', 'producerkit' ); ?> </span>
					<span class="lfuf-event-card__date"><?php echo esc_html( $date_str ); ?></span>
					<?php if ( $time_str ) : ?>
						<span class="lfuf-event-card__time">
							<span class="screen-reader-text"><?php esc_html_e( 'Time:', 'producerkit' ); ?> </span>
							<?php echo esc_html( $time_str ); ?>
						</span>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<?php if ( $show_location && $event['location'] ) : ?>
				<p class="lfuf-event-card__location">
					<span class="lfuf-event-card__icon" aria-hidden="true">📍</span>
					<span class="screen-reader-text"><?php esc_html_e( 'Location:', 'producerkit' ); ?> </span>
					<?php echo esc_html( $event['location']['title'] ); ?>
					<?php if ( $event['location']['address'] ) : ?>
						<span class="lfuf-event-card__address">— <?php echo esc_html( $event['location']['address'] ); ?></span>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<?php if ( $event['excerpt'] ) : ?>
				<p class="lfuf-event-card__excerpt"><?php echo esc_html( $event['excerpt'] ); ?></p>
			<?php endif; ?>

			<?php if ( $event['cost_note'] || $event['what_to_bring'] ) : ?>
				<div class="lfuf-event-card__details">
					<?php if ( $event['cost_note'] ) : ?>
						<span class="lfuf-event-card__cost">
							<span aria-hidden="true">💸</span>
							<span class="screen-reader-text"><?php esc_html_e( 'Cost:', 'producerkit' ); ?> </span>
							<?php echo esc_html( $event['cost_note'] ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $event['what_to_bring'] ) : ?>
						<span class="lfuf-event-card__bring">
							<span aria-hidden="true">🧺</span>
							<span class="screen-reader-text"><?php esc_html_e( 'What to bring:', 'producerkit' ); ?> </span>
							<?php echo esc_html( $event['what_to_bring'] ); ?>
						</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $event['donation_link'] && ! $cancelled ) : ?>
				<a class="lfuf-event-card__donate-link"
					href="<?php echo esc_url( $event['donation_link'] ); ?>"
					target="_blank"
					rel="noopener noreferrer">
					<?php esc_html_e( 'Donate / Pay', 'producerkit' ); ?>
					<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'producerkit' ); ?></span>
				</a>
			<?php endif; ?>

			<?php if ( $show_rsvp && $rsvp && $rsvp['enabled'] && ! $cancelled ) : ?>
				<div class="lfuf-event-card__rsvp" aria-live="polite">
					<div class="lfuf-event-card__rsvp-summary">
						<span data-wp-text="state.rsvpSummaryText">
							<?php
							printf(
								/* translators: %d: number of people who have RSVPed. */
								esc_html__( '%d people coming', 'producerkit' ),
								(int) $rsvp['headcount'],
							);
							if ( $rsvp['spots_left'] !== null ) {
								printf(
									' · %s',
									sprintf(
									/* translators: %d: number of RSVP spots remaining. */
										esc_html__( '%d spots left', 'producerkit' ),
										(int) $rsvp['spots_left'],
									)
								);
							}
							?>
						</span>
					</div>

					<div data-wp-bind--hidden="context.rsvpSubmitted">
						<?php if ( ! $rsvp['closed'] && ! $rsvp['is_full'] ) : ?>
							<div class="lfuf-event-card__rsvp-form" role="group" aria-label="<?php esc_attr_e( 'RSVP form', 'producerkit' ); ?>">
								<div class="lfuf-event-card__rsvp-field">
									<label for="<?php echo esc_attr( $uid ); ?>-name" class="screen-reader-text">
										<?php esc_html_e( 'Your name', 'producerkit' ); ?>
									</label>
									<input
										type="text"
										id="<?php echo esc_attr( $uid ); ?>-name"
										class="lfuf-event-card__rsvp-input"
										placeholder="<?php esc_attr_e( 'Your name', 'producerkit' ); ?>"
										autocomplete="name"
										data-wp-on--input="actions.updateRsvpName"
										data-wp-bind--value="context.rsvpName"
										required
									>
								</div>
								<div class="lfuf-event-card__rsvp-field">
									<label for="<?php echo esc_attr( $uid ); ?>-size" class="screen-reader-text">
										<?php esc_html_e( 'Party size', 'producerkit' ); ?>
									</label>
									<input
										type="number"
										id="<?php echo esc_attr( $uid ); ?>-size"
										class="lfuf-event-card__rsvp-size"
										min="1"
										max="10"
										autocomplete="off"
										data-wp-on--input="actions.updateRsvpSize"
										data-wp-bind--value="context.rsvpSize"
									>
								</div>

								<!-- Honeypot: hidden from humans, bots fill it -->
								<div class="lfuf-event-card__rsvp-hp" aria-hidden="true" tabindex="-1">
									<label for="<?php echo esc_attr( $uid ); ?>-website">Website</label>
									<input
										type="text"
										id="<?php echo esc_attr( $uid ); ?>-website"
										name="website"
										autocomplete="off"
										tabindex="-1"
										data-wp-on--input="actions.updateHoneypot"
									>
								</div>

								<button
									type="button"
									class="lfuf-event-card__rsvp-btn"
									data-wp-on--click="actions.submitRsvp"
									data-wp-bind--disabled="context.submitting"
									data-wp-text="state.rsvpButtonText"
								>
								<?php
								echo esc_html(
									get_post_meta( $id, '_lfuf_em_rsvp_label', true ) ?: __( "I'm coming!", 'producerkit' )
								);
								?>
								</button>
							</div>
							<p class="lfuf-event-card__rsvp-error"
								role="alert"
								data-wp-text="context.rsvpError"
								data-wp-bind--hidden="!context.rsvpError"></p>
						<?php elseif ( $rsvp['is_full'] ) : ?>
							<p class="lfuf-event-card__rsvp-full">
								<?php esc_html_e( 'This event is full!', 'producerkit' ); ?>
							</p>
						<?php else : ?>
							<p class="lfuf-event-card__rsvp-closed">
								<?php esc_html_e( 'RSVPs are closed.', 'producerkit' ); ?>
							</p>
						<?php endif; ?>
					</div>

					<div data-wp-bind--hidden="!context.rsvpSubmitted">
						<p class="lfuf-event-card__rsvp-success" role="status" data-wp-text="context.rsvpMessage"></p>
						<button
							type="button"
							class="lfuf-event-card__rsvp-cancel-btn"
							data-wp-on--click="actions.cancelRsvp"
						><?php esc_html_e( 'Cancel my RSVP', 'producerkit' ); ?></button>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</article>
	<?php
	return ob_get_clean();
}