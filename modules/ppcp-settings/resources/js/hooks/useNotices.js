import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { useCallback, useEffect, useRef } from '@wordpress/element';

import { SuccessIcon, ErrorIcon } from '../Components/ReusableComponents/Icons';

const AUTO_DISMISS_DELAY = 5000;

/**
 * Hook for creating notices with snackbar type by default.
 * Notices show a dismiss button and auto-dismiss after 5 seconds.
 *
 * @return {Object} Notice functions.
 */
const useNotices = () => {
	const {
		createSuccessNotice,
		createErrorNotice,
		createInfoNotice,
		removeNotice,
	} = useDispatch( noticesStore );

	// Track active timeouts to clear on unmount
	const timeoutsRef = useRef( [] );

	useEffect( () => {
		const timeouts = timeoutsRef.current;
		return () => {
			timeouts.forEach( clearTimeout );
		};
	}, [] );

	const scheduleAutoDismiss = useCallback(
		( id ) => {
			const timeoutId = setTimeout( () => {
				removeNotice( id );
			}, AUTO_DISMISS_DELAY );

			timeoutsRef.current.push( timeoutId );
		},
		[ removeNotice ]
	);

	const successNotice = useCallback(
		( message, options = {} ) => {
			const id = options.id || `ppcp-success-${ Date.now() }`;

			createSuccessNotice( message, {
				id,
				type: 'snackbar',
				icon: SuccessIcon,
				explicitDismiss: true,
				speak: true,
				...options,
			} );

			scheduleAutoDismiss( id );
		},
		[ createSuccessNotice, scheduleAutoDismiss ]
	);

	const errorNotice = useCallback(
		( message, options = {} ) => {
			const id = options.id || `ppcp-error-${ Date.now() }`;

			createErrorNotice( message, {
				id,
				type: 'snackbar',
				icon: ErrorIcon,
				explicitDismiss: true,
				speak: true,
				...options,
			} );

			scheduleAutoDismiss( id );
		},
		[ createErrorNotice, scheduleAutoDismiss ]
	);

	const infoNotice = useCallback(
		( message, options = {} ) => {
			const id = options.id || `ppcp-info-${ Date.now() }`;

			createInfoNotice( message, {
				id,
				type: 'snackbar',
				explicitDismiss: true,
				speak: true,
				...options,
			} );

			scheduleAutoDismiss( id );
		},
		[ createInfoNotice, scheduleAutoDismiss ]
	);

	return {
		createSuccessNotice: successNotice,
		createErrorNotice: errorNotice,
		createInfoNotice: infoNotice,
		removeNotice,
	};
};

export default useNotices;
