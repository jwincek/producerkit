/**
 * Event RSVP — shared by the Event List and Event Card blocks.
 *
 * One implementation, deliberately. These two blocks previously each carried
 * their own copy of this logic while registering into the same store
 * namespace, which had two consequences: the copies drifted (only the list
 * recorded is_full, so an RSVP from a card never learned the event had just
 * filled), and on a page holding both blocks whichever module loaded second
 * overwrote the other's actions for both.
 */

import { store, getContext } from '@wordpress/interactivity';
import { NAMESPACE, request, json } from '../store.js';

const { state } = store( NAMESPACE, {
	state: {
		/** Button label, which doubles as the in-flight indicator. */
		get rsvpButtonText() {
			const ctx = getContext();
			return ctx.submitting ? 'Sending…' : "I'm coming!";
		},

		get rsvpSummaryText() {
			const ctx = getContext();
			let text = `${ ctx.headcount } people coming`;
			if ( ctx.spotsLeft !== null ) {
				text += ` · ${ ctx.spotsLeft } spots left`;
			}
			return text;
		},
	},

	actions: {
		updateRsvpName( event ) {
			getContext().rsvpName = event.target.value;
		},

		updateRsvpSize( event ) {
			const ctx = getContext();
			ctx.rsvpSize = Math.max(
				1,
				parseInt( event.target.value, 10 ) || 1
			);
		},

		/**
		 * Honeypot: bots fill this, people never see it.
		 * @param event
		 */
		updateHoneypot( event ) {
			getContext()._hp = event.target.value;
		},

		*submitRsvp() {
			const ctx = getContext();

			if ( ! ctx.rsvpName.trim() ) {
				ctx.rsvpError = 'Please enter your name.';
				return;
			}

			ctx.submitting = true;
			ctx.rsvpError = '';

			const { ok, data } = yield request(
				`/events/${ ctx.eventId }/rsvp`,
				json( {
					name: ctx.rsvpName.trim(),
					email: ctx.rsvpEmail,
					party_size: ctx.rsvpSize,
					note: ctx.rsvpNote,
					website: ctx._hp || '',
				} )
			);

			if ( ! ok ) {
				ctx.rsvpError = data.message || 'Something went wrong.';
				ctx.submitting = false;
				return;
			}

			ctx.rsvpSubmitted = true;
			ctx.rsvpMessage = data.message || "You're on the list!";
			ctx.rsvpToken = data.rsvp?.token || '';
			ctx.headcount =
				data.summary?.headcount ?? ctx.headcount + ctx.rsvpSize;
			ctx.spotsLeft = data.summary?.spots_left ?? ctx.spotsLeft;
			ctx.isFull = data.summary?.is_full ?? false;
			ctx.submitting = false;
		},

		*cancelRsvp() {
			const ctx = getContext();
			if ( ! ctx.rsvpToken ) {
				return;
			}

			const { ok } = yield request( `/rsvp/${ ctx.rsvpToken }`, {
				method: 'DELETE',
			} );

			if ( ! ok ) {
				return;
			}

			// Restore the seats this party was holding before clearing it.
			ctx.headcount = Math.max( 0, ctx.headcount - ctx.rsvpSize );
			if ( ctx.spotsLeft !== null ) {
				ctx.spotsLeft = ctx.spotsLeft + ctx.rsvpSize;
			}

			ctx.rsvpSubmitted = false;
			ctx.rsvpMessage = '';
			ctx.rsvpToken = '';
			ctx.rsvpName = '';
			ctx.rsvpSize = 1;
			ctx.isFull = false;
		},
	},
} );

export { state };
