/**
 * Store Configuration: Defines and registers the settings data store.
 *
 * Creates a Redux-style store with WordPress data layer integration.
 * Combines reducers, actions, selectors and controls into a unified store.
 *
 * @file
 */

import { createReduxStore, register } from '@wordpress/data';
import { controls as wpControls } from '@wordpress/data-controls';

import { STORE_NAME } from './constants';
import reducer from './reducer';
import * as selectors from './selectors';
import * as actions from './actions';
import * as hooks from './hooks';
import { resolvers } from './resolvers';
import { controls } from './controls';

/**
 * Initializes and registers the settings store with WordPress data layer.
 * Combines custom controls with WordPress data controls.
 *
 * @return {boolean} True if initialization succeeded, false otherwise.
 */
export const initStore = () => {
	const store = createReduxStore( STORE_NAME, {
		reducer,
		controls: { ...wpControls, ...controls },
		actions,
		selectors,
		resolvers,
	} );
	register( store );

	return Boolean( wp.data.select( STORE_NAME ) );
};

export { hooks, selectors, STORE_NAME };
