<?php
/**
 * Server-side render for producerkit/stand-hours-schedule.
 *
 * Accessibility: Uses a proper <table> for the schedule grid
 * (it IS tabular data — day/hours), aria-current="date" on today's
 * row, aria-label on the section, screen-reader labels on fallback.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$location_id     = (int) ( $attributes['locationId'] ?? 0 );
$highlight_today = (bool) ( $attributes['highlightToday'] ?? true );

if ( $location_id < 1 ) {
	return;
}

$post = get_post( $location_id );
if ( ! $post || $post->post_type !== 'pkit_location' || $post->post_status !== 'publish' ) {
	return;
}

$schedule_json  = get_post_meta( $location_id, '_pkit_ss_schedule', true );
$schedule       = $schedule_json ? json_decode( $schedule_json, true ) : [];
$hours_fallback = get_post_meta( $location_id, '_pkit_hours', true );

$by_day = [];
if ( is_array( $schedule ) ) {
	foreach ( $schedule as $entry ) {
		$day = (int) ( $entry['day'] ?? -1 );
		if ( $day >= 0 && $day <= 6 ) {
			$by_day[ $day ][] = $entry;
		}
	}
}

$today     = (int) current_datetime()->format( 'w' );
$day_names = [
	0 => __( 'Sunday', 'producerkit' ),
	1 => __( 'Monday', 'producerkit' ),
	2 => __( 'Tuesday', 'producerkit' ),
	3 => __( 'Wednesday', 'producerkit' ),
	4 => __( 'Thursday', 'producerkit' ),
	5 => __( 'Friday', 'producerkit' ),
	6 => __( 'Saturday', 'producerkit' ),
];

$wrapper_attrs = get_block_wrapper_attributes(
	[
		'class' => 'pkit-stand-schedule',
	]
);

$section_label = sprintf(
	/* translators: %s = location name */
	__( '%s — Weekly Schedule', 'producerkit' ),
	$post->post_title,
);
?>

<section <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes(). ?> aria-label="<?php echo esc_attr( $section_label ); ?>">
	<?php if ( empty( $by_day ) ) : ?>
		<?php if ( $hours_fallback ) : ?>
			<p class="pkit-stand-schedule__fallback">
				<span aria-hidden="true">🕐</span>
				<span class="screen-reader-text"><?php esc_html_e( 'Hours:', 'producerkit' ); ?> </span>
				<?php echo esc_html( $hours_fallback ); ?>
			</p>
		<?php else : ?>
			<p class="pkit-stand-schedule__empty">
				<?php esc_html_e( 'No schedule set yet.', 'producerkit' ); ?>
			</p>
		<?php endif; ?>
	<?php else : ?>
		<table class="pkit-stand-schedule__table" role="table">
			<caption class="screen-reader-text">
				<?php echo esc_html( $section_label ); ?>
			</caption>
			<thead class="screen-reader-text">
				<tr>
					<th scope="col"><?php esc_html_e( 'Day', 'producerkit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Hours', 'producerkit' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				for ( $d = 0; $d <= 6; $d++ ) :
					$is_today  = $highlight_today && $d === $today;
					$has_hours = isset( $by_day[ $d ] );
					$classes   = 'pkit-stand-schedule__day';
					if ( $is_today ) {
						$classes .= ' pkit-stand-schedule__day--today';
					}
					if ( ! $has_hours ) {
						$classes .= ' pkit-stand-schedule__day--closed';
					}
					?>
					<tr
						class="<?php echo esc_attr( $classes ); ?>"
						<?php echo $is_today ? 'aria-current="date"' : ''; ?>
					>
						<th scope="row" class="pkit-stand-schedule__day-label">
							<?php echo esc_html( $day_names[ $d ] ); ?>
							<?php if ( $is_today ) : ?>
								<span class="pkit-stand-schedule__today-badge">
									<?php esc_html_e( 'Today', 'producerkit' ); ?>
								</span>
							<?php endif; ?>
						</th>
						<td class="pkit-stand-schedule__day-hours">
							<?php
							if ( $has_hours ) :
								$time_strings = array_map(
									function ( $e ) {
										$open  = date_i18n( 'g:i A', strtotime( '2000-01-01 ' . ( $e['open'] ?? '00:00' ) ) );
										$close = date_i18n( 'g:i A', strtotime( '2000-01-01 ' . ( $e['close'] ?? '23:59' ) ) );
										return $open . ' – ' . $close;
									},
									$by_day[ $d ]
								);
								echo esc_html( implode( ', ', $time_strings ) );
							else :
								?>
								<?php esc_html_e( 'Closed', 'producerkit' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endfor; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>