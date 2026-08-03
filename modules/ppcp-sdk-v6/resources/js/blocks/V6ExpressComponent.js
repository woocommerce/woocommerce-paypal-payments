/**
 * One WooCommerce Blocks express button (one funding source), backed by
 * the v6 SDK.
 *
 * The button click starts a v6 payment session; on approval the buyer's
 * PayPal order is fetched, their address is pushed into the cart, the
 * order is approved in the WC session, and the Blocks checkout is
 * submitted. Payment then processes through the ppcp-gateway.
 *
 * @package
 */

import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import { loadSdkV6 } from '../sdkLoader';
import { checkEligibility } from '../eligibility';
import { createSession } from '../sessions/createSession';
import {
	approveOrderInSession,
	createOrder,
	getOrder,
	navigation,
} from '../endpointsAdapter';
import { continuationRedirectUrl } from '../utils/continuation';
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

	const [ sdk, setSdk ] = useState( null );
	const [ eligibility, setEligibility ] = useState( null );
	const [ paypalOrder, setPaypalOrder ] = useState( null );

	// The live cart total (block cart/checkout update it) drives the Pay
	// Later product details, which are amount-sensitive.
	const amount = amountFromBilling( billing ) || config.amount;

	// Load the SDK and eligibility. loadSdkV6 is promise-memoized, so this
	// shares the instance already created by canMakePayment; the eligibility
	// call supplies the Pay Later product details and is re-run when the cart
	// amount changes.
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

	// Surfaces the failure to the Blocks registry and releases the express UI,
	// mirroring the v5 handleApprove failure path. Without this the buyer is
	// left in a blocked express state with no message.
	const failFlow = ( error ) => {
		if ( onError ) {
			onError( error?.message || '' );
		}
		if ( onClose ) {
			onClose();
		}
	};

	// Set once the Pay Now path has approved the order in the WC session and
	// handed off to the Blocks submit. From that point a failed submit must land
	// the buyer on the review page, mirroring v5's gotoContinuationOnError: the
	// approved order in the session activates the ppcp_continuation payment
	// requirement, which hides every express method on the next cart refresh, so
	// staying here can leave the buyer with no way to pay at all.
	const continuationOnErrorRef = useRef( false );

	const approve = async ( data ) => {
		try {
			const order = await getOrder( config, data.orderId );

			if ( order?.purchase_units?.[ 0 ]?.shipping?.address ) {
				await prefillFromPayPalOrder( order, { needsShipping } );
			}

			await approveOrderInSession( config, fundingSource, data.orderId );

			setPaypalOrder( order );

			// Mirrors v5's shouldskipFinalConfirmation(): the buyer confirms on
			// the checkout page instead of the order being placed straight from
			// the express flow. Venmo with vaulting always takes the review
			// path, whatever the merchant's Pay Now setting. The reload is what
			// builds the review surface — the server only emits the
			// continuation payload once the approved order is in the session.
			const requiresReview =
				config.final_review ||
				( fundingSource === 'venmo' && config.vaulting_enabled );

			if ( requiresReview ) {
				navigation.assign( continuationRedirectUrl( config ) );
				return;
			}

			continuationOnErrorRef.current = true;
			onSubmit();
		} catch ( error ) {
			failFlow( error );
			// Rethrown so the SDK learns the approval failed and leaves the
			// popup in a retryable state; v5's handleApprove does the same.
			throw error;
		}
	};

	// Session handlers close over props whose identity changes every render, so
	// the session reads them through a ref instead of being rebuilt. Assigned in
	// an effect because mutating a ref during render is unsafe under concurrent
	// React, and safe to defer since nothing reads it before buyer interaction.
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
			shippingHandlers: buildBlocksShippingHandlers(
				config,
				shippingData
			),
		};
	} );

	// A primitive, so it only changes when the requirement actually flips —
	// unlike the shippingData object identity.
	const needsShipping = Boolean( shippingData?.needsShipping );

	// createSession() calls into the PayPal SDK, so it must not run during
	// render — useMemo offers no once-only guarantee.
	const [ session, setSession ] = useState( null );

	useEffect( () => {
		if ( ! sdk ) {
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
			method === 'paypal' &&
			needsShipping &&
			config.shipping?.handle_in_paypal
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

		// No teardown: the v6 SDK exposes no documented way to dispose a
		// one-time payment session, so a rebuild abandons the previous one.
		// That is why the dependency list is kept as narrow as it is — every
		// entry here costs an abandoned session and remounts the button.
	}, [ sdk, method, needsShipping, config, context ] );

	// Provide the approved order to the checkout processing step.
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

	// Validation errors after a Pay Now approval must send the buyer to the
	// review page rather than leaving them on a checkout whose express methods
	// are about to disappear. Only the instance whose approve() ran carries the
	// flag, so no active-method gate is needed here.
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

	// The block's own sizing controls win over the plugin settings. They arrive
	// as unitless numbers, while ButtonStyleMapper emits CSS strings, so both
	// are converted here rather than in the renderer.
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
		createOrderFn: () => createOrder( config, context, fundingSource ),
		payLaterDetails: eligibility?.payLaterDetails,
		onClick,
	} );
}
