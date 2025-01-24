import { useDispatch, useSelect } from '@wordpress/data';
import { useCallback } from '@wordpress/element';

/**
 * Updates an object with new values, filtering based on allowed keys.
 *
 * Helper method used by createSetters.
 *
 * @param {Object} oldObject   The original object to update.
 * @param {Object} newValues   The new values to apply.
 * @param {Object} allowedKeys An object whose keys define the allowed keys to update.
 * @return {Object} A new object with the allowed updates applied.
 */
const updateObject = ( oldObject, newValues, allowedKeys = {} ) => ( {
	...oldObject,
	...Object.keys( newValues ).reduce( ( acc, key ) => {
		if ( key in allowedKeys ) {
			acc[ key ] = newValues[ key ];
		} else {
			console.warn(
				`Ignoring unknown key "${ key }" - to use it, add it to the initial store properties in the reducer.`
			);
		}
		return acc;
	}, {} ),
} );

/**
 * Creates setter functions for updating state.
 *
 * Only properties that are present in the "defaultTransient" or "defaultPersistent"
 * arguments can be updated by the setters. Make sure that the default state defines
 * ALL possible properties.
 *
 * @param {Object} defaultTransient  Object defining initial transient values.
 * @param {Object} defaultPersistent Object defining initial persistent values.
 * @return {[Function, Function]} An array containing setTransient and setPersistent functions.
 */
export const createReducerSetters = ( defaultTransient, defaultPersistent ) => {
	const changeTransient = ( oldState, newValues = {} ) =>
		updateObject( oldState, newValues, defaultTransient );

	const changePersistent = ( oldState, newValues = {} ) => ( {
		...oldState,
		data: updateObject( oldState.data, newValues, defaultPersistent ),
	} );

	return [ changeTransient, changePersistent ];
};

/**
 * Creates a reducer function with predefined action handlers.
 *
 * @param {Object} defaultTransient  Object defining initial transient values.
 * @param {Object} defaultPersistent Object defining initial persistent values.
 * @param {Object} handlers          An object mapping action types to handler functions.
 * @return {Function} A reducer function.
 */
export const createReducer = (
	defaultTransient,
	defaultPersistent,
	handlers
) => {
	if ( Object.hasOwnProperty.call( defaultTransient, 'data' ) ) {
		throw new Error(
			'The transient state cannot contain a "data" property.'
		);
	}

	const initialState = {
		...defaultTransient,
		data: defaultPersistent,
	};

	return function reducer( state = initialState, action ) {
		if ( Object.hasOwnProperty.call( handlers, action.type ) ) {
			return handlers[ action.type ]( state, action.payload ?? {} );
		}

		return state;
	};
};

/**
 * Returns an object with two hooks:
 * - useTransient( prop )
 * - usePersistent( prop )
 *
 * Both hooks have a similar syntax to the native "useState( prop )" hook, but provide access to
 * a transient or persistent property in the relevant Redux store.
 *
 * Sample:
 *
 * const { useTransient } = createHooksForStore( STORE_NAME );
 * const [ isReady, setIsReady ] = useTransient( 'isReady' );
 *
 * @param {string} storeName Store name.
 * @return {{useTransient, usePersistent}} Store hooks.
 */
export const createHooksForStore = ( storeName ) => {
	const createHook = ( selector, dispatcher ) => ( key ) => {
		const value = useSelect(
			( select ) => {
				const store = select( storeName );
				if ( ! store?.[ selector ] ) {
					throw new Error(
						`Please create the selector "${ selector }" for store "${ storeName }"`
					);
				}
				const selectorResult = store[ selector ]();
				if ( undefined === selectorResult?.[ key ] ) {
					console.error(
						`Warning: ${ selector }()[${ key }] is undefined in store "${ storeName }". This may indicate a bug.`
					);
				}
				return selectorResult?.[ key ];
			},
			[ key ]
		);

		const actions = useDispatch( storeName );

		const setValue = useCallback(
			( newValue ) => {
				if ( ! actions?.[ dispatcher ] ) {
					throw new Error(
						`Please create the action "${ dispatcher }" for store "${ storeName }"`
					);
				}
				actions[ dispatcher ]( key, newValue );
			},
			[ actions, key ]
		);

		return [ value, setValue ];
	};

	return {
		useTransient: createHook( 'transientData', 'setTransient' ),
		usePersistent: createHook( 'persistentData', 'setPersistent' ),
	};
};
