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
import { useSelect } from '@wordpress/data';
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
// Reused for the same reason, so the regular PayPal row cannot drift from the
// v5 one it exists to match.
import { PaypalLabel } from '@ppcp-blocks/Components/paypal-label';
import { PayPalPlaceOrderContent } from '@ppcp-blocks/Components/paypal-place-order-content';
import { FundingSources } from './utils/fundingSources';
import { fundingSourceLabel } from './utils/fundingSourceLabel';
import { amountFromCartTotals } from './utils/amount';
import { isFreeTrialCart } from './utils/freeTrial';
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

// The gateway that processes every method registered here; also the name the
// regular PayPal row registers under. Undefined when v6 does not run on this
// page, where nothing below registers anything either.
const PAYPAL_GATEWAY_ID = config?.id;

// Wording for the error notices the bridges raise.
setErrorLabels( config?.labels );

// Pay Later carries a per-context merchant setting on top of eligibility;
// renderButtons() applies the same flag for the classic stack.
const FUNDING_SOURCES = ALL_FUNDING_SOURCES.filter( ( fundingSource ) => {
	if ( fundingSource !== FundingSources.PAYLATER ) {
		return true;
	}

	return Boolean( config?.pay_later_button?.[ config.page_context ] );
} );

/**
 * Whether a method may be offered for the cart the shopper has right now.
 *
 * A free-trial ($0) subscription is vaulted through the save flow, which only
 * PayPal offers, so such a cart offers PayPal alone (mirrors the v5 blocks
 * checkout).
 *
 * Asked per cart update rather than at registration, which cannot be undone:
 * canMakePayment is the only lever left once a coupon zeroes the cart, and it
 * restores the methods when the coupon is removed again.
 *
 * @param {string} fundingSource - The method's funding source.
 * @param {string} amount        - The live cart total as a decimal string.
 * @return {boolean} False when only PayPal may be offered and this is not it.
 */
function expressMethodAllowedForCart( fundingSource, amount ) {
	if ( fundingSource === FundingSources.PAYPAL ) {
		return true;
	}

	return ! isFreeTrialCart( config, amount );
}

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

if ( config && config.page_context && config.continuation ) {
	registerPaymentMethod( {
		name: PAYPAL_GATEWAY_ID,
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
	// canMakePayment runs per funding source on every cart update; caching the
	// current amount keeps that to one lookup per update.
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
	 * @param {string[]} args.features          - What the processing gateway
	 *                                          supports. Deliberately without
	 *                                          a default, so a wallet cannot
	 *                                          inherit PayPal's list.
	 */
	const registerExpress = ( {
		name,
		gatewayId,
		fundingSource,
		content,
		isDeviceCapable,
		features,
	} ) => {
		const label = fundingSourceLabel( fundingSource );

		registerExpressPaymentMethod( {
			/*
			 * title: Shown in the block editor; unique per registration.
			 * paymentMethodId: Clears the gateway from the editor's
			 *   "incompatible with block-based checkout" list.
			 * gatewayId: Links to the gateway's settings.
			 * supports.features: ppcp_continuation is declared up front,
			 *   since approving in the wallet sheet raises that cart
			 *   requirement after the method is chosen.
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

				// A $0 free-trial subscription is vaulted through the PayPal
				// save flow (see V6ExpressComponent), which the amount-based
				// eligibility check would reject for a zero amount. Only PayPal
				// is offered on such carts (see FUNDING_SOURCES above), so guard
				// on it; mirrors boot.js, which bypasses eligibility too.
				if ( config.is_free_trial_cart ) {
					return fundingSource === FundingSources.PAYPAL;
				}

				const amount =
					amountFromCartTotals( cartTotals ) || config.amount;

				// Before the SDK is asked, so a cart that only PayPal can pay
				// for costs no eligibility lookup for the other methods.
				if ( ! expressMethodAllowedForCart( fundingSource, amount ) ) {
					return false;
				}

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
			gatewayId: PAYPAL_GATEWAY_ID,
			fundingSource,
			content: createElement( V6ExpressComponent, {
				config,
				fundingSource,
			} ),
			features: config.supported_features,
		} );
	}

	// No wallet can vault, and a free trial has to be, so the express gate keeps
	// every wallet row off such a cart (it answers for anything but PayPal).
	// Ordinary subscription carts are dropped by the features gate instead.
	for ( const method of MERCHANT_PRESENTED_METHODS ) {
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
 * PaymentMethodIcons comes off the `components` prop WooCommerce Blocks injects
 * into a label, not an import. card_icons is empty when "Show logos of supported
 * cards" is off.
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
			// Blocks' native save checkbox, whose choice arrives as the
			// shouldSavePayment prop. Suppressed on a subscription cart: the
			// native checkbox cannot be locked, so the card component renders
			// its own checked-and-disabled one instead.
			showSaveOption:
				Boolean( config.card_fields.is_vaulting_enabled ) &&
				! config.has_subscriptions,
		},
	} );
}

// The regular (non-express) ppcp-gateway method. One registration serving two
// purposes, because registerPaymentMethod keeps only the last call for a name:
//
// - the "Place order" row, which redirects to PayPal server-side. v5 offers this
//   by default, so v6 must too, or PayPal vanishes from the payment-method list
//   and only the express buttons remain.
// - the returning-buyer saved-PayPal row, which hosts the in-row vault approval.
//
// Either alone is enough to register; in continuation mode the branch at the top
// of this file owns the name instead.
const savedPayPalEligible =
	Boolean( config?.vault_component?.is_eligible ) && ! config?.continuation;
const placeOrderEnabled =
	Boolean( config?.place_order?.enabled ) && ! config?.continuation;

/**
 * Whether the regular PayPal row may be offered for the current cart.
 *
 * A zero-total cart normally needs no payment method, but a subscription cart
 * does: the method is vaulted to pay the renewals. The total is read live, so a
 * coupon applied on the checkout is taken into account. Mirrors v5's
 * paypalPaymentMethodAllowed().
 *
 * @param {Object} [cartTotals] - The canMakePayment cart totals.
 * @return {boolean} Whether the row may show.
 */
function regularRowAllowedForCart( cartTotals ) {
	if ( config.has_subscriptions ) {
		return true;
	}

	const amount = amountFromCartTotals( cartTotals ) || config.amount;

	return parseFloat( amount ) > 0;
}

/**
 * The note shown when the row exists only to host a saved PayPal token: there is
 * no "Place order" flow to describe, and a new PayPal payment goes through the
 * express button instead.
 *
 * @return {Object} The content element.
 */
const SavedTokenNote = () =>
	createElement(
		'p',
		{ className: 'ppcp-sdk-v6-saved-paypal-note' },
		__(
			'To pay with a different PayPal account, use the PayPal button at the top of the page.',
			'woocommerce-paypal-payments'
		)
	);

if ( savedPayPalEligible || placeOrderEnabled ) {
	/**
	 * The saved-PayPal row, with its free-trial state answered per render.
	 *
	 * PayPalSavedToken suppresses its Vault Component on a zero-total cart, which
	 * would create a $0 order. It reads that from the config it is handed, so the
	 * config is rebuilt per render against the live total.
	 *
	 * @param {Object} props - The props WooCommerce Blocks injects into a
	 *                       saved-token component.
	 * @return {Object} The wrapped saved-token element.
	 */
	const SavedPayPalToken = ( props ) => {
		const total = useSelect( ( selectStore ) => {
			const cartStore = selectStore( 'wc/store/cart' );

			return amountFromCartTotals( cartStore?.getCartTotals?.() );
		}, [] );

		return createElement( PayPalSavedToken, {
			...props,
			config: {
				scriptData: {
					vault_component: config.vault_component,
					is_free_trial_cart: isFreeTrialCart( config, total ),
					client_id: config.vault_client_id,
					script_attributes: config.script_attributes || {},
				},
			},
		} );
	};

	// The props that differ between this row's two variants, so which belongs to
	// which is one decision rather than a test per field.
	let rowProps;
	if ( placeOrderEnabled ) {
		rowProps = {
			content: createElement( PayPalPlaceOrderContent, {
				description: config.description,
				placeOrderButtonDescription: config.place_order.description,
			} ),
			placeOrderButtonLabel: config.place_order.text,
			// Gone on a zero-total cart that needs no payment method, but kept
			// on a subscription cart, which needs one even at $0.
			canMakePayment: ( { cartTotals } = {} ) =>
				regularRowAllowedForCart( cartTotals ),
		};
	} else {
		rowProps = {
			// The row exists because a saved token does, so it is always
			// available; a new PayPal payment uses the express button.
			content: createElement( SavedTokenNote ),
			canMakePayment: () => true,
		};
	}

	if ( savedPayPalEligible ) {
		// WooCommerce Blocks injects the selected token plus event props here.
		rowProps.savedTokenComponent = createElement( SavedPayPalToken );
	}

	registerPaymentMethod( {
		name: PAYPAL_GATEWAY_ID,
		label: createElement( PaypalLabel, { config } ),
		ariaLabel: config.title,
		edit: createElement( V6EditorPreview, {
			fundingSource: FundingSources.PAYPAL,
		} ),
		...rowProps,
		supports: {
			features: gatewayFeatures( config.supported_features ),
			// Renders WooCommerce Blocks' saved-token radio list for this gateway.
			showSavedCards: savedPayPalEligible,
			showSaveOption: false,
		},
	} );

	// placeOrderButtonLabel above is not honoured on its own by the Checkout
	// Actions block, which reads the label through this filter instead. Same
	// belt-and-braces pair as v5.
	if ( placeOrderEnabled ) {
		const placeOrderButtonLabel = ( defaultLabel ) => {
			const payment = window.wp?.data?.select( 'wc/store/payment' );

			if ( payment?.getActivePaymentMethod?.() !== PAYPAL_GATEWAY_ID ) {
				return defaultLabel;
			}

			return config.place_order.text;
		};

		window.wc?.blocksCheckout?.registerCheckoutFilters?.(
			PAYPAL_GATEWAY_ID,
			{ placeOrderButtonLabel }
		);
	}
}

/**
 * Pay Later messages on the block cart and checkout.
 *
 * At module scope, since messaging needs only the config and the DOM. Skipped in
 * continuation mode, which shows the order review instead.
 *
 * Placeholders arrive with the React tree, so initMessages()'s body observer is
 * what fills them.
 */
if ( config?.messages?.enabled && ! config.continuation ) {
	initMessages( config, config.page_context ).catch( ( error ) => {
		// eslint-disable-next-line no-console
		console.error( '[ppcp-sdk-v6] messages', error );
	} );

	watchBlockCartTotal( updateMessagesAmount );
}
