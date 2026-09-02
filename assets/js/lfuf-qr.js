/**
 * QR rendering for [data-pkit-qr] elements.
 *
 * Server-side render emits an empty container carrying the (already
 * URL-validated) target in data-pkit-qr; this script draws the QR as an
 * inline SVG using the bundled qrcode-generator library (MIT, Kazuhiko
 * Arase). If JS is unavailable or encoding fails, the container stays
 * empty and the adjacent payment link remains the fallback.
 */
( function () {
	'use strict';

	function render() {
		if ( typeof window.qrcode !== 'function' ) {
			return;
		}
		const nodes = document.querySelectorAll(
			'[data-pkit-qr]:not([data-pkit-qr-done])'
		);
		Array.prototype.forEach.call( nodes, function ( node ) {
			const text = node.getAttribute( 'data-pkit-qr' );
			if ( ! text ) {
				return;
			}
			try {
				const qr = window.qrcode( 0, 'M' ); // Type 0 = auto-size for the data.
				qr.addData( text );
				qr.make();
				node.innerHTML = qr.createSvgTag( {
					cellSize: 4,
					margin: 2,
					scalable: true,
					alt: node.getAttribute( 'data-pkit-qr-label' ) || text,
				} );
				node.setAttribute( 'data-pkit-qr-done', '1' );
			} catch ( e ) {
				// Data too long or encoder failure — leave the fallback link.
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', render );
	} else {
		render();
	}
} )();
