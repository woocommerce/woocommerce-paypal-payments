/**
 * Reducer: Field source tracking store.
 *
 * State structure: { storeName: { fieldName: { source, timestamp } } }
 *
 * @file
 */

import ACTION_TYPES from './action-types';

const initialState = {};

const trackingReducer = ( state = initialState, action ) => {
	switch ( action.type ) {
		case ACTION_TYPES.UPDATE_SOURCES: {
			const { storeName, fieldName, source, timestamp } = action.payload;

			return {
				...state,
				[ storeName ]: {
					...( state[ storeName ] || {} ),
					[ fieldName ]: { source, timestamp },
				},
			};
		}

		case ACTION_TYPES.CLEAR_FIELD_SOURCE: {
			const { storeName, fieldName } = action.payload;
			const storeData = state[ storeName ];

			if ( ! storeData ) {
				return state;
			}

			const newStoreData = { ...storeData };
			delete newStoreData[ fieldName ];

			return {
				...state,
				[ storeName ]: newStoreData,
			};
		}

		case ACTION_TYPES.CLEAR_SOURCES: {
			const { storeName } = action.payload;

			if ( storeName ) {
				const newState = { ...state };
				delete newState[ storeName ];
				return newState;
			}

			return initialState;
		}

		case ACTION_TYPES.RESET:
			return initialState;

		default:
			return state;
	}
};

export default trackingReducer;
