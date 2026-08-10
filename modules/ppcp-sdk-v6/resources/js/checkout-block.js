/**
 * WooCommerce Blocks entry for the SDK v6 buttons.
 *
 * Two mutually exclusive modes, matching v5:
 *
 * - Normally, one *express* payment method per funding source.
 * - In continuation mode (an approved PayPal order sits in the WC session), a
 *   single *regular* method rendering the order review. Express buttons must
 *   not appear there: the order is approved and awaits confirmation.
 *
 * Either way payment processes through the existing ppcp-gateway, so no
 * parallel gateway is introduced.
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
import { V6CardFieldsComponent } from './blocks/V6CardFieldsComponent';
import { V6EditorPreview } from './blocks/V6EditorPreview';
import { fundingSourceLabel } from './utils/fundingSources';
import { minorUnitsToDecimal } from './utils/amount';

const FUNDING_SOURCES = [ 'paypal', 'venmo', 'paylater' ];

// get_payment_method_data() lands under the wcSettings `paymentMethodData`
// container, keyed by the method's registered name (V6PaymentMethod::$name).
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
	registerPaymentMethod( {
		name: 'ppcp-gateway',
		// The session's actual funding source, so the label cannot contradict
		// the server-rendered cancel text ("You are currently paying with X").
		label: createElement(
			'div',
			null,
			fundingSourceLabel( config.continuation.funding_source )
		),
		ariaLabel: fundingSourceLabel( config.continuation.funding_source ),
		content: createElement( V6ContinuationComponent, { config } ),
		edit: createElement( V6EditorPreview, { fundingSource: 'paypal' } ),
		// Set explicitly so the button never reads "Proceed to PayPal", which
		// would tell the buyer they are heading back to PayPal.
		placeOrderButtonLabel: __(
			'Place order',
			'woocommerce-paypal-payments'
		),
		canMakePayment: () => true,
		supports: {
			// WooCommerce hides any method whose features do not cover every
			// cart requirement, and v5's ppcp-gateway is unregistered here, so
			// a missing feature leaves the buyer no way to pay or cancel.
			features: [ 'products', 'subscriptions', 'ppcp_continuation' ],
		},
	} );
} else if ( config && config.page_context ) {
	// WooCommerce re-invokes canMakePayment on every cart update, so the current
	// amount is cached to avoid a lookup per funding source per update. Only the
	// current one: a stale amount is never asked for again.
	let cached = { amount: null, eligibility: null };
	const getEligibility = ( amount ) => {
		if ( cached.amount !== amount ) {
			cached = {
				amount,
				eligibility: loadSdkV6( config, config.page_context )
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
					} ),
			};
		}
		return cached.eligibility;
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
				// Exposes the block's height and corner-radius controls, which
				// arrive as the buttonAttributes prop.
				style: [ 'height', 'borderRadius' ],
			},
		} );
	}
}

// Skipped in continuation mode, where the buyer has already approved a PayPal
// order and only the review shows.
if ( config?.card_fields?.enabled && ! config.continuation ) {
	registerPaymentMethod( {
		name: config.card_fields.payment_method,
		label: createElement( 'div', null, config.card_fields.title ),
		ariaLabel: config.card_fields.title,
		content: createElement( V6CardFieldsComponent, { config } ),
		// A static placeholder, not the live fields: the SDK does not boot in
		// the block editor.
		edit: createElement(
			'div',
			{ className: 'ppcp-sdk-v6-editor-preview' },
			config.card_fields.title
		),
		canMakePayment: () => true,
		supports: {
			features: [ 'products' ],
		},
	} );
}
