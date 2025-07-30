/**
 * Utilities: Essential tracking helper functions.
 *
 * Provides helper functions for the tracking system.
 * Includes field validation, metadata creation, and store utilities.
 *
 * @file
 */

/**
 * Get the value of a field from the store with comprehensive error handling.
 *
 * @param {Function} select      - WordPress data select function.
 * @param {string}   storeName   - The name of the store.
 * @param {Object}   fieldConfig - Configuration for the field.
 * @return {*} The field value
 */
export function getFieldValue( select, storeName, fieldConfig ) {
	try {
		// Use custom selector if provided.
		if ( typeof fieldConfig.selector === 'function' ) {
			return fieldConfig.selector( select, storeName );
		}

		// Get the store.
		const store = select( storeName );
		if ( ! store ) {
			return undefined;
		}

		// Determine data source.
		const dataType = fieldConfig.type || 'persistent';
		let data;

		if ( dataType === 'persistent' ) {
			data = store.persistentData?.();
		} else if ( dataType === 'transient' ) {
			data = store.transientData?.();
		} else {
			console.warn( `[FIELD VALUE] Unknown data type: ${ dataType }` );
			return undefined;
		}

		if ( ! data || typeof data !== 'object' ) {
			return undefined;
		}

		// Handle nested field paths.
		const fieldPath = fieldConfig.fieldName.split( '.' );
		let value = data;

		for ( const key of fieldPath ) {
			if ( value === null || value === undefined ) {
				return undefined;
			}
			value = value[ key ];
		}

		return value;
	} catch ( error ) {
		console.error(
			`[FIELD VALUE] Error getting value for ${ fieldConfig.fieldName }:`,
			error
		);
		return undefined;
	}
}
