import { __ } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';

import { learnMoreLinks } from '@ppcp-settings/utils/countryInfoLinks';
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

// List of all payment icons and which requirements they have.
const PAYMENT_ICONS = [
	{ name: 'paypal', always: true },
	{ name: 'venmo', isOwnBrand: true, onlyAcdc: false, countries: [ 'US' ] },
	{ name: 'visa', isOwnBrand: false, onlyAcdc: false },
	{ name: 'mastercard', isOwnBrand: false, onlyAcdc: false },
	{ name: 'amex', isOwnBrand: false, onlyAcdc: false },
	{ name: 'discover', isOwnBrand: false, onlyAcdc: false },
	{
		name: 'apple-pay',
		isOwnBrand: false,
		onlyAcdc: false,
		isDigitalWallet: true,
	},
	{
		name: 'google-pay',
		isOwnBrand: false,
		onlyAcdc: false,
		isDigitalWallet: true,
	},
	{ name: 'blik', isOwnBrand: true, onlyAcdc: true },
	{ name: 'ideal', isOwnBrand: true, onlyAcdc: true },
	{ name: 'bancontact', isOwnBrand: true, onlyAcdc: true },
	{ name: 'oxxo', isOwnBrand: true, onlyAcdc: false, countries: [ 'MX' ] },
];

// Default configuration, used for all countries, unless they override individual attributes below.
const DEFAULT_CONFIG = {
	includedMethods: [
		{ name: 'PayWithPayPal', Component: PayWithPayPal },
		{ name: 'PayLater', Component: PayLater },
	],
	extendedMethods: [
		{
			name: 'CreditDebitCards',
			Component: CreditDebitCards,
			isOwnBrand: false,
			isAcdc: false,
		},
		{
			name: 'CardFields',
			Component: CardFields,
			isOwnBrand: false,
			isAcdc: true,
		},
		{
			name: 'DigitalWallets',
			Component: DigitalWallets,
			isOwnBrand: false,
			isAcdc: false,
			isDigitalWallet: true,
		},
		{
			name: 'APMs',
			Component: AlternativePaymentMethods,
			isOwnBrand: true,
			isAcdc: true,
		},
		{
			name: 'Fastlane',
			Component: Fastlane,
			isOwnBrand: false,
			isAcdc: true,
			isFastlane: true,
		},
	],
};

// Country-specific configurations.
const COUNTRY_CONFIGS = {
	US: {
		includedMethods: [
			{ name: 'PayWithPayPal', Component: PayWithPayPal },
			{ name: 'PayLater', Component: PayLater },
			{ name: 'Venmo', Component: Venmo },
			{ name: 'Crypto', Component: Crypto },
		],
	},
	GB: {
		includedMethods: [
			{ name: 'PayWithPayPal', Component: PayWithPayPal },
			{ name: 'PayInThree', Component: PayInThree },
		],
	},
};

/**
 * Gets all UI text elements based on country and branding options.
 *
 * @param {string}  country            - The country code
 * @param {boolean} canUseCardPayments - Whether merchant can use card payments (ACDC)
 * @param {boolean} onlyBranded        - Whether to show only branded payment methods
 * @return {Object} All UI text elements
 */
const getUIText = ( country, canUseCardPayments, onlyBranded ) => {
	const TITLES = {
		EXPANDED: __( 'Expanded Checkout', 'woocommerce-paypal-payments' ),
		OPTIONAL: __(
			'Optional payment methods',
			'woocommerce-paypal-payments'
		),
	};

	const OPTIONAL_DESCRIPTIONS = {
		LOCAL_METHODS: __(
			'Accept local payment methods. Note: Additional application required for some methods',
			'woocommerce-paypal-payments'
		),
		WITH_APPLICATION: __(
			'with additional application',
			'woocommerce-paypal-payments'
		),
		US_EXPANDED: __(
			'Accept debit/credit cards, PayPal, Apple Pay, Google Pay, and more. Note: Additional application required for some methods',
			'woocommerce-paypal-payments'
		),
	};

	const CORE_DESCRIPTIONS = {
		DEFAULT_CHECKOUT: __(
			'Our all-in-one checkout solution lets you offer PayPal, Pay Later options, and more to help maximise conversion',
			'woocommerce-paypal-payments'
		),
		US_CHECKOUT: __(
			'Our all-in-one checkout solution lets you offer PayPal, Venmo, Pay Later options, and more to help maximise conversion',
			'woocommerce-paypal-payments'
		),
	};

	// Base text configuration for all countries.
	const texts = {
		paypalCheckoutDescription: CORE_DESCRIPTIONS.DEFAULT_CHECKOUT,
		optionalTitle: canUseCardPayments
			? TITLES.EXPANDED
			: TITLES.OPTIONAL,
		optionalDescription: OPTIONAL_DESCRIPTIONS.WITH_APPLICATION,
	};

	// Country-specific overrides.
	if ( country === 'US' ) {
		texts.paypalCheckoutDescription = CORE_DESCRIPTIONS.US_CHECKOUT;
		texts.optionalDescription = OPTIONAL_DESCRIPTIONS.US_EXPANDED;
	}

	// Branded-only mode overrides.
	if ( onlyBranded ) {
		texts.optionalTitle = TITLES.EXPANDED;
		texts.optionalDescription = OPTIONAL_DESCRIPTIONS.LOCAL_METHODS;
	}

	return texts;
};

/**
 * Filters payment icons based on country and configuration.
 *
 * @param {string}  country               - The country code
 * @param {boolean} includeAcdc           - Whether to include advanced card payment methods
 * @param {boolean} includeDigitalWallets - Whether to include digital wallet icons
 * @param {boolean} onlyBranded           - Whether to show only branded payment methods
 * @return {string[]} List of icon names
 */
const getRelevantIcons = (
	country,
	includeAcdc,
	includeDigitalWallets,
	onlyBranded
) =>
	PAYMENT_ICONS.filter(
		( {
			always,
			isOwnBrand,
			onlyAcdc,
			isDigitalWallet,
			countries = [],
		} ) => {
			if ( always ) {
				return true;
			}

			// Digital wallet icons are independent of ACDC.
			if ( isDigitalWallet ) {
				return (
					includeDigitalWallets &&
					( ! onlyBranded || isOwnBrand )
				);
			}


			if ( onlyBranded && ! isOwnBrand ) {
				return false;
			}

			if ( ! includeAcdc && onlyAcdc ) {
				return false;
			}

			return ! countries.length || countries.includes( country );
		}
	).map( ( icon ) => icon.name );

/**
 * Filters payment methods based on provided conditions.
 *
 * @param {Array}           methods    - The methods to filter
 * @param {Array<Function>} conditions - List of filter conditions
 * @return {Array} Filtered methods
 */
const filterMethods = ( methods, conditions ) => {
	return methods.filter( ( method ) =>
		conditions.every( ( condition ) => condition( method ) )
	);
};

/**
 * Custom hook that generates payment configuration based on merchant settings.
 *
 * @param {string}  country              - Merchant country code
 * @param {boolean} canUseCardPayments   - Whether merchant can use card payments
 * @param {boolean} canUseDigitalWallets - Whether merchant can use digital wallets (Apple Pay/Google Pay)
 * @param {boolean} hasFastlane          - Whether merchant has Fastlane enabled
 * @param {boolean} ownBrandOnly         - Whether to show only branded payment methods
 * @return {Object} Complete payment configuration
 */
export const usePaymentConfig = (
	country,
	canUseCardPayments,
	canUseDigitalWallets,
	hasFastlane,
	ownBrandOnly
) => {
	return useMemo( () => {
		// Merge country-specific config with default.
		const countryConfig = COUNTRY_CONFIGS[ country ] || {};
		const config = { ...DEFAULT_CONFIG, ...countryConfig };

		// Get "learn more" links for the country
		let learnMoreConfig = learnMoreLinks[ country ] || {};

		// If ownBrandOnly is true, move the "OptionalMethods" link to the "APMs" component.
		if ( ownBrandOnly && learnMoreConfig.OptionalMethods ) {
			const { OptionalMethods, ...rest } = learnMoreConfig;
			learnMoreConfig = { ...rest, APMs: OptionalMethods };
		}

		// Filter out conditional methods.
		const availableOptionalMethods = filterMethods(
			config.extendedMethods,
			[
				// Digital wallets are independent of ACDC.
				( method ) =>
					! method.isDigitalWallet || canUseDigitalWallets,
				// Include ACDC methods when card payments available, non-ACDC otherwise.
				( method ) =>
					method.isDigitalWallet ||
					method.isAcdc === canUseCardPayments,
				// Only include own-brand methods when ownBrandOnly is true.
				( method ) => ! ownBrandOnly || method.isOwnBrand === true,
				// Only include Fastlane when hasFastlane is true.
				( method ) => method.name !== 'Fastlane' || hasFastlane,
			]
		);

		// Get all UI text elements.
		const uiText = getUIText( country, canUseCardPayments, ownBrandOnly );

		// Get icons appropriate for this configuration.
		const icons = getRelevantIcons(
			country,
			canUseCardPayments,
			canUseDigitalWallets,
			ownBrandOnly
		);

		// Return the complete configuration.
		return {
			// Payment methods configuration.
			includedMethods: config.includedMethods,
			basicMethods: config.basicMethods,
			optionalMethods: availableOptionalMethods,

			// UI text configuration.
			paypalCheckoutDescription: uiText.paypalCheckoutDescription,
			optionalTitle: uiText.optionalTitle,
			optionalDescription: uiText.optionalDescription,

			// Additional configuration.
			learnMoreConfig,
			icons,
		};
	}, [
		country,
		canUseCardPayments,
		canUseDigitalWallets,
		hasFastlane,
		ownBrandOnly,
	] );
};
