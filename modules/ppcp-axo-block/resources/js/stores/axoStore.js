import { createReduxStore, register, dispatch } from '@wordpress/data';

export const STORE_NAME = 'woocommerce-paypal-payments/axo-block';

const DEFAULT_STATE = {
	isPayPalLoaded: false,
	isGuest: true,
	isAxoActive: false,
	isAxoScriptLoaded: false,
	isEmailSubmitted: false,
	isEmailLookupCompleted: false,
	shippingAddress: null,
	cardDetails: null,
	phoneNumber: '',
};

// Action creators for updating the store state
const actions = {
	setIsPayPalLoaded: ( isPayPalLoaded ) => ( {
		type: 'SET_IS_PAYPAL_LOADED',
		payload: isPayPalLoaded,
	} ),
	setIsGuest: ( isGuest ) => ( {
		type: 'SET_IS_GUEST',
		payload: isGuest,
	} ),
	setIsAxoActive: ( isAxoActive ) => ( {
		type: 'SET_IS_AXO_ACTIVE',
		payload: isAxoActive,
	} ),
	setIsAxoScriptLoaded: ( isAxoScriptLoaded ) => ( {
		type: 'SET_IS_AXO_SCRIPT_LOADED',
		payload: isAxoScriptLoaded,
	} ),
	setIsEmailSubmitted: ( isEmailSubmitted ) => ( {
		type: 'SET_IS_EMAIL_SUBMITTED',
		payload: isEmailSubmitted,
	} ),
	setIsEmailLookupCompleted: ( isEmailLookupCompleted ) => ( {
		type: 'SET_IS_EMAIL_LOOKUP_COMPLETED',
		payload: isEmailLookupCompleted,
	} ),
	setShippingAddress: ( shippingAddress ) => ( {
		type: 'SET_SHIPPING_ADDRESS',
		payload: shippingAddress,
	} ),
	setCardDetails: ( cardDetails ) => ( {
		type: 'SET_CARD_DETAILS',
		payload: cardDetails,
	} ),
	setPhoneNumber: ( phoneNumber ) => ( {
		type: 'SET_PHONE_NUMBER',
		payload: phoneNumber,
	} ),
};

/**
 * Reducer function to handle state updates based on dispatched actions.
 *
 * @param {Object} state  - Current state of the store.
 * @param {Object} action - Dispatched action object.
 * @return {Object} New state after applying the action.
 */
const reducer = ( state = DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case 'SET_IS_PAYPAL_LOADED':
			return { ...state, isPayPalLoaded: action.payload };
		case 'SET_IS_GUEST':
			return { ...state, isGuest: action.payload };
		case 'SET_IS_AXO_ACTIVE':
			return { ...state, isAxoActive: action.payload };
		case 'SET_IS_AXO_SCRIPT_LOADED':
			return { ...state, isAxoScriptLoaded: action.payload };
		case 'SET_IS_EMAIL_SUBMITTED':
			return { ...state, isEmailSubmitted: action.payload };
		case 'SET_IS_EMAIL_LOOKUP_COMPLETED':
			return { ...state, isEmailLookupCompleted: action.payload };
		case 'SET_SHIPPING_ADDRESS':
			return { ...state, shippingAddress: action.payload };
		case 'SET_CARD_DETAILS':
			return { ...state, cardDetails: action.payload };
		case 'SET_PHONE_NUMBER':
			return { ...state, phoneNumber: action.payload };
		default:
			return state;
	}
};

// Selector functions to retrieve specific pieces of state
const selectors = {
	getIsPayPalLoaded: ( state ) => state.isPayPalLoaded,
	getIsGuest: ( state ) => state.isGuest,
	getIsAxoActive: ( state ) => state.isAxoActive,
	getIsAxoScriptLoaded: ( state ) => state.isAxoScriptLoaded,
	getIsEmailSubmitted: ( state ) => state.isEmailSubmitted,
	getIsEmailLookupCompleted: ( state ) => state.isEmailLookupCompleted,
	getShippingAddress: ( state ) => state.shippingAddress,
	getCardDetails: ( state ) => state.cardDetails,
	getPhoneNumber: ( state ) => state.phoneNumber,
};

// Create and register the Redux store for the AXO block
const store = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
} );

register( store );

// Action dispatchers

/**
 * Action dispatcher to update the PayPal script load status in the store.
 *
 * @param {boolean} isPayPalLoaded - Whether the PayPal script has loaded.
 */
export const setIsPayPalLoaded = ( isPayPalLoaded ) => {
	dispatch( STORE_NAME ).setIsPayPalLoaded( isPayPalLoaded );
};

/**
 * Action dispatcher to update the guest status in the store.
 *
 * @param {boolean} isGuest - Whether the user is a guest or not.
 */
export const setIsGuest = ( isGuest ) => {
	dispatch( STORE_NAME ).setIsGuest( isGuest );
};

/**
 * Action dispatcher to update the email lookup completion status in the store.
 *
 * @param {boolean} isEmailLookupCompleted - Whether the email lookup is completed.
 */
export const setIsEmailLookupCompleted = ( isEmailLookupCompleted ) => {
	dispatch( STORE_NAME ).setIsEmailLookupCompleted( isEmailLookupCompleted );
};

/**
 * Action dispatcher to update the shipping address in the store.
 *
 * @param {Object} shippingAddress - The user's shipping address.
 */
export const setShippingAddress = ( shippingAddress ) => {
	dispatch( STORE_NAME ).setShippingAddress( shippingAddress );
};

/**
 * Action dispatcher to update the card details in the store.
 *
 * @param {Object} cardDetails - The user's card details.
 */
export const setCardDetails = ( cardDetails ) => {
	dispatch( STORE_NAME ).setCardDetails( cardDetails );
};

/**
 * Action dispatcher to update the phone number in the store.
 *
 * @param {string} phoneNumber - The user's phone number.
 */
export const setPhoneNumber = ( phoneNumber ) => {
	dispatch( STORE_NAME ).setPhoneNumber( phoneNumber );
};
