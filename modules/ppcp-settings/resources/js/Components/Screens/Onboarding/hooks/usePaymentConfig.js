import { __ } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';

import { learnMoreLinks } from '../../../../utils/countryInfoLinks';
import {
	PayWithPayPal,
	PayLater,
	Venmo,
	Crypto,
	PayInThree,
	CardFields,
	DigitalWallets,
	AlternativePaymentMethods,
	Fastlane,
	CreditDebitCards,
} from '../Components/PaymentOptions';

// Default configuration, used for all countries, unless they override individual attributes below.
const defaultConfig = {
	includedMethods: [
		{ name: 'PayWithPayPal', Component: PayWithPayPal },
		{ name: 'PayLater', Component: PayLater },
	],
	optionalMethods: [
		{ name: 'CreditDebitCards', Component: CreditDebitCards },
		{ name: 'DigitalWallets', Component: DigitalWallets },
		{ name: 'APMs', Component: AlternativePaymentMethods },
	],
	optionalTitle: __(
		'Optional payment methods',
		'woocommerce-paypal-payments'
	),
	optionalDescription: __(
		'with additional application',
		'woocommerce-paypal-payments'
	),
};

const countrySpecificConfigs = {
	US: {
		includedMethods: [
			{ name: 'PayWithPayPal', Component: PayWithPayPal },
			{ name: 'PayLater', Component: PayLater },
			{ name: 'Venmo', Component: Venmo },
			{ name: 'Crypto', Component: Crypto },
		],
		optionalMethods: [
			{ name: 'CardFields', Component: CardFields },
			{ name: 'DigitalWallets', Component: DigitalWallets },
			{ name: 'APMs', Component: AlternativePaymentMethods },
			{ name: 'Fastlane', Component: Fastlane },
		],
		optionalTitle: __( 'Expanded Checkout', 'woocommerce-paypal-payments' ),
		optionalDescription: __(
			'Accept debit/credit cards, PayPal, Apple Pay, Google Pay, and more. Note: Additional application required for more methods',
			'woocommerce-paypal-payments'
		),
	},
	GB: {
		includedMethods: [
			{ name: 'PayWithPayPal', Component: PayWithPayPal },
			{ name: 'PayInThree', Component: PayInThree },
		],
	},
};

const filterMethods = ( methods, conditions ) => {
	return methods.filter( ( method ) =>
		conditions.every( ( condition ) => condition( method ) )
	);
};

export const usePaymentConfig = (
	country,
	isPayLater,
	useAcdc,
	isFastlane
) => {
	return useMemo( () => {
		const countryConfig = countrySpecificConfigs[ country ] || {};
		const config = { ...defaultConfig, ...countryConfig };
		const learnMoreConfig = learnMoreLinks[ country ] || {};

		const includedMethods = filterMethods( config.includedMethods, [
			( method ) => isPayLater || method.name !== 'PayLater',
		] );

		const optionalMethods = filterMethods( config.optionalMethods, [
			( method ) => method.name !== 'Fastlane' || isFastlane,
		] );

		return {
			...config,
			includedMethods,
			optionalMethods,
			learnMoreConfig,
		};
	}, [ country, isPayLater, useAcdc, isFastlane ] );
};
