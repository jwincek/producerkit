/**
 * Commission Request Form — front end.
 *
 * Serialises the form (including whatever hidden fields Onsite Spam Guard
 * added) and POSTs it as JSON to lfuf/v1/commissions. No nonce: the route is
 * public by design, and the server applies the honeypot, the spam guard and a
 * per-IP rate limit.
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		const roots = document.querySelectorAll(
			'[data-lfuf-commission-form]'
		);

		Array.prototype.forEach.call( roots, function ( root ) {
			const form = root.querySelector( '.lfuf-commission-form__form' );
			const endpoint = root.getAttribute( 'data-endpoint' );

			if ( ! form || ! endpoint ) {
				return;
			}

			const message = root.querySelector(
				'.lfuf-commission-form__message'
			);
			const submit = root.querySelector(
				'.lfuf-commission-form__submit'
			);

			function say( text, kind ) {
				message.textContent = text;
				message.className = 'lfuf-commission-form__message is-' + kind;
			}

			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();

				// Let the browser surface its own validation first.
				if ( ! form.checkValidity() ) {
					form.reportValidity();
					return;
				}

				const payload = {};
				Array.prototype.forEach.call(
					form.querySelectorAll(
						'input[name], select[name], textarea[name]'
					),
					function ( field ) {
						payload[ field.name ] = field.value;
					}
				);

				submit.disabled = true;
				say(
					submit.getAttribute( 'data-sending' ) || 'Sending…',
					'pending'
				);

				fetch( endpoint, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( payload ),
				} )
					.then( function ( response ) {
						return response.json().then( function ( data ) {
							return { ok: response.ok, data };
						} );
					} )
					.then( function ( result ) {
						if ( ! result.ok ) {
							submit.disabled = false;
							say(
								( result.data && result.data.message ) ||
									'Something went wrong. Please try again.',
								'error'
							);
							return;
						}

						// Replace the form outright: a second submission would
						// only trip the rate limiter.
						form.hidden = true;
						say(
							root.getAttribute( 'data-thanks' ) ||
								'Thank you — your request is in. We will be in touch with a quote.',
							'success'
						);
					} )
					.catch( function () {
						submit.disabled = false;
						say(
							'Could not reach the server. Please try again.',
							'error'
						);
					} );
			} );
		} );
	} );
} )();
