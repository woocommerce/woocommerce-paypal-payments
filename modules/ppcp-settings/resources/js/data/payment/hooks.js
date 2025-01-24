/**
 * Hooks: Provide the main API for components to interact with the store.
 *
 * These encapsulate store interactions, offering a consistent interface.
 * Hooks simplify data access and manipulation for components.
 *
 * @file
 */

import { useSelect, useDispatch } from '@wordpress/data';

import { STORE_NAME } from './constants';

const useTransient = ( key ) =>
	useSelect(
		( select ) => select( STORE_NAME ).transientData()?.[ key ],
		[ key ]
	);

const usePersistent = ( key ) =>
	useSelect(
		( select ) => select( STORE_NAME ).persistentData()?.[ key ],
		[ key ]
	);

const useHooks = () => {
	const { persist, setPersistent } = useDispatch( STORE_NAME );

	// Read-only flags and derived state.
	// Nothing here yet.

	// Transient accessors.
	const isReady = useTransient( 'isReady' );

	// PayPal checkout.
	const paypal = usePersistent( 'ppcp-gateway' );
	const venmo = usePersistent( 'venmo' );
	const payLater = usePersistent( 'pay-later' );
	const creditCard = usePersistent( 'ppcp-card-button-gateway' );

	// Online card Payments.
	const advancedCreditCard = usePersistent( 'ppcp-credit-card-gateway' );
	const fastlane = usePersistent( 'ppcp-axo-gateway' );
	const applePay = usePersistent( 'ppcp-applepay' );
	const googlePay = usePersistent( 'ppcp-googlepay' );

	// Alternative payment methods.
	const bancontact = usePersistent( 'ppcp-bancontact' );
	const blik = usePersistent( 'ppcp-blik' );
	const eps = usePersistent( 'ppcp-eps' );
	const ideal = usePersistent( 'ppcp-ideal' );
	const mybank = usePersistent( 'ppcp-mybank' );
	const p24 = usePersistent( 'ppcp-p24' );
	const trustly = usePersistent( 'ppcp-trustly' );
	const multibanco = usePersistent( 'ppcp-multibanco' );
	const pui = usePersistent( 'ppcp-pay-upon-invoice-gateway' );
	const oxxo = usePersistent( 'ppcp-oxxo-gateway' );

	// Custom modal data.
	const paypalShowLogo = usePersistent( 'paypalShowLogo' );
	const threeDSecure = usePersistent( 'threeDSecure' );
	const fastlaneCardholderName = usePersistent( 'fastlaneCardholderName' );
	const fastlaneDisplayWatermark = usePersistent(
		'fastlaneDisplayWatermark'
	);

	return {
		persist,
		isReady,
		setPersistent,
		paypal,
		venmo,
		payLater,
		creditCard,
		advancedCreditCard,
		fastlane,
		applePay,
		googlePay,
		bancontact,
		blik,
		eps,
		ideal,
		mybank,
		p24,
		trustly,
		multibanco,
		pui,
		oxxo,
		paypalShowLogo,
		threeDSecure,
		fastlaneCardholderName,
		fastlaneDisplayWatermark,
	};
};

export const useStore = () => {
	const { persist, isReady } = useHooks();
	return { persist, isReady };
};

export const usePaymentMethods = () => {
	const {
		setPersistent,
		paypal,
		venmo,
		payLater,
		creditCard,
		advancedCreditCard,
		fastlane,
		applePay,
		googlePay,
		bancontact,
		blik,
		eps,
		ideal,
		mybank,
		p24,
		trustly,
		multibanco,
		pui,
		oxxo,
	} = useHooks();

	const paymentMethods = [
		paypal,
		venmo,
		payLater,
		creditCard,
		advancedCreditCard,
		fastlane,
		applePay,
		googlePay,
		bancontact,
		blik,
		eps,
		ideal,
		mybank,
		p24,
		trustly,
		multibanco,
		pui,
		oxxo,
	];

	return {
		setPersistent,
		paymentMethods,
	};
};

export const usePaymentMethodsModal = () => {
	const {
		paypalShowLogo,
		threeDSecure,
		fastlaneCardholderName,
		fastlaneDisplayWatermark,
	} = useHooks();

	return {
		paypalShowLogo,
		threeDSecure,
		fastlaneCardholderName,
		fastlaneDisplayWatermark,
	};
};

export const usePaymentMethodsPayPalCheckout = () => {
	const { paypal, venmo, payLater, creditCard } = useHooks();
	const paymentMethodsPayPalCheckout = [
		paypal,
		venmo,
		payLater,
		creditCard,
	];

	return {
		paymentMethodsPayPalCheckout,
	};
};

export const usePaymentMethodsOnlineCardPayments = () => {
	const { advancedCreditCard, fastlane, applePay, googlePay } = useHooks();
	const paymentMethodsOnlineCardPayments = [
		advancedCreditCard,
		fastlane,
		applePay,
		googlePay,
	];

	return {
		paymentMethodsOnlineCardPayments,
	};
};

export const usePaymentMethodsAlternative = () => {
	const {
		bancontact,
		blik,
		eps,
		ideal,
		mybank,
		p24,
		trustly,
		multibanco,
		pui,
		oxxo,
	} = useHooks();

	const paymentMethodsAlternative = [
		bancontact,
		blik,
		eps,
		ideal,
		mybank,
		p24,
		trustly,
		multibanco,
		pui,
		oxxo,
	];

	return {
		paymentMethodsAlternative,
	};
};
