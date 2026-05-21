import { createReduxStore, register } from '@wordpress/data';

const STORE_NAME = 'ppcp/settings-registry';

const DEFAULT_STATE = {
	extensionStores: {}, // Track extension stores by name
};

const actions = {
	registerSetting( slot, id, component, priority = 10 ) {
		return {
			type: 'REGISTER_SETTING',
			slot,
			id,
			component,
			priority,
		};
	},

	unregisterSetting( slot, id ) {
		return {
			type: 'UNREGISTER_SETTING',
			slot,
			id,
		};
	},

	registerExtensionStore( storeName, config ) {
		return {
			type: 'REGISTER_EXTENSION_STORE',
			storeName,
			config,
		};
	},
};

const reducer = ( state = DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case 'REGISTER_SETTING': {
			const { slot, id, component, priority } = action;

			const slotItems = state[ slot ] || [];

			// Check for duplicate ID
			if ( slotItems.some( ( item ) => item.id === id ) ) {
				console.warn( `[SettingsRegistry] Duplicate ID: "${ id }"` );
				return state;
			}

			const newItems = [ ...slotItems, { component, priority, id } ].sort(
				( a, b ) => a.priority - b.priority
			);

			return {
				...state,
				[ slot ]: newItems,
			};
		}

		case 'UNREGISTER_SETTING': {
			const { slot, id } = action;
			if ( ! state[ slot ] ) {
				return state;
			}

			return {
				...state,
				[ slot ]: state[ slot ].filter( ( item ) => item.id !== id ),
			};
		}

		case 'REGISTER_EXTENSION_STORE': {
			const { storeName, config } = action;

			// Check for duplicate store name
			if ( state.extensionStores[ storeName ] ) {
				console.warn(
					`[SettingsRegistry] Extension store already registered: "${ storeName }"`
				);
				return state;
			}

			return {
				...state,
				extensionStores: {
					...state.extensionStores,
					[ storeName ]: config,
				},
			};
		}

		default:
			return state;
	}
};

const selectors = {
	getRegisteredSettings( state, slot ) {
		return state[ slot ] || [];
	},

	getAllRegistrations( state ) {
		return state;
	},

	getExtensionStores( state ) {
		return Object.values( state.extensionStores || {} );
	},
};

const store = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
} );

register( store );

export { STORE_NAME };
