/**
 * Source Editor Sidebar — custom panels for pkit_source meta.
 *
 * Panels:
 *   1. Source Details — who it came from, and where
 *   2. Provenance     — what happened to it before it reached you
 *
 * These four fields were registered, rendered on the front end, returned by
 * REST and reported by the list-sources ability from the beginning, and there
 * was never anywhere to type them. The post type declares custom-fields
 * support, so a determined person could set them through WordPress's raw
 * key/value box knowing the meta keys — which is the absence of a feature
 * rather than one.
 *
 * The labels are not fixed. Who this came from, where, and what was done to
 * it in between are the right three questions for a record label or a tannery
 * as much as a mill — but not in the same words, so the active producer
 * profile supplies them and the strings below are only the fallback.
 */
( function () {
	'use strict';

	const el = wp.element.createElement;
	const __ = wp.i18n.__;
	const registerPlugin = wp.plugins.registerPlugin;
	const PluginDocumentSettingPanel = wp.editor.PluginDocumentSettingPanel;
	const useEntityProp = wp.coreData.useEntityProp;
	const useSelect = wp.data.useSelect;
	const TextControl = wp.components.TextControl;
	const TextareaControl = wp.components.TextareaControl;

	/**
	 * The wording this trade uses for a field.
	 *
	 * Resolved server-side from the active producer profile, so the label a
	 * beekeeper sees says Apiary and a musician's says Label. Falls back to
	 * the farm-and-mill defaults if the blob is missing, which is what a
	 * cached page or a stripped settings object looks like.
	 *
	 * @param key
	 * @param slot
	 * @param fallback
	 */
	function fieldText( key, slot, fallback ) {
		const labels =
			( window.pkitSettings && window.pkitSettings.metaLabels ) || {};

		return ( labels[ key ] && labels[ key ][ slot ] ) || fallback;
	}

	/**
	 * Shared setup for both panels.
	 *
	 * Returns null rather than the meta pair when this is not a source, so a
	 * caller can bail before touching useEntityProp for a post type that has
	 * none of these fields.
	 */
	function useSourceMeta() {
		const postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		const meta = useEntityProp( 'postType', 'pkit_source', 'meta' );

		if ( postType !== 'pkit_source' ) {
			return null;
		}

		return {
			meta: meta[ 0 ] || {},
			setMeta: meta[ 1 ],
		};
	}

	/**
	 * Write one key without dropping the others.
	 * @param meta
	 * @param setMeta
	 */
	function updater( meta, setMeta ) {
		return function ( key, value ) {
			const updated = {};
			updated[ key ] = value;
			setMeta( Object.assign( {}, meta, updated ) );
		};
	}

	/* ─────────────────────────────────────────────
	 * Panel 1: Source Details
	 * ───────────────────────────────────────────── */

	function SourceDetailsPanel() {
		const source = useSourceMeta();

		if ( ! source ) {
			return null;
		}

		const updateMeta = updater( source.meta, source.setMeta );

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'pkit-source-details',
				title: __( 'Source Details', 'producerkit' ),
				initialOpen: true,
				icon: 'admin-site-alt3',
			},

			el( TextControl, {
				label: fieldText(
					'_pkit_source_farm_name',
					'label',
					__( 'Farm / Origin Name', 'producerkit' )
				),
				value: source.meta._pkit_source_farm_name || '',
				onChange( value ) {
					updateMeta( '_pkit_source_farm_name', value );
				},
				help: fieldText(
					'_pkit_source_farm_name',
					'help',
					__(
						'Who this came from. Falls back to the post title on the front end if left empty.',
						'producerkit'
					)
				),
			} ),

			el( TextControl, {
				label: fieldText(
					'_pkit_source_location',
					'label',
					__( 'Location', 'producerkit' )
				),
				value: source.meta._pkit_source_location || '',
				onChange( value ) {
					updateMeta( '_pkit_source_location', value );
				},
				help: fieldText(
					'_pkit_source_location',
					'help',
					__(
						'County and state, or however you would say where.',
						'producerkit'
					)
				),
			} )
		);
	}

	/* ─────────────────────────────────────────────
	 * Panel 2: Provenance
	 * ───────────────────────────────────────────── */

	function SourceProvenancePanel() {
		const source = useSourceMeta();

		if ( ! source ) {
			return null;
		}

		const updateMeta = updater( source.meta, source.setMeta );

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'pkit-source-provenance',
				title: __( 'Provenance', 'producerkit' ),
				initialOpen: false,
				icon: 'book-alt',
			},

			el( TextareaControl, {
				label: fieldText(
					'_pkit_source_history',
					'label',
					__( 'History', 'producerkit' )
				),
				value: source.meta._pkit_source_history || '',
				rows: 4,
				onChange( value ) {
					updateMeta( '_pkit_source_history', value );
				},
				help: fieldText(
					'_pkit_source_history',
					'help',
					__(
						'Heritage notes — the story behind this ingredient or variety.',
						'producerkit'
					)
				),
			} ),

			el( TextareaControl, {
				label: fieldText(
					'_pkit_milling_notes',
					'label',
					__( 'Milling / Process Notes', 'producerkit' )
				),
				value: source.meta._pkit_milling_notes || '',
				rows: 4,
				onChange( value ) {
					updateMeta( '_pkit_milling_notes', value );
				},
				help: fieldText(
					'_pkit_milling_notes',
					'help',
					__(
						'What was done to it in between — grind, cure, age, finish.',
						'producerkit'
					)
				),
			} )
		);
	}

	/* ─────────────────────────────────────────────
	 * Register
	 * ───────────────────────────────────────────── */

	registerPlugin( 'pkit-source-details', {
		render: SourceDetailsPanel,
		icon: 'admin-site-alt3',
	} );

	registerPlugin( 'pkit-source-provenance', {
		render: SourceProvenancePanel,
		icon: 'book-alt',
	} );
} )();
