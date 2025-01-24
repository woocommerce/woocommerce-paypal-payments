/**
 * Hooks: Provide the main API for components to interact with the store.
 *
 * These encapsulate store interactions, offering a consistent interface.
 * Hooks simplify data access and manipulation for components.
 *
 * @file
 */

import { useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

import { createHooksForStore } from '../utils';
import { STORE_NAME } from './constants';
import {
	STYLING_COLORS,
	STYLING_LABELS,
	STYLING_LAYOUTS,
	STYLING_LOCATIONS,
	STYLING_PAYMENT_METHODS,
	STYLING_SHAPES,
} from './configuration';

const useHooks = () => {
	const { useTransient } = createHooksForStore( STORE_NAME );
	const { persist, setPersistent } = useDispatch( STORE_NAME );

	// Transient accessors.
	const [ isReady ] = useTransient( 'isReady' );
	const [ location, setLocation ] = useTransient( 'location' );

	// Persistent accessors.
	const persistentData = useSelect(
		( select ) => select( STORE_NAME ).persistentData(),
		[]
	);

	const getLocationProp = useCallback(
		( locationId, prop ) => {
			if ( undefined === persistentData[ locationId ]?.[ prop ] ) {
				console.error(
					`Trying to access non-existent style property: ${ locationId }.${ prop }. Possibly wrong style name - review the reducer.`
				);
				return null;
			}
			return persistentData[ locationId ][ prop ];
		},
		[ persistentData ]
	);

	const setLocationProp = useCallback(
		( locationId, prop, value ) => {
			const updatedStyles = {
				...persistentData[ locationId ],
				[ prop ]: value,
			};

			setPersistent( locationId, updatedStyles );
		},
		[ persistentData, setPersistent ]
	);

	return {
		persist,
		isReady,
		location,
		setLocation,
		getLocationProp,
		setLocationProp,
	};
};

export const useStore = () => {
	const { persist, isReady } = useHooks();
	return { persist, isReady };
};

export const useStylingLocation = () => {
	const { location, setLocation } = useHooks();
	return { location, setLocation };
};

export const useLocationProps = ( location ) => {
	const { getLocationProp, setLocationProp } = useHooks();
	const details = STYLING_LOCATIONS[ location ] ?? {};

	const sanitize = ( value ) => ( undefined === value ? true : !! value );

	return {
		choices: Object.values( STYLING_LOCATIONS ),
		details,
		isActive: sanitize( getLocationProp( location, 'enabled' ) ),
		setActive: ( state ) =>
			setLocationProp( location, 'enabled', sanitize( state ) ),
	};
};

export const usePaymentMethodProps = ( location ) => {
	const { getLocationProp, setLocationProp } = useHooks();

	const sanitize = ( value ) => {
		if ( Array.isArray( value ) ) {
			return value;
		}
		return value ? [ value ] : [];
	};

	return {
		choices: Object.values( STYLING_PAYMENT_METHODS ),
		paymentMethods: sanitize( getLocationProp( location, 'methods' ) ),
		setPaymentMethods: ( methods ) =>
			setLocationProp( location, 'methods', sanitize( methods ) ),
	};
};

export const useColorProps = ( location ) => {
	const { getLocationProp, setLocationProp } = useHooks();

	const sanitize = ( value ) => {
		const isValidColor = Object.values( STYLING_COLORS ).some(
			( color ) => color.value === value
		);
		return isValidColor ? value : STYLING_COLORS.gold.value;
	};

	return {
		choices: Object.values( STYLING_COLORS ),
		color: sanitize( getLocationProp( location, 'color' ) ),
		setColor: ( color ) =>
			setLocationProp( location, 'color', sanitize( color ) ),
	};
};

export const useShapeProps = ( location ) => {
	const { getLocationProp, setLocationProp } = useHooks();

	const sanitize = ( value ) => {
		const isValidColor = Object.values( STYLING_SHAPES ).some(
			( color ) => color.value === value
		);
		return isValidColor ? value : STYLING_SHAPES.rect.value;
	};

	return {
		choices: Object.values( STYLING_SHAPES ),
		shape: sanitize( getLocationProp( location, 'shape' ) ),
		setShape: ( shape ) =>
			setLocationProp( location, 'shape', sanitize( shape ) ),
	};
};

export const useLabelProps = ( location ) => {
	const { getLocationProp, setLocationProp } = useHooks();

	const sanitize = ( value ) => {
		const isValidColor = Object.values( STYLING_LABELS ).some(
			( color ) => color.value === value
		);
		return isValidColor ? value : STYLING_LABELS.paypal.value;
	};

	return {
		choices: Object.values( STYLING_LABELS ),
		label: sanitize( getLocationProp( location, 'label' ) ),
		setLabel: ( label ) =>
			setLocationProp( location, 'label', sanitize( label ) ),
	};
};

export const useLayoutProps = ( location ) => {
	const { getLocationProp, setLocationProp } = useHooks();
	const { details } = useLocationProps( location );
	const isAvailable = false !== details.props.layout;

	const sanitize = ( value ) => {
		const isValidColor = Object.values( STYLING_LAYOUTS ).some(
			( color ) => color.value === value
		);
		return isValidColor ? value : STYLING_LAYOUTS.vertical.value;
	};

	return {
		choices: Object.values( STYLING_LAYOUTS ),
		isAvailable,
		layout: sanitize( getLocationProp( location, 'layout' ) ),
		setLayout: ( layout ) =>
			setLocationProp( location, 'layout', sanitize( layout ) ),
	};
};

export const useTaglineProps = ( location ) => {
	const { getLocationProp, setLocationProp } = useHooks();
	const { details } = useLocationProps( location );

	// Tagline is only available for horizontal layouts.
	const isAvailable =
		false !== details.props.tagline &&
		STYLING_LAYOUTS.horizontal.value ===
			getLocationProp( location, 'layout' );

	const sanitize = ( value ) => !! value;

	return {
		isAvailable,
		tagline: isAvailable
			? sanitize( getLocationProp( location, 'tagline' ) )
			: false,
		setTagline: ( tagline ) =>
			setLocationProp( location, 'tagline', sanitize( tagline ) ),
	};
};
