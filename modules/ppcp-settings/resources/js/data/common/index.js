import { createReduxStore, register } from '@wordpress/data';

import { STORE_NAME } from './constants';
import reducer from './reducer';
import * as selectors from './selectors';
import * as actions from './actions';
import * as thunkActions from './actions-thunk';
import * as hooks from './hooks';
import * as resolvers from './resolvers';

import { addStoreToFunnel } from '../../services/tracking';
import { ONBOARDING_FUNNEL_ID } from '../../services/tracking/init';

/**
 * Initializes and registers the settings store with WordPress data layer.
 * Combines custom controls with WordPress data controls.
 *
 * @return {boolean} True if initialization succeeded, false otherwise.
 */
export const initStore = () => {
	const store = createReduxStore( STORE_NAME, {
		reducer,
		actions: { ...actions, ...thunkActions },
		selectors,
		resolvers,
	} );

	register( store );

	// Add this store to the onboarding funnel.
	addStoreToFunnel( STORE_NAME, ONBOARDING_FUNNEL_ID );

	return Boolean( wp.data.select( STORE_NAME ) );
};

export { hooks, selectors, STORE_NAME };
