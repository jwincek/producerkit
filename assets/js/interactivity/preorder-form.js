/**
 * Pre-Order Form — Interactivity API view module.
 */

import { store, getContext } from '@wordpress/interactivity';
import { NAMESPACE } from '../store.js';

store( NAMESPACE, {
	state: {
		/**
		 * Where the customer can view or cancel this order.
		 *
		 * Empty until the order comes back, because the token is what makes
		 * the link work — the success panel previously printed that token as
		 * a bare "cancellation code" with nowhere to use it.
		 */
		get orderUrl() {
			const ctx = getContext();

			if ( ! ctx.token || ! ctx.orderUrlTemplate ) {
				return '';
			}

			return ctx.orderUrlTemplate.replace(
				'__TOKEN__',
				encodeURIComponent( ctx.token )
			);
		},
	},

	actions: {
		updateQty( event ) {
			const ctx = getContext();
			const productId = event.target.dataset.productId;
			const qty = Math.max(
				0,
				Math.min( 99, parseInt( event.target.value, 10 ) || 0 )
			);
			if ( qty > 0 ) {
				ctx.items[ productId ] = qty;
			} else {
				delete ctx.items[ productId ];
			}
		},
		updateField( event ) {
			getContext()[ event.target.dataset.field ] = event.target.value;
		},
		*submit( event ) {
			event.preventDefault();
			const ctx = getContext();

			const items = Object.keys( ctx.items ).map( ( productId ) => ( {
				product_id: parseInt( productId, 10 ),
				qty: ctx.items[ productId ],
			} ) );

			if ( ! items.length ) {
				ctx.error = 'Please choose at least one product.';
				return;
			}
			if ( ! ctx.name.trim() ) {
				ctx.error = 'Please enter your name.';
				return;
			}
			if ( ! ctx.pickupDate ) {
				ctx.error = 'Please choose a pickup date.';
				return;
			}
			if ( Array.isArray( ctx.allowedDays ) && ctx.allowedDays.length ) {
				const weekday = new Date(
					ctx.pickupDate + 'T12:00:00'
				).getDay();
				if ( ctx.allowedDays.indexOf( weekday ) === -1 ) {
					ctx.error =
						"That day isn't a pickup day. Pickup days: " +
						ctx.allowedLabel +
						'.';
					return;
				}
			}
			if (
				Array.isArray( ctx.blackouts ) &&
				ctx.blackouts.indexOf( ctx.pickupDate ) !== -1
			) {
				ctx.error =
					"We're closed that day — please choose another date.";
				return;
			}

			ctx.submitting = true;
			ctx.error = '';
			try {
				const response = yield fetch( `${ ctx.restBase }/preorders`, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( {
						name: ctx.name.trim(),
						email: ctx.email,
						phone: ctx.phone,
						pickup_date: ctx.pickupDate,
						note: ctx.note,
						location_id: ctx.locationId,
						items,
						website: ctx._hp || '',
					} ),
				} );
				const data = yield response.json();
				if ( ! response.ok ) {
					ctx.error =
						data.message ||
						'Something went wrong. Please try again.';
					ctx.submitting = false;
					return;
				}
				ctx.token = data.order?.token || '';
				ctx.submitted = true;
			} catch ( err ) {
				ctx.error = 'Could not reach the server. Please try again.';
				ctx.submitting = false;
			}
		},
	},
} );
