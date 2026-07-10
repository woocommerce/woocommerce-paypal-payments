/**
 * PayPal SDK v6 Bootstrap.
 *
 * Loads the SDK, checks eligibility, creates payment sessions,
 * and renders Web Component buttons.
 *
 * @package
 */

import { loadSdkV6, getSdkInstance } from './sdkLoader';
import { checkEligibility } from './eligibility';
import { createPayPalSession } from './sessions/paypalSession';
import { createVenmoSession } from './sessions/venmoSession';
import { createPayLaterSession } from './sessions/payLaterSession';
import { renderButtons } from './components/buttonRenderer';
import { detectContext } from './utils/contextDetector';
import { createOrder } from './utils/orderDataBuilder';
import { handleError } from './utils/errorHandler';

( function ( config ) {
	'use strict';

	if ( ! config ) {
		return;
	}

	/**
	 * Renders buttons for the given context.
	 *
	 * @param {Object} sdk     - The SDK instance.
	 * @param {string} context - The page context.
	 */
	async function renderForContext( sdk, context ) {
		const eligibility = await checkEligibility( sdk, {
			currencyCode: config.currency,
			countryCode: config.buyer_country,
		} );

		const sessions = {};
		if ( eligibility.paypal ) {
			sessions.paypal = createPayPalSession( sdk, config );
		}
		if ( eligibility.venmo ) {
			sessions.venmo = createVenmoSession( sdk, config );
		}
		if ( eligibility.payLater ) {
			sessions.payLater = createPayLaterSession( sdk, config );
		}

		const createOrderForFunding = ( fundingSource ) => () =>
			createOrder( config.ajax.create_order, context, fundingSource );

		const styles =
			config.button_styles[ context ] ||
			config.button_styles.checkout ||
			{};

		renderButtons( {
			wrapperSelector: config.wrapper,
			eligibility,
			sessions,
			styles,
			createOrderForFunding,
			payLaterDetails: eligibility.payLaterDetails,
		} );
	}

	/**
	 * Main initialization.
	 */
	async function init() {
		try {
			const sdk = await loadSdkV6( config );

			const context = config.page_context || detectContext();
			if ( ! context ) {
				return;
			}

			await renderForContext( sdk, context );

			// Notify other modules that the SDK v6 is ready.
			document.dispatchEvent(
				new CustomEvent( 'ppcp-sdk-v6-ready', {
					detail: { sdkInstance: sdk },
				} )
			);

			// Re-render on WC cart/checkout updates.
			if ( typeof jQuery !== 'undefined' ) {
				const reinit = () => {
					const cachedSdk = getSdkInstance();
					if ( cachedSdk ) {
						renderForContext( cachedSdk, context );
					}
				};
				jQuery( document.body ).on( 'updated_cart_totals', reinit );
				jQuery( document.body ).on( 'updated_checkout', reinit );
			}
		} catch ( error ) {
			handleError( error );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )( window.wc_ppcp_sdk_v6 );
