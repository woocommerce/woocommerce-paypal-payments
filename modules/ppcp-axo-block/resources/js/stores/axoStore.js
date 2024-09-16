// File: axoStore.js

import { createReduxStore, register, dispatch } from '@wordpress/data';

export const STORE_NAME = 'woocommerce-paypal-payments/axo-block';

// Initial state
const DEFAULT_STATE = {
	isGuest: true,
	isAxoActive: false,
	isAxoScriptLoaded: false,
	isEmailSubmitted: false,
	isEmailLookupCompleted: false,
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
};

// Create and register the store
const store = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
} );

register( store );

export const setIsGuest = ( isGuest ) => {
	try {
		dispatch( STORE_NAME ).setIsGuest( isGuest );
	} catch ( error ) {
		console.error( 'Error updating isGuest state:', error );
	}
};

export const setIsEmailLookupCompleted = ( isEmailLookupCompleted ) => {
	try {
		dispatch( STORE_NAME ).setIsEmailLookupCompleted(
			isEmailLookupCompleted
		);
	} catch ( error ) {
		console.error( 'Error updating isEmailLookupCompleted state:', error );
	}
};
