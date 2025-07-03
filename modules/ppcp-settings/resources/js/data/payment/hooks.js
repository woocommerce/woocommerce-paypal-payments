/**
 * Hooks: Provide the main API for components to interact with the store.
 *
 * These encapsulate store interactions, offering a consistent interface.
 * Hooks simplify data access and manipulation for components.
 *
 * @file
 */

import { useDispatch, useSelect } from '@wordpress/data';

import { STORE_NAME } from './constants';
import { createHooksForStore } from '../utils';
import { useMemo } from '@wordpress/element';

/**
 * Single source of truth for access Redux details.
 *
 * This hook returns a stable API to access actions, selectors and special hooks to generate
 * getter- and setters for transient or persistent properties.
 *
 * @return {{select, dispatch, useTransient, usePersistent}} Store data API.
 */
const useStoreData = () => {
	const select = useSelect( ( selectors ) => selectors( STORE_NAME ), [] );
	const dispatch = useDispatch( STORE_NAME );
	const { useTransient, usePersistent } = createHooksForStore( STORE_NAME );

	return useMemo(
		() => ( {
			select,
			dispatch,
			useTransient,
			usePersistent,
		} ),
		[ select, dispatch, useTransient, usePersistent ]
	);
};

export const useStore = () => {
	const { select, useTransient, dispatch } = useStoreData();
	const { persist, refresh, setPersistent, changePaymentSettings } = dispatch;
	const [ isReady ] = useTransient( 'isReady' );

	// Load persistent data from REST if not done yet.
	if ( ! isReady ) {
		select.persistentData();
	}

	return {
		persist,
		refresh,
		setPersistent,
		changePaymentSettings,
		isReady,
	};
};

export const usePaymentMethods = () => {
	const { usePersistent } = useStoreData();

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

	const removeEmpty = ( list ) =>
		list.filter( ( item ) => item && item.id?.length );

	const payPalCheckout = removeEmpty( [
		paypal,
		venmo,
		payLater,
		creditCard,
	] );
	const onlineCardPayments = removeEmpty( [
		advancedCreditCard,
		fastlane,
		applePay,
		googlePay,
	] );
	const alternative = removeEmpty( [
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
	] );

	const paymentMethods = [
		...payPalCheckout,
		...onlineCardPayments,
		...alternative,
	];

	return {
		all: paymentMethods,
		paypal: payPalCheckout,
		cardPayment: onlineCardPayments,
		apm: alternative,
	};
};

export const usePaymentMethodsModal = () => {
	const { usePersistent } = useStoreData();

	const [ paypalShowLogo ] = usePersistent( 'paypalShowLogo' );
	const [ fastlaneCardholderName ] = usePersistent(
		'fastlaneCardholderName'
	);
	const [ fastlaneDisplayWatermark ] = usePersistent(
		'fastlaneDisplayWatermark'
	);

	return {
		paypalShowLogo,
		fastlaneCardholderName,
		fastlaneDisplayWatermark,
	};
};
