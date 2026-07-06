<?php
/**
 * Server-side render for lfuf/location-info.
 *
 * Accessibility: <section> with aria-label, screen-reader labels
 * on address/hours, new-tab warning on Venmo link, role="status"
 * on open/closed badge.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$location_id = (int) ($attributes['locationId'] ?? 0);
$show_venmo  = (bool) ($attributes['showVenmo'] ?? true);
$show_status = (bool) ($attributes['showStatus'] ?? true);

if ($location_id < 1) {
    return;
}

$location = get_post($location_id);
if (! $location || $location->post_type !== 'lfuf_location' || $location->post_status !== 'publish') {
    return;
}

$address       = get_post_meta($location_id, '_lfuf_address', true);
$location_type = get_post_meta($location_id, '_lfuf_location_type', true);
$venmo_handle  = get_post_meta($location_id, '_lfuf_venmo_handle', true);
$hours         = get_post_meta($location_id, '_lfuf_hours', true);
$is_open       = (bool) get_post_meta($location_id, '_lfuf_is_open', true);

// Compute effective status from schedule + season (matches stand-status-banner logic).
$auto_toggle  = (bool) get_post_meta($location_id, '_lfuf_ss_auto_toggle', true);
$schedule     = get_post_meta($location_id, '_lfuf_ss_schedule', true);
$season_start = get_post_meta($location_id, '_lfuf_ss_season_start', true);
$season_end   = get_post_meta($location_id, '_lfuf_ss_season_end', true);

if ($auto_toggle && $schedule && function_exists('\\Leftfield\\StandStatus\\REST\\compute_schedule_status')) {
    $is_open = \Leftfield\StandStatus\REST\compute_schedule_status($schedule);
}
if ($season_start && $season_end && function_exists('\\Leftfield\\StandStatus\\REST\\is_in_season')) {
    if (! \Leftfield\StandStatus\REST\is_in_season($season_start, $season_end)) {
        $is_open = false;
    }
}

$wrapper_attrs = get_block_wrapper_attributes([
    'class' => 'lfuf-location-info',
]);

$section_label = sprintf(
    /* translators: %s = location name */
    __('%s — Location Details', 'farm-stand-manager'),
    $location->post_title,
);
?>

<section <?php echo $wrapper_attrs; ?> aria-label="<?php echo esc_attr($section_label); ?>">
    <div class="lfuf-location-info__header">
        <h3 class="lfuf-location-info__title">
            <?php echo esc_html($location->post_title); ?>
        </h3>

        <?php if ($show_status) : ?>
            <span
                class="lfuf-location-info__status lfuf-location-info__status--<?php echo $is_open ? 'open' : 'closed'; ?>"
                role="status"
            >
                <?php echo $is_open ? esc_html__('Open Now', 'farm-stand-manager') : esc_html__('Closed', 'farm-stand-manager'); ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if ($location_type) : ?>
        <span class="lfuf-location-info__type">
            <?php echo esc_html(ucfirst($location_type)); ?>
        </span>
    <?php endif; ?>

    <?php if ($address) : ?>
        <p class="lfuf-location-info__address">
            <span class="screen-reader-text"><?php esc_html_e('Address:', 'farm-stand-manager'); ?> </span>
            <?php echo esc_html($address); ?>
        </p>
    <?php endif; ?>

    <?php if ($hours) : ?>
        <p class="lfuf-location-info__hours">
            <span class="screen-reader-text"><?php esc_html_e('Hours:', 'farm-stand-manager'); ?> </span>
            <?php echo esc_html($hours); ?>
        </p>
    <?php endif; ?>

    <?php if ($show_venmo && $venmo_handle) : ?>
        <a
            class="lfuf-location-info__venmo"
            href="<?php echo esc_url('https://venmo.com/' . ltrim($venmo_handle, '@')); ?>"
            target="_blank"
            rel="noopener noreferrer"
        >
            <?php printf(esc_html__('Pay via Venmo (@%s)', 'farm-stand-manager'), esc_html(ltrim($venmo_handle, '@'))); ?>
            <span class="screen-reader-text"><?php esc_html_e('(opens in a new tab)', 'farm-stand-manager'); ?></span>
        </a>
    <?php endif; ?>
</section>