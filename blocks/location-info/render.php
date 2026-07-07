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
$show_qr     = (bool) ($attributes['showQR'] ?? false);

if ($location_id < 1) {
    return;
}

$location = get_post($location_id);
if (! $location || $location->post_type !== 'lfuf_location' || $location->post_status !== 'publish') {
    return;
}

$address         = get_post_meta($location_id, '_lfuf_address', true);
$location_type   = get_post_meta($location_id, '_lfuf_location_type', true);
$payment_methods = \Leftfield\Core\Payments\get_payment_methods($location_id);
$hours           = get_post_meta($location_id, '_lfuf_hours', true);
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

<section <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes(). ?> aria-label="<?php echo esc_attr($section_label); ?>">
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
            <?php
            // Directions destination: coordinates when set, else the address.
            $lat = (float) get_post_meta($location_id, '_lfuf_lat', true);
            $lng = (float) get_post_meta($location_id, '_lfuf_lng', true);
            $destination = ($lat !== 0.0 || $lng !== 0.0) ? $lat . ',' . $lng : $address;
            ?>
            <a
                class="lfuf-location-info__directions"
                href="<?php echo esc_url('https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($destination)); ?>"
                target="_blank"
                rel="noopener noreferrer"
            >
                <?php esc_html_e('Get directions', 'farm-stand-manager'); ?>
                <span class="screen-reader-text"><?php esc_html_e('(opens in a new tab)', 'farm-stand-manager'); ?></span>
            </a>
        </p>
    <?php endif; ?>

    <?php if ($hours) : ?>
        <p class="lfuf-location-info__hours">
            <span class="screen-reader-text"><?php esc_html_e('Hours:', 'farm-stand-manager'); ?> </span>
            <?php echo esc_html($hours); ?>
        </p>
    <?php endif; ?>

    <?php // showVenmo is the stored attribute key (pre-payment-methods content); it now means "show payment options". ?>
    <?php if ($show_venmo && $payment_methods) : ?>
        <div class="lfuf-location-info__payments">
            <span class="lfuf-location-info__payments-label"><?php esc_html_e('Payment options:', 'farm-stand-manager'); ?></span>
            <ul class="lfuf-location-info__payments-list">
                <?php foreach ($payment_methods as $method) : ?>
                    <li class="lfuf-location-info__payment lfuf-location-info__payment--<?php echo esc_attr($method['type']); ?>">
                        <?php if ($method['is_link']) : ?>
                            <a
                                class="lfuf-location-info__payment-link"
                                href="<?php echo esc_url($method['url']); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <?php
                                if (in_array($method['type'], ['venmo', 'cashapp', 'paypal'], true)) {
                                    printf(
                                        /* translators: 1: payment service name, 2: account handle. */
                                        esc_html__('%1$s (@%2$s)', 'farm-stand-manager'),
                                        esc_html($method['label']),
                                        esc_html($method['value']),
                                    );
                                } else {
                                    echo esc_html($method['label']);
                                }
                                ?>
                                <span class="screen-reader-text"><?php esc_html_e('(opens in a new tab)', 'farm-stand-manager'); ?></span>
                            </a>
                        <?php else : ?>
                            <span class="lfuf-location-info__payment-badge"><?php echo esc_html($method['label']); ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php
            // QR code for the first payment link (rendered client-side by lfuf-qr).
            $qr_link = null;
            if ($show_qr) {
                foreach ($payment_methods as $method) {
                    if ($method['is_link']) {
                        $qr_link = $method;
                        break;
                    }
                }
            }
            ?>
            <?php if ($qr_link) : ?>
                <?php wp_enqueue_script('lfuf-qr'); ?>
                <div class="lfuf-location-info__qr">
                    <div
                        class="lfuf-location-info__qr-code"
                        data-lfuf-qr="<?php echo esc_attr($qr_link['url']); ?>"
                        data-lfuf-qr-label="<?php
                            printf(
                                /* translators: %s: payment method label. */
                                esc_attr__('QR code: pay with %s', 'farm-stand-manager'),
                                esc_attr($qr_link['label']),
                            );
                        ?>"
                    ></div>
                    <span class="lfuf-location-info__qr-caption">
                        <?php
                        printf(
                            /* translators: %s: payment method label. */
                            esc_html__('Scan to pay with %s', 'farm-stand-manager'),
                            esc_html($qr_link['label']),
                        );
                        ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>