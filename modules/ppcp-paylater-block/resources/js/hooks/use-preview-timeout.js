import { useState, useEffect } from '@wordpress/element';

export const PREVIEW_TIMEOUT_MS = 10000;

/**
 * Flips to `true` once `timeoutMs` has elapsed without `loaded` becoming truthy.
 * Returns `false` if the preview loaded in time. Cleans up on unmount or when
 * `loaded` flips, so no setState-on-unmounted warnings.
 * @param loaded
 * @param timeoutMs
 */
export function usePreviewTimeout( loaded, timeoutMs = PREVIEW_TIMEOUT_MS ) {
	const [ timedOut, setTimedOut ] = useState( false );

	useEffect( () => {
		if ( loaded ) {
			return;
		}
		const timer = setTimeout( () => setTimedOut( true ), timeoutMs );
		return () => clearTimeout( timer );
	}, [ loaded, timeoutMs ] );

	return timedOut;
}
