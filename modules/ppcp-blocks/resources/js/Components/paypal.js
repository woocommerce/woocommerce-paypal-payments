import { useEffect, useState } from '@wordpress/element';
import { loadPayPalScript } from '../../../../ppcp-button/resources/js/modules/Helper/PayPalScriptLoading';
import {
	mergeWcAddress,
	paypalAddressToWc,
	paypalOrderToWcAddresses,
} from '../Helper/Address';
import { convertKeysToSnakeCase } from '../Helper/Helper';
import buttonModuleWatcher from '../../../../ppcp-button/resources/js/modules/ButtonModuleWatcher';
import { normalizeStyleForFundingSource } from '../../../../ppcp-button/resources/js/modules/Helper/Style';
import {
	cartHasSubscriptionProducts,
	isPayPalSubscription,
} from '../Helper/Subscription';
import {
	createOrder,
	createSubscription,
	createVaultSetupToken,
	handleApprove,
	handleApproveSubscription,
	onApproveSavePayment,
} from '../paypal-config';
import { useRef } from 'react';
import Spinner from '../../../../ppcp-button/resources/js/modules/Helper/Spinner';

const PAYPAL_GATEWAY_ID = 'ppcp-gateway';

const namespace = 'ppcpBlocksPaypalExpressButtons';
let registeredContext = false;
let paypalScriptPromise = null;

export const shouldEnableAppSwitch = ( config ) => {
	// AppSwitch should only be enabled in Pay Now flows with server side shipping callback.
	return (
		config.scriptData.appswitch.enabled &&
		! config.scriptData.final_review_enabled &&
		config.scriptData.server_side_shipping_callback.enabled
	);
};

export const PayPalComponent = ( {
	config,
	onClick,
	onClose,
	onSubmit,
	onError,
	eventRegistration,
	emitResponse,
	activePaymentMethod,
	shippingData,
	isEditing,
	fundingSource,
	buttonAttributes,
} ) => {
	const { onPaymentSetup, onCheckoutFail, onCheckoutValidation } =
		eventRegistration;
	const { responseTypes } = emitResponse;

	const [ paypalOrder, setPaypalOrder ] = useState( null );
	const [ continuationFilled, setContinuationFilled ] = useState( false );
	const [ gotoContinuationOnError, setGotoContinuationOnError ] =
		useState( false );

	const [ paypalScriptLoaded, setPaypalScriptLoaded ] = useState( false );
	const [ isFullPageSpinnerActive, setIsFullPageSpinnerActive ] =
		useState( false );

	const paypalButtonRef = useRef( null );
	const spinnerRef = useRef( null );

	if ( ! paypalScriptLoaded ) {
		if ( ! paypalScriptPromise ) {
			// for editor, since canMakePayment was not called
			paypalScriptPromise = loadPayPalScript(
				namespace,
				config.scriptData
			);
		}
		paypalScriptPromise.then( () => setPaypalScriptLoaded( true ) );
	}

	const methodId = fundingSource
		? `${ config.id }-${ fundingSource }`
		: config.id;

	// Full-page spinner used to block UI interactions during flows like AppSwitch.
	useEffect( () => {
		if ( isFullPageSpinnerActive ) {
			if ( ! spinnerRef.current ) {
				spinnerRef.current = Spinner.fullPage();
			}
			spinnerRef.current.block();
		} else if ( spinnerRef.current ) {
			spinnerRef.current.unblock();
		}
	}, [ isFullPageSpinnerActive ] );

	useEffect( () => {
		// fill the form if in continuation (for product or mini-cart buttons)
		if ( continuationFilled || ! config.scriptData.continuation?.order ) {
			return;
		}

		try {
			const paypalAddresses = paypalOrderToWcAddresses(
				config.scriptData.continuation.order
			);
			const wcAddresses = wp.data
				.select( 'wc/store/cart' )
				.getCustomerData();
			const addresses = mergeWcAddress( wcAddresses, paypalAddresses );

			wp.data
				.dispatch( 'wc/store/cart' )
				.setBillingAddress( addresses.billingAddress );

			if ( shippingData.needsShipping ) {
				wp.data
					.dispatch( 'wc/store/cart' )
					.setShippingAddress( addresses.shippingAddress );
			}
		} catch ( err ) {
			// sometimes the PayPal address is missing, skip in this case.
			console.error( err );
		}

		// this useEffect should run only once, but adding this in case of some kind of full re-rendering
		setContinuationFilled( true );
	}, [ shippingData.needsShipping, continuationFilled ] );

	const getCheckoutRedirectUrl = () => {
		const checkoutUrl = new URL( config.scriptData.redirect );
		// sometimes some browsers may load some kind of cached version of the page,
		// so adding a parameter to avoid that
		checkoutUrl.searchParams.append(
			'ppcp-continuation-redirect',
			new Date().getTime().toString()
		);
		return checkoutUrl.toString();
	};

	useEffect( () => {
		const unsubscribe = onCheckoutValidation( () => {
			if ( config.scriptData.continuation ) {
				return true;
			}
			if (
				gotoContinuationOnError &&
				wp.data.select( 'wc/store/validation' ).hasValidationErrors()
			) {
				location.href = getCheckoutRedirectUrl();
				return { type: responseTypes.ERROR };
			}

			return true;
		} );
		return unsubscribe;
	}, [ onCheckoutValidation, gotoContinuationOnError ] );

	const handleClick = ( data, actions ) => {
		if ( isEditing ) {
			return actions.reject();
		}

		window.ppcpFundingSource = data.fundingSource;

		onClick();
	};

	const handleCancel = () => {
		// Don't call onClose if AppSwitch is enabled - PayPal SDK fires onCancel
		// when switching to the app, but the user hasn't actually canceled
		if ( shouldEnableAppSwitch( config ) ) {
			return;
		}

		onClose();
	};

	const handleButtonInit = () => {
		if ( fundingSource === 'paypal' ) {
			const buttonInstance = paypalButtonRef.current?.state?.parent;

			if ( buttonInstance?.hasReturned?.() ) {
				buttonInstance.resume();
			}
		}
	};

	const shouldHandleShippingInPayPal = () => {
		return shouldskipFinalConfirmation() && config.needShipping;
	};

	const shouldskipFinalConfirmation = () => {
		if ( config.finalReviewEnabled ) {
			return false;
		}

		return (
			window.ppcpFundingSource !== 'venmo' ||
			! config.scriptData.vaultingEnabled
		);
	};

	let handleShippingOptionsChange = null;
	let handleShippingAddressChange = null;

	if ( shippingData.needsShipping && shouldHandleShippingInPayPal() ) {
		handleShippingOptionsChange = async ( data, actions ) => {
			try {
				const shippingOptionId = data.selectedShippingOption?.id;
				if ( shippingOptionId ) {
					await wp.data
						.dispatch( 'wc/store/cart' )
						.selectShippingRate( shippingOptionId );
					await shippingData.setSelectedRates( shippingOptionId );
				}

				const res = await fetch( config.ajax.update_shipping.endpoint, {
					method: 'POST',
					credentials: 'same-origin',
					body: JSON.stringify( {
						nonce: config.ajax.update_shipping.nonce,
						order_id: data.orderID,
					} ),
				} );

				const json = await res.json();

				if ( ! json.success ) {
					throw new Error( json.data.message );
				}
			} catch ( e ) {
				console.error( e );

				actions.reject();
			}
		};

		handleShippingAddressChange = async ( data, actions ) => {
			try {
				const address = paypalAddressToWc(
					convertKeysToSnakeCase( data.shippingAddress )
				);

				await wp.data.dispatch( 'wc/store/cart' ).updateCustomerData( {
					shipping_address: address,
				} );

				await shippingData.setShippingAddress( address );

				const res = await fetch( config.ajax.update_shipping.endpoint, {
					method: 'POST',
					credentials: 'same-origin',
					body: JSON.stringify( {
						nonce: config.ajax.update_shipping.nonce,
						order_id: data.orderID,
					} ),
				} );

				const json = await res.json();

				if ( ! json.success ) {
					throw new Error( json.data.message );
				}
			} catch ( e ) {
				console.error( e );

				actions.reject();
			}
		};
	}

	useEffect( () => {
		if ( activePaymentMethod !== methodId ) {
			return;
		}

		const unsubscribeProcessing = onPaymentSetup( () => {
			if (
				cartHasSubscriptionProducts( config.scriptData ) &&
				config.scriptData.is_free_trial_cart
			) {
				return {
					type: responseTypes.SUCCESS,
				};
			}

			if ( config.scriptData.continuation ) {
				return {
					type: responseTypes.SUCCESS,
					meta: {
						paymentMethodData: {
							paypal_order_id:
								config.scriptData.continuation.order_id,
							funding_source:
								window.ppcpFundingSource ?? 'paypal',
						},
					},
				};
			}

			let addresses = {};
			if ( paypalOrder.purchase_units?.[ 0 ]?.shipping?.address ) {
				addresses = paypalOrderToWcAddresses( paypalOrder );
			}

			return {
				type: responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
						paypal_order_id: paypalOrder.id,
						funding_source: window.ppcpFundingSource ?? 'paypal',
					},
					...addresses,
				},
			};
		} );
		return () => {
			unsubscribeProcessing();
		};
	}, [ onPaymentSetup, paypalOrder, activePaymentMethod ] );

	useEffect( () => {
		const unsubscribe = onCheckoutFail( () => {
			setIsFullPageSpinnerActive( false );
		} );

		return unsubscribe;
	}, [ onCheckoutFail ] );

	useEffect( () => {
		if ( activePaymentMethod !== methodId ) {
			return;
		}
		const unsubscribe = onCheckoutFail( ( { processingResponse } ) => {
			console.error( processingResponse );
			if ( onClose ) {
				onClose();
			}
			if ( config.scriptData.continuation ) {
				return true;
			}

			// Don't redirect for trial vaulting subscriptions
			if (
				cartHasSubscriptionProducts( config.scriptData ) &&
				config.scriptData.is_free_trial_cart
			) {
				return true;
			}

			if ( shouldskipFinalConfirmation() ) {
				location.href = getCheckoutRedirectUrl();
			}
			return true;
		} );
		return unsubscribe;
	}, [ onCheckoutFail, onClose, activePaymentMethod ] );

	if ( config.scriptData.continuation ) {
		return (
			<div
				dangerouslySetInnerHTML={ {
					__html: config.scriptData.continuation.cancel.html,
				} }
			></div>
		);
	}

	if ( ! registeredContext ) {
		buttonModuleWatcher.registerContextBootstrap(
			config.scriptData.context,
			{
				createOrder: ( data ) => {
					return createOrder( data, config, onError, onClose );
				},
				onApprove: ( data, actions ) => {
					return handleApprove(
						data,
						actions,
						config,
						shouldHandleShippingInPayPal,
						shippingData,
						setPaypalOrder,
						shouldskipFinalConfirmation,
						getCheckoutRedirectUrl,
						setGotoContinuationOnError,
						onSubmit,
						onError,
						onClose,
						setIsFullPageSpinnerActive
					);
				},
			}
		);
		registeredContext = true;
	}

	const style = normalizeStyleForFundingSource(
		config.scriptData.button.style,
		fundingSource
	);

	if ( typeof buttonAttributes !== 'undefined' ) {
		style.height = buttonAttributes?.height
			? Number( buttonAttributes.height )
			: style.height;
		style.borderRadius = buttonAttributes?.borderRadius
			? Number( buttonAttributes.borderRadius )
			: style.borderRadius;
	}

	if ( ! paypalScriptLoaded ) {
		return null;
	}

	const PayPalButton = ppcpBlocksPaypalExpressButtons.Buttons.driver(
		'react',
		{ React, ReactDOM }
	);

	const getOnShippingOptionsChange = ( fundingSource ) => {
		if ( config.scriptData.server_side_shipping_callback.enabled ) {
			return null;
		}

		if ( fundingSource === 'venmo' ) {
			return null;
		}

		return ( data, actions ) => {
			shouldHandleShippingInPayPal()
				? handleShippingOptionsChange( data, actions )
				: null;
		};
	};

	const getOnShippingAddressChange = ( fundingSource ) => {
		if ( config.scriptData.server_side_shipping_callback.enabled ) {
			return null;
		}

		if ( fundingSource === 'venmo' ) {
			return null;
		}

		return ( data, actions ) => {
			const shippingAddressChange = shouldHandleShippingInPayPal()
				? handleShippingAddressChange( data, actions )
				: null;

			return shippingAddressChange;
		};
	};

	if (
		cartHasSubscriptionProducts( config.scriptData ) &&
		config.scriptData.is_free_trial_cart
	) {
		return (
			<PayPalButton
				style={ style }
				onClick={ handleClick }
				onCancel={ handleCancel }
				onError={ onClose }
				createVaultSetupToken={ () => createVaultSetupToken( config ) }
				onApprove={ ( { vaultSetupToken } ) =>
					onApproveSavePayment( vaultSetupToken, config, onSubmit )
				}
			/>
		);
	}

	if ( isPayPalSubscription( config.scriptData ) ) {
		return (
			<PayPalButton
				fundingSource={ fundingSource }
				style={ style }
				onClick={ handleClick }
				onCancel={ handleCancel }
				onError={ onClose }
				createSubscription={ ( data, actions ) =>
					createSubscription( data, actions, config )
				}
				onApprove={ ( data, actions ) =>
					handleApproveSubscription(
						data,
						actions,
						config,
						shouldHandleShippingInPayPal,
						shippingData,
						setPaypalOrder,
						shouldskipFinalConfirmation,
						getCheckoutRedirectUrl,
						setGotoContinuationOnError,
						onSubmit,
						onError,
						onClose
					)
				}
				onShippingOptionsChange={ getOnShippingOptionsChange(
					fundingSource
				) }
				onShippingAddressChange={ getOnShippingAddressChange(
					fundingSource
				) }
			/>
		);
	}

	return (
		<PayPalButton
			ref={ paypalButtonRef }
			appSwitchWhenAvailable={ shouldEnableAppSwitch( config ) }
			fundingSource={ fundingSource }
			style={ style }
			onInit={ handleButtonInit }
			onClick={ handleClick }
			onCancel={ handleCancel }
			onError={ onClose }
			createOrder={ ( data ) =>
				createOrder( data, config, onError, onClose )
			}
			onApprove={ ( data, actions ) =>
				handleApprove(
					data,
					actions,
					config,
					shouldHandleShippingInPayPal,
					shippingData,
					setPaypalOrder,
					shouldskipFinalConfirmation,
					getCheckoutRedirectUrl,
					setGotoContinuationOnError,
					onSubmit,
					onError,
					onClose,
					setIsFullPageSpinnerActive
				)
			}
			onShippingOptionsChange={ getOnShippingOptionsChange(
				fundingSource
			) }
			onShippingAddressChange={ getOnShippingAddressChange(
				fundingSource
			) }
		/>
	);
};
