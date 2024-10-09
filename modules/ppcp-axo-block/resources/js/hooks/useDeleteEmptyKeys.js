import { useCallback } from '@wordpress/element';

const isObject = ( value ) => typeof value === 'object' && value !== null;
const isNonEmptyString = ( value ) => value !== '';

/**
 * Recursively removes empty values from an object.
 * Empty values are considered to be:
 * - Empty strings
 * - Empty objects
 * - Null or undefined values
 *
 * @param {Object} obj - The object to clean.
 * @return {Object} A new object with empty values removed.
 */
const removeEmptyValues = ( obj ) => {
	// If not an object, return the value as is
	if ( ! isObject( obj ) ) {
		return obj;
	}

	return Object.fromEntries(
		Object.entries( obj )
			// Recursively apply removeEmptyValues to nested objects
			.map( ( [ key, value ] ) => [
				key,
				isObject( value ) ? removeEmptyValues( value ) : value,
			] )
			// Filter out empty values
			.filter( ( [ _, value ] ) =>
				isObject( value )
					? Object.keys( value ).length > 0
					: isNonEmptyString( value )
			)
	);
};

/**
 * Custom hook that returns a memoized function to remove empty values from an object.
 *
 * @return {Function} A memoized function that removes empty values from an object.
 */
export const useDeleteEmptyKeys = () => {
	return useCallback( removeEmptyValues, [] );
};
