import { createReduxStore, register, combineReducers } from '@wordpress/data';
import { controls } from '@wordpress/data-controls';
import { STORE_NAME } from './constants';
import * as onboarding from './onboarding';

const actions = {};
const selectors = {};
const resolvers = {};

[ onboarding ].forEach( ( item ) => {
	Object.assign( actions, { ...item.actions } );
	Object.assign( selectors, { ...item.selectors } );
	Object.assign( resolvers, { ...item.resolvers } );
} );

const reducer = combineReducers( {
	onboarding: onboarding.reducer,
} );

export const initStore = () => {
	const store = createReduxStore( STORE_NAME, {
		reducer,
		controls,
		actions,
		selectors,
		resolvers,
	} );

	register( store );
};
