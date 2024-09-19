import { useCallback } from '@wordpress/element';

const isObject = ( value ) => typeof value === 'object' && value !== null;
const isNonEmptyString = ( value ) => value !== '';

const removeEmptyValues = ( obj ) => {
	if ( ! isObject( obj ) ) {
		return obj;
	}

	return Object.fromEntries(
		Object.entries( obj )
			.map( ( [ key, value ] ) => [
				key,
				isObject( value ) ? removeEmptyValues( value ) : value,
			] )
			.filter( ( [ _, value ] ) =>
				isObject( value )
					? Object.keys( value ).length > 0
					: isNonEmptyString( value )
			)
	);
};

export const useDeleteEmptyKeys = () => {
	return useCallback( removeEmptyValues, [] );
};
