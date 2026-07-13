/**
 * PayPal SDK v6 Bootstrap.
 *
 * Loads the SDK, checks eligibility, creates payment sessions,
 * and renders Web Component buttons.
 *
 * @package
 */

import { loadSdkV6 } from './sdkLoader';
import { checkEligibility } from './eligibility';
import { createSession } from './sessions/createSession';
import { renderButtons } from './components/buttonRenderer';
import { detectContext } from './utils/contextDetector';
import { createOrder } from './endpointsAdapter';
import { setErrorLabels } from './utils/errorHandler';

( function ( config ) {
	'use strict';

	if ( ! config ) {
		return;
	}

	setErrorLabels( config.labels );

	const context = config.page_context || detectContext();
	if ( ! context ) {
		return;
	}

	const wrapperSelector =
		context === 'mini-cart' ? config.mini_cart_wrapper : config.wrapper;

	const styles =
		config.button_styles[ context ] || config.button_styles.checkout || {};

	const createOrderForFunding = ( fundingSource ) => () =>
		createOrder( config, context, fundingSource );

	// SDK, eligibility and sessions are created once and reused across
	// re-renders; only the Web Components are rebuilt when WC replaces
	// the surrounding DOM. The in-flight promise is cached so concurrent
	// render triggers share one initialization (and one client token).
	let sessionsPromise = null;

	function ensureSessions() {
		if ( ! sessionsPromise ) {
			sessionsPromise = createSessions().catch( ( error ) => {
				sessionsPromise = null;
				throw error;
			} );
		}
		return sessionsPromise;
	}

	/**
	 * Loads the SDK, checks eligibility and creates the sessions.
	 *
	 * @return {Promise<Object>} Sessions keyed by method.
	 */
	async function createSessions() {
		const sdk = await loadSdkV6( config, context );

		document.dispatchEvent(
			new CustomEvent( 'ppcp-sdk-v6-ready', {
				detail: { sdkInstance: sdk },
			} )
		);

		const eligibility = await checkEligibility( sdk, {
			currencyCode: config.currency,
			countryCode: config.buyer_country,
			amount: config.amount,
		} );

		const sessions = {
			payLaterDetails: eligibility.payLaterDetails,
			map: {},
		};
		for ( const method of [ 'paypal', 'venmo', 'paylater' ] ) {
			if ( eligibility[ method ] ) {
				sessions.map[ method ] = createSession(
					sdk,
					method,
					config,
					context
				);
			}
		}

		return sessions;
	}

	/**
	 * Renders buttons if the wrapper is present in the DOM.
	 *
	 * The SDK and client token are only loaded once a wrapper exists,
	 * so pages without buttons never hit the token endpoint.
	 */
	async function render() {
		if ( ! document.querySelector( wrapperSelector ) ) {
			return;
		}

		const { map, payLaterDetails } = await ensureSessions();

		renderButtons( {
			wrapper: document.querySelector( wrapperSelector ),
			sessions: map,
			styles,
			createOrderForFunding,
			payLaterDetails,
		} );
	}

	function tryRender() {
		render().catch( ( error ) => {
			// eslint-disable-next-line no-console
			console.error( '[PPCP SDK v6]', error );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', tryRender );
	} else {
		tryRender();
	}

	// Re-render when WC replaces the surrounding DOM: cart/checkout AJAX
	// updates and mini-cart fragment refreshes.
	if ( typeof jQuery !== 'undefined' ) {
		jQuery( document.body ).on(
			'updated_cart_totals updated_checkout wc_fragments_loaded wc_fragments_refreshed added_to_cart',
			tryRender
		);
	}
} )( window.wc_ppcp_sdk_v6 );
