/**
 * Keeps the Apple Pay sheet total readable synchronously.
 *
 * Safari requires the ApplePaySession to be constructed inside the click handler
 * itself, so the bridge cannot await a total the way every other v6 surface does.
 * This resolves it ahead of the click and refreshes it whenever it can change.
 *
 * @package
 */

import {
	fetchCartTotal,
	productForm,
	simulateCart,
} from '../endpointsAdapter';
import { hasJQuery } from '../utils/api';

/**
 * How long to coalesce product-form changes before re-pricing.
 *
 * Kept short because the total cannot be re-read on click: a stale one is what the
 * shopper gets charged. Long enough to collapse the event burst a variation switch
 * fires, short enough that clicking straight after a change gets the new price.
 * Debounced rather than throttled, which would price the burst's first event and
 * so describe a variation the shopper has already moved away from.
 */
const REFRESH_DEBOUNCE_MS = 250;

/**
 * The form listeners are attached once per page, but every render pass creates a
 * new watcher, so they call whichever watcher is current. That way re-renders
 * neither stack listeners nor leave the live watcher unsubscribed.
 */
let refreshActive = null;
let listeningToProductForm = false;

/**
 * Resolves the total the sheet must display, without side effects.
 *
 * The product page cannot ask the cart, because the viewed product is not in it,
 * and must not add it either: that would mutate the cart of a shopper who has
 * not clicked anything yet. Hence the simulate endpoint.
 *
 * @param {Object} config  - The wc_ppcp_sdk_v6 config object.
 * @param {string} context - The page context.
 * @return {Promise<string>} The total as a decimal string, or '' when unknown.
 */
async function resolveSheetTotal( config, context ) {
	try {
		if ( context === 'product' ) {
			const { total } = await simulateCart( config );

			return total ?? '';
		}

		return await fetchCartTotal( config );
	} catch ( error ) {
		// Unknown rather than thrown, so the caller keeps the total it already had.
		return '';
	}
}

/**
 * Subscribes to the events that change what the viewed product costs.
 *
 * A native change listener covers the quantity field; the variation events cover
 * variable products, whose variation_id WooCommerce only fills in after the change
 * event that triggered the selection.
 *
 * @param {HTMLElement} form - The product form.
 */
function listenToProductForm( form ) {
	let timer = null;

	const schedule = () => {
		clearTimeout( timer );
		timer = setTimeout( () => {
			refreshActive?.();
		}, REFRESH_DEBOUNCE_MS );
	};

	form.addEventListener( 'change', schedule );

	if ( hasJQuery() ) {
		jQuery( form ).on( 'found_variation reset_data', schedule );
	}
}

/**
 * Starts tracking the sheet total for one render target.
 *
 * @param {Object} config  - The wc_ppcp_sdk_v6 config object.
 * @param {string} context - The page context.
 * @return {{get: Function}} The synchronously-readable total.
 */
export function watchSheetTotal( config, context ) {
	// Seeded so a click that beats the first resolve still has a number to show.
	let total = config.amount || '';

	// An existing order is being paid, so the localized amount is that order's
	// total and nothing on this page can change it.
	if ( context === 'pay-now' ) {
		return {
			get: () => total,
		};
	}

	// Refreshes overlap and can resolve out of order, letting a slow answer about
	// an older variation overwrite a fresh one: the stale total the debounce
	// exists to prevent.
	let latestRequest = 0;

	const refresh = async () => {
		const request = ++latestRequest;
		const resolved = await resolveSheetTotal( config, context );

		// Never overwritten by a failed lookup's empty string, nor by a request
		// that a newer one has already superseded.
		if ( resolved && request === latestRequest ) {
			total = resolved;
		}

		return total;
	};

	refreshActive = refresh;

	// Matters most on the product page, where the localized amount is the cart
	// total whenever the cart is not empty, so the seed is a placeholder rather
	// than an answer. Elsewhere it is already this page's own total.
	refresh().catch( () => {} );

	if ( context === 'product' ) {
		const form = productForm();
		if ( form && ! listeningToProductForm ) {
			listeningToProductForm = true;
			listenToProductForm( form );
		}
	}

	return {
		get: () => total,
	};
}
