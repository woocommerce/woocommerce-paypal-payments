/**
 * PayPal SDK v6 Bootstrap for My Account › Add Payment Method.
 *
 * Renders the PayPal "save for later" button using the v6 vault session,
 * replacing the v5 paypal.Buttons({ createVaultSetupToken }) flow. The
 * card fields on this page stay on the v5 stack for now (deferred), so
 * this boot renders the PayPal button only and never creates a v6 card
 * session (a save and a payment card session cannot coexist on a page).
 *
 * @package
 */

import { loadSdkV6 } from './sdkLoader';
import { checkVaultEligibility } from './eligibility';
import { createSavePayPalSession } from './sessions/createSaveSession';
import { postJson } from './utils/api';
import { handleError, setErrorLabels } from './utils/errorHandler';

( function ( config ) {
	'use strict';

	if ( ! config ) {
		return;
	}

	setErrorLabels( config.labels );

	/**
	 * Requests a vault setup token from the existing WC AJAX endpoint.
	 *
	 * The returned promise is passed to session.start() without being
	 * awaited first — awaiting it before the call loses the transient
	 * user activation and breaks the popup. It resolves to a
	 * { vaultSetupToken } object, mirroring the { orderId } shape the
	 * one-time payment session expects from its create-order promise.
	 *
	 * @return {Promise<{vaultSetupToken: string}>} Resolves to the setup token.
	 */
	function createVaultSetupToken() {
		return postJson( config.ajax.create_setup_token ).then( ( data ) => ( {
			vaultSetupToken: data.id,
		} ) );
	}

	/**
	 * Builds the PayPal save button and binds the click handler.
	 *
	 * @param {Object} session - The v6 save payment session.
	 * @return {HTMLElement} The configured button element.
	 */
	function createButton( session ) {
		const button = document.createElement( 'paypal-button' );
		button.setAttribute( 'type', 'pay' );

		if ( config.button?.color_class ) {
			button.className = config.button.color_class;
		}

		button.addEventListener( 'click', async () => {
			try {
				await session.start(
					{ presentationMode: 'auto' },
					createVaultSetupToken()
				);
			} catch ( error ) {
				handleError( error );
			}
		} );

		return button;
	}

	async function init() {
		const wrapper = document.querySelector( config.button.wrapper );
		if ( ! wrapper ) {
			return;
		}

		const sdk = await loadSdkV6( config, 'checkout' );

		const eligibility = await checkVaultEligibility( sdk, {
			currencyCode: config.currency,
		} );
		if ( ! eligibility.paypal ) {
			return;
		}

		const session = createSavePayPalSession( sdk, config );

		wrapper.innerHTML = '';
		wrapper.appendChild( createButton( session ) );
	}

	function boot() {
		init().catch( ( error ) => handleError( error ) );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )( window.wc_ppcp_sdk_v6_save );
