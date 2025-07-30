/**
 * Store setup: Field source tracking store.
 *
 * Initializes and registers the field source tracking store.
 *
 * @file
 */

import { createReduxStore, register } from '@wordpress/data';

import { STORE_NAME } from './constants';
import reducer from './reducer';
import * as selectors from './selectors';
import * as actions from './actions';

/**
 * Initialize the field source tracking store.
 *
 * @return {boolean} True if initialization succeeded
 */
export const initStore = () => {
	const store = createReduxStore( STORE_NAME, {
		reducer,
		actions,
		selectors,
	} );

	register( store );

	return Boolean( wp.data.select( STORE_NAME ) );
};

export { selectors, STORE_NAME };
