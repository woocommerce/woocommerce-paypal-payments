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
	// Included: Items in the left column.
	includedMethods: [
		{ name: 'PayWithPayPal', Component: PayWithPayPal },
		{ name: 'PayLater', Component: PayLater },
	],

	// Basic: Items on right side for BCDC-flow.
	basicMethods: [ { name: 'CreditDebitCards', Component: CreditDebitCards } ],

	// Extended: Items on right side for ACDC-flow.
	extendedMethods: [
		{ name: 'DigitalWallets', Component: DigitalWallets },
		{ name: 'APMs', Component: AlternativePaymentMethods },
	],

	// Title, Description: Header above the right column items.
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
		basicMethods: [
			{ name: 'CreditDebitCards', Component: CreditDebitCards },
		],
		extendedMethods: [
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

		// Filter the "left side" list. PayLater is conditional.
		const includedMethods = filterMethods( config.includedMethods, [
			( method ) => isPayLater || method.name !== 'PayLater',
		] );

		// Determine the "right side" items: Either BCDC or ACDC items.
		const optionalMethods = useAcdc
			? config.extendedMethods
			: config.basicMethods;

		// Remove conditional items from the right side list.
		const availableOptionalMethods = filterMethods( optionalMethods, [
			( method ) => method.name !== 'Fastlane' || isFastlane,
		] );

		return {
			...config,
			includedMethods,
			optionalMethods: availableOptionalMethods,
			learnMoreConfig,
		};
	}, [ country, isPayLater, useAcdc, isFastlane ] );
};
