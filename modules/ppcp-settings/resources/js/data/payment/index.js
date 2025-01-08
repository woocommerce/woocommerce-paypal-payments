import { createReduxStore, register } from '@wordpress/data';
import { STORE_NAME } from './constants';

export const initStore = () => {
	const store = createReduxStore( STORE_NAME, {
		reducer( state = { payments: [ { foo: 'bar' } ] } ) {
			return state;
		},
		selectors: {
			getPayments( state ) {
				return state.payments;
			},
		},
	} );

	register( store );
};
