import { createReduxStore, register } from '@wordpress/data';
import { controls as wpControls } from '@wordpress/data-controls';

import { STORE_NAME } from './constants';
import reducer from './reducer';
import { controls } from './controls';
import * as actions from './actions';
import * as selectors from './selectors';
import * as resolvers from './resolvers';
import * as hooks from './hooks';

export const initStore = () => {
	const store = createReduxStore( STORE_NAME, {
		reducer,
		controls: { ...wpControls, ...controls },
		actions,
		selectors,
		resolvers,
	} );

	register( store );
};

export { hooks, selectors, STORE_NAME };
