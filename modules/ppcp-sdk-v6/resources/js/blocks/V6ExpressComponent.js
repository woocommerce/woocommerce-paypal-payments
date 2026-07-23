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
} from '../endpointsAdapter';
import { paypalOrderToWcAddresses } from './address';
import { buildBlocksShippingHandlers } from './blocksShippingHandlers';
import { V6ButtonContainer } from './V6ButtonContainer';

/**
 * Derives a decimal amount string from the Blocks billing prop.
 *
 * @param {Object} billing - The Blocks billing prop (cart total in minor units).
 * @return {string} The amount as a decimal string, or '' when unknown.
 */
function amountFromBilling( billing ) {
	const minor = parseInt( billing?.cartTotal?.value, 10 );
	if ( isNaN( minor ) ) {
		return '';
	}
	const minorUnit = billing?.currency?.minorUnit ?? 2;
	return ( minor / Math.pow( 10, minorUnit ) ).toFixed( minorUnit );
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

	const approve = async ( data ) => {
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
		onSubmit();
	};

	// Session handlers close over props (onSubmit, shippingData, ...) whose
	// identity changes across renders. Route them through a ref so the
	// session can stay tied to [sdk, method] while the handlers stay current.
	const callbacksRef = useRef( {} );
	callbacksRef.current = {
		onApprove: approve,
		onError: ( error ) => {
			if ( onError ) {
				onError( error?.message || '' );
			}
			if ( onClose ) {
				onClose();
			}
		},
		onCancel: () => {
			if ( onClose ) {
				onClose();
			}
		},
	};

	const shippingHandlers = useMemo(
		() => buildBlocksShippingHandlers( config, shippingData ),
		[ config, shippingData ]
	);

	const session = useMemo( () => {
		if ( ! sdk ) {
			return null;
		}

		const handlers = {
			onApprove: ( data ) => callbacksRef.current.onApprove( data ),
			onError: ( error ) => callbacksRef.current.onError( error ),
			onCancel: () => callbacksRef.current.onCancel(),
		};

		// Shipping in the popup only for PayPal when the cart needs it and
		// the merchant handles shipping in PayPal (blocks store-based
		// handlers; classic fetch handlers must not run on block pages).
		if (
			method === 'paypal' &&
			shippingData?.needsShipping &&
			config.shipping?.handle_in_paypal
		) {
			handlers.onShippingAddressChange =
				shippingHandlers.onShippingAddressChange;
			handlers.onShippingOptionsChange =
				shippingHandlers.onShippingOptionsChange;
		}

		return createSession( sdk, method, config, context, handlers );
		// The session is intentionally rebuilt only when the SDK or method
		// changes; handlers are read live through refs / stable memos.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ sdk, method ] );

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
