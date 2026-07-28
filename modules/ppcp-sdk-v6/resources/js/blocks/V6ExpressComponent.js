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

import {
	createElement,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
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
} ) {
	const { onPaymentSetup, onCheckoutFail } = eventRegistration;
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

	const approve = async ( data ) => {
		try {
			const order = await getOrder( config, data.orderId );

			if ( order?.purchase_units?.[ 0 ]?.shipping?.address ) {
				const addresses = paypalOrderToWcAddresses( order );
				await wp.data.dispatch( 'wc/store/cart' ).updateCustomerData( {
					billing_address: addresses.billingAddress,
					shipping_address: addresses.shippingAddress,
				} );
			}

			await approveOrderInSession( config, fundingSource, data.orderId );

			setPaypalOrder( order );

			// The v5 fork (paypal-config.js handleApprove): with the final
			// review enabled the buyer confirms on the checkout page instead of
			// the order being placed straight from the express flow. The reload
			// is what builds the review surface — the server only emits the
			// continuation payload once the approved order is in the session.
			if ( config.final_review ) {
				navigation.assign( continuationRedirectUrl( config ) );
				return;
			}

			onSubmit();
		} catch ( error ) {
			failFlow( error );
		}
	};

	// Session handlers close over props (onSubmit, shippingData, ...) whose
	// identity changes across renders. Route them through a ref so the session
	// survives those changes while the handlers stay current.
	const callbacksRef = useRef( {} );

	const shippingHandlers = useMemo(
		() => buildBlocksShippingHandlers( config, shippingData ),
		[ config, shippingData ]
	);

	// Assigned in an effect rather than during render: mutating a ref while
	// rendering is unsafe under concurrent React. Safe to defer because the
	// session only reads these on buyer interaction, long after mount.
	useEffect( () => {
		callbacksRef.current = {
			onApprove: approve,
			onError: failFlow,
			onCancel: () => {
				if ( onClose ) {
					onClose();
				}
			},
			// Read live by the session handlers: shippingData carries the Blocks
			// setters, whose identities change across renders.
			shippingHandlers,
		};
	} );

	// A primitive, so it only changes when the cart's shipping requirement
	// actually flips — unlike the shippingData object identity.
	const needsShipping = Boolean( shippingData?.needsShipping );

	// createSession() calls into the PayPal SDK, so it must not run during
	// render: useMemo offers no once-only guarantee and a discarded render would
	// leave an orphaned SDK session behind. Created in an effect and held in
	// state instead.
	const [ session, setSession ] = useState( null );

	useEffect( () => {
		if ( ! sdk ) {
			return;
		}

		const handlers = {
			onApprove: ( data ) => callbacksRef.current.onApprove( data ),
			onError: ( error ) => callbacksRef.current.onError( error ),
			onCancel: () => callbacksRef.current.onCancel(),
		};

		// Shipping in the popup only for PayPal when the cart needs it and
		// the merchant handles shipping in PayPal (blocks store-based
		// handlers; classic fetch handlers must not run on block pages).
		// Attaching these tells the SDK to collect shipping, so the
		// needsShipping check must gate attachment, not just the body — hence
		// it is an effect dependency rather than a live ref read.
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

		const created = createSession( sdk, method, config, context, handlers );
		setSession( created );
		// Rebuilt only when the SDK, method or shipping requirement changes; the
		// handler bodies are read live through the ref, so changing Blocks
		// callback identities do not churn the session or remount the button.
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

	useEffect( () => {
		if ( activePaymentMethod !== methodId ) {
			return undefined;
		}

		return onCheckoutFail( () => {
			if ( onClose ) {
				onClose();
			}
			return true;
		} );
	}, [ onCheckoutFail, onClose, activePaymentMethod, methodId ] );

	if ( ! session ) {
		return null;
	}

	return createElement( V6ButtonContainer, {
		method,
		session,
		styles: config.button_styles?.[ context ] || {},
		createOrderFn: () => createOrder( config, context, fundingSource ),
		payLaterDetails: eligibility?.payLaterDetails,
		onClick,
	} );
}
