/**
 * Keeps the Apple Pay sheet total readable synchronously.
 *
 * Safari requires the ApplePaySession to be constructed inside the click handler
 * itself, so the bridge cannot await a total the way every other v6 surface
 * does. The shared watcher resolves it ahead of the click and re-prices it
 * whenever the viewed product changes.
 *
 * @package
 */

import { watchViewedTotal } from '../utils/viewedTotal';

/**
 * Starts tracking the sheet total for one render target.
 *
 * @param {Object} config  - The wc_ppcp_sdk_v6 config object.
 * @param {string} context - The page context.
 * @return {{get: Function}} The synchronously-readable total.
 */
export function watchSheetTotal( config, context ) {
	// An existing order is being paid, so the localized amount is that order's
	// total and nothing on this page can change it. The shared watcher prices
	// the cart, which is not what this page charges.
	if ( context === 'pay-now' ) {
		const total = config.amount || '';

		return { get: () => total };
	}

	return { get: watchViewedTotal( config, context ).get };
}
