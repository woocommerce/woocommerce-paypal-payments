/**
 * Tracks what the page the shopper is looking at currently costs.
 *
 * A product page cannot ask the cart: the viewed product is not in it, and
 * adding it would mutate the cart of a shopper who has not clicked anything.
 * It prices through the simulate endpoint instead. Every other context asks
 * the cart.
 *
 * Shared, because two surfaces need the same answer in different shapes: Apple
 * Pay must read it synchronously inside the click handler, Pay Later messaging
 * needs to be told when it changes.
 *
 * @package
 */

import { fetchCartTotal, productForm, simulateCart } from '../endpointsAdapter';
import { hasJQuery } from './api';

/**
 * How long to coalesce product-form changes before re-pricing.
 *
 * Short, because Apple Pay cannot re-read the total on click — a stale one is
 * what the shopper gets charged. Debounced rather than throttled, which would
 * price the burst's first event and so describe a variation already moved away
 * from.
 */
const REFRESH_DEBOUNCE_MS = 250;

// Per context: 'product' resolves through the simulate endpoint and everything
// else through the cart, so the two cannot share one cached total.
const states = new Map();

// Attached once per page, however many surfaces are watching.
let listening = false;

/**
 * Resets module state. Test seam only.
 */
export function resetViewedTotals() {
	states.clear();
	listening = false;
}

/**
 * @param {string} context - The page context.
 * @return {Object} The state for that context.
 */
function stateFor( context ) {
	if ( ! states.has( context ) ) {
		states.set( context, {
			total: '',
			latestRequest: 0,
			subscribers: new Set(),
		} );
	}

	return states.get( context );
}

/**
 * @param {Object} config  - The wc_ppcp_sdk_v6 config object.
 * @param {string} context - The page context.
 * @return {Promise<string>} The total, or '' when unknown.
 */
async function resolve( config, context ) {
	try {
		if ( 'product' === context ) {
			const { total } = await simulateCart( config );

			return total ?? '';
		}

		return await fetchCartTotal( config );
	} catch ( error ) {
		// Unknown rather than thrown, so watchers keep the total they had.
		return '';
	}
}

/**
 * Re-prices one context and notifies its subscribers.
 *
 * @param {Object} config  - The wc_ppcp_sdk_v6 config object.
 * @param {string} context - The page context.
 * @return {Promise<string>} The current total.
 */
async function refresh( config, context ) {
	const state = stateFor( context );
	const request = ++state.latestRequest;
	const resolved = await resolve( config, context );

	// Never overwritten by a failed lookup's empty string, nor by a request a
	// newer one has already superseded — overlapping refreshes can resolve out
	// of order, which is the stale total the debounce exists to prevent.
	const superseded = request !== state.latestRequest;
	if ( resolved && ! superseded && resolved !== state.total ) {
		state.total = resolved;
		state.subscribers.forEach( ( notify ) => notify( resolved ) );
	}

	return state.total;
}

/**
 * Subscribes to the events that change what the viewed product costs.
 *
 * The native change listener covers the quantity field; the variation events
 * cover variable products, whose variation_id WooCommerce only fills in after
 * the change event that triggered the selection.
 *
 * @param {Object}      config - The wc_ppcp_sdk_v6 config object.
 * @param {HTMLElement} form   - The product form.
 */
function listenToProductForm( config, form ) {
	let timer = null;

	const schedule = () => {
		clearTimeout( timer );
		timer = setTimeout( () => {
			refresh( config, 'product' ).catch( () => {} );
		}, REFRESH_DEBOUNCE_MS );
	};

	form.addEventListener( 'change', schedule );

	if ( hasJQuery() ) {
		jQuery( form ).on( 'found_variation reset_data', schedule );
	}
}

/**
 * Starts tracking the total for one context.
 *
 * Repeated calls for the same context share its total, so every surface sees
 * the same number and the form listeners are not stacked.
 *
 * @param {Object} config  - The wc_ppcp_sdk_v6 config object.
 * @param {string} context - The page context.
 * @return {{get: Function, subscribe: Function}} Reader and change subscription.
 */
export function watchViewedTotal( config, context ) {
	const state = stateFor( context );

	// Seeded so a synchronous reader that beats the first resolve still has a
	// number. On a product page it is the cart total whenever the cart is not
	// empty, so it is a placeholder rather than an answer.
	if ( ! state.total ) {
		state.total = config.amount || '';
	}

	refresh( config, context ).catch( () => {} );

	if ( 'product' === context && ! listening ) {
		const form = productForm();
		if ( form ) {
			listening = true;
			listenToProductForm( config, form );
		}
	}

	return {
		get: () => state.total,
		subscribe: ( notify ) => {
			state.subscribers.add( notify );

			return () => state.subscribers.delete( notify );
		},
	};
}
