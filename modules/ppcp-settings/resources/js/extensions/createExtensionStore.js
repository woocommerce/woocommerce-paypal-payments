/**
 * Extension Settings Store Factory
 *
 * Creates a complete Redux store for extension settings with zero boilerplate.
 * Extensions only need to define their unique settings fields.
 *
 * @file
 */

import {
	createReduxStore,
	register,
	useDispatch,
	useSelect,
} from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

import { registerExtensionStore } from './registry';

const EMPTY_OBJ = Object.freeze( {} );

/**
 * Creates a complete settings store for an extension module.
 *
 * @param {Object} config           Store configuration
 * @param {string} config.name      Extension name (e.g., 'agentic-settings')
 * @param {Object} config.defaults  Default values for persistent settings
 * @param {string} config.namespace Optional REST namespace (default: 'wc/v3/wc_paypal')
 * @return {Function}               Hook to access the store.
 *
 * @example
 * // Store is registered immediately on import
 * export const useSettings = createExtensionStore({
 *     name: 'agentic-settings',
 *     defaults: {
 *         active: false,
 *         maxItems: 10,
 *     }
 * });
 *
 * // In component:
 * const { active, setActive, maxItems, setMaxItems, persist } = useSettings();
 */
export const createExtensionStore = ( config ) => {
	const { name, defaults, namespace = 'wc/v3/wc_paypal' } = config;

	// Generate store identifiers
	const STORE_NAME = `wc/paypal/${ name }`;
	const REST_PATH = `/${ namespace }/ext/${ name }`;

	// Action types
	const ACTION_TYPES = {
		SET_TRANSIENT: `ppcp/${ name }/SET_TRANSIENT`,
		SET_PERSISTENT: `ppcp/${ name }/SET_PERSISTENT`,
		HYDRATE: `ppcp/${ name }/HYDRATE`,
	};

	// Default state
	const defaultTransient = Object.freeze( {
		isReady: false,
	} );

	const defaultPersistent = Object.freeze( { ...defaults } );

	// Reducer
	const reducer = (
		state = {
			...defaultTransient,
			data: defaultPersistent,
		},
		action
	) => {
		switch ( action.type ) {
			case ACTION_TYPES.SET_TRANSIENT:
				return {
					...state,
					...action.payload,
				};

			case ACTION_TYPES.SET_PERSISTENT:
				return {
					...state,
					data: {
						...state.data,
						...action.payload,
					},
				};

			case ACTION_TYPES.HYDRATE:
				return {
					...state,
					data: {
						...state.data,
						...action.payload.data,
					},
				};

			default:
				return state;
		}
	};

	// Actions
	const setTransient = ( prop, value ) => ( {
		type: ACTION_TYPES.SET_TRANSIENT,
		payload: { [ prop ]: value },
	} );

	const setPersistent = ( prop, value ) => ( {
		type: ACTION_TYPES.SET_PERSISTENT,
		payload: { [ prop ]: value },
	} );

	const hydrate = ( payload ) => ( {
		type: ACTION_TYPES.HYDRATE,
		payload,
	} );

	const setIsReady = ( isReady ) => setTransient( 'isReady', isReady );

	const persist = () => {
		return async ( { select } ) => {
			await apiFetch( {
				path: REST_PATH,
				method: 'POST',
				data: select.persistentData(),
			} );
		};
	};

	const refresh = () => {
		return ( { dispatch, select } ) => {
			dispatch.setIsReady( false );
			select.persistentData();
		};
	};

	const actions = {
		setTransient,
		setPersistent,
		hydrate,
		setIsReady,
		persist,
		refresh,
	};

	// Selectors
	const getState = ( state ) => state || EMPTY_OBJ;

	const persistentData = ( state ) => {
		return getState( state ).data || EMPTY_OBJ;
	};

	const transientData = ( state ) => {
		const { data, ...transientState } = getState( state );
		return transientState || EMPTY_OBJ;
	};

	const selectors = {
		getState,
		persistentData,
		transientData,
	};

	// Resolvers
	const persistentDataResolver = () => {
		return async ( { dispatch } ) => {
			try {
				const result = await apiFetch( { path: REST_PATH } );

				await dispatch.hydrate( result );
				await dispatch.setIsReady( true );
			} catch ( e ) {
				console.error( `Error loading ${ name } settings:`, e );
				await dispatch.setIsReady( true );
			}
		};
	};

	const resolvers = {
		persistentData: persistentDataResolver,
	};

	// Register and initialize the store
	const store = createReduxStore( STORE_NAME, {
		reducer,
		actions,
		selectors,
		resolvers,
	} );
	register( store );

	// Auto-register this extension store for central persistence management
	const dispatch = wp.data.dispatch( STORE_NAME );
	registerExtensionStore( STORE_NAME, {
		key: name,
		message: `Process ${ name } settings`,
		store: {
			persist: dispatch.persist,
			refresh: dispatch.refresh,
		},
	} );

	// Return hook to access the store
	return () => {
		const select = useSelect(
			( storeSelectors ) => storeSelectors( STORE_NAME ),
			[]
		);
		const storeDispatch = useDispatch( STORE_NAME );

		// Check if store is ready
		const isReady = useSelect(
			( storeSelectors ) =>
				storeSelectors( STORE_NAME ).transientData().isReady,
			[]
		);

		// Trigger data load if not ready
		if ( ! isReady ) {
			select.persistentData();
		}

		// Get all persistent data
		const data = useSelect(
			( storeSelectors ) => storeSelectors( STORE_NAME ).persistentData(),
			[]
		);

		const { persist: persistAction } = storeDispatch;

		// Generate getters and setters for each field
		return useMemo( () => {
			const result = {
				isReady,
				persist: persistAction,
			};

			// Create getter and setter for each default field
			Object.keys( defaults ).forEach( ( key ) => {
				// Getter: returns current value
				result[ key ] = data[ key ];

				// Setter: creates a function that updates the value
				const setterName = `set${
					key.charAt( 0 ).toUpperCase() + key.slice( 1 )
				}`;
				result[ setterName ] = ( value ) => {
					storeDispatch.setPersistent( key, value );
				};
			} );

			return result;
		}, [ data, isReady, persistAction, storeDispatch ] );
	};
};
