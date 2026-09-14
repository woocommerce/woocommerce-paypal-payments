/**
 * Funding-source display labels for the v6 express buttons.
 *
 * Separate from fundingSources.js because of the @wordpress/i18n import: only
 * the block bundles enqueue wp-i18n, and boot.js is registered without script
 * dependencies, so it must not reach this file.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { FundingSources } from './fundingSources';

/**
 * The display label for a funding source.
 *
 * Resolved lazily rather than from a module-level map so the translation is
 * looked up after the i18n data is in place. PayPal, Venmo, Google Pay and Apple
 * Pay are brand names and stay untranslated; 'Pay Later' is descriptive UI text.
 *
 * @param {string} fundingSource - The funding source (paypal, venmo, paylater,
 *                               googlepay, applepay).
 * @return {string} The label, falling back to PayPal for unknown sources.
 */
export function fundingSourceLabel( fundingSource ) {
	switch ( fundingSource ) {
		case FundingSources.VENMO:
			return 'Venmo';

		case FundingSources.GOOGLEPAY:
			return 'Google Pay';

		case FundingSources.APPLEPAY:
			return 'Apple Pay';

		case FundingSources.PAYLATER:
			return __( 'Pay Later', 'woocommerce-paypal-payments' );

		default:
			return 'PayPal';
	}
}
