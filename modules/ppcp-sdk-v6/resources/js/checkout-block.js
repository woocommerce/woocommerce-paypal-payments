/**
 * WooCommerce Blocks entry for the SDK v6 buttons.
 *
 * Two mutually exclusive modes, matching v5:
 *
 * - Normally, one *express* payment method per funding source, each rendering
 *   a v6 button. Eligibility is amount-sensitive (Pay Later thresholds), so it
 *   is re-checked when the cart total changes; WooCommerce re-invokes
 *   canMakePayment on every cart update.
 * - In continuation mode (the buyer has an approved PayPal order in the WC
 *   session, after a final-review redirect), a single *regular* payment method
 *   rendering the order review instead. Express buttons must not appear here:
 *   the order is already approved and awaits confirmation.
 *
 * Either way payment processes through the existing ppcp-gateway
 * (paymentMethodId), so no parallel gateway is introduced.
 *
 * @package
 */

import {
	registerExpressPaymentMethod,
	registerPaymentMethod,
} from '@woocommerce/blocks-registry';
import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { loadSdkV6 } from './sdkLoader';
import { checkEligibility } from './eligibility';
import { V6ExpressComponent } from './blocks/V6ExpressComponent';
import { V6ContinuationComponent } from './blocks/V6ContinuationComponent';
import { V6EditorPreview } from './blocks/V6EditorPreview';
import { fundingSourceLabel } from './utils/fundingSources';
import { minorUnitsToDecimal } from './utils/amount';

const FUNDING_SOURCES = [ 'paypal', 'venmo', 'paylater' ];

// WooCommerce exposes each block payment method's get_payment_method_data()
// under the wcSettings `paymentMethodData` container, keyed by the method's
// registered name (V6PaymentMethod::$name).
const paymentMethodData =
	window.wc?.wcSettings?.getSetting?.( 'paymentMethodData' ) || {};
const config = paymentMethodData[ 'ppcp-sdk-v6' ];

/**
 * Derives a decimal amount string from the WC Blocks cart totals.
 *
 * @param {Object} cartTotals - The canMakePayment cartTotals (minor units).
 * @return {string} The amount as a decimal string, or '' when unknown.
 */
function amountFromCartTotals( cartTotals ) {
	return minorUnitsToDecimal(
		cartTotals?.total_price,
		cartTotals?.currency_minor_unit
	);
}

if ( config && config.page_context && config.continuation ) {
	// Continuation mode: one regular method rendering the order review. No
	// express buttons, no SDK load — the buyer's order is already approved.
	registerPaymentMethod( {
		name: 'ppcp-gateway',
		label: createElement( 'div', null, fundingSourceLabel( 'paypal' ) ),
		ariaLabel: fundingSourceLabel( 'paypal' ),
		content: createElement( V6ContinuationComponent, { config } ),
		edit: createElement( V6EditorPreview, { fundingSource: 'paypal' } ),
		// The order is already approved; this click places it. Set explicitly so
		// the button never reads "Proceed to PayPal", which would tell the buyer
		// they are heading back to PayPal.
		placeOrderButtonLabel: __(
			'Place order',
			'woocommerce-paypal-payments'
		),
		canMakePayment: () => true,
		supports: {
			features: [ 'products', 'ppcp_continuation' ],
		},
	} );
} else if ( config && config.page_context ) {
	// Eligibility depends on the amount (Pay Later thresholds), so cache the
	// SDK lookup per amount; loadSdkV6 is promise-memoized across amounts.
	const eligibilityByAmount = {};
	const getEligibility = ( amount ) => {
		if ( ! eligibilityByAmount[ amount ] ) {
			eligibilityByAmount[ amount ] = loadSdkV6(
				config,
				config.page_context
			)
				.then( ( sdk ) =>
					checkEligibility( sdk, {
						currencyCode: config.currency,
						countryCode: config.buyer_country,
						amount,
					} )
				)
				.catch( ( error ) => {
					// eslint-disable-next-line no-console
					console.error(
						'[ppcp-sdk-v6] eligibility check failed',
						error
					);
					return {};
				} );
		}
		return eligibilityByAmount[ amount ];
	};

	for ( const fundingSource of FUNDING_SOURCES ) {
		registerExpressPaymentMethod( {
			name: `ppcp-gateway-${ fundingSource }`,
			title: 'PayPal',
			description: __(
				'Eligible users will see the PayPal button.',
				'woocommerce-paypal-payments'
			),
			gatewayId: 'ppcp-gateway',
			paymentMethodId: 'ppcp-gateway',
			label: createElement(
				'div',
				null,
				fundingSourceLabel( fundingSource )
			),
			ariaLabel: fundingSourceLabel( fundingSource ),
			content: createElement( V6ExpressComponent, {
				config,
				fundingSource,
			} ),
			edit: createElement( V6EditorPreview, { fundingSource } ),
			canMakePayment: async ( { cartTotals } = {} ) => {
				const amount =
					amountFromCartTotals( cartTotals ) || config.amount;
				const eligibility = await getEligibility( amount );
				return Boolean( eligibility[ fundingSource ] );
			},
			supports: {
				features: [ 'products' ],
			},
		} );
	}
}
