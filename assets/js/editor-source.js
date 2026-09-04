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
 * The labels below are the farm-and-mill wording the fields were named for.
 * They are the right three questions for a record label or a tannery too —
 * who, where, and what was done in between — but not in these words. Issue
 * #22 covers letting a producer profile re-word them, which is a change to
 * this file once a panel exists to re-word.
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
				label: __( 'Farm / Origin Name', 'producerkit' ),
				value: source.meta._pkit_source_farm_name || '',
				onChange( value ) {
					updateMeta( '_pkit_source_farm_name', value );
				},
				help: __(
					'Who this came from. Falls back to the post title on the front end if left empty.',
					'producerkit'
				),
			} ),

			el( TextControl, {
				label: __( 'Location', 'producerkit' ),
				value: source.meta._pkit_source_location || '',
				onChange( value ) {
					updateMeta( '_pkit_source_location', value );
				},
				help: __(
					'County and state, or however you would say where.',
					'producerkit'
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
				label: __( 'History', 'producerkit' ),
				value: source.meta._pkit_source_history || '',
				rows: 4,
				onChange( value ) {
					updateMeta( '_pkit_source_history', value );
				},
				help: __(
					'Heritage notes — the story behind this ingredient or variety.',
					'producerkit'
				),
			} ),

			el( TextareaControl, {
				label: __( 'Milling / Process Notes', 'producerkit' ),
				value: source.meta._pkit_milling_notes || '',
				rows: 4,
				onChange( value ) {
					updateMeta( '_pkit_milling_notes', value );
				},
				help: __(
					'What was done to it in between — grind, cure, age, finish.',
					'producerkit'
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
