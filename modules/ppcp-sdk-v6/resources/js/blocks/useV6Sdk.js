/**
 * React hook that loads the v6 SDK, checks eligibility and builds
 * payment sessions for use inside WooCommerce Blocks (React) contexts.
 *
 * @package
 */

import { useEffect, useRef, useState } from '@wordpress/element';
import { loadSdkV6 } from '../sdkLoader';
import { checkEligibility } from '../eligibility';
import { createSession } from '../sessions/createSession';

const METHODS = [ 'paypal', 'venmo', 'paylater' ];

/**
 * Loads the SDK (once, promise-cached), determines eligibility for the
 * given amount, and creates a session per eligible method.
 *
 * The sessions object keeps a stable reference while the eligible-method
 * set is unchanged, so buttons mounted from it are not needlessly
 * recreated on re-render. When the cart amount changes the eligible set
 * is re-evaluated (Pay Later is amount-sensitive) and sessions rebuilt
 * only if the set actually changed; this must stay in sync with the
 * classic-page equivalent in boot.js (refreshEligibility).
 *
 * config and context must be stable references across renders: they are
 * effect dependencies, so an inline object literal would re-run the
 * eligibility check on every render.
 *
 * @param {Object} config   - The localized sdk-v6 config (script_data shape).
 * @param {string} context  - The page context (e.g. cart-block, checkout-block).
 * @param {string} [amount] - The current cart total; changing it re-checks eligibility.
 * @return {{sdk: ?Object, eligibility: ?Object, sessions: ?Object, error: ?Error}} Hook state.
 */
export function useV6Sdk( config, context, amount ) {
	const [ state, setState ] = useState( {
		sdk: null,
		eligibility: null,
		sessions: null,
		error: null,
	} );
	const previousEligibility = useRef( null );

	useEffect( () => {
		let active = true;

		( async () => {
			const sdk = await loadSdkV6( config, context );
			const eligibility = await checkEligibility( sdk, {
				currencyCode: config.currency,
				countryCode: config.buyer_country,
				amount,
			} );

			if ( ! active ) {
				return;
			}

			const previous = previousEligibility.current;
			const setChanged =
				! previous ||
				METHODS.some(
					( method ) => previous[ method ] !== eligibility[ method ]
				);
			previousEligibility.current = eligibility;

			setState( ( current ) => {
				// Keep the same sessions object when the eligible set is
				// unchanged, so already-mounted buttons survive the update.
				if ( ! setChanged && current.sessions ) {
					return { ...current, sdk, eligibility };
				}

				const sessions = {
					payLaterDetails: eligibility.payLaterDetails,
					map: {},
				};
				for ( const method of METHODS ) {
					if ( eligibility[ method ] ) {
						sessions.map[ method ] = createSession(
							sdk,
							method,
							config,
							context
						);
					}
				}

				return { sdk, eligibility, sessions, error: null };
			} );
		} )().catch( ( error ) => {
			if ( active ) {
				setState( ( current ) => ( { ...current, error } ) );
			}
		} );

		return () => {
			active = false;
		};
	}, [ config, context, amount ] );

	return state;
}
