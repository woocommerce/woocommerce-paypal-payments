/**
 * Reducer: Defines store structure and state updates for this module.
 *
 * Manages both transient (temporary) and persistent (saved) state.
 * The initial state must define all properties, as dynamic additions are not supported.
 *
 * @file
 */

import { createReducer, createReducerSetters } from '../utils';
import ACTION_TYPES from './action-types';

// Store structure.

const defaultTransient = Object.freeze( {
	isReady: false,
	activities: new Map(),
	activeModal: '',
	activeHighlight: '',

	// Read only values, provided by the server via hydrate.
	merchant: Object.freeze( {
		isConnected: false,
		isSandbox: false,
		id: '',
		email: '',
		clientId: '',
		clientSecret: '',
		sellerType: 'unknown',
	} ),

	wooSettings: Object.freeze( {
		storeCountry: '',
		storeCurrency: '',
	} ),

	features: Object.freeze( {
		save_paypal_and_venmo: {
			enabled: false,
		},
		advanced_credit_and_debit_cards: {
			enabled: false,
		},
		apple_pay: {
			enabled: false,
		},
		google_pay: {
			enabled: false,
		},
		alternative_payment_methods: {
			enabled: false,
		},
		pay_later_messaging: {
			enabled: false,
		},
	} ),

	webhooks: Object.freeze( [] ),
} );

const defaultPersistent = Object.freeze( {
	useSandbox: false,
	useManualConnection: false,
} );

// Reducer logic.

const [ changeTransient, changePersistent ] = createReducerSetters(
	defaultTransient,
	defaultPersistent
);

const commonReducer = createReducer( defaultTransient, defaultPersistent, {
	[ ACTION_TYPES.SET_TRANSIENT ]: ( state, action ) =>
		changeTransient( state, action ),

	[ ACTION_TYPES.SET_PERSISTENT ]: ( state, action ) =>
		changePersistent( state, action ),

	[ ACTION_TYPES.RESET ]: ( state ) => {
		const cleanState = changeTransient(
			changePersistent( state, defaultPersistent ),
			defaultTransient
		);

		// Keep "read-only" details and initialization flags.
		cleanState.wooSettings = { ...state.wooSettings };
		cleanState.merchant = { ...state.merchant };
		cleanState.features = { ...state.features };
		cleanState.isReady = true;

		return cleanState;
	},

	[ ACTION_TYPES.START_ACTIVITY ]: ( state, payload ) => {
		return changeTransient( state, {
			activities: new Map( state.activities ).set(
				payload.id,
				payload.description
			),
		} );
	},

	[ ACTION_TYPES.STOP_ACTIVITY ]: ( state, payload ) => {
		const newActivities = new Map( state.activities );
		newActivities.delete( payload.id );
		return changeTransient( state, { activities: newActivities } );
	},

	// Instantly reset the merchant data and features before refreshing the details.
	[ ACTION_TYPES.RESET_MERCHANT ]: ( state ) => ( {
		...state,
		merchant: Object.freeze( { ...defaultTransient.merchant } ),
		features: Object.freeze( { ...defaultTransient.features } ),
	} ),

	[ ACTION_TYPES.SET_MERCHANT ]: ( state, payload ) => {
		return changePersistent( state, { merchant: payload.merchant } );
	},

	[ ACTION_TYPES.HYDRATE ]: ( state, payload ) => {
		const newState = changePersistent( state, payload.data );

		// Populate read-only properties.
		[ 'wooSettings', 'merchant', 'features', 'webhooks' ].forEach(
			( key ) => {
				if ( ! payload[ key ] ) {
					return;
				}

				newState[ key ] = Object.freeze( {
					...newState[ key ],
					...payload[ key ],
				} );
			}
		);

		return newState;
	},
} );

export default commonReducer;
