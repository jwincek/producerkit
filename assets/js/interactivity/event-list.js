/**
 * Event List — the filtering that belongs to the list block alone.
 *
 * RSVP lives in ./rsvp.js because the Event Card needs it too; type filtering
 * only makes sense where there is a list to filter.
 */

import { store, getContext } from '@wordpress/interactivity';
import { NAMESPACE } from '../store.js';
import './rsvp.js';

const { state } = store( NAMESPACE, {
	state: {
		// activeTypeFilter is seeded by wp_interactivity_state() — not redeclared.

		/** Drives data-wp-class and aria-pressed on each filter button. */
		get isEventTypeActive() {
			return state.activeTypeFilter === getContext().filterType;
		},

		get isEventHidden() {
			const ctx = getContext();
			if ( ! state.activeTypeFilter ) {
				return false;
			}
			return ctx.eventType !== state.activeTypeFilter;
		},
	},

	actions: {
		setEventTypeFilter() {
			state.activeTypeFilter = getContext().filterType;
		},
	},
} );

export { state };
