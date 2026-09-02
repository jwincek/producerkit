/**
 * Shared Interactivity store.
 *
 * Every interactive block on the front end lands here rather than defining a
 * store of its own. Two reasons, both learned the hard way in this plugin:
 *
 * Blocks that share a namespace merge at runtime, and when the same action is
 * written twice in two files the later module silently wins. Event List and
 * Event Card each carried their own copy of submitRsvp, the copies had already
 * drifted, and on a page holding both blocks whichever loaded second decided
 * how RSVPs behaved for both.
 *
 * And a per-block store cannot answer a question about another block. A
 * favourites toggle that updates a count elsewhere, an availability board that
 * reacts to a stand closing — none of that is reachable when each block keeps
 * its state to itself.
 *
 * Feature modules under ./interactivity/ add to this namespace; a block's
 * view.js does nothing but import the features it needs.
 */

import { store, getContext } from '@wordpress/interactivity';

export const NAMESPACE = 'producerkit';

/**
 * The plugin's REST root.
 *
 * Blocks pass restBase through their own context because a block can render
 * before the settings script has run. Falls back to the global, then to a
 * conventional path, so a fetch never builds a URL beginning "undefined/".
 *
 * @return {string} REST base, no trailing slash.
 */
export function restBase() {
	const ctx = getContext();

	return (
		ctx?.restBase ||
		( window.pkitSettings || {} ).restBase ||
		'/wp-json/producerkit/v1'
	);
}

/**
 * A fetch that always resolves to { ok, data }.
 *
 * The Interactivity API drives async actions with generators, so callers
 * `yield` this. Every caller previously repeated the same response.json()
 * dance and the same try/catch, and the ones that forgot the catch turned a
 * dropped connection into an unhandled rejection with no message on screen.
 *
 * @param {string} path    Path below the REST base, leading slash included.
 * @param {Object} options fetch options.
 * @return {Promise<{ok: boolean, data: Object}>} Never rejects.
 */
export async function request( path, options = {} ) {
	try {
		const response = await fetch( `${ restBase() }${ path }`, options );

		let data = {};
		try {
			data = await response.json();
		} catch ( parseError ) {
			// A 204, or an error page from in front of WordPress.
			data = {};
		}

		return { ok: response.ok, data };
	} catch ( networkError ) {
		return { ok: false, data: {} };
	}
}

/**
 * JSON POST body plus header, since every write here sends JSON.
 *
 * @param {Object} body Payload.
 * @return {Object} fetch options.
 */
export function json( body ) {
	return {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify( body ),
	};
}

const { state, actions, callbacks } = store( NAMESPACE, {
	state: {},
	actions: {},
	callbacks: {},
} );

export { state, actions, callbacks };
