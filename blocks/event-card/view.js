/**
 * Event Card — Interactivity API view module.
 *
 * Shares the producerkit/event-list store namespace so the
 * RSVP actions and state getters work identically whether
 * the card is rendered standalone or within the event list.
 *
 * This module simply ensures the store is loaded. The actual
 * store definition lives in event-list/view.js — WordPress
 * merges stores with the same namespace automatically.
 */

import { store, getContext } from '@wordpress/interactivity';

// Register a minimal store to ensure the namespace exists
// even if event-list/view.js hasn't loaded on this page.
// If event-list IS loaded, WordPress merges the stores.
store( 'producerkit/event-list', {
	state: {
		get isEventHidden() {
			return false; // Single card is never hidden.
		},
		get rsvpSummaryText() {
			const ctx = getContext();
			let text = `${ ctx.headcount } people coming`;
			if ( ctx.spotsLeft !== null ) {
				text += ` · ${ ctx.spotsLeft } spots left`;
			}
			return text;
		},
		get rsvpButtonText() {
			const ctx = getContext();
			return ctx.submitting ? 'Sending…' : "I'm coming!";
		},
	},
	actions: {
		updateRsvpName( event ) {
			getContext().rsvpName = event.target.value;
		},
		updateRsvpSize( event ) {
			getContext().rsvpSize = Math.max(
				1,
				parseInt( event.target.value ) || 1
			);
		},
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
			try {
				const response = yield fetch(
					`${ ctx.restBase }/events/${ ctx.eventId }/rsvp`,
					{
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify( {
							name: ctx.rsvpName.trim(),
							email: ctx.rsvpEmail,
							party_size: ctx.rsvpSize,
							note: ctx.rsvpNote,
							website: ctx._hp || '',
						} ),
					}
				);
				const data = yield response.json();
				if ( ! response.ok ) {
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
			} catch ( err ) {
				ctx.rsvpError = 'Network error. Please try again.';
			}
			ctx.submitting = false;
		},
		*cancelRsvp() {
			const ctx = getContext();
			if ( ! ctx.rsvpToken ) {
				return;
			}
			try {
				const response = yield fetch(
					`${ ctx.restBase }/rsvp/${ ctx.rsvpToken }`,
					{ method: 'DELETE' }
				);
				if ( response.ok ) {
					ctx.rsvpSubmitted = false;
					ctx.rsvpMessage = '';
					ctx.rsvpToken = '';
					ctx.rsvpName = '';
					ctx.rsvpSize = 1;
					ctx.headcount = Math.max( 0, ctx.headcount - ctx.rsvpSize );
					if ( ctx.spotsLeft !== null ) {
						ctx.spotsLeft += ctx.rsvpSize;
					}
					ctx.isFull = false;
				}
			} catch ( err ) {}
		},
	},
} );
