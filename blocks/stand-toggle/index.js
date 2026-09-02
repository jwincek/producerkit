/**
 * Stand Quick Toggle — editor-only control block (no-build IIFE).
 *
 * Renders a toggle switch + message input that hit the REST API
 * directly from the editor. Renders nothing on the front end.
 * @param blocks
 * @param element
 * @param blockEditor
 * @param components
 * @param data
 */
( function ( blocks, element, blockEditor, components, data ) {
	'use strict';

	const el = element.createElement;
	const useState = element.useState;
	const useEffect = element.useEffect;
	const useCallback = element.useCallback;
	const Fragment = element.Fragment;
	const registerBlockType = blocks.registerBlockType;
	const InspectorControls = blockEditor.InspectorControls;
	const useBlockProps = blockEditor.useBlockProps;
	const PanelBody = components.PanelBody;
	const ComboboxControl = components.ComboboxControl;
	const ToggleControl = components.ToggleControl;
	const TextControl = components.TextControl;
	const Button = components.Button;
	const Spinner = components.Spinner;
	const Notice = components.Notice;
	const useSelect = data.useSelect;

	function getRestBase() {
		return (
			( window.pkitStandSettings || window.pkitSettings || {} )
				.restBase || '/wp-json/producerkit/v1'
		);
	}

	function getNonce() {
		return (
			( window.pkitStandSettings || window.pkitSettings || {} ).nonce ||
			''
		);
	}

	registerBlockType( 'producerkit/stand-toggle', {
		edit: function EditStandToggle( props ) {
			const attributes = props.attributes;
			const setAttributes = props.setAttributes;
			const locationId = attributes.locationId;
			const blockProps = useBlockProps( {
				className: 'pkit-stand-toggle',
			} );

			const _state = useState( false );
			const isOpen = _state[ 0 ];
			const setIsOpen = _state[ 1 ];

			const _msg = useState( '' );
			const message = _msg[ 0 ];
			const setMessage = _msg[ 1 ];

			const _saving = useState( false );
			const saving = _saving[ 0 ];
			const setSaving = _saving[ 1 ];

			const _notice = useState( '' );
			const notice = _notice[ 0 ];
			const setNotice = _notice[ 1 ];

			const _loaded = useState( false );
			const loaded = _loaded[ 0 ];
			const setLoaded = _loaded[ 1 ];

			// Fetch locations for selector.
			const locations = useSelect( function ( select ) {
				return (
					select( 'core' ).getEntityRecords(
						'postType',
						'pkit_location',
						{
							per_page: 50,
							status: 'publish',
							_fields: 'id,title',
						}
					) || []
				);
			}, [] );

			const options = locations.map( function ( l ) {
				return {
					value: l.id,
					label: l.title?.rendered || '(untitled)',
				};
			} );

			// Load current state from REST.
			useEffect(
				function () {
					if ( ! locationId ) {
						return;
					}
					setLoaded( false );
					fetch( getRestBase() + '/stand/' + locationId + '/info' )
						.then( function ( r ) {
							return r.json();
						} )
						.then( function ( payload ) {
							setIsOpen( !! payload.is_open );
							setMessage( payload.status_message || '' );
							setLoaded( true );
						} )
						.catch( function () {
							setLoaded( true );
						} );
				},
				[ locationId ]
			);

			// Save handler.
			const save = useCallback(
				function () {
					if ( ! locationId ) {
						return;
					}
					setSaving( true );
					setNotice( '' );
					fetch( getRestBase() + '/stand/' + locationId + '/status', {
						method: 'PATCH',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': getNonce(),
						},
						body: JSON.stringify( {
							is_open: isOpen,
							status_message: message,
						} ),
					} )
						.then( function ( r ) {
							return r.json();
						} )
						.then( function ( payload ) {
							setSaving( false );
							setNotice(
								'Stand is now ' +
									( payload.is_open ? 'OPEN' : 'CLOSED' ) +
									'.'
							);
							setTimeout( function () {
								setNotice( '' );
							}, 4000 );
						} )
						.catch( function () {
							setSaving( false );
							setNotice( 'Error updating stand status.' );
						} );
				},
				[ locationId, isOpen, message ]
			);

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: 'Stand Selection', initialOpen: true },
						el( ComboboxControl, {
							label: 'Location',
							value: locationId || '',
							options,
							onChange( val ) {
								setAttributes( {
									locationId: val ? Number( val ) : 0,
								} );
							},
						} )
					)
				),
				el(
					'div',
					blockProps,
					! locationId
						? el( components.Placeholder, {
								icon: 'controls-repeat',
								label: 'Stand Quick Toggle',
								instructions:
									'Select a location in the sidebar.',
						  } )
						: ! loaded
						? el(
								'div',
								{ className: 'pkit-stand-toggle__loading' },
								el( Spinner ),
								' Loading stand status…'
						  )
						: el(
								'div',
								{ className: 'pkit-stand-toggle__panel' },
								el(
									'div',
									{ className: 'pkit-stand-toggle__header' },
									el( 'span', {
										className:
											'pkit-stand-toggle__dot pkit-stand-toggle__dot--' +
											( isOpen ? 'open' : 'closed' ),
									} ),
									el(
										'strong',
										null,
										isOpen
											? 'Stand is OPEN'
											: 'Stand is CLOSED'
									)
								),
								el( ToggleControl, {
									label: isOpen ? 'Open' : 'Closed',
									checked: isOpen,
									onChange( val ) {
										setIsOpen( val );
									},
								} ),
								el( TextControl, {
									label: 'Status message (optional)',
									value: message,
									onChange( val ) {
										setMessage( val );
									},
									placeholder:
										'e.g. "Back at 2 PM" or "Sold out for today"',
								} ),
								el(
									'div',
									{ className: 'pkit-stand-toggle__actions' },
									el(
										Button,
										{
											variant: 'primary',
											onClick: save,
											isBusy: saving,
											disabled: saving,
										},
										saving
											? 'Saving…'
											: 'Update Stand Status'
									)
								),
								notice
									? el(
											Notice,
											{
												status: notice.includes(
													'Error'
												)
													? 'error'
													: 'success',
												isDismissible: true,
												onDismiss() {
													setNotice( '' );
												},
											},
											notice
									  )
									: null
						  )
				)
			);
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.data
);
