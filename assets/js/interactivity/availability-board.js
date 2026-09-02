/**
 * Availability Board — Interactivity API view module.
 *
 * CRITICAL: Do NOT declare default values for state properties that are
 * initialized server-side via wp_interactivity_state(). The server values
 * are injected into the store BEFORE this module runs. If we declare
 * state: { activeStatuses: {} }, it OVERWRITES the server values.
 *
 * Only declare computed getters here. The server provides:
 *   - state.activeStatuses  (object map)
 *   - state.allStatuses     (array of status keys)
 *   - state.activeType      (string)
 *   - state.totalItems      (number)
 *
 * Registers into the shared producerkit store; see ../store.js.
 */

import { store, getContext } from '@wordpress/interactivity';
import { NAMESPACE } from '../store.js';

const { state } = store( NAMESPACE, {
	state: {
		// NO default values here — they come from wp_interactivity_state().

		/**
		 * Whether any status filter is currently active.
		 *
		 * Iterates ALL status keys so the Interactivity API proxy
		 * registers dependencies on every property — required for
		 * reactive updates when any status toggles.
		 */
		get anyStatusActive() {
			let active = false;
			const keys = state.allStatuses;
			for ( let i = 0; i < keys.length; i++ ) {
				if ( state.activeStatuses[ keys[ i ] ] === true ) {
					active = true;
				}
			}
			return active;
		},

		get isCurrentStatusActive() {
			const ctx = getContext();
			return state.activeStatuses[ ctx.filterStatus ] === true;
		},

		get isProductTypeActive() {
			const ctx = getContext();
			return state.activeType === ctx.filterType;
		},

		get isCurrentItemHidden() {
			const ctx = getContext();

			if (
				state.anyStatusActive &&
				state.activeStatuses[ ctx.itemStatus ] !== true
			) {
				return true;
			}

			if ( state.activeType && ctx.itemType !== state.activeType ) {
				return true;
			}

			return false;
		},

		get isCurrentGroupHidden() {
			const ctx = getContext();

			if ( state.activeType && state.activeType !== ctx.groupSlug ) {
				return true;
			}

			if ( ! state.anyStatusActive ) {
				return false;
			}

			const statuses = ctx.itemStatuses || [];
			for ( let i = 0; i < statuses.length; i++ ) {
				if ( state.activeStatuses[ statuses[ i ] ] === true ) {
					return false;
				}
			}

			return true;
		},

		get currentGroupCount() {
			const ctx = getContext();

			if ( state.activeType && state.activeType !== ctx.groupSlug ) {
				return '0';
			}

			if ( ! state.anyStatusActive ) {
				return String( ctx.itemCount || 0 );
			}

			const statuses = ctx.itemStatuses || [];
			let count = 0;
			for ( let i = 0; i < statuses.length; i++ ) {
				if ( state.activeStatuses[ statuses[ i ] ] === true ) {
					count++;
				}
			}
			return String( count );
		},

		get footerText() {
			const items = state.allItems;
			let count = 0;
			for ( let i = 0; i < items.length; i++ ) {
				const statusMatch =
					! state.anyStatusActive ||
					state.activeStatuses[ items[ i ].status ] === true;
				const typeMatch =
					! state.activeType || items[ i ].type === state.activeType;
				if ( statusMatch && typeMatch ) {
					count++;
				}
			}

			if ( count === state.totalItems ) {
				return `Showing ${ count } items`;
			}
			return `Showing ${ count } of ${ state.totalItems } items`;
		},
	},

	actions: {
		toggleStatus() {
			const ctx = getContext();
			const status = ctx.filterStatus;
			if ( ! status ) {
				return;
			}
			state.activeStatuses[ status ] = ! state.activeStatuses[ status ];
		},

		setProductTypeFilter() {
			const ctx = getContext();
			state.activeType = ctx.filterType;
		},
	},
} );
