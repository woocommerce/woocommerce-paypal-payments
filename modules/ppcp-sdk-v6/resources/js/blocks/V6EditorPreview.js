/**
 * Static block-editor preview for a v6 express button.
 *
 * `edit` is a required property of registerExpressPaymentMethod, so this has
 * to exist. It is a placeholder rather than a rendered button because v6 is
 * not active in admin today, so nothing here is reachable: the editor still
 * shows the v5 previews. v5 renders a real, merchant-styled button that
 * follows the block's sizing controls, so matching it is part of v5
 * retirement — createInstance() accepts a clientId instead of a client token,
 * which is the path to doing so without a token round-trip.
 *
 * @package
 */

import { createElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { fundingSourceLabel } from '../utils/fundingSourceLabel';

/**
 * @param {Object} props               - Component props.
 * @param {string} props.fundingSource - The funding source (paypal, venmo, paylater).
 * @return {Object} The placeholder element.
 */
export function V6EditorPreview( { fundingSource } ) {
	const label = fundingSourceLabel( fundingSource );

	return createElement(
		'div',
		{ className: 'ppcp-sdk-v6-editor-preview' },
		sprintf(
			// translators: %s is the funding source name (PayPal, Venmo, Pay Later).
			__( '%s button', 'woocommerce-paypal-payments' ),
			label
		)
	);
}
