/**
 * WooCommerce Blocks entry for the SDK v6 express buttons.
 *
 * Registers one express payment method per funding source. Each renders a
 * v6 button. Eligibility is amount-sensitive (Pay Later thresholds), so it
 * is re-checked when the cart total changes; WooCommerce re-invokes
 * canMakePayment on every cart update. Payment processes through the
 * existing ppcp-gateway (paymentMethodId), so no parallel gateway is
 * introduced.
 *
 * @package
 */

import { registerExpressPaymentMethod } from '@woocommerce/blocks-registry';
import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { loadSdkV6 } from './sdkLoader';
import { checkEligibility } from './eligibility';
import { V6ExpressComponent } from './blocks/V6ExpressComponent';
import { V6EditorPreview } from './blocks/V6EditorPreview';

const FUNDING_SOURCES = [ 'paypal', 'venmo', 'paylater' ];

const LABELS = {
	paypal: 'PayPal',
	venmo: 'Venmo',
	paylater: 'Pay Later',
};

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
	const minor = parseInt( cartTotals?.total_price, 10 );
	if ( isNaN( minor ) ) {
		return '';
	}
	const minorUnit = cartTotals?.currency_minor_unit ?? 2;
	return ( minor / Math.pow( 10, minorUnit ) ).toFixed( minorUnit );
}

if ( config && config.page_context ) {
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
			label: createElement( 'div', null, LABELS[ fundingSource ] ),
			ariaLabel: LABELS[ fundingSource ],
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
