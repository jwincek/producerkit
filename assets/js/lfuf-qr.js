/**
 * QR rendering for [data-lfuf-qr] elements.
 *
 * Server-side render emits an empty container carrying the (already
 * URL-validated) target in data-lfuf-qr; this script draws the QR as an
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
			'[data-lfuf-qr]:not([data-lfuf-qr-done])'
		);
		Array.prototype.forEach.call( nodes, function ( node ) {
			const text = node.getAttribute( 'data-lfuf-qr' );
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
					alt: node.getAttribute( 'data-lfuf-qr-label' ) || text,
				} );
				node.setAttribute( 'data-lfuf-qr-done', '1' );
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
