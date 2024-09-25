import { createReduxStore, register, dispatch } from '@wordpress/data';

export const STORE_NAME = 'woocommerce-paypal-payments/axo-block';

// Initial state
const DEFAULT_STATE = {
	isGuest: true,
	isAxoActive: false,
	isAxoScriptLoaded: false,
	isEmailSubmitted: false,
	isEmailLookupCompleted: false,
	shippingAddress: null,
	cardDetails: null,
	phoneNumber: '',
};

// Actions
const actions = {
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

// Reducer
const reducer = ( state = DEFAULT_STATE, action ) => {
	switch ( action.type ) {
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

// Selectors
const selectors = {
	getIsGuest: ( state ) => state.isGuest,
	getIsAxoActive: ( state ) => state.isAxoActive,
	getIsAxoScriptLoaded: ( state ) => state.isAxoScriptLoaded,
	getIsEmailSubmitted: ( state ) => state.isEmailSubmitted,
	getIsEmailLookupCompleted: ( state ) => state.isEmailLookupCompleted,
	getShippingAddress: ( state ) => state.shippingAddress,
	getCardDetails: ( state ) => state.cardDetails,
	getPhoneNumber: ( state ) => state.phoneNumber,
};

// Create and register the store
const store = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
} );

register( store );

// Action dispatchers
export const setIsGuest = ( isGuest ) => {
	dispatch( STORE_NAME ).setIsGuest( isGuest );
};

export const setIsEmailLookupCompleted = ( isEmailLookupCompleted ) => {
	dispatch( STORE_NAME ).setIsEmailLookupCompleted( isEmailLookupCompleted );
};

export const setShippingAddress = ( shippingAddress ) => {
	dispatch( STORE_NAME ).setShippingAddress( shippingAddress );
};

export const setCardDetails = ( cardDetails ) => {
	dispatch( STORE_NAME ).setCardDetails( cardDetails );
};

export const setPhoneNumber = ( phoneNumber ) => {
	dispatch( STORE_NAME ).setPhoneNumber( phoneNumber );
};
