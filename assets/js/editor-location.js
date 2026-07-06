/**
 * Location Editor Sidebar — custom panels for lfuf_location meta.
 *
 * Registers two sidebar panels:
 *   1. Location Details — address, type, hours, Venmo, coordinates
 *   2. Stand Schedule   — season dates, weekly schedule builder, auto-toggle
 *
 * Uses PluginDocumentSettingPanel + useEntityProp (no build step).
 */
( function () {
    'use strict';

    var el               = wp.element.createElement;
    var Fragment          = wp.element.Fragment;
    var useState          = wp.element.useState;
    var useEffect         = wp.element.useEffect;
    var registerPlugin    = wp.plugins.registerPlugin;
    var PluginDocumentSettingPanel = wp.editor.PluginDocumentSettingPanel;
    var useEntityProp     = wp.coreData.useEntityProp;
    var useSelect         = wp.data.useSelect;
    var TextControl       = wp.components.TextControl;
    var SelectControl     = wp.components.SelectControl;
    var ToggleControl     = wp.components.ToggleControl;
    var Button            = wp.components.Button;
    var PanelRow          = wp.components.PanelRow;
    var Notice            = wp.components.Notice;
    var DatePicker        = wp.components.__experimentalDatePicker || wp.components.DatePicker;

    // Only run on lfuf_location post type.
    var currentPostType = useSelect
        ? null // will check inside component
        : null;

    /* ─────────────────────────────────────────────
     * Panel 1: Location Details
     * ───────────────────────────────────────────── */

    function LocationDetailsPanel() {
        var postType = useSelect( function ( select ) {
            return select( 'core/editor' ).getCurrentPostType();
        }, [] );

        if ( postType !== 'lfuf_location' ) return null;

        var _address      = useEntityProp( 'postType', 'lfuf_location', 'meta' );
        var meta          = _address[ 0 ];
        var setMeta       = _address[ 1 ];

        function updateMeta( key, value ) {
            var updated = {};
            updated[ key ] = value;
            setMeta( Object.assign( {}, meta, updated ) );
        }

        return el(
            PluginDocumentSettingPanel,
            {
                name: 'lfuf-location-details',
                title: 'Location Details',
                initialOpen: true,
                icon: 'store',
            },

            el( SelectControl, {
                label: 'Location Type',
                value: meta._lfuf_location_type || 'stand',
                options: [
                    { label: 'Farm Stand', value: 'stand' },
                    { label: 'Farmers Market', value: 'market' },
                    { label: 'On-Farm', value: 'on-farm' },
                    { label: 'Other', value: 'other' },
                ],
                onChange: function ( val ) { updateMeta( '_lfuf_location_type', val ); },
                help: 'What kind of location is this?',
            } ),

            el( TextControl, {
                label: 'Address',
                value: meta._lfuf_address || '',
                onChange: function ( val ) { updateMeta( '_lfuf_address', val ); },
                placeholder: '1820 E Myrtle Ave, Johnson City, TN 37601',
                help: 'Full street address shown to visitors.',
            } ),

            el( TextControl, {
                label: 'Hours',
                value: meta._lfuf_hours || '',
                onChange: function ( val ) { updateMeta( '_lfuf_hours', val ); },
                placeholder: 'Saturdays 1:00 – 4:00 PM, May – December',
                help: 'Displayed on the front end. Free-form text.',
            } ),

            el( TextControl, {
                label: 'Venmo Handle',
                value: meta._lfuf_venmo_handle || '',
                onChange: function ( val ) { updateMeta( '_lfuf_venmo_handle', val.replace( /^@/, '' ) ); },
                placeholder: 'examplefarm',
                help: 'Without the @. Used to generate the Venmo payment link.',
            } ),

            el( 'div', { style: { display: 'flex', gap: '8px' } },
                el( TextControl, {
                    label: 'Latitude',
                    type: 'number',
                    step: 'any',
                    value: meta._lfuf_lat || '',
                    onChange: function ( val ) { updateMeta( '_lfuf_lat', parseFloat( val ) || 0 ); },
                    style: { flex: 1 },
                } ),
                el( TextControl, {
                    label: 'Longitude',
                    type: 'number',
                    step: 'any',
                    value: meta._lfuf_lng || '',
                    onChange: function ( val ) { updateMeta( '_lfuf_lng', parseFloat( val ) || 0 ); },
                    style: { flex: 1 },
                } )
            ),

            el( ToggleControl, {
                label: 'Currently Open',
                checked: !! meta._lfuf_is_open,
                onChange: function ( val ) { updateMeta( '_lfuf_is_open', val ); },
                help: 'Toggle this location open or closed right now.',
            } ),

            meta._lfuf_ss_status_message !== undefined
                ? el( TextControl, {
                    label: 'Status Message',
                    value: meta._lfuf_ss_status_message || '',
                    onChange: function ( val ) { updateMeta( '_lfuf_ss_status_message', val ); },
                    placeholder: 'Back at 2 PM',
                    help: 'Optional message shown alongside open/closed status.',
                } )
                : null
        );
    }

    /* ─────────────────────────────────────────────
     * Panel 2: Stand Schedule & Season
     * ───────────────────────────────────────────── */

    var DAY_LABELS = [
        'Sunday', 'Monday', 'Tuesday', 'Wednesday',
        'Thursday', 'Friday', 'Saturday',
    ];

    function StandSchedulePanel() {
        var postType = useSelect( function ( select ) {
            return select( 'core/editor' ).getCurrentPostType();
        }, [] );

        if ( postType !== 'lfuf_location' ) return null;

        var _meta    = useEntityProp( 'postType', 'lfuf_location', 'meta' );
        var meta     = _meta[ 0 ];
        var setMeta  = _meta[ 1 ];

        function updateMeta( key, value ) {
            var updated = {};
            updated[ key ] = value;
            setMeta( Object.assign( {}, meta, updated ) );
        }

        // Parse schedule JSON into array.
        var scheduleRaw = meta._lfuf_ss_schedule || '[]';
        var schedule;
        try {
            schedule = JSON.parse( scheduleRaw );
            if ( ! Array.isArray( schedule ) ) schedule = [];
        } catch ( e ) {
            schedule = [];
        }

        function updateSchedule( newSchedule ) {
            updateMeta( '_lfuf_ss_schedule', JSON.stringify( newSchedule ) );
        }

        function addDay() {
            // Find first day not in schedule.
            var usedDays = schedule.map( function ( e ) { return e.day; } );
            var nextDay = 6; // Default to Saturday.
            for ( var d = 0; d <= 6; d++ ) {
                if ( usedDays.indexOf( d ) === -1 ) {
                    nextDay = d;
                    break;
                }
            }
            var updated = schedule.concat( [ { day: nextDay, open: '09:00', close: '16:00' } ] );
            updateSchedule( updated );
        }

        function removeDay( index ) {
            var updated = schedule.filter( function ( _, i ) { return i !== index; } );
            updateSchedule( updated );
        }

        function updateDay( index, field, value ) {
            var updated = schedule.map( function ( entry, i ) {
                if ( i !== index ) return entry;
                var copy = Object.assign( {}, entry );
                copy[ field ] = field === 'day' ? parseInt( value, 10 ) : value;
                return copy;
            } );
            updateSchedule( updated );
        }

        // Season date handling.
        var seasonStart = meta._lfuf_ss_season_start || '';
        var seasonEnd   = meta._lfuf_ss_season_end || '';

        return el(
            PluginDocumentSettingPanel,
            {
                name: 'lfuf-stand-schedule',
                title: 'Schedule & Season',
                initialOpen: false,
                icon: 'clock',
            },

            // Season dates.
            el( 'p', { style: { fontWeight: 600, marginBottom: '4px' } }, 'Season Dates' ),
            el( 'p', {
                className: 'components-base-control__help',
                style: { marginTop: 0, marginBottom: '8px' },
            }, 'Leave blank if open year-round.' ),

            el( 'div', { style: { display: 'flex', gap: '8px', marginBottom: '16px' } },
                el( TextControl, {
                    label: 'Start',
                    type: 'date',
                    value: seasonStart,
                    onChange: function ( val ) { updateMeta( '_lfuf_ss_season_start', val ); },
                    style: { flex: 1 },
                } ),
                el( TextControl, {
                    label: 'End',
                    type: 'date',
                    value: seasonEnd,
                    onChange: function ( val ) { updateMeta( '_lfuf_ss_season_end', val ); },
                    style: { flex: 1 },
                } )
            ),

            // Auto-toggle.
            el( ToggleControl, {
                label: 'Auto-toggle from schedule',
                checked: !! meta._lfuf_ss_auto_toggle,
                onChange: function ( val ) { updateMeta( '_lfuf_ss_auto_toggle', val ); },
                help: 'Automatically open/close based on the weekly schedule below.',
            } ),

            // Weekly schedule entries.
            el( 'p', { style: { fontWeight: 600, marginBottom: '4px', marginTop: '12px' } }, 'Weekly Schedule' ),

            schedule.length === 0
                ? el( 'p', {
                    style: { color: '#6b7280', fontStyle: 'italic', fontSize: '13px' },
                }, 'No schedule set. Add a day below.' )
                : null,

            schedule.map( function ( entry, i ) {
                return el( 'div', {
                    key: i,
                    style: {
                        display: 'flex',
                        gap: '6px',
                        alignItems: 'flex-end',
                        marginBottom: '8px',
                        padding: '8px',
                        background: '#f9fafb',
                        borderRadius: '4px',
                    },
                },
                    el( SelectControl, {
                        label: i === 0 ? 'Day' : '',
                        value: entry.day,
                        options: DAY_LABELS.map( function ( label, d ) {
                            return { label: label, value: d };
                        } ),
                        onChange: function ( val ) { updateDay( i, 'day', val ); },
                        style: { flex: 2 },
                        __nextHasNoMarginBottom: true,
                    } ),
                    el( TextControl, {
                        label: i === 0 ? 'Open' : '',
                        type: 'time',
                        value: entry.open || '09:00',
                        onChange: function ( val ) { updateDay( i, 'open', val ); },
                        style: { flex: 1 },
                    } ),
                    el( TextControl, {
                        label: i === 0 ? 'Close' : '',
                        type: 'time',
                        value: entry.close || '16:00',
                        onChange: function ( val ) { updateDay( i, 'close', val ); },
                        style: { flex: 1 },
                    } ),
                    el( Button, {
                        isDestructive: true,
                        isSmall: true,
                        icon: 'no-alt',
                        label: 'Remove',
                        onClick: function () { removeDay( i ); },
                        style: { marginBottom: '8px' },
                    } )
                );
            } ),

            schedule.length < 7
                ? el( Button, {
                    variant: 'secondary',
                    isSmall: true,
                    icon: 'plus-alt2',
                    onClick: addDay,
                    style: { marginTop: '4px' },
                }, 'Add Day' )
                : null
        );
    }

    /* ─────────────────────────────────────────────
     * Register
     * ───────────────────────────────────────────── */

    registerPlugin( 'lfuf-location-details', {
        render: LocationDetailsPanel,
        icon: 'store',
    } );

    registerPlugin( 'lfuf-stand-schedule', {
        render: StandSchedulePanel,
        icon: 'clock',
    } );

} )();
