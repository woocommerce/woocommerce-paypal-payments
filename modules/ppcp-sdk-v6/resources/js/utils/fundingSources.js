/**
 * Funding-source display labels for the v6 express buttons.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';

/**
 * The display label for a funding source.
 *
 * Resolved lazily rather than from a module-level map so the translation is
 * looked up after the i18n data is in place. PayPal and Venmo are brand
 * names and stay untranslated; 'Pay Later' is descriptive UI text.
 *
 * @param {string} fundingSource - The funding source (paypal, venmo, paylater).
 * @return {string} The label, falling back to PayPal for unknown sources.
 */
export function fundingSourceLabel( fundingSource ) {
	switch ( fundingSource ) {
		case 'venmo':
			return 'Venmo';

		case 'paylater':
			return __( 'Pay Later', 'woocommerce-paypal-payments' );

		default:
			return 'PayPal';
	}
}
