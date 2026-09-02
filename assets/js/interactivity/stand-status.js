/**
 * Stand Status Banner — Interactivity API view module.
 *
 * This replaces the old IIFE polling script with a declarative,
 * reactive store. The render.php output includes Interactivity
 * directives (data-wp-text, data-wp-class, data-wp-bind) that
 * automatically update the DOM when context values change.
 *
 * Registers into the shared producerkit store; see ../store.js.
 *
 * Context (per-block instance, set via data-wp-context in render.php):
 *   - locationId      (number)  — the stand's post ID
 *   - isOpen           (boolean) — current open/closed state
 *   - inSeason         (boolean) — whether within season boundaries
 *   - statusLabel      (string)  — "Open Now" or "Closed"
 *   - statusMessage    (string)  — custom message from the operator
 *   - nextOpen         (string)  — next scheduled opening text
 *   - timeAgo          (string)  — "Updated X ago"
 *   - pollingEnabled   (boolean) — whether to auto-refresh
 *   - restBase         (string)  — REST API base URL
 *
 * Designed for WP 6.5+ Interactivity API.
 * Structured so WP 7.0's watch() can replace the setInterval
 * pattern with a one-line swap when it ships.
 */

import { store, getContext } from '@wordpress/interactivity';
import { NAMESPACE } from '../store.js';

const POLL_INTERVAL = 60000; // 60 seconds

store( NAMESPACE, {
	/**
	 * Derived state — computed from context values.
	 * These are referenced by directives in render.php.
	 */
	state: {
		get nextOpenText() {
			const ctx = getContext();
			return ctx.nextOpen ? `Next open: ${ ctx.nextOpen }` : '';
		},
		get hideNextOpen() {
			const ctx = getContext();
			return ctx.isOpen || ! ctx.nextOpen;
		},
		get updatedText() {
			const ctx = getContext();
			return ctx.timeAgo ? `Updated ${ ctx.timeAgo }` : '';
		},
	},

	/**
	 * Actions — async operations that update context.
	 *
	 * Uses generator syntax for async actions as required
	 * by the Interactivity API's cooperative scheduling.
	 */
	actions: {
		*refreshStatus() {
			const ctx = getContext();
			if ( ! ctx.locationId || ! ctx.restBase ) {
				return;
			}

			try {
				const response = yield fetch(
					`${ ctx.restBase }/stand/${ ctx.locationId }/info`
				);
				const data = yield response.json();

				// Update context — directives react automatically.
				ctx.isOpen = !! data.is_open;
				ctx.inSeason = !! data.in_season;
				ctx.statusLabel = data.is_open ? 'Open Now' : 'Closed';
				ctx.statusMessage = data.status_message || '';

				// timeAgo will go stale between polls, but that's
				// acceptable — it refreshes on the next poll cycle.
				if ( data.last_toggled ) {
					ctx.timeAgo = formatTimeAgo( data.last_toggled );
				}
			} catch ( err ) {
				// Silently fail — stale server-rendered content is
				// better than breaking the display.
				console.warn( '[producerkit] Poll failed:', err );
			}
		},
	},

	/**
	 * Callbacks — lifecycle hooks tied to directives.
	 *
	 * initPolling is referenced by data-wp-init="callbacks.initPolling"
	 * in render.php. It runs once when the block hydrates.
	 *
	 * When WP 7.0 ships, this can be replaced with:
	 *   import { watch } from '@wordpress/interactivity';
	 *   watch( () => { ... } );
	 * for cleaner teardown and reactivity to pollingEnabled changes.
	 */
	callbacks: {
		initPolling() {
			const ctx = getContext();
			if ( ! ctx.pollingEnabled ) {
				return;
			}

			const { actions } = store( NAMESPACE );

			// Start polling. The interval ID is stored on the DOM element
			// so it can be cleaned up if needed in the future.
			const intervalId = setInterval( () => {
				actions.refreshStatus();
			}, POLL_INTERVAL );

			// Return cleanup for when/if the element is removed.
			// In WP 6.5 data-wp-init doesn't call cleanup on removal,
			// but structuring it this way prepares for watch() in 7.0.
			return () => {
				clearInterval( intervalId );
			};
		},
	},
} );

/**
 * Format an ISO 8601 timestamp as a relative time string.
 *
 * Simple implementation — covers the common cases for a farm stand
 * where "2 minutes ago" vs "3 hours ago" is sufficient granularity.
 *
 * @param {string} isoString - ISO 8601 date string.
 * @return {string} e.g. "5 minutes ago", "2 hours ago"
 */
function formatTimeAgo( isoString ) {
	const then = new Date( isoString );
	const now = new Date();
	const diff = Math.floor( ( now - then ) / 1000 ); // seconds

	if ( diff < 60 ) {
		return 'just now';
	}
	if ( diff < 3600 ) {
		const mins = Math.floor( diff / 60 );
		return `${ mins } minute${ mins === 1 ? '' : 's' } ago`;
	}
	if ( diff < 86400 ) {
		const hrs = Math.floor( diff / 3600 );
		return `${ hrs } hour${ hrs === 1 ? '' : 's' } ago`;
	}
	const days = Math.floor( diff / 86400 );
	return `${ days } day${ days === 1 ? '' : 's' } ago`;
}
