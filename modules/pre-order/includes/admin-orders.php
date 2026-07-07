<?php
/**
 * Pre-Orders admin screen.
 *
 * Submenu under the Farm Stand dashboard: list with status filter and
 * one-click status transitions. Actions go through the plugin's own
 * REST endpoints with a wp_rest nonce (same pattern as Quick Entry).
 */

declare(strict_types=1);

namespace Leftfield\PreOrder\Admin;

use Leftfield\PreOrder\Orders;

defined('ABSPATH') || exit;

add_action('admin_menu', function (): void {
    add_submenu_page(
        'farm-stand-dashboard',
        __('Pre-Orders', 'farm-stand-manager'),
        __('Pre-Orders', 'farm-stand-manager'),
        'edit_posts',
        'farm-stand-preorders',
        __NAMESPACE__ . '\\render_page',
    );
});

/**
 * Status → next-step actions offered in the list.
 *
 * @return array<string, string[]>
 */
function next_actions(): array {
    return [
        'pending'   => ['confirmed', 'cancelled'],
        'confirmed' => ['ready', 'cancelled'],
        'ready'     => ['picked_up'],
        'picked_up' => [],
        'cancelled' => [],
    ];
}

function status_label(string $status): string {
    return match ($status) {
        'pending'   => __('Pending', 'farm-stand-manager'),
        'confirmed' => __('Confirmed', 'farm-stand-manager'),
        'ready'     => __('Ready for pickup', 'farm-stand-manager'),
        'picked_up' => __('Picked up', 'farm-stand-manager'),
        'cancelled' => __('Cancelled', 'farm-stand-manager'),
        default     => $status,
    };
}

function render_page(): void {
    if (! current_user_can('edit_posts')) {
        return;
    }

    // Read-only view filter; no state change happens on GET.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $status_filter = sanitize_key(wp_unslash($_GET['status'] ?? ''));
    if (! in_array($status_filter, Orders\valid_statuses(), true)) {
        $status_filter = '';
    }

    $result = Orders\get_orders(['status' => $status_filter, 'limit' => 100]);
    $counts = [];
    foreach (Orders\valid_statuses() as $s) {
        $counts[$s] = Orders\get_orders(['status' => $s, 'limit' => 1])['total'];
    }

    $rest_base = esc_url_raw(rest_url('lfuf/v1'));
    $nonce     = wp_create_nonce('wp_rest');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Pre-Orders', 'farm-stand-manager'); ?></h1>

        <ul class="subsubsub">
            <li>
                <a href="<?php echo esc_url(admin_url('admin.php?page=farm-stand-preorders')); ?>"
                   <?php echo $status_filter === '' ? 'class="current"' : ''; ?>>
                    <?php esc_html_e('All', 'farm-stand-manager'); ?>
                </a> |
            </li>
            <?php $i = 0; foreach ($counts as $s => $count) : $i++; ?>
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=farm-stand-preorders&status=' . $s)); ?>"
                       <?php echo $status_filter === $s ? 'class="current"' : ''; ?>>
                        <?php echo esc_html(status_label($s)); ?>
                        <span class="count">(<?php echo (int) $count; ?>)</span>
                    </a><?php echo $i < count($counts) ? ' |' : ''; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <table class="widefat striped" style="margin-top: 2.5em;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Pickup', 'farm-stand-manager'); ?></th>
                    <th><?php esc_html_e('Customer', 'farm-stand-manager'); ?></th>
                    <th><?php esc_html_e('Items', 'farm-stand-manager'); ?></th>
                    <th><?php esc_html_e('Location', 'farm-stand-manager'); ?></th>
                    <th><?php esc_html_e('Status', 'farm-stand-manager'); ?></th>
                    <th><?php esc_html_e('Actions', 'farm-stand-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (! $result['orders']) : ?>
                    <tr><td colspan="6"><?php esc_html_e('No pre-orders yet.', 'farm-stand-manager'); ?></td></tr>
                <?php endif; ?>
                <?php foreach ($result['orders'] as $order) : ?>
                    <tr data-lfuf-order="<?php echo (int) $order['id']; ?>">
                        <td><strong><?php echo esc_html($order['pickup_date']); ?></strong></td>
                        <td>
                            <?php echo esc_html($order['name']); ?>
                            <?php if ($order['email']) : ?>
                                <br><a href="mailto:<?php echo esc_attr($order['email']); ?>"><?php echo esc_html($order['email']); ?></a>
                            <?php endif; ?>
                            <?php if ($order['phone']) : ?>
                                <br><?php echo esc_html($order['phone']); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php foreach ($order['items'] as $item) : ?>
                                <?php
                                printf(
                                    /* translators: 1: quantity, 2: product title, 3: unit. */
                                    esc_html__('%1$d × %2$s %3$s', 'farm-stand-manager'),
                                    (int) $item['qty'],
                                    esc_html($item['title']),
                                    $item['unit'] ? esc_html('(' . $item['unit'] . ')') : '',
                                );
                                ?><br>
                            <?php endforeach; ?>
                            <?php if ($order['note']) : ?>
                                <em><?php echo esc_html($order['note']); ?></em>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($order['location_name'] ?: '—'); ?></td>
                        <td class="lfuf-preorder-status"><?php echo esc_html(status_label($order['status'])); ?></td>
                        <td>
                            <?php foreach (next_actions()[$order['status']] ?? [] as $next) : ?>
                                <button
                                    type="button"
                                    class="button button-small lfuf-preorder-action"
                                    data-order-id="<?php echo (int) $order['id']; ?>"
                                    data-next-status="<?php echo esc_attr($next); ?>"
                                >
                                    <?php echo esc_html(status_label($next)); ?>
                                </button>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
    ( function () {
        var restBase = <?php echo wp_json_encode($rest_base); ?>;
        var nonce    = <?php echo wp_json_encode($nonce); ?>;

        document.addEventListener( 'click', function ( event ) {
            var button = event.target.closest( '.lfuf-preorder-action' );
            if ( ! button ) return;

            button.disabled = true;
            fetch( restBase + '/preorders/' + button.dataset.orderId + '/status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce,
                },
                body: JSON.stringify( { status: button.dataset.nextStatus } ),
            } )
                .then( function ( r ) { return r.json().then( function ( d ) { return { ok: r.ok, data: d }; } ); } )
                .then( function ( result ) {
                    if ( result.ok ) {
                        window.location.reload();
                    } else {
                        alert( result.data.message || 'Could not update the pre-order.' );
                        button.disabled = false;
                    }
                } )
                .catch( function () {
                    alert( 'Could not update the pre-order.' );
                    button.disabled = false;
                } );
        } );
    } )();
    </script>
    <?php
}
