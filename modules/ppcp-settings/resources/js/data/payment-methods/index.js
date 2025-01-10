import { createReduxStore, register, select } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

export const initStore = () => {
	const actions = {
		updatePaymentMethods( paymentMethods ) {
			return {
				type: 'update_payment_methods',
				paymentMethods,
			};
		},
		fetch( path ) {
			return {
				type: 'FETCH',
				path,
			};
		},
		*update( data ) {
			return yield apiFetch( {
				path: '/wc/v3/payment_gateways/ppcp-gateway',
				method: 'post',
				data,
			} );
		},
	};

	const store = createReduxStore( 'wc/paypal/payment-methods', {
		reducer( state = { paymentMethods: [] }, action ) {
			switch ( action.type ) {
				case 'update_payment_methods':
					return { ...state, paymentMethods: action.paymentMethods };
			}

			return state;
		},
		actions,
		selectors: {
			getPaymentMethods( state ) {
				return state.paymentMethods;
			},
		},
		controls: {
			FETCH( action ) {
				return apiFetch( { path: action.path } );
			},
		},
		resolvers: {
			*getPaymentMethods() {
				const path = '/wc/v3/payment_gateways';
				const response = yield actions.fetch( path );

				return actions.updatePaymentMethods( response );
			},
		},
	} );

	register( store );
};
