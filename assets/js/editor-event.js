/**
 * Event Editor Sidebar — custom panels for lfuf_event meta.
 *
 * Panels:
 *   1. Event Details   — date/time pickers, location, donation link
 *   2. RSVP Settings   — enable, cap, label, close, headcount display
 *   3. Event Info      — cost note, what to bring, cancelled toggle
 */
( function () {
    'use strict';

    var el               = wp.element.createElement;
    var useState          = wp.element.useState;
    var registerPlugin    = wp.plugins.registerPlugin;
    var PluginDocumentSettingPanel = wp.editor.PluginDocumentSettingPanel;
    var useEntityProp     = wp.coreData.useEntityProp;
    var useSelect         = wp.data.useSelect;
    var TextControl       = wp.components.TextControl;
    var TextareaControl   = wp.components.TextareaControl;
    var SelectControl     = wp.components.SelectControl;
    var ToggleControl     = wp.components.ToggleControl;
    var RangeControl      = wp.components.RangeControl;
    var Notice            = wp.components.Notice;

    /* ─────────────────────────────────────────────
     * Helpers
     * ───────────────────────────────────────────── */

    /**
     * Split ISO datetime "2026-06-06T18:00:00" into { date, time }.
     */
    function splitDatetime( iso ) {
        if ( ! iso ) return { date: '', time: '' };
        var parts = iso.split( 'T' );
        return {
            date: parts[ 0 ] || '',
            time: ( parts[ 1 ] || '' ).substring( 0, 5 ), // HH:MM
        };
    }

    /**
     * Combine date + time into ISO string.
     */
    function joinDatetime( date, time ) {
        if ( ! date ) return '';
        return date + 'T' + ( time || '00:00' ) + ':00';
    }

    /* ─────────────────────────────────────────────
     * Panel 1: Event Details
     * ───────────────────────────────────────────── */

    function EventDetailsPanel() {
        var postType = useSelect( function ( select ) {
            return select( 'core/editor' ).getCurrentPostType();
        }, [] );

        if ( postType !== 'lfuf_event' ) return null;

        var _meta   = useEntityProp( 'postType', 'lfuf_event', 'meta' );
        var meta    = _meta[ 0 ];
        var setMeta = _meta[ 1 ];

        function updateMeta( key, value ) {
            var updated = {};
            updated[ key ] = value;
            setMeta( Object.assign( {}, meta, updated ) );
        }

        // Parse start/end into date + time.
        var start = splitDatetime( meta._lfuf_start_datetime );
        var end   = splitDatetime( meta._lfuf_end_datetime );

        // Fetch locations for the selector.
        var locations = useSelect( function ( select ) {
            return select( 'core' ).getEntityRecords( 'postType', 'lfuf_location', {
                per_page: 50,
                status: 'publish',
                _fields: 'id,title',
            } ) || [];
        }, [] );

        var locationOptions = [ { label: '— Select Location —', value: 0 } ].concat(
            locations.map( function ( loc ) {
                return { label: loc.title?.rendered || '(untitled)', value: loc.id };
            } )
        );

        // Validation.
        var noStartDate = ! meta._lfuf_start_datetime;

        return el(
            PluginDocumentSettingPanel,
            {
                name: 'lfuf-event-details',
                title: 'Event Details',
                initialOpen: true,
                icon: 'calendar-alt',
            },

            noStartDate
                ? el( Notice, {
                    status: 'warning',
                    isDismissible: false,
                    style: { marginBottom: '12px' },
                }, 'Set a start date before publishing.' )
                : null,

            // Start date/time.
            el( 'p', { style: { fontWeight: 600, marginBottom: '4px' } }, 'Start' ),
            el( 'div', { style: { display: 'flex', gap: '8px', marginBottom: '12px' } },
                el( TextControl, {
                    label: 'Date',
                    type: 'date',
                    value: start.date,
                    onChange: function ( val ) {
                        updateMeta( '_lfuf_start_datetime', joinDatetime( val, start.time ) );
                    },
                    style: { flex: 1 },
                } ),
                el( TextControl, {
                    label: 'Time',
                    type: 'time',
                    value: start.time,
                    onChange: function ( val ) {
                        updateMeta( '_lfuf_start_datetime', joinDatetime( start.date, val ) );
                    },
                    style: { flex: 1 },
                } )
            ),

            // End date/time.
            el( 'p', { style: { fontWeight: 600, marginBottom: '4px' } }, 'End' ),
            el( 'div', { style: { display: 'flex', gap: '8px', marginBottom: '12px' } },
                el( TextControl, {
                    label: 'Date',
                    type: 'date',
                    value: end.date || start.date, // Default to same day.
                    onChange: function ( val ) {
                        updateMeta( '_lfuf_end_datetime', joinDatetime( val, end.time ) );
                    },
                    style: { flex: 1 },
                } ),
                el( TextControl, {
                    label: 'Time',
                    type: 'time',
                    value: end.time,
                    onChange: function ( val ) {
                        updateMeta( '_lfuf_end_datetime', joinDatetime( end.date || start.date, val ) );
                    },
                    style: { flex: 1 },
                } )
            ),

            // Location.
            el( SelectControl, {
                label: 'Location',
                value: meta._lfuf_event_location_id || 0,
                options: locationOptions,
                onChange: function ( val ) { updateMeta( '_lfuf_event_location_id', parseInt( val, 10 ) ); },
                help: 'Where is this event happening?',
            } ),

            // Donation link.
            el( TextControl, {
                label: 'Donation / Payment Link',
                value: meta._lfuf_donation_link || '',
                onChange: function ( val ) { updateMeta( '_lfuf_donation_link', val ); },
                placeholder: 'https://venmo.com/examplefarm',
                help: 'Venmo link or other payment URL.',
                type: 'url',
            } )
        );
    }

    /* ─────────────────────────────────────────────
     * Panel 2: RSVP Settings
     * ───────────────────────────────────────────── */

    function EventRsvpPanel() {
        var postType = useSelect( function ( select ) {
            return select( 'core/editor' ).getCurrentPostType();
        }, [] );

        if ( postType !== 'lfuf_event' ) return null;

        var _meta   = useEntityProp( 'postType', 'lfuf_event', 'meta' );
        var meta    = _meta[ 0 ];
        var setMeta = _meta[ 1 ];

        function updateMeta( key, value ) {
            var updated = {};
            updated[ key ] = value;
            setMeta( Object.assign( {}, meta, updated ) );
        }

        var rsvpEnabled = !! meta._lfuf_em_rsvp_enabled;

        return el(
            PluginDocumentSettingPanel,
            {
                name: 'lfuf-event-rsvp',
                title: 'RSVP Settings',
                initialOpen: false,
                icon: 'groups',
            },

            el( ToggleControl, {
                label: 'Enable RSVPs',
                checked: rsvpEnabled,
                onChange: function ( val ) { updateMeta( '_lfuf_em_rsvp_enabled', val ); },
                help: 'Allow visitors to RSVP on the front end.',
            } ),

            rsvpEnabled
                ? el( wp.element.Fragment, null,
                    el( RangeControl, {
                        label: 'RSVP Cap',
                        value: meta._lfuf_rsvp_cap || 0,
                        onChange: function ( val ) { updateMeta( '_lfuf_rsvp_cap', val ); },
                        min: 0,
                        max: 200,
                        help: '0 = unlimited. Total headcount including party sizes.',
                    } ),

                    el( TextControl, {
                        label: 'Button Label',
                        value: meta._lfuf_em_rsvp_label || '',
                        onChange: function ( val ) { updateMeta( '_lfuf_em_rsvp_label', val ); },
                        placeholder: "I'm coming!",
                        help: 'Custom text for the RSVP button.',
                    } ),

                    el( ToggleControl, {
                        label: 'Manually Close RSVPs',
                        checked: !! meta._lfuf_em_rsvp_closed,
                        onChange: function ( val ) { updateMeta( '_lfuf_em_rsvp_closed', val ); },
                        help: 'Close RSVPs regardless of the cap.',
                    } )
                )
                : null
        );
    }

    /* ─────────────────────────────────────────────
     * Panel 3: Event Info
     * ───────────────────────────────────────────── */

    function EventInfoPanel() {
        var postType = useSelect( function ( select ) {
            return select( 'core/editor' ).getCurrentPostType();
        }, [] );

        if ( postType !== 'lfuf_event' ) return null;

        var _meta   = useEntityProp( 'postType', 'lfuf_event', 'meta' );
        var meta    = _meta[ 0 ];
        var setMeta = _meta[ 1 ];

        function updateMeta( key, value ) {
            var updated = {};
            updated[ key ] = value;
            setMeta( Object.assign( {}, meta, updated ) );
        }

        return el(
            PluginDocumentSettingPanel,
            {
                name: 'lfuf-event-info',
                title: 'Event Info',
                initialOpen: false,
                icon: 'info-outline',
            },

            el( TextControl, {
                label: 'Cost / Donation Note',
                value: meta._lfuf_em_cost_note || '',
                onChange: function ( val ) { updateMeta( '_lfuf_em_cost_note', val ); },
                placeholder: 'Donation-based — suggested $10/person',
                help: 'Shown on the event card.',
            } ),

            el( TextControl, {
                label: 'What to Bring',
                value: meta._lfuf_em_what_to_bring || '',
                onChange: function ( val ) { updateMeta( '_lfuf_em_what_to_bring', val ); },
                placeholder: 'A side dish or dessert to share',
                help: 'Shown on the event card with a 🧺 icon.',
            } ),

            el( ToggleControl, {
                label: 'Event Cancelled',
                checked: !! meta._lfuf_em_cancelled,
                onChange: function ( val ) { updateMeta( '_lfuf_em_cancelled', val ); },
                help: 'Mark this event as cancelled. It will show a cancelled badge.',
            } ),

            !! meta._lfuf_em_cancelled
                ? el( Notice, {
                    status: 'error',
                    isDismissible: false,
                }, 'This event is marked as cancelled. It will display with a cancelled badge on the front end.' )
                : null
        );
    }

    /* ─────────────────────────────────────────────
     * Register
     * ───────────────────────────────────────────── */

    registerPlugin( 'lfuf-event-details', {
        render: EventDetailsPanel,
        icon: 'calendar-alt',
    } );

    registerPlugin( 'lfuf-event-rsvp', {
        render: EventRsvpPanel,
        icon: 'groups',
    } );

    registerPlugin( 'lfuf-event-info', {
        render: EventInfoPanel,
        icon: 'info-outline',
    } );

} )();
