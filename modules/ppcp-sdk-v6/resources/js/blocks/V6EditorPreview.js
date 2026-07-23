/**
 * Static block-editor preview for a v6 express button.
 *
 * The editor must not load the SDK or hit the network (and only one PayPal
 * SDK may run per page), so this renders a labelled placeholder standing in
 * for the funding-source button.
 *
 * @package
 */

import { createElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

const LABELS = {
	paypal: 'PayPal',
	venmo: 'Venmo',
	paylater: 'Pay Later',
};

/**
 * @param {Object} props               - Component props.
 * @param {string} props.fundingSource - The funding source (paypal, venmo, paylater).
 * @return {Object} The placeholder element.
 */
export function V6EditorPreview( { fundingSource } ) {
	const label = LABELS[ fundingSource ] || 'PayPal';

	return createElement(
		'div',
		{
			className: 'ppcp-sdk-v6-editor-preview',
			style: {
				padding: '12px',
				textAlign: 'center',
				border: '1px dashed #c3c4c7',
				borderRadius: '4px',
				color: '#50575e',
			},
		},
		sprintf(
			// translators: %s is the funding source name (PayPal, Venmo, Pay Later).
			__( '%s button', 'woocommerce-paypal-payments' ),
			label
		)
	);
}
