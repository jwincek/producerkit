<?php
/**
 * Server-side render for producerkit/event-card.
 *
 * Renders a single event using the shared render helper
 * from the event-manager module.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$event_id      = (int) ( $attributes['eventId'] ?? 0 );
$show_image    = (bool) ( $attributes['showImage'] ?? true );
$show_rsvp     = (bool) ( $attributes['showRsvp'] ?? true );
$show_location = (bool) ( $attributes['showLocation'] ?? true );

if ( $event_id < 1 ) {
	return;
}

$event_post = get_post( $event_id );
if ( ! $event_post || $event_post->post_type !== 'pkit_event' || $event_post->post_status !== 'publish' ) {
	return;
}

// Build event data using the REST helper.
$event_data = \ProducerKit\EventManager\REST\build_event_data( $event_post );

$context = [
	'restBase' => esc_url_raw( rest_url( 'producerkit/v1' ) ),
];

$wrapper_attrs = get_block_wrapper_attributes(
	[
		'class' => 'pkit-event-card-wrapper',
	]
);
?>

<div
	<?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes(). ?>
	data-wp-interactive="producerkit"
	<?php echo wp_interactivity_data_wp_context( $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- returns a pre-escaped data-wp-context attribute. ?>
>
	<?php echo \ProducerKit\EventManager\Render\render_event_card( $event_data, $show_image, $show_rsvp, $show_location ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_event_card() escapes all output internally. ?>
</div>
