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
import { __, sprintf } from '@wordpress/i18n';
import { loadSdkV6 } from './sdkLoader';
import { checkEligibility } from './eligibility';
import { V6ExpressComponent } from './blocks/V6ExpressComponent';
import { V6WalletComponent } from './blocks/V6WalletComponent';
import { V6ContinuationComponent } from './blocks/V6ContinuationComponent';
import { V6CardFieldsComponent } from './blocks/V6CardFieldsComponent';
import { V6EditorPreview } from './blocks/V6EditorPreview';
// Reused as-is from the blocks module: renders the saved-PayPal vault approval
// into the selected saved-token row (its own namespaced SDK, no v6 clash).
import { PayPalSavedToken } from '@ppcp-blocks/Components/paypal-saved-token';
import { FundingSources } from './utils/fundingSources';
import { fundingSourceLabel } from './utils/fundingSourceLabel';
import { minorUnitsToDecimal } from './utils/amount';
import { setErrorLabels } from './utils/errorHandler';
import {
	methodConfig,
	methodGatewayId,
	MERCHANT_PRESENTED_METHODS,
} from './methods/methodRegistry';
import { isDeviceEligible } from './wallets/applePay';
import { initMessages, updateMessagesAmount } from './messages/renderer';
import { watchBlockCartTotal } from './messages/cartTotalWatcher';

/**
 * The one-line description shown beside an express method in the block editor.
 *
 * @param {string} label - The funding source's display label.
 * @return {string} The description.
 */
function expressDescription( label ) {
	return sprintf(
		// translators: %s is the payment method name, e.g. Venmo or Apple Pay.
		__(
			'Eligible users will see the %s button.',
			'woocommerce-paypal-payments'
		),
		label
	);
}

const ALL_FUNDING_SOURCES = [
	FundingSources.PAYPAL,
	FundingSources.VENMO,
	FundingSources.PAYLATER,
];

// get_payment_method_data() lands under the wcSettings `paymentMethodData`
// container, keyed by the method's registered name (V6PaymentMethod::$name).
const paymentMethodData =
	window.wc?.wcSettings?.getSetting?.( 'paymentMethodData' ) || {};
const config = paymentMethodData[ 'ppcp-sdk-v6' ];

// Wording for the error notices the bridges raise.
setErrorLabels( config?.labels );

// A free-trial ($0) subscription is vaulted through the PayPal save flow, which
// only PayPal offers; Venmo/Pay Later cannot save without a purchase, so they are
// suppressed on a free-trial cart (mirrors the v5 blocks checkout).
//
// Pay Later carries a per-context merchant setting on top of eligibility;
// renderButtons() applies the same flag for the classic stack.
const FUNDING_SOURCES = config?.is_free_trial_cart
	? [ FundingSources.PAYPAL ]
	: ALL_FUNDING_SOURCES.filter(
			( fundingSource ) =>
				fundingSource !== FundingSources.PAYLATER ||
				Boolean( config?.pay_later_button?.[ config.page_context ] )
	  );

/**
 * Blocks drops a method whose features miss a cart requirement, so a method
 * that declares none is unusable everywhere; 'products' is the minimum claim.
 *
 * @param {string[]} [features] - The processing gateway's features.
 * @return {string[]} The features to declare, never empty.
 */
function gatewayFeatures( features ) {
	return features || [ 'products' ];
}

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
		edit: createElement( V6EditorPreview, {
			fundingSource: FundingSources.PAYPAL,
		} ),
		// Set explicitly so the button never reads "Proceed to PayPal", which
		// would tell the buyer they are heading back to PayPal.
		placeOrderButtonLabel: __(
			'Place order',
			'woocommerce-paypal-payments'
		),
		canMakePayment: () => true,
		supports: {
			// v5's ppcp-gateway is unregistered here, so a dropped method
			// leaves the buyer no way to pay or cancel.
			features: [
				...gatewayFeatures( config.supported_features ),
				'ppcp_continuation',
			],
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

						// Cleared, so the next update refetches instead of
						// reusing a failure that hides every express button.
						cached = { amount: null, eligibility: null };

						return {};
					} ),
			};
		}
		return cached.eligibility;
	};

	/**
	 * Registers one express button, for a PayPal funding source or a wallet.
	 *
	 * @param {Object}   args                   - The registration inputs.
	 * @param {string}   args.name              - The name the block registry
	 *                                          knows the method by.
	 * @param {string}   args.gatewayId         - The gateway that processes the
	 *                                          payment.
	 * @param {string}   args.fundingSource     - The funding source rendered.
	 * @param {Object}   args.content           - The element that renders the
	 *                                          button.
	 * @param {Function} [args.isDeviceCapable] - Synchronous capability check,
	 *                                          asked before eligibility.
	 * @param {string[]} [args.features]        - What the processing gateway
	 *                                          supports; defaults to PayPal's.
	 */
	const registerExpress = ( {
		name,
		gatewayId,
		fundingSource,
		content,
		isDeviceCapable,
		features = config.supported_features,
	} ) => {
		const label = fundingSourceLabel( fundingSource );

		registerExpressPaymentMethod( {
			/*
			 * title: Shown in the block editor; unique per registration.
			 * paymentMethodId: Clears the gateway from the editor's
			 *   "incompatible with block-based checkout" list.
			 * gatewayId: Links to the gateway's settings.
			 * supports.features: ppcp_continuation is declared up front
			 *   because approving in the wallet sheet raises that cart
			 *   requirement mid-flow, after the method is chosen.
			 * supports.style: Exposes the block's height/borderRadius controls.
			 */
			name,
			title: label,
			description: expressDescription( label ),
			gatewayId,
			paymentMethodId: gatewayId,
			label: createElement( 'div', null, label ),
			ariaLabel: label,
			content,
			edit: createElement( V6EditorPreview, { fundingSource } ),
			canMakePayment: async ( { cartTotals } = {} ) => {
				if ( isDeviceCapable && ! isDeviceCapable() ) {
					return false;
				}

				const amount =
					amountFromCartTotals( cartTotals ) || config.amount;
				const eligibility = await getEligibility( amount );
				return Boolean( eligibility[ fundingSource ] );
			},
			supports: {
				features: [
					...gatewayFeatures( features ),
					'ppcp_continuation',
				],
				style: [ 'height', 'borderRadius' ],
			},
		} );
	};

	for ( const fundingSource of FUNDING_SOURCES ) {
		registerExpress( {
			name: `ppcp-gateway-${ fundingSource }`,
			gatewayId: 'ppcp-gateway',
			fundingSource,
			content: createElement( V6ExpressComponent, {
				config,
				fundingSource,
			} ),
		} );
	}

	// No wallet can vault, and a free trial has to be. Ordinary subscription
	// carts are dropped by the features gate instead.
	let walletMethods = MERCHANT_PRESENTED_METHODS;
	if ( config.is_free_trial_cart ) {
		walletMethods = [];
	}

	for ( const method of walletMethods ) {
		const settings = methodConfig( config, method );

		// No styles for this context means PHP withheld the wallet here.
		if ( ! settings?.styles?.[ config.page_context ] ) {
			continue;
		}

		// Apple answers off a native global, before any row is offered. Google's
		// probe needs a session, so its bridge drops the row from inside.
		let isDeviceCapable;
		if ( method === FundingSources.APPLEPAY ) {
			isDeviceCapable = isDeviceEligible;
		}

		const gatewayId = methodGatewayId( method );

		registerExpress( {
			name: gatewayId,
			gatewayId,
			fundingSource: method,
			content: createElement( V6WalletComponent, { config, method } ),
			isDeviceCapable,
			features: settings.supported_features,
		} );
	}
}

/**
 * The card method label: the gateway title plus the supported-card logos.
 *
 * PaymentMethodIcons comes off the `components` prop that WooCommerce Blocks
 * injects into the label (not an import), matching the v5 block. card_icons is
 * empty when "Show logos of supported cards" is disabled, so nothing renders.
 *
 * @param {Object} props            - Label props from the Blocks registry.
 * @param {Object} props.components - Blocks-provided label components.
 * @return {Object} The label element.
 */
const CardFieldsLabel = ( { components } ) => {
	const { PaymentMethodIcons } = components || {};
	const icons = config.card_fields.card_icons || [];

	return createElement(
		'span',
		{
			style: {
				display: 'flex',
				alignItems: 'center',
				justifyContent: 'space-between',
				width: '100%',
			},
		},
		createElement( 'span', null, config.card_fields.title ),
		PaymentMethodIcons &&
			icons.length > 0 &&
			createElement( PaymentMethodIcons, { icons, align: 'right' } )
	);
};

// Skipped in continuation mode, where the buyer has already approved a PayPal
// order and only the review shows.
if ( config?.card_fields?.enabled && ! config.continuation ) {
	registerPaymentMethod( {
		name: config.card_fields.payment_method,
		label: createElement( CardFieldsLabel ),
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
			features: gatewayFeatures( config.card_fields.supported_features ),
			// WooCommerce Blocks renders its native "Save payment information…"
			// checkbox and exposes the choice as the shouldSavePayment prop;
			// only offered when card vaulting is enabled. Suppressed on a
			// subscription cart, where the card must always be vaulted for
			// renewals: the card component renders its own checked-and-disabled
			// checkbox instead (the native one cannot be locked), matching the
			// classic checkout.
			showSaveOption:
				Boolean( config.card_fields.is_vaulting_enabled ) &&
				! config.card_fields.has_subscriptions,
		},
	} );
}

// Returning-buyer saved-PayPal selector. Registered as the regular ppcp-gateway
// method (alongside the express ones) only when the buyer has an eligible saved
// PayPal token, so WooCommerce Blocks renders its saved-token list and this
// method supplies the in-row vault approval. New PayPal payments go through the
// express button above, so this method exists for the saved token.
if ( config?.vault_component?.is_eligible && ! config.continuation ) {
	const vaultConfig = {
		scriptData: {
			vault_component: config.vault_component,
			is_free_trial_cart: config.is_free_trial_cart,
			client_id: config.vault_client_id,
			script_attributes: config.script_attributes || {},
		},
	};

	registerPaymentMethod( {
		name: 'ppcp-gateway',
		label: createElement(
			'div',
			null,
			fundingSourceLabel( FundingSources.PAYPAL )
		),
		ariaLabel: fundingSourceLabel( FundingSources.PAYPAL ),
		content: createElement(
			'p',
			{ className: 'ppcp-sdk-v6-saved-paypal-note' },
			__(
				'To pay with a different PayPal account, use the PayPal button at the top of the page.',
				'woocommerce-paypal-payments'
			)
		),
		edit: createElement( V6EditorPreview, {
			fundingSource: FundingSources.PAYPAL,
		} ),
		// WooCommerce Blocks injects the selected token plus event props here.
		savedTokenComponent: createElement( PayPalSavedToken, {
			config: vaultConfig,
		} ),
		canMakePayment: () => true,
		supports: {
			features: gatewayFeatures( config.supported_features ),
			// Renders WooCommerce Blocks' saved-token radio list for this gateway.
			showSavedCards: true,
			showSaveOption: false,
		},
	} );
}

/**
 * Pay Later messages on the block cart and checkout.
 *
 * Done at module scope: messaging needs only the config and the DOM, not eligibility
 * or a session. Skipped in continuation mode, where the buyer has approved an
 * order and sees the review instead.
 *
 * Placeholders arrive with the React tree, so the body observer that
 * initMessages() installs is what actually fills them.
 */
if ( config?.messages?.enabled && ! config.continuation ) {
	initMessages( config, config.page_context ).catch( ( error ) => {
		// eslint-disable-next-line no-console
		console.error( '[ppcp-sdk-v6] messages', error );
	} );

	watchBlockCartTotal( updateMessagesAmount );
}
