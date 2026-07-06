<?php
/**
 * Admin Bar quick-toggle for stand status.
 *
 * Supports multiple stands. Each stand gets its own toggle action
 * and inline message input. Updates apply via REST without page
 * reload — the admin bar DOM updates in place.
 */

declare(strict_types=1);

namespace Leftfield\StandStatus\AdminBar;

defined('ABSPATH') || exit;

add_action('admin_bar_menu', __NAMESPACE__ . '\\add_stand_nodes', 100);
add_action('wp_head', __NAMESPACE__ . '\\inline_styles');
add_action('admin_head', __NAMESPACE__ . '\\inline_styles');
add_action('wp_footer', __NAMESPACE__ . '\\inline_script');
add_action('admin_footer', __NAMESPACE__ . '\\inline_script');

/**
 * Get all published stand-type locations, cached per request.
 *
 * @return \WP_Post[]
 */
function get_all_stands(): array {
    static $stands = null;
    if ($stands !== null) {
        return $stands;
    }

    $stands = get_posts([
        'post_type'   => 'lfuf_location',
        'post_status' => 'publish',
        'numberposts' => 20,
        'meta_query'  => [
            ['key' => '_lfuf_location_type', 'value' => 'stand'],
        ],
        'orderby' => 'title',
        'order'   => 'ASC',
    ]);

    return $stands;
}

/**
 * Compute effective open/closed status considering auto-toggle schedule
 * and season boundaries. Matches the logic in stand-status-banner render.php.
 */
function get_effective_status(int $id): bool {
    $is_open = (bool) get_post_meta($id, '_lfuf_is_open', true);

    $auto_toggle = (bool) get_post_meta($id, '_lfuf_ss_auto_toggle', true);
    $schedule    = get_post_meta($id, '_lfuf_ss_schedule', true);
    if ($auto_toggle && $schedule && function_exists('\\Leftfield\\StandStatus\\REST\\compute_schedule_status')) {
        $is_open = \Leftfield\StandStatus\REST\compute_schedule_status($schedule);
    }

    $season_start = get_post_meta($id, '_lfuf_ss_season_start', true);
    $season_end   = get_post_meta($id, '_lfuf_ss_season_end', true);
    if ($season_start && $season_end && function_exists('\\Leftfield\\StandStatus\\REST\\is_in_season')) {
        if (! \Leftfield\StandStatus\REST\is_in_season($season_start, $season_end)) {
            $is_open = false;
        }
    }

    return $is_open;
}

/**
 * Build stand data array for all stands.
 * Cached per request since multiple functions need it.
 *
 * @return array<array{post: \WP_Post, is_open: bool, message: string}>
 */
function get_stand_data(): array {
    static $data = null;
    if ($data !== null) {
        return $data;
    }

    $data = [];
    foreach (get_all_stands() as $stand) {
        $data[] = [
            'post'    => $stand,
            'is_open' => get_effective_status($stand->ID),
            'message' => get_post_meta($stand->ID, '_lfuf_ss_status_message', true) ?: '',
        ];
    }

    return $data;
}

function add_stand_nodes(\WP_Admin_Bar $bar): void {
    if (! current_user_can('edit_posts')) {
        return;
    }

    $stand_data = get_stand_data();
    if (empty($stand_data)) {
        return;
    }

    $total      = count($stand_data);
    $single     = $total === 1;
    $open_count = count(array_filter($stand_data, fn ($d) => $d['is_open']));

    // Top-level node.
    if ($single) {
        $d = $stand_data[0];
        $top_title = sprintf(
            '<span class="lfuf-ab-indicator lfuf-ab-indicator--%s"></span>%s%s',
            $d['is_open'] ? 'open' : 'closed',
            $d['is_open'] ? __('Stand Open', 'farm-stand-manager') : __('Stand Closed', 'farm-stand-manager'),
            $d['message'] ? ' &mdash; <span class="lfuf-ab-msg">' . esc_html($d['message']) . '</span>' : '',
        );
    } else {
        $top_title = sprintf(
            '<span class="lfuf-ab-indicator lfuf-ab-indicator--%s"></span>%s',
            $open_count > 0 ? 'open' : 'closed',
            sprintf(__('Stands (%d/%d open)', 'farm-stand-manager'), $open_count, $total),
        );
    }

    $bar->add_node([
        'id'    => 'lfuf-stands',
        'title' => $top_title,
        'href'  => '#',
        'meta'  => ['class' => 'lfuf-stands-node'],
    ]);

    // Per-stand nodes.
    foreach ($stand_data as $d) {
        $stand   = $d['post'];
        $sid     = $stand->ID;
        $is_open = $d['is_open'];
        $message = $d['message'];

        // For multiple stands, add a parent node per stand.
        if (! $single) {
            $bar->add_node([
                'id'     => 'lfuf-stand-' . $sid,
                'parent' => 'lfuf-stands',
                'title'  => sprintf(
                    '<span class="lfuf-ab-indicator lfuf-ab-indicator--%s"></span>%s%s',
                    $is_open ? 'open' : 'closed',
                    esc_html($stand->post_title),
                    $message ? ' <span class="lfuf-ab-msg">&mdash; ' . esc_html($message) . '</span>' : '',
                ),
                'href'   => '#',
                'meta'   => ['class' => 'lfuf-stand-item'],
            ]);
        }

        $parent = $single ? 'lfuf-stands' : 'lfuf-stand-' . $sid;

        // Toggle action.
        $bar->add_node([
            'id'     => 'lfuf-stand-' . $sid . '-toggle',
            'parent' => $parent,
            'title'  => $is_open
                ? __('Close the Stand', 'farm-stand-manager')
                : __('Open the Stand', 'farm-stand-manager'),
            'href'   => '#',
            'meta'   => [
                'class'         => 'lfuf-stand-toggle-action',
                'data-stand-id' => (string) $sid,
            ],
        ]);

        // Inline message input.
        $input_html = sprintf(
            '<label class="screen-reader-text" for="lfuf-ab-msg-%1$d">%2$s</label>'
            . '<input type="text" id="lfuf-ab-msg-%1$d" class="lfuf-ab-msg-input"'
            . ' placeholder="%3$s" value="%4$s" data-stand-id="%1$d" />'
            . '<button type="button" class="lfuf-ab-msg-save" data-stand-id="%1$d">%5$s</button>',
            $sid,
            esc_attr__('Status message', 'farm-stand-manager'),
            esc_attr__("Status message\u{2026}", 'farm-stand-manager'),
            esc_attr($message),
            esc_html__('Save', 'farm-stand-manager'),
        );

        $bar->add_node([
            'id'     => 'lfuf-stand-' . $sid . '-message',
            'parent' => $parent,
            'title'  => $input_html,
            'href'   => false,
            'meta'   => ['class' => 'lfuf-stand-msg-form'],
        ]);

        // Edit location link.
        $bar->add_node([
            'id'     => 'lfuf-stand-' . $sid . '-edit',
            'parent' => $parent,
            'title'  => __('Edit Stand Settings', 'farm-stand-manager'),
            'href'   => get_edit_post_link($sid, 'raw'),
        ]);
    }
}

function inline_styles(): void {
    if (! current_user_can('edit_posts') || empty(get_all_stands())) {
        return;
    }
    ?>
    <style>
        /* ── Indicator dot ── */
        .lfuf-ab-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
            vertical-align: middle;
        }
        .lfuf-ab-indicator--open  { background: #22c55e; box-shadow: 0 0 4px #22c55e; }
        .lfuf-ab-indicator--closed { background: #ef4444; box-shadow: 0 0 4px #ef4444; }

        #wp-admin-bar-lfuf-stands > .ab-item { cursor: pointer; }

        /* ── Message text in node labels ── */
        .lfuf-ab-msg { opacity: 0.7; font-size: 0.9em; }

        /* ── Updating state ── */
        .lfuf-stand-toggle-action.lfuf-ab-updating > .ab-item {
            opacity: 0.5;
            pointer-events: none;
        }

        /* ── Inline message form ── */
        .lfuf-stand-msg-form .ab-item {
            display: flex !important;
            align-items: center;
            gap: 4px;
            height: auto !important;
            padding: 4px 8px !important;
        }
        .lfuf-ab-msg-input {
            width: 150px;
            padding: 3px 6px;
            font-size: 12px;
            border: 1px solid #5b5d5f;
            border-radius: 3px;
            background: #32373c;
            color: #eee;
            line-height: 1.4;
        }
        .lfuf-ab-msg-input:focus {
            border-color: #72aee6;
            outline: none;
            box-shadow: 0 0 0 1px #72aee6;
        }
        .lfuf-ab-msg-input::placeholder { color: #9ca3af; }
        .lfuf-ab-msg-save {
            padding: 3px 8px;
            font-size: 11px;
            font-weight: 600;
            border: none;
            border-radius: 3px;
            background: #2271b1;
            color: #fff;
            cursor: pointer;
            white-space: nowrap;
            line-height: 1.4;
        }
        .lfuf-ab-msg-save:hover { background: #135e96; }
        .lfuf-ab-msg-save:disabled {
            opacity: 0.5;
            pointer-events: none;
        }
    </style>
    <?php
}

function inline_script(): void {
    if (! current_user_can('edit_posts') || empty(get_all_stands())) {
        return;
    }

    $stand_data = get_stand_data();
    $js_stands  = new \stdClass();
    foreach ($stand_data as $d) {
        $id = $d['post']->ID;
        $js_stands->$id = [
            'name'    => $d['post']->post_title,
            'isOpen'  => $d['is_open'],
            'message' => $d['message'],
        ];
    }
    ?>
    <script>
    ( function () {
        'use strict';

        var restBase = <?php echo wp_json_encode(esc_url_raw(rest_url('lfuf/v1'))); ?>;
        var nonce    = <?php echo wp_json_encode(wp_create_nonce('wp_rest')); ?>;
        var stands   = <?php echo wp_json_encode($js_stands); ?>;
        var total    = Object.keys( stands ).length;

        /**
         * PATCH stand status via REST API.
         */
        function patchStand( id, isOpen, message ) {
            return fetch( restBase + '/stand/' + id + '/status', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce,
                },
                body: JSON.stringify( {
                    is_open: !! isOpen,
                    status_message: message,
                } ),
            } ).then( function ( r ) { return r.json(); } );
        }

        /**
         * Escape HTML for safe insertion into innerHTML.
         */
        function esc( str ) {
            var d = document.createElement( 'div' );
            d.appendChild( document.createTextNode( str ) );
            return d.innerHTML;
        }

        /**
         * Update all admin bar DOM nodes for a stand after a state change.
         */
        function updateDOM( id ) {
            var s = stands[ id ];
            var slug = s.isOpen ? 'open' : 'closed';

            // Toggle action: swap text.
            var toggleItem = document.querySelector(
                '#wp-admin-bar-lfuf-stand-' + id + '-toggle .ab-item'
            );
            if ( toggleItem ) {
                toggleItem.textContent = s.isOpen ? 'Close the Stand' : 'Open the Stand';
            }

            // Message input: sync value.
            var msgInput = document.getElementById( 'lfuf-ab-msg-' + id );
            if ( msgInput ) {
                msgInput.value = s.message;
            }

            if ( total === 1 ) {
                // Single stand: update the top-level node directly.
                var topItem = document.querySelector( '#wp-admin-bar-lfuf-stands > .ab-item' );
                if ( topItem ) {
                    var label = s.isOpen ? 'Stand Open' : 'Stand Closed';
                    var msgHtml = s.message
                        ? ' &mdash; <span class="lfuf-ab-msg">' + esc( s.message ) + '</span>'
                        : '';
                    topItem.innerHTML =
                        '<span class="lfuf-ab-indicator lfuf-ab-indicator--' + slug + '"></span>' +
                        esc( label ) + msgHtml;
                }
            } else {
                // Multi-stand: update the per-stand node.
                var standItem = document.querySelector(
                    '#wp-admin-bar-lfuf-stand-' + id + ' > .ab-item'
                );
                if ( standItem ) {
                    var msgHtml2 = s.message
                        ? ' <span class="lfuf-ab-msg">&mdash; ' + esc( s.message ) + '</span>'
                        : '';
                    standItem.innerHTML =
                        '<span class="lfuf-ab-indicator lfuf-ab-indicator--' + slug + '"></span>' +
                        esc( s.name ) + msgHtml2;
                }

                // Update the top-level summary count.
                var openCount = 0;
                var ids = Object.keys( stands );
                for ( var i = 0; i < ids.length; i++ ) {
                    if ( stands[ ids[ i ] ].isOpen ) openCount++;
                }
                var topItem2 = document.querySelector( '#wp-admin-bar-lfuf-stands > .ab-item' );
                if ( topItem2 ) {
                    var topSlug = openCount > 0 ? 'open' : 'closed';
                    topItem2.innerHTML =
                        '<span class="lfuf-ab-indicator lfuf-ab-indicator--' + topSlug + '"></span>' +
                        'Stands (' + openCount + '/' + total + ' open)';
                }
            }
        }

        // ── Toggle click handler ──

        document.addEventListener( 'click', function ( e ) {
            var toggleLink = e.target.closest( '.lfuf-stand-toggle-action' );
            if ( ! toggleLink ) return;

            e.preventDefault();
            var id = toggleLink.getAttribute( 'data-stand-id' );
            if ( ! id || ! stands[ id ] ) return;

            // Show updating state.
            toggleLink.classList.add( 'lfuf-ab-updating' );
            var item = toggleLink.querySelector( '.ab-item' );
            var prevText = item ? item.textContent : '';
            if ( item ) item.textContent = 'Updating\u2026';

            var newOpen = ! stands[ id ].isOpen;
            var message = stands[ id ].message; // Preserve current message.

            patchStand( id, newOpen, message )
                .then( function ( data ) {
                    stands[ id ].isOpen = !! data.is_open;
                    stands[ id ].message = data.status_message || message;
                    updateDOM( id );
                } )
                .catch( function () {
                    // Restore previous text on failure.
                    if ( item ) item.textContent = prevText;
                } )
                .finally( function () {
                    toggleLink.classList.remove( 'lfuf-ab-updating' );
                } );
        } );

        // ── Message save click handler ──

        document.addEventListener( 'click', function ( e ) {
            var saveBtn = e.target.closest( '.lfuf-ab-msg-save' );
            if ( ! saveBtn ) return;

            e.preventDefault();
            e.stopPropagation();
            var id = saveBtn.getAttribute( 'data-stand-id' );
            if ( ! id || ! stands[ id ] ) return;

            var input = document.getElementById( 'lfuf-ab-msg-' + id );
            if ( ! input ) return;

            var msg = input.value.trim();
            saveBtn.disabled = true;
            saveBtn.textContent = '\u2026';

            patchStand( id, stands[ id ].isOpen, msg )
                .then( function ( data ) {
                    stands[ id ].message = data.status_message || msg;
                    updateDOM( id );
                } )
                .finally( function () {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save';
                } );
        } );

        // ── Message input: Enter to save, prevent menu close on click ──

        document.addEventListener( 'keydown', function ( e ) {
            if ( e.key !== 'Enter' ) return;
            var input = e.target.closest( '.lfuf-ab-msg-input' );
            if ( ! input ) return;

            e.preventDefault();
            var id = input.getAttribute( 'data-stand-id' );
            var saveBtn = document.querySelector(
                '.lfuf-ab-msg-save[data-stand-id="' + id + '"]'
            );
            if ( saveBtn ) saveBtn.click();
        } );

        document.addEventListener( 'click', function ( e ) {
            if ( e.target.closest( '.lfuf-stand-msg-form' ) ) {
                e.stopPropagation();
            }
        } );

    } )();
    </script>
    <?php
}
