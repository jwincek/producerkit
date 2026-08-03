<?php
/**
 * Server-side render for lfuf/event-list.
 *
 * Renders upcoming events with Interactivity API directives
 * for type filtering and inline RSVP form submission.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$show_past         = (bool) ( $attributes['showPastEvents'] ?? false );
$per_page          = (int) ( $attributes['perPage'] ?? 10 );
$show_images       = (bool) ( $attributes['showImages'] ?? true );
$show_rsvp         = (bool) ( $attributes['showRsvp'] ?? true );
$show_location     = (bool) ( $attributes['showLocation'] ?? true );
$show_type_filters = (bool) ( $attributes['showTypeFilters'] ?? true );
$empty_message     = $attributes['emptyMessage'] ?? __( 'No upcoming events right now — check back soon!', 'farm-stand-manager' );

// Fetch upcoming events.
$request = new \WP_REST_Request( 'GET', '/lfuf/v1/events/upcoming' );
$request->set_param( 'per_page', $per_page );
$response = \Leftfield\EventManager\REST\get_upcoming_events( $request );
$upcoming = $response->get_data();

// Fetch past events if enabled.
$past = [];
if ( $show_past ) {
	$past_request = new \WP_REST_Request( 'GET', '/lfuf/v1/events/past' );
	$past_request->set_param( 'per_page', $per_page );
	$past_response = \Leftfield\EventManager\REST\get_past_events( $past_request );
	$past          = $past_response->get_data();
}

// Collect event type terms for filters.
$all_types    = get_terms(
	[
		'taxonomy'   => 'lfuf_event_type',
		'hide_empty' => true,
	]
);
$filter_types = [];
if ( $all_types && ! is_wp_error( $all_types ) ) {
	foreach ( $all_types as $term ) {
		$filter_types[] = [
			'slug'  => $term->slug,
			'label' => $term->name,
		];
	}
}

$has_events = ! empty( $upcoming ) || ! empty( $past );

// Interactivity API state + context.
wp_interactivity_state(
	'leftfield/event-list',
	[
		'activeTypeFilter' => '',
	]
);

$context = [
	'showPast' => $show_past,
	'restBase' => esc_url_raw( rest_url( 'lfuf/v1' ) ),
];

$wrapper_attrs = get_block_wrapper_attributes(
	[
		'class' => 'lfuf-event-list',
	]
);
?>

<div
	<?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes(). ?>
	data-wp-interactive="leftfield/event-list"
	<?php echo wp_interactivity_data_wp_context( $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- returns a pre-escaped data-wp-context attribute. ?>
>
	<?php if ( ! $has_events ) : ?>
		<p class="lfuf-event-list__empty"><?php echo esc_html( $empty_message ); ?></p>
	<?php else : ?>

		<!-- ── Type filters ── -->
		<?php if ( $show_type_filters && count( $filter_types ) > 1 ) : ?>
			<div class="lfuf-event-list__filters">
				<button
					type="button"
					class="lfuf-event-list__filter-btn lfuf-event-list__filter-btn--active"
					data-wp-on--click="actions.setTypeFilter"
					data-wp-context='<?php echo esc_attr( wp_json_encode( [ 'filterType' => '' ] ) ); ?>'
					data-wp-class--lfuf-event-list__filter-btn--active="state.isCurrentTypeActive"
					data-wp-bind--aria-pressed="state.isCurrentTypeActive"
					aria-pressed="true"
				><?php esc_html_e( 'All Events', 'farm-stand-manager' ); ?></button>
				<?php foreach ( $filter_types as $ft ) : ?>
					<button
						type="button"
						class="lfuf-event-list__filter-btn"
						data-wp-on--click="actions.setTypeFilter"
						data-wp-context='<?php echo esc_attr( wp_json_encode( [ 'filterType' => $ft['slug'] ] ) ); ?>'
						data-wp-class--lfuf-event-list__filter-btn--active="state.isCurrentTypeActive"
						data-wp-bind--aria-pressed="state.isCurrentTypeActive"
						aria-pressed="false"
					><?php echo esc_html( $ft['label'] ); ?></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<!-- ── Upcoming events ── -->
		<?php if ( ! empty( $upcoming ) ) : ?>
			<div class="lfuf-event-list__section">
				<h3 class="lfuf-event-list__section-title"><?php esc_html_e( 'Upcoming', 'farm-stand-manager' ); ?></h3>
				<?php
				foreach ( $upcoming as $event ) :
					echo \Leftfield\EventManager\Render\render_event_card( $event, $show_images, $show_rsvp, $show_location ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_event_card() escapes all output internally.
				endforeach;
				?>
			</div>
		<?php endif; ?>

		<!-- ── Past events ── -->
		<?php if ( $show_past && ! empty( $past ) ) : ?>
			<div class="lfuf-event-list__section lfuf-event-list__section--past">
				<h3 class="lfuf-event-list__section-title"><?php esc_html_e( 'Past Events', 'farm-stand-manager' ); ?></h3>
				<?php
				foreach ( $past as $event ) :
					echo \Leftfield\EventManager\Render\render_event_card( $event, $show_images, false, $show_location ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_event_card() escapes all output internally.
				endforeach;
				?>
			</div>
		<?php endif; ?>

	<?php endif; ?>
</div>


