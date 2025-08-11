import { useState, useRef } from '@wordpress/element';

/**
 * Custom hook for handling copy to clipboard functionality
 *
 * @param {Object} options                 - Configuration options
 * @param {number} options.successDuration - How long to show success state (ms)
 * @return {Object} Copy functionality and state
 */
export const useCopyToClipboard = ( options = {} ) => {
	const { successDuration = 1000 } = options;
	const [ copied, setCopied ] = useState( false );
	const [ error, setError ] = useState( false );
	const timerRef = useRef( null );

	const copy = async ( text ) => {
		try {
			await navigator.clipboard.writeText( text );

			clearTimeout( timerRef.current );
			setCopied( true );
			setError( false );

			timerRef.current = setTimeout(
				() => setCopied( false ),
				successDuration
			);
		} catch ( err ) {
			console.error( 'Copy failed:', err );
			setError( true );
			setCopied( false );
		}
	};

	return { copy, copied, error };
};

export default useCopyToClipboard;
