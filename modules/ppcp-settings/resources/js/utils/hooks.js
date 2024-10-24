import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { debounce } from '../../../../ppcp-blocks/resources/js/Helper/debounce';

/**
 * Hook to manage a form field in a local state, and sync it to the Redux store via a
 * debounced setter. This ensures a good balance between fast/lightweight state management
 * and a stable Redux integration, even when the value changes very frequently.
 *
 * @param {Function} syncToStore - Function to sync value to the Redux store.
 * @param {string}   storeValue  - Initial value from the Redux store.
 * @param {number}   delay       - Debounce delay, in milliseconds.
 * @return {[string, Function]} Tuple of [fieldValue, updateField]
 */
export const useDebounceField = (
	syncToStore,
	storeValue = '',
	delay = 300
) => {
	const [ fieldValue, setFieldValue ] = useState( storeValue );

	// Memoize the debounced store sync.
	const debouncedSync = useMemo(
		() => debounce( syncToStore, delay ),
		[ syncToStore, delay ]
	);

	// Sync field with store changes.
	useEffect( () => {
		if ( storeValue !== '' && fieldValue !== storeValue ) {
			setFieldValue( storeValue );
		}
	}, [ storeValue, fieldValue ] );

	// Handle field updates and store sync.
	const updateField = useCallback(
		( newValue ) => {
			setFieldValue( newValue );
			debouncedSync( newValue );
		},
		[ debouncedSync ]
	);

	// Cleanup on unmount.
	useEffect( () => {
		return () => {
			debouncedSync?.flush();
		};
	}, [ debouncedSync ] );

	return [ fieldValue, updateField ];
};
