import { useEffect } from '@wordpress/element';

import { CommonHooks } from '@ppcp-settings/data';
import useNotices from '../../hooks/useNotices';

/**
 * Displays one-shot notices queued by the server.
 *
 * Some onboarding failures happen during a server-side request (e.g. the OAuth
 * "Return to Store" redirect) that has no REST response the app can read. The
 * server queues those messages, and they arrive with the store hydration; this
 * component pushes them into the shared notices store - where <Notifications />
 * renders them as snackbars - then clears the queue so they fire only once.
 *
 * Renders no markup of its own.
 *
 * @return {null} Nothing.
 */
const FlashNotices = () => {
	const { notices, clear } = CommonHooks.useOnboardingNotices();
	const { createErrorNotice, createSuccessNotice, createInfoNotice } =
		useNotices();

	useEffect( () => {
		if ( ! notices.length ) {
			return;
		}

		notices.forEach( ( { type, message } ) => {
			if ( ! message ) {
				return;
			}

			switch ( type ) {
				case 'success':
					createSuccessNotice( message );
					break;
				case 'info':
				case 'warning':
					createInfoNotice( message );
					break;
				default:
					createErrorNotice( message );
			}
		} );

		clear();
	}, [
		notices,
		clear,
		createErrorNotice,
		createSuccessNotice,
		createInfoNotice,
	] );

	return null;
};

export default FlashNotices;
