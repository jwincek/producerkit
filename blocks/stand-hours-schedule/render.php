<?php
/**
 * Server-side render for lfuf/stand-hours-schedule.
 *
 * Accessibility: Uses a proper <table> for the schedule grid
 * (it IS tabular data — day/hours), aria-current="date" on today's
 * row, aria-label on the section, screen-reader labels on fallback.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$location_id     = (int) ($attributes['locationId'] ?? 0);
$highlight_today = (bool) ($attributes['highlightToday'] ?? true);

if ($location_id < 1) {
    return;
}

$post = get_post($location_id);
if (! $post || $post->post_type !== 'lfuf_location' || $post->post_status !== 'publish') {
    return;
}

$schedule_json  = get_post_meta($location_id, '_lfuf_ss_schedule', true);
$schedule       = $schedule_json ? json_decode($schedule_json, true) : [];
$hours_fallback = get_post_meta($location_id, '_lfuf_hours', true);

$by_day = [];
if (is_array($schedule)) {
    foreach ($schedule as $entry) {
        $day = (int) ($entry['day'] ?? -1);
        if ($day >= 0 && $day <= 6) {
            $by_day[$day][] = $entry;
        }
    }
}

$today     = (int) current_datetime()->format('w');
$day_names = [
    0 => __('Sunday', 'farm-stand-manager'),
    1 => __('Monday', 'farm-stand-manager'),
    2 => __('Tuesday', 'farm-stand-manager'),
    3 => __('Wednesday', 'farm-stand-manager'),
    4 => __('Thursday', 'farm-stand-manager'),
    5 => __('Friday', 'farm-stand-manager'),
    6 => __('Saturday', 'farm-stand-manager'),
];

$wrapper_attrs = get_block_wrapper_attributes([
    'class' => 'lfuf-stand-schedule',
]);

$section_label = sprintf(
    /* translators: %s = location name */
    __('%s — Weekly Schedule', 'farm-stand-manager'),
    $post->post_title,
);
?>

<section <?php echo $wrapper_attrs; ?> aria-label="<?php echo esc_attr($section_label); ?>">
    <?php if (empty($by_day)) : ?>
        <?php if ($hours_fallback) : ?>
            <p class="lfuf-stand-schedule__fallback">
                <span aria-hidden="true">🕐</span>
                <span class="screen-reader-text"><?php esc_html_e('Hours:', 'farm-stand-manager'); ?> </span>
                <?php echo esc_html($hours_fallback); ?>
            </p>
        <?php else : ?>
            <p class="lfuf-stand-schedule__empty">
                <?php esc_html_e('No schedule set yet.', 'farm-stand-manager'); ?>
            </p>
        <?php endif; ?>
    <?php else : ?>
        <table class="lfuf-stand-schedule__table" role="table">
            <caption class="screen-reader-text">
                <?php echo esc_html($section_label); ?>
            </caption>
            <thead class="screen-reader-text">
                <tr>
                    <th scope="col"><?php esc_html_e('Day', 'farm-stand-manager'); ?></th>
                    <th scope="col"><?php esc_html_e('Hours', 'farm-stand-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php for ($d = 0; $d <= 6; $d++) :
                    $is_today  = $highlight_today && $d === $today;
                    $has_hours = isset($by_day[$d]);
                    $classes   = 'lfuf-stand-schedule__day';
                    if ($is_today) {
                        $classes .= ' lfuf-stand-schedule__day--today';
                    }
                    if (! $has_hours) {
                        $classes .= ' lfuf-stand-schedule__day--closed';
                    }
                ?>
                    <tr
                        class="<?php echo esc_attr($classes); ?>"
                        <?php echo $is_today ? 'aria-current="date"' : ''; ?>
                    >
                        <th scope="row" class="lfuf-stand-schedule__day-label">
                            <?php echo esc_html($day_names[$d]); ?>
                            <?php if ($is_today) : ?>
                                <span class="lfuf-stand-schedule__today-badge">
                                    <?php esc_html_e('Today', 'farm-stand-manager'); ?>
                                </span>
                            <?php endif; ?>
                        </th>
                        <td class="lfuf-stand-schedule__day-hours">
                            <?php if ($has_hours) :
                                $time_strings = array_map(function ($e) {
                                    $open  = date_i18n('g:i A', strtotime('2000-01-01 ' . ($e['open'] ?? '00:00')));
                                    $close = date_i18n('g:i A', strtotime('2000-01-01 ' . ($e['close'] ?? '23:59')));
                                    return $open . ' – ' . $close;
                                }, $by_day[$d]);
                                echo esc_html(implode(', ', $time_strings));
                            else : ?>
                                <?php esc_html_e('Closed', 'farm-stand-manager'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>