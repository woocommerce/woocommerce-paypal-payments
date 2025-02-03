/**
 * Hooks: Provide the main API for components to interact with the store.
 *
 * These encapsulate store interactions, offering a consistent interface.
 * Hooks simplify data access and manipulation for components.
 *
 * @file
 */

import { useDispatch } from '@wordpress/data';

import { STORE_NAME } from './constants';
import { createHooksForStore } from '../utils';

const useHooks = () => {
	const { useTransient, usePersistent } = createHooksForStore( STORE_NAME );
	const { persist, setPersistent, changePaymentSettings } =
		useDispatch( STORE_NAME );

	// Read-only flags and derived state.
	// Nothing here yet.

	// Transient accessors.
	const [ isReady ] = useTransient( 'isReady' );

	// PayPal checkout.
	const [ paypal ] = usePersistent( 'ppcp-gateway' );
	const [ venmo ] = usePersistent( 'venmo' );
	const [ payLater ] = usePersistent( 'pay-later' );
	const [ creditCard ] = usePersistent( 'ppcp-card-button-gateway' );

	// Online card Payments.
	const [ advancedCreditCard ] = usePersistent( 'ppcp-credit-card-gateway' );
	const [ fastlane ] = usePersistent( 'ppcp-axo-gateway' );
	const [ applePay ] = usePersistent( 'ppcp-applepay' );
	const [ googlePay ] = usePersistent( 'ppcp-googlepay' );

	// Alternative payment methods.
	const [ bancontact ] = usePersistent( 'ppcp-bancontact' );
	const [ blik ] = usePersistent( 'ppcp-blik' );
	const [ eps ] = usePersistent( 'ppcp-eps' );
	const [ ideal ] = usePersistent( 'ppcp-ideal' );
	const [ mybank ] = usePersistent( 'ppcp-mybank' );
	const [ p24 ] = usePersistent( 'ppcp-p24' );
	const [ trustly ] = usePersistent( 'ppcp-trustly' );
	const [ multibanco ] = usePersistent( 'ppcp-multibanco' );
	const [ pui ] = usePersistent( 'ppcp-pay-upon-invoice-gateway' );
	const [ oxxo ] = usePersistent( 'ppcp-oxxo-gateway' );

	// Custom modal data.
	const [ paypalShowLogo ] = usePersistent( 'paypalShowLogo' );
	const [ threeDSecure ] = usePersistent( 'threeDSecure' );
	const [ fastlaneCardholderName ] = usePersistent(
		'fastlaneCardholderName'
	);
	const [ fastlaneDisplayWatermark ] = usePersistent(
		'fastlaneDisplayWatermark'
	);

	return {
		persist,
		isReady,
		setPersistent,
		changePaymentSettings,
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
	const { persist, isReady, setPersistent, changePaymentSettings } =
		useHooks();
	return { persist, isReady, setPersistent, changePaymentSettings };
};

export const usePaymentMethods = () => {
	const {
		// PayPal Checkout.
		paypal,
		venmo,
		payLater,
		creditCard,

		// Online card payments.
		advancedCreditCard,
		fastlane,
		applePay,
		googlePay,

		// Local APMs.
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

	const payPalCheckout = [ paypal, venmo, payLater, creditCard ];
	const onlineCardPayments = [
		advancedCreditCard,
		fastlane,
		applePay,
		googlePay,
	];
	const alternative = [
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
		all: paymentMethods,
		paypal: payPalCheckout,
		cardPayment: onlineCardPayments,
		apm: alternative,
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
