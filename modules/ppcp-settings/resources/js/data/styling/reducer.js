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
import {
	STYLING_COLORS,
	STYLING_LABELS,
	STYLING_LAYOUTS,
	STYLING_LOCATIONS,
	STYLING_SHAPES,
} from './configuration';

// Store structure.

// Transient: Values that are _not_ saved to the DB (like app lifecycle-flags).
const defaultTransient = Object.freeze( {
	isReady: false,
	location: STYLING_LOCATIONS.cart.value, // Which location is selected in the Styling tab.
} );

// Persistent: Values that are loaded from the DB.
const defaultPersistent = Object.freeze( {
	[ STYLING_LOCATIONS.cart.value ]: Object.freeze( {
		enabled: true,
		methods: [],
		label: STYLING_LABELS.pay.value,
		shape: STYLING_SHAPES.rect.value,
		color: STYLING_COLORS.gold.value,
	} ),
	[ STYLING_LOCATIONS.classicCheckout.value ]: Object.freeze( {
		enabled: true,
		methods: [],
		label: STYLING_LABELS.checkout.value,
		shape: STYLING_SHAPES.rect.value,
		color: STYLING_COLORS.gold.value,
		layout: STYLING_LAYOUTS.vertical.value,
		tagline: false,
	} ),
	[ STYLING_LOCATIONS.expressCheckout.value ]: Object.freeze( {
		enabled: true,
		methods: [],
		label: STYLING_LABELS.checkout.value,
		shape: STYLING_SHAPES.rect.value,
		color: STYLING_COLORS.gold.value,
	} ),
	[ STYLING_LOCATIONS.miniCart.value ]: Object.freeze( {
		enabled: true,
		methods: [],
		label: STYLING_LABELS.pay.value,
		shape: STYLING_SHAPES.rect.value,
		color: STYLING_COLORS.gold.value,
		layout: STYLING_LAYOUTS.vertical.value,
		tagline: false,
	} ),
	[ STYLING_LOCATIONS.product.value ]: Object.freeze( {
		enabled: true,
		methods: [],
		label: STYLING_LABELS.buynow.value,
		shape: STYLING_SHAPES.rect.value,
		color: STYLING_COLORS.gold.value,
		layout: STYLING_LAYOUTS.vertical.value,
		tagline: false,
	} ),
} );

const sanitizeLocation = ( oldDetails, newDetails ) => {
	// Skip if provided details are not a plain object.
	if (
		! newDetails ||
		'object' !== typeof newDetails ||
		Array.isArray( newDetails )
	) {
		return oldDetails;
	}

	return { ...oldDetails, ...newDetails };
};

// Reducer logic.

const [ changeTransient, changePersistent ] = createReducerSetters(
	defaultTransient,
	defaultPersistent
);

const reducer = createReducer( defaultTransient, defaultPersistent, {
	[ ACTION_TYPES.SET_TRANSIENT ]: ( state, payload ) =>
		changeTransient( state, payload ),

	[ ACTION_TYPES.SET_PERSISTENT ]: ( state, payload ) =>
		changePersistent( state, payload ),

	[ ACTION_TYPES.RESET ]: ( state ) => {
		const cleanState = changeTransient(
			changePersistent( state, defaultPersistent ),
			defaultTransient
		);

		// Keep "read-only" details and initialization flags.
		cleanState.isReady = true;

		return cleanState;
	},

	[ ACTION_TYPES.HYDRATE ]: ( state, payload ) => {
		const validData = Object.keys( defaultPersistent ).reduce(
			( data, location ) => {
				data[ location ] = sanitizeLocation(
					state.data[ location ],
					payload.data[ location ]
				);
				return data;
			},
			{}
		);

		return changePersistent( state, validData );
	},
} );

export default reducer;
