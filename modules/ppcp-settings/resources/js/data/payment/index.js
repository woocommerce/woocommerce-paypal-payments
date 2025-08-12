import { createReduxStore, register } from '@wordpress/data';

import { STORE_NAME } from './constants';
import reducer from './reducer';
import * as selectors from './selectors';
import * as actions from './actions';
import * as hooks from './hooks';
import * as resolvers from './resolvers';
import { initTodoSync } from '../sync/todo-state-sync';
import { initPaymentDependencySync } from '../sync/payment-methods-sync';
import { initSettingBasedPaymentMethodsSync } from '../sync/setting-based-payment-methods-sync';

/**
 * Initializes and registers the settings store with WordPress data layer.
 * Combines custom controls with WordPress data controls.
 *
 * @return {boolean} True if initialization succeeded, false otherwise.
 */
export const initStore = () => {
	const store = createReduxStore( STORE_NAME, {
		reducer,
		actions,
		selectors,
		resolvers,
	} );

	register( store );

	// Initialize todo sync after store registration.
	initTodoSync();

	// Initialize payment method dependency sync.
	initPaymentDependencySync();
	initSettingBasedPaymentMethodsSync();

	return Boolean( wp.data.select( STORE_NAME ) );
};

export { hooks, selectors, STORE_NAME };
