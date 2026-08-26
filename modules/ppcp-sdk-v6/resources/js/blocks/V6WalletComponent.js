/**
 * One WooCommerce Blocks express wallet button (Apple Pay or Google Pay),
 * backed by the v6 SDK.
 *
 * The sheet collects the address and the bridge carries the payment through to
 * the order-received page, so nothing hands back to the block checkout.
 *
 * @package
 */

import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import { loadSdkV6 } from '../sdkLoader';
import { createSession } from '../sessions/createSession';
import { minorUnitsToDecimal } from '../utils/amount';
import { refreshCartUi } from '../utils/cartUi';
import { methodShippingRequired } from '../methods/methodShipping';
import { wcAddressToApplePay } from '../wallets/walletContacts';
import { V6BridgeContainer } from './V6BridgeContainer';

// A render can fail on the way to the button, so the row is dropped only after
// this many attempts. The delay grows by one step per attempt.
const MAX_RENDER_ATTEMPTS = 3;
const RETRY_DELAY_MS = 1000;

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
 * @param {Object}                    props                    - Props from the Blocks express payment registry.
 * @param {Object}                    props.config             - The localized sdk-v6 config.
 * @param {string}                    props.method             - The wallet's funding source.
 * @param {() => void}                props.onClick            - Signals the start of the express flow.
 * @param {() => void}                props.onClose            - Called when the express flow ends without an order.
 * @param {(message: string) => void} props.onError            - Called with an error message on failure.
 * @param {Object}                    props.billing            - The Blocks billing data (cart totals).
 * @param {Object}                    props.shippingData       - The Blocks shipping data.
 * @param {Object}                    [props.buttonAttributes] - Height/borderRadius from the express block.
 * @return {?Object} The button element, or null while the wallet cannot render.
 */
export function V6WalletComponent( {
	config,
	method,
	onClick,
	onClose,
	onError,
	billing,
	shippingData,
	buttonAttributes,
} ) {
	const context = config.page_context;

	const [ sdk, setSdk ] = useState( null );

	// Once true, the express row is gone for this page load.
	const [ unavailable, setUnavailable ] = useState( false );

	const [ renderAttempt, setRenderAttempt ] = useState( 0 );
	const retryTimerRef = useRef( null );

	// True from the tap that opens the sheet until it closes without paying.
	const [ paying, setPaying ] = useState( false );

	// Promise-memoized, so this shares the instance the express buttons made.
	useEffect( () => {
		let active = true;

		loadSdkV6( config, context )
			.then( ( instance ) => {
				if ( active ) {
					setSdk( instance );
				}
			} )
			.catch( ( error ) => {
				// eslint-disable-next-line no-console
				console.error( '[ppcp-sdk-v6] wallet SDK load failed', error );
			} );

		return () => {
			active = false;
		};
	}, [ config, context ] );

	const totalRef = useRef( config.amount );
	const total = amountFromBilling( billing ) || config.amount;
	useEffect( () => {
		totalRef.current = total;
	}, [ total ] );

	const sheetTotalRef = useRef( { get: () => totalRef.current } );

	// Apple only: Google's PaymentDataRequest has no equivalent field.
	const contactsRef = useRef( {} );
	useEffect( () => {
		contactsRef.current = {
			billing: wcAddressToApplePay( billing?.billingAddress ),
			shipping: wcAddressToApplePay( shippingData?.shippingAddress ),
		};
	} );

	const sheetContactsRef = useRef( { get: () => contactsRef.current } );

	// Whether the sheet asks the shopper for shipping details.
	const requiresShipping =
		methodShippingRequired( config, context ) &&
		Boolean( shippingData?.needsShipping );

	const callbacksRef = useRef( {} );

	useEffect( () => {
		callbacksRef.current = { onClick, onClose, onError };
	} );

	// Without onClose the buyer keeps a dimmed checkout and no message.
	const reportAndClose = ( error ) => {
		const { onError: reportError, onClose: close } = callbacksRef.current;

		if ( reportError ) {
			reportError( error?.message || '' );
		}
		if ( close ) {
			close();
		}
	};

	const closeExpressFlow = () => {
		const { onClose: close } = callbacksRef.current;

		if ( close ) {
			close();
		}
	};

	const [ session, setSession ] = useState( null );

	useEffect( () => {
		if ( ! sdk ) {
			return undefined;
		}

		setSession(
			createSession( sdk, method, config, context, {
				onError: reportAndClose,
				onCancel: () => {
					refreshCartUi( context );
					closeExpressFlow();
				},
			} )
		);

		// No teardown: the SDK cannot dispose a session, so every extra
		// dependency here abandons one and remounts the button.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ sdk, method, config, context ] );

	// The block control is unitless; CSS needs a length.
	const height = buttonAttributes?.height
		? `${ Number( buttonAttributes.height ) }px`
		: undefined;

	const liveRebuildKey = `${ requiresShipping }|${ height }|${ buttonAttributes?.borderRadius }|${ renderAttempt }`;
	const [ rebuildKey, setRebuildKey ] = useState( liveRebuildKey );

	// Rebuilding tears the button out of the DOM, which under an open sheet
	// would abandon the live one and let a second open beside it. So the live
	// key is only applied once the sheet has closed.
	useEffect( () => {
		if ( paying ) {
			return;
		}

		if ( rebuildKey !== liveRebuildKey ) {
			setRebuildKey( liveRebuildKey );
		}
	}, [ paying, liveRebuildKey, rebuildKey ] );

	useEffect( () => {
		return () => {
			if ( retryTimerRef.current ) {
				clearTimeout( retryTimerRef.current );
			}
		};
	}, [] );

	if ( unavailable || ! session ) {
		return null;
	}

	// The counter is part of the rebuild key, so bumping it re-renders.
	const retryRender = () => {
		const attempted = renderAttempt + 1;

		if ( attempted >= MAX_RENDER_ATTEMPTS ) {
			setUnavailable( true );
			return;
		}

		retryTimerRef.current = setTimeout( () => {
			setRenderAttempt( attempted );
		}, RETRY_DELAY_MS * attempted );
	};

	return createElement( V6BridgeContainer, {
		/*
		 * rebuildKey: The values captured at mount; a change rebuilds the button.
		 * overrides.onUnavailable: This browser or merchant cannot pay this way,
		 *   so the empty express row goes.
		 * overrides.onRenderFailed: A render that threw on the way to the button,
		 *   which may succeed on a second attempt.
		 */
		method,
		config,
		context,
		session,
		rebuildKey,
		overrides: {
			height,
			borderRadius: buttonAttributes?.borderRadius,
			requiresShipping,
			sheetTotal: sheetTotalRef.current,
			sheetContacts: sheetContactsRef.current,
			onClick: () => {
				setPaying( true );

				const { onClick: start } = callbacksRef.current;
				if ( start ) {
					start();
				}
			},
			onUnavailable: () => setUnavailable( true ),
			onRenderFailed: retryRender,
			onSheetClosed: () => {
				setPaying( false );
				closeExpressFlow();
			},
		},
	} );
}
