import { registerPlugin } from '@wordpress/plugins';
import { useEffect, useCallback, useState, useRef } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import PayPalInsights from '../../../../ppcp-axo/resources/js/Insights/PayPalInsights';
import { STORE_NAME } from '../stores/axoStore';
import usePayPalCommerceGateway from '../hooks/usePayPalCommerceGateway';

const GATEWAY_HANDLE = 'ppcp-axo-gateway';

const useEventTracking = () => {
	const [ triggeredEvents, setTriggeredEvents ] = useState( {
		initialized: false,
		jsLoaded: false,
		beginCheckout: false,
		emailSubmitted: false,
	} );

	const currentPaymentMethod = useRef( null );

	const setEventTriggered = useCallback( ( eventName, value = true ) => {
		setTriggeredEvents( ( prev ) => ( {
			...prev,
			[ eventName ]: value,
		} ) );
	}, [] );

	const isEventTriggered = useCallback(
		( eventName ) => triggeredEvents[ eventName ],
		[ triggeredEvents ]
	);

	const setCurrentPaymentMethod = useCallback( ( methodName ) => {
		currentPaymentMethod.current = methodName;
	}, [] );

	const getCurrentPaymentMethod = useCallback(
		() => currentPaymentMethod.current,
		[]
	);

	return {
		setEventTriggered,
		isEventTriggered,
		setCurrentPaymentMethod,
		getCurrentPaymentMethod,
	};
};

const waitForPayPalInsight = () => {
	return new Promise( ( resolve, reject ) => {
		// If already loaded, resolve immediately
		if ( window.paypalInsight ) {
			resolve( window.paypalInsight );
			return;
		}

		// Set a reasonable timeout
		const timeoutId = setTimeout( () => {
			observer.disconnect();
			reject( new Error( 'PayPal Insights script load timeout' ) );
		}, 10000 );

		// Create MutationObserver to watch for script initialization
		const observer = new MutationObserver( () => {
			if ( window.paypalInsight ) {
				observer.disconnect();
				clearTimeout( timeoutId );
				resolve( window.paypalInsight );
			}
		} );

		// Start observing
		observer.observe( document, {
			childList: true,
			subtree: true,
		} );
	} );
};

const usePayPalInsightsInit = ( axoConfig, ppcpConfig, eventTracking ) => {
	const { setEventTriggered, isEventTriggered } = eventTracking;
	const initialized = useRef( false );

	useEffect( () => {
		if (
			! axoConfig?.insights?.enabled ||
			! axoConfig?.insights?.client_id ||
			! axoConfig?.insights?.session_id ||
			initialized.current ||
			isEventTriggered( 'initialized' )
		) {
			return;
		}

		const initializePayPalInsights = async () => {
			try {
				await waitForPayPalInsight();

				if ( initialized.current ) {
					return;
				}

				// Track JS load first
				PayPalInsights.trackJsLoad();
				setEventTriggered( 'jsLoaded' );

				PayPalInsights.config( axoConfig.insights.client_id, {
					debug: axoConfig?.wp_debug === '1',
				} );

				PayPalInsights.setSessionId( axoConfig.insights.session_id );
				initialized.current = true;
				setEventTriggered( 'initialized' );

				if (
					isEventTriggered( 'jsLoaded' ) &&
					! isEventTriggered( 'beginCheckout' )
				) {
					PayPalInsights.trackBeginCheckout( {
						amount: axoConfig.insights.amount,
						page_type: 'checkout',
						user_data: {
							country: 'US',
							is_store_member: false,
						},
					} );
					setEventTriggered( 'beginCheckout' );
				}
			} catch ( error ) {
				console.error(
					'PayPal Insights initialization failed:',
					error
				);
			}
		};

		initializePayPalInsights();

		return () => {
			initialized.current = false;
		};
	}, [ axoConfig, ppcpConfig, setEventTriggered, isEventTriggered ] );
};

const usePaymentMethodTracking = ( axoConfig, eventTracking ) => {
	const { setCurrentPaymentMethod } = eventTracking;
	const lastPaymentMethod = useRef( null );
	const isInitialMount = useRef( true );

	const activePaymentMethod = useSelect( ( select ) => {
		const { PAYMENT_STORE_KEY } = window.wc.wcBlocksData;
		return select( PAYMENT_STORE_KEY )?.getActivePaymentMethod();
	}, [] );

	const handlePaymentMethodChange = useCallback(
		async ( paymentMethod ) => {
			// Skip if no payment method or same as last one
			if (
				! paymentMethod ||
				paymentMethod === lastPaymentMethod.current
			) {
				return;
			}

			try {
				await waitForPayPalInsight();

				// Only track if it's not the initial mount, and we have a previous payment method
				if ( ! isInitialMount.current && lastPaymentMethod.current ) {
					PayPalInsights.trackSelectPaymentMethod( {
						payment_method_selected:
							axoConfig?.insights?.payment_method_selected_map[
								paymentMethod
							] || 'other',
						page_type: 'checkout',
					} );
				}

				lastPaymentMethod.current = paymentMethod;
				setCurrentPaymentMethod( paymentMethod );
			} catch ( error ) {
				console.error( 'Failed to track payment method:', error );
			}
		},
		[
			axoConfig?.insights?.payment_method_selected_map,
			setCurrentPaymentMethod,
		]
	);

	useEffect( () => {
		if ( activePaymentMethod ) {
			if ( isInitialMount.current ) {
				// Just set the initial payment method without tracking
				lastPaymentMethod.current = activePaymentMethod;
				setCurrentPaymentMethod( activePaymentMethod );
				isInitialMount.current = false;
			} else {
				handlePaymentMethodChange( activePaymentMethod );
			}
		}
	}, [
		activePaymentMethod,
		handlePaymentMethodChange,
		setCurrentPaymentMethod,
	] );

	useEffect( () => {
		return () => {
			lastPaymentMethod.current = null;
			isInitialMount.current = true;
		};
	}, [] );
};

const PayPalInsightsLoader = () => {
	const eventTracking = useEventTracking();
	const { setEventTriggered, isEventTriggered } = eventTracking;

	const initialConfig =
		window?.wc?.wcSettings?.getSetting( `${ GATEWAY_HANDLE }_data` ) || {};

	const { ppcpConfig } = usePayPalCommerceGateway( initialConfig );
	const axoConfig = window?.wc_ppcp_axo;

	const { isEmailSubmitted } = useSelect( ( select ) => {
		const storeSelect = select( STORE_NAME );
		return {
			isEmailSubmitted: storeSelect?.getIsEmailSubmitted?.() ?? false,
		};
	}, [] );

	usePayPalInsightsInit( axoConfig, ppcpConfig, eventTracking );
	usePaymentMethodTracking( axoConfig, eventTracking );

	useEffect( () => {
		const trackEmail = async () => {
			if ( isEmailSubmitted && ! isEventTriggered( 'emailSubmitted' ) ) {
				try {
					await waitForPayPalInsight();
					PayPalInsights.trackSubmitCheckoutEmail();
					setEventTriggered( 'emailSubmitted' );
				} catch ( error ) {
					console.error( 'Failed to track email submission:', error );
				}
			}
		};
		trackEmail();
	}, [ isEmailSubmitted, setEventTriggered, isEventTriggered ] );

	return null;
};

registerPlugin( 'wc-ppcp-paypal-insights', {
	render: PayPalInsightsLoader,
	scope: 'woocommerce-checkout',
} );

export default PayPalInsightsLoader;
