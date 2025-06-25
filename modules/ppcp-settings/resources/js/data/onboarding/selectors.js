/**
 * Selectors: Extract specific pieces of state from the store.
 *
 * These functions provide a consistent interface for accessing store data.
 * They allow components to retrieve data without knowing the store structure.
 *
 * @file
 */

import { PAYPAL_PRODUCTS, PRODUCT_TYPES } from './configuration';

const EMPTY_OBJ = Object.freeze( {} );

const getState = ( state ) => state || EMPTY_OBJ;

export const persistentData = ( state ) => {
	return getState( state ).data || EMPTY_OBJ;
};

export const transientData = ( state ) => {
	const { data, flags, ...transientState } = getState( state );
	return transientState || EMPTY_OBJ;
};

export const flags = ( state ) => {
	return getState( state ).flags || EMPTY_OBJ;
};

/**
 * Returns details about products and capabilities to use for the production login link in
 * the last onboarding step.
 *
 * This selector does not return state-values, but uses the state to derive the products-array
 * that should be returned.
 *
 * @param {{}}      state
 * @param {boolean} ownBrandOnly
 * @param {string}  storeCountry
 * @return {{products:string[], options:{}}} The ISU products, based on choices made in the onboarding wizard.
 */
export const determineProductsAndCaps = (
	state,
	ownBrandOnly,
	storeCountry
) => {
	/**
	 * An array of product-names that are used to build an onboarding URL via the
	 * PartnerReferrals API. To avoid confusion with the "products" property from the
	 * Redux store, this collection has a distinct name.
	 *
	 * On server-side, this value is referred to as "products" again.
	 */
	const apiModules = [];

	/**
	 * Internal options that are parsed by the PartnerReferrals class to customize
	 * the API payload.
	 */
	const options = {
		useSubscriptions: false,
		useCardPayments: false,
	};

	const { isCasualSeller, areOptionalPaymentMethodsEnabled, products } =
		persistentData( state );
	const { canUseVaulting, canUseCardPayments } = flags( state );
	const isBrandedCasualSeller = isCasualSeller && ownBrandOnly;

	const cardPaymentsEligibleAndSelected =
		canUseCardPayments &&
		areOptionalPaymentMethodsEnabled &&
		! isBrandedCasualSeller;

	if ( ! cardPaymentsEligibleAndSelected ) {
		/**
		 * Branch 1: Credit Card Payments not available.
		 * The store uses the Express-checkout product.
		 */
		apiModules.push( PAYPAL_PRODUCTS.BCDC );

		if ( products?.includes( PRODUCT_TYPES.SUBSCRIPTIONS ) ) {
			options.useSubscriptions = true;
		}

		if ( canUseVaulting ) {
			apiModules.push( PAYPAL_PRODUCTS.VAULTING );
		}
	} else if ( isCasualSeller || 'MX' === storeCountry ) {
		/**
		 * Branch 2: Merchant has no business.
		 * The store uses the Express-checkout product.
		 */
		apiModules.push( PAYPAL_PRODUCTS.BCDC );
	} else {
		/**
		 * Branch 3: Merchant is business, and can use CC payments.
		 * The store uses the advanced PPCP product.
		 *
		 * This is the only branch that can use subscriptions.
		 */
		apiModules.push( PAYPAL_PRODUCTS.ACDC );

		if ( products?.includes( PRODUCT_TYPES.SUBSCRIPTIONS ) ) {
			options.useSubscriptions = true;
		}

		if ( canUseVaulting ) {
			apiModules.push( PAYPAL_PRODUCTS.VAULTING );
		}
	}

	options.useCardPayments = cardPaymentsEligibleAndSelected;

	return { products: apiModules, options };
};
