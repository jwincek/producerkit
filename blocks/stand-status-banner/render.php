<?php
/**
 * Server-side render for lfuf/stand-status-banner.
 *
 * Accessibility improvements:
 *   - <section> landmark with aria-label for navigation
 *   - aria-live="polite" on status region for polling announcements
 *   - Visually-hidden labels for icon-prefixed details
 *   - Screen-reader-only text for new-tab Venmo link
 *   - prefers-reduced-motion handled in CSS
 *   - Status communicated via text, not color alone
 *   - Configurable heading level (defaults to h2)
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$location_id     = (int) ($attributes['locationId'] ?? 0);
$show_address     = (bool) ($attributes['showAddress'] ?? true);
$show_hours       = (bool) ($attributes['showHours'] ?? true);
$show_venmo       = (bool) ($attributes['showVenmo'] ?? true);
$show_season      = (bool) ($attributes['showSeasonDates'] ?? true);
$layout           = $attributes['layout'] ?? 'banner';
$polling_enabled  = (bool) ($attributes['pollingEnabled'] ?? false);

if ($location_id < 1) {
    return;
}

$post = get_post($location_id);
if (! $post || $post->post_type !== 'lfuf_location' || $post->post_status !== 'publish') {
    return;
}

// Gather all meta.
$is_open        = (bool) get_post_meta($location_id, '_lfuf_is_open', true);
$address        = get_post_meta($location_id, '_lfuf_address', true);
$hours          = get_post_meta($location_id, '_lfuf_hours', true);
$pay_methods    = \Leftfield\Core\Payments\get_payment_methods($location_id);
$status_message = get_post_meta($location_id, '_lfuf_ss_status_message', true);
$last_toggled   = get_post_meta($location_id, '_lfuf_ss_last_toggled', true);
$season_start   = get_post_meta($location_id, '_lfuf_ss_season_start', true);
$season_end     = get_post_meta($location_id, '_lfuf_ss_season_end', true);
$auto_toggle    = (bool) get_post_meta($location_id, '_lfuf_ss_auto_toggle', true);
$schedule       = get_post_meta($location_id, '_lfuf_ss_schedule', true);

// Compute effective status.
if ($auto_toggle && $schedule) {
    $is_open = \Leftfield\StandStatus\REST\compute_schedule_status($schedule);
}
if ($season_start && $season_end) {
    $in_season = \Leftfield\StandStatus\REST\is_in_season($season_start, $season_end);
    if (! $in_season) {
        $is_open = false;
    }
} else {
    $in_season = true;
}

$status_slug  = $is_open ? 'open' : 'closed';
$status_label = $is_open
    ? __('Open Now', 'farm-stand-manager')
    : __('Closed', 'farm-stand-manager');

// Format "last updated" as relative time.
$time_ago = '';
if ($last_toggled) {
    $toggled_ts = strtotime($last_toggled);
    if ($toggled_ts) {
        $time_ago = human_time_diff($toggled_ts, current_time('timestamp')) . ' ' . __('ago', 'farm-stand-manager');
    }
}

// Next scheduled opening.
$next_open = '';
if (! $is_open && $schedule) {
    $next_open = \Leftfield\StandStatus\REST\compute_next_open($schedule);
}

// Primary payment link: the first link-kind method (legacy Venmo merges in first).
$pay_link = null;
foreach ($pay_methods as $m) {
    if ($m['is_link']) {
        $pay_link = $m;
        break;
    }
}

// Season display strings.
$season_range_full = ($season_start && $season_end)
    ? sprintf(
        /* translators: %1$s: season start date, %2$s: season end date. */
        __('Our season runs %1$s – %2$s. See you then!', 'farm-stand-manager'),
        date_i18n('F j', strtotime($season_start)),
        date_i18n('F j', strtotime($season_end)),
    )
    : '';

$season_range_short = ($season_start && $season_end)
    ? sprintf(
        /* translators: %1$s: season start date, %2$s: season end date. */
        __('Season: %1$s – %2$s', 'farm-stand-manager'),
        date_i18n('M j', strtotime($season_start)),
        date_i18n('M j', strtotime($season_end)),
    )
    : '';

// Interactivity API context.
$context = [
    'locationId'      => $location_id,
    'isOpen'          => $is_open,
    'inSeason'        => $in_season,
    'statusLabel'     => $status_label,
    'statusMessage'   => $status_message ?: '',
    'nextOpen'        => $next_open,
    'timeAgo'         => $time_ago,
    'pollingEnabled'  => $polling_enabled,
    'restBase'        => esc_url_raw(rest_url('lfuf/v1')),
];

$wrapper_attrs = get_block_wrapper_attributes([
    'class' => 'lfuf-stand-banner lfuf-stand-banner--' . esc_attr($layout) . ' lfuf-stand-banner--' . $status_slug,
]);

// Aria label for the section landmark.
$section_label = sprintf(
    /* translators: %s = stand name */
    __('%s — Farm Stand Status', 'farm-stand-manager'),
    $post->post_title,
);
?>

<section
    <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes(). ?>
    aria-label="<?php echo esc_attr($section_label); ?>"
    data-wp-interactive="leftfield/stand-status"
    <?php echo wp_interactivity_data_wp_context($context); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- returns a pre-escaped data-wp-context attribute. ?>
    data-wp-init="callbacks.initPolling"
    data-wp-class--lfuf-stand-banner--open="context.isOpen"
    data-wp-class--lfuf-stand-banner--closed="!context.isOpen"
>
    <!--
        The status region uses aria-live="polite" so screen readers
        announce changes when polling updates the open/closed state.
        "polite" waits for the user's current reading to finish.
    -->
    <div class="lfuf-stand-banner__main" aria-live="polite" aria-atomic="true">
        <div class="lfuf-stand-banner__status-row">
            <span
                class="lfuf-stand-banner__indicator"
                aria-hidden="true"
                data-wp-class--lfuf-stand-banner__indicator--open="context.isOpen"
                data-wp-class--lfuf-stand-banner__indicator--closed="!context.isOpen"
            ></span>
            <span
                class="lfuf-stand-banner__status-label"
                role="status"
                data-wp-text="context.statusLabel"
            ><?php echo esc_html($status_label); ?></span>
        </div>

        <h2 class="lfuf-stand-banner__name"><?php echo esc_html($post->post_title); ?></h2>

        <p
            class="lfuf-stand-banner__message"
            data-wp-text="context.statusMessage"
            data-wp-bind--hidden="!context.statusMessage"
            <?php echo $status_message ? '' : 'hidden'; ?>
        ><?php echo esc_html($status_message); ?></p>

        <p
            class="lfuf-stand-banner__next-open"
            data-wp-text="state.nextOpenText"
            data-wp-bind--hidden="state.hideNextOpen"
            <?php echo (! $is_open && $next_open) ? '' : 'hidden'; ?>
        ><?php
            if (! $is_open && $next_open) {
                /* translators: %s: date and time the stand next opens. */
                printf(esc_html__('Next open: %s', 'farm-stand-manager'), esc_html($next_open));
            }
        ?></p>

        <?php if ($time_ago) : ?>
            <span
                class="lfuf-stand-banner__updated"
                data-wp-text="state.updatedText"
            ><?php
                /* translators: %s: human-readable time since the last status update (e.g. "5 mins ago"). */
                printf(esc_html__('Updated %s', 'farm-stand-manager'), esc_html($time_ago));
            ?></span>
        <?php endif; ?>
    </div>

    <div class="lfuf-stand-banner__details">
        <?php if (! $in_season && $show_season && $season_range_full) : ?>
            <p class="lfuf-stand-banner__off-season">
                <?php echo esc_html($season_range_full); ?>
            </p>
        <?php endif; ?>

        <?php if ($in_season && $show_season && $season_range_short) : ?>
            <p class="lfuf-stand-banner__season-note">
                <?php echo esc_html($season_range_short); ?>
            </p>
        <?php endif; ?>

        <?php if ($show_address && $address) : ?>
            <p class="lfuf-stand-banner__address">
                <span class="lfuf-stand-banner__icon" aria-hidden="true">📍</span>
                <span class="screen-reader-text"><?php esc_html_e('Address:', 'farm-stand-manager'); ?> </span>
                <?php echo esc_html($address); ?>
            </p>
        <?php endif; ?>

        <?php if ($show_hours && $hours) : ?>
            <p class="lfuf-stand-banner__hours">
                <span class="lfuf-stand-banner__icon" aria-hidden="true">🕐</span>
                <span class="screen-reader-text"><?php esc_html_e('Hours:', 'farm-stand-manager'); ?> </span>
                <?php echo esc_html($hours); ?>
            </p>
        <?php endif; ?>

        <?php if ($show_venmo && $pay_link) : ?>
            <a class="lfuf-stand-banner__venmo-link"
               href="<?php echo esc_url($pay_link['url']); ?>"
               target="_blank"
               rel="noopener noreferrer">
                <span class="lfuf-stand-banner__icon" aria-hidden="true">💸</span>
                <?php
                if (in_array($pay_link['type'], ['venmo', 'cashapp', 'paypal'], true)) {
                    printf(
                        /* translators: 1: payment service name, 2: account handle. */
                        esc_html__('Pay with %1$s (@%2$s)', 'farm-stand-manager'),
                        esc_html($pay_link['label']),
                        esc_html($pay_link['value']),
                    );
                } else {
                    printf(
                        /* translators: %s: payment method label. */
                        esc_html__('Pay online: %s', 'farm-stand-manager'),
                        esc_html($pay_link['label']),
                    );
                }
                ?>
                <span class="screen-reader-text"><?php esc_html_e('(opens in a new tab)', 'farm-stand-manager'); ?></span>
            </a>
        <?php endif; ?>
    </div>
</section>