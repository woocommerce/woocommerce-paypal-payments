// File: axoStore.js

import { createReduxStore, register, dispatch } from '@wordpress/data';

export const STORE_NAME = 'woocommerce-paypal-payments/axo-block';

// Initial state
const DEFAULT_STATE = {
	isGuest: true,
	isAxoActive: false,
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
};

// Reducer
const reducer = ( state = DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case 'SET_IS_GUEST':
			return { ...state, isGuest: action.payload };
		case 'SET_IS_AXO_ACTIVE':
			return { ...state, isAxoActive: action.payload };
		default:
			return state;
	}
};

// Selectors
const selectors = {
	getIsGuest: ( state ) => state.isGuest,
	getIsAxoActive: ( state ) => state.isAxoActive,
};

// Create and register the store
const store = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
} );

register( store );

// Utility functions
export const setIsGuest = ( isGuest ) => {
	try {
		dispatch( STORE_NAME ).setIsGuest( isGuest );
	} catch ( error ) {
		console.error( 'Error updating isGuest state:', error );
	}
};
