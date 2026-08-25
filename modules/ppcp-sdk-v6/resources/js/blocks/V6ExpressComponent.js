/**
 * One WooCommerce Blocks express button (one funding source), backed by the
 * v6 SDK. Payment processes through the ppcp-gateway.
 *
 * @package
 */

import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import { loadSdkV6 } from '../sdkLoader';
import { checkEligibility } from '../eligibility';
import { createSession } from '../sessions/createSession';
import {
	createFreeTrialPayPalSession,
	createVaultSetupToken,
} from '../sessions/freeTrialSave';
import {
	approveOrder,
	approveOrderInSession,
	createOrder,
	getOrder,
	navigation,
} from '../endpointsAdapter';
import { continuationRedirectUrl } from '../utils/continuation';
import { FundingSources } from '../utils/fundingSources';
import { paypalOrderToWcAddresses } from './address';
import { prefillFromPayPalOrder } from './prefillAddresses';
import { buildBlocksShippingHandlers } from './blocksShippingHandlers';
import { V6ButtonContainer } from './V6ButtonContainer';
import { minorUnitsToDecimal } from '../utils/amount';

/**
 * Derives a decimal amount string from the Blocks billing prop.
 *
 * @param {Object} billing - The Blocks billing prop (cart total in minor units).
 * @return {string} The amount as a decimal string, or '' when unknown.
 */
function amountFromBilling( billing ) {
	return minorUnitsToDecimal(
		billing?.cartTotal?.value,
		billing?.currency?.minorUnit
	);
}

/**
 * @param {Object}                    props                     - Props from the Blocks express payment registry.
 * @param {Object}                    props.config              - The localized sdk-v6 config.
 * @param {string}                    props.fundingSource       - The funding source (paypal, venmo, paylater).
 * @param {() => void}                props.onClick             - Signals the start of the express flow.
 * @param {() => void}                props.onClose             - Called when the express flow is cancelled.
 * @param {(message: string) => void} props.onError             - Called with an error message on failure.
 * @param {() => void}                props.onSubmit            - Submits the Blocks checkout.
 * @param {Object}                    props.eventRegistration   - Blocks checkout event subscriptions.
 * @param {Object}                    props.emitResponse        - Blocks response-type constants.
 * @param {string}                    props.activePaymentMethod - The active express method id.
 * @param {Object}                    props.shippingData        - The Blocks shipping data.
 * @param {Object}                    props.billing             - The Blocks billing data (cart totals).
 * @param {Object}                    [props.buttonAttributes]  - Height/borderRadius from the express block.
 * @return {?Object} The button element, or null before the SDK is ready.
 */
export function V6ExpressComponent( {
	config,
	fundingSource,
	onClick,
	onClose,
	onError,
	onSubmit,
	eventRegistration,
	emitResponse,
	activePaymentMethod,
	shippingData,
	billing,
	buttonAttributes,
} ) {
	const { onPaymentSetup, onCheckoutFail, onCheckoutValidation } =
		eventRegistration;
	const { responseTypes } = emitResponse;

	const method = fundingSource;
	const context = config.page_context;
	const methodId = `ppcp-gateway-${ fundingSource }`;

	// A $0 free-trial subscription is vaulted through the PayPal save flow rather
	// than a one-time order: the buyer approves a setup token, it is exchanged for
	// a stored token, and the Blocks checkout submit places the $0 WC order. Only
	// PayPal is offered on such carts (see checkout-block.js), so guard on it.
	const isFreeTrial =
		Boolean( config.is_free_trial_cart ) &&
		fundingSource === FundingSources.PAYPAL;

	const [ sdk, setSdk ] = useState( null );
	const [ eligibility, setEligibility ] = useState( null );
	const [ paypalOrder, setPaypalOrder ] = useState( null );

	// Pay Later thresholds are amount-sensitive, so follow the live cart total.
	const amount = amountFromBilling( billing ) || config.amount;

	// loadSdkV6 is promise-memoized, so this shares the instance
	// canMakePayment already created.
	useEffect( () => {
		let active = true;

		( async () => {
			const instance = await loadSdkV6( config, context );
			const methods = await checkEligibility( instance, {
				currencyCode: config.currency,
				countryCode: config.buyer_country,
				amount,
			} );

			if ( active ) {
				setSdk( instance );
				setEligibility( methods );
			}
		} )().catch( ( error ) => {
			// eslint-disable-next-line no-console
			console.error( '[ppcp-sdk-v6] express SDK load failed', error );
		} );

		return () => {
			active = false;
		};
	}, [ config, context, amount ] );

	// Without releasing the express UI the buyer is stuck with no message.
	const failFlow = ( error ) => {
		if ( onError ) {
			onError( error?.message || '' );
		}
		if ( onClose ) {
			onClose();
		}
	};

	// Once the order is approved in the session, ppcp_continuation hides every
	// express method on the next cart refresh — so a failed submit from here has
	// to land on the review page rather than leave the buyer with no method.
	const continuationOnErrorRef = useRef( false );

	const approve = async ( data ) => {
		try {
			const order = await getOrder( config, data.orderId );

			if ( order?.purchase_units?.[ 0 ]?.shipping?.address ) {
				await prefillFromPayPalOrder( order, { needsShipping } );
			}

			setPaypalOrder( order );

			// Venmo with vaulting always reviews, whatever the Pay Now setting.
			// The reload is what builds the review surface: the server emits the
			// continuation payload only once the order is approved in session.
			const requiresReview =
				config.final_review ||
				( fundingSource === FundingSources.VENMO &&
					config.vaulting_enabled );

			if ( requiresReview ) {
				await approveOrderInSession(
					config,
					fundingSource,
					data.orderId
				);
				navigation.assign( continuationRedirectUrl( config ) );
				return;
			}

			// Pay Now on the block CART: there is no checkout form to submit
			// here, so create and capture the WC order server-side (mirroring
			// the classic cart Pay Now flow) and land on the order-received
			// page. Calling onSubmit() here would submit the checkout store
			// from the cart and fail with "No payment method provided."
			if ( context === 'cart-block' ) {
				await approveOrder(
					config,
					context,
					fundingSource,
					data.orderId
				);
				return;
			}

			await approveOrderInSession( config, fundingSource, data.orderId );

			continuationOnErrorRef.current = true;
			onSubmit();
		} catch ( error ) {
			failFlow( error );
			// Rethrown so the SDK leaves the popup in a retryable state.
			throw error;
		}
	};

	// The session reads handlers through a ref so changing prop identities do
	// not rebuild it. Written in an effect: ref mutation during render is unsafe.
	const callbacksRef = useRef( {} );

	useEffect( () => {
		callbacksRef.current = {
			onApprove: approve,
			onError: failFlow,
			onCancel: () => {
				if ( onClose ) {
					onClose();
				}
			},
			// Free trial: the token is already stored, so just submit the Blocks
			// checkout — the gateway places the $0 order server-side.
			onFreeTrialComplete: () => onSubmit(),
			shippingHandlers: buildBlocksShippingHandlers(
				config,
				shippingData
			),
		};
	} );

	// A primitive, so it changes only when the requirement flips.
	const needsShipping = Boolean( shippingData?.needsShipping );

	// createSession() calls into the SDK, so it must not run during render.
	const [ session, setSession ] = useState( null );

	useEffect( () => {
		if ( ! sdk ) {
			return undefined;
		}

		// Free trial: a save session whose onApprove exchanges the setup token
		// and then submits the Blocks checkout to place the $0 order. None of the
		// one-time order/shipping/review wiring below applies.
		if ( isFreeTrial ) {
			setSession(
				createFreeTrialPayPalSession( sdk, config, {
					onComplete: () => callbacksRef.current.onFreeTrialComplete(),
					onError: ( error ) => callbacksRef.current.onError( error ),
				} )
			);
			return undefined;
		}

		const handlers = {
			onApprove: ( data ) => callbacksRef.current.onApprove( data ),
			onError: ( error ) => callbacksRef.current.onError( error ),
			onCancel: () => callbacksRef.current.onCancel(),
		};

		// Attaching these tells the SDK to collect shipping, so needsShipping
		// has to gate attachment rather than the handler body.
		if (
			method === FundingSources.PAYPAL &&
			needsShipping &&
			config.shipping?.in_context?.[ context ]
		) {
			handlers.onShippingAddressChange = ( data ) =>
				callbacksRef.current.shippingHandlers.onShippingAddressChange(
					data
				);
			handlers.onShippingOptionsChange = ( data ) =>
				callbacksRef.current.shippingHandlers.onShippingOptionsChange(
					data
				);
		}

		setSession( createSession( sdk, method, config, context, handlers ) );

		// No teardown: the SDK cannot dispose a session, so every extra
		// dependency here abandons one and remounts the button. Keep it narrow.
	}, [ sdk, method, needsShipping, config, context, isFreeTrial ] );

	useEffect( () => {
		if ( activePaymentMethod !== methodId ) {
			return undefined;
		}

		return onPaymentSetup( () => {
			let addresses = {};
			if ( paypalOrder?.purchase_units?.[ 0 ]?.shipping?.address ) {
				addresses = paypalOrderToWcAddresses( paypalOrder );
			}

			return {
				type: responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
						paypal_order_id: paypalOrder?.id,
						funding_source: fundingSource,
					},
					...addresses,
				},
			};
		} );
	}, [
		onPaymentSetup,
		paypalOrder,
		activePaymentMethod,
		methodId,
		fundingSource,
		responseTypes,
	] );

	// Only the instance whose approve() ran carries the flag, so this needs no
	// active-method gate.
	useEffect(
		() =>
			onCheckoutValidation( () => {
				if (
					continuationOnErrorRef.current &&
					wp.data
						.select( 'wc/store/validation' )
						.hasValidationErrors()
				) {
					navigation.assign( continuationRedirectUrl( config ) );
					return { type: responseTypes.ERROR };
				}

				return true;
			} ),
		[ onCheckoutValidation, config, responseTypes ]
	);

	useEffect( () => {
		if ( activePaymentMethod !== methodId ) {
			return undefined;
		}

		return onCheckoutFail( () => {
			if ( onClose ) {
				onClose();
			}
			if ( continuationOnErrorRef.current ) {
				navigation.assign( continuationRedirectUrl( config ) );
			}
			return true;
		} );
	}, [ onCheckoutFail, onClose, activePaymentMethod, methodId, config ] );

	if ( ! session ) {
		return null;
	}

	// Block sizing controls win over the plugin settings, and arrive unitless
	// where ButtonStyleMapper emits CSS strings.
	const styles = {
		...( config.button_styles?.[ context ] || {} ),
		...( buttonAttributes?.height && {
			height: `${ Number( buttonAttributes.height ) }px`,
		} ),
		...( buttonAttributes?.borderRadius && {
			borderRadius: `${ Number( buttonAttributes.borderRadius ) }px`,
		} ),
	};

	return createElement( V6ButtonContainer, {
		method,
		session,
		styles,
		// Free trial starts the save session with a setup token instead of an
		// order; both resolve to the shape session.start() expects.
		createOrderFn: isFreeTrial
			? () => createVaultSetupToken( config )
			: () => createOrder( config, context, fundingSource ),
		payLaterDetails: eligibility?.payLaterDetails,
		onClick,
	} );
}
