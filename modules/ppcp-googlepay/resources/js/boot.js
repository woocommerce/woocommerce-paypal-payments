import { loadCustomScript } from '@paypal/paypal-js';
import { loadPaypalScript } from '../../../ppcp-button/resources/js/modules/Helper/ScriptLoading';
import GooglepayManager from './GooglepayManager';
import { setupButtonEvents } from '../../../ppcp-button/resources/js/modules/Helper/ButtonRefreshHelper';

( function ( { buttonConfig, ppcpConfig, jQuery } ) {
	let manager;

	const bootstrap = function () {
		manager = new GooglepayManager( buttonConfig, ppcpConfig );
		manager.init();
	};

	setupButtonEvents( function () {
		if ( manager ) {
			manager.reinit();
		}
	} );

	document.addEventListener( 'DOMContentLoaded', () => {
		if (
			typeof buttonConfig === 'undefined' ||
			typeof ppcpConfig === 'undefined'
		) {
			// No PayPal buttons present on this page.
			return;
		}

		let bootstrapped = false;
		let paypalLoaded = false;
		let googlePayLoaded = false;

		const tryToBoot = () => {
			if ( ! bootstrapped && paypalLoaded && googlePayLoaded ) {
				bootstrapped = true;
				bootstrap();
			}
		};

		// Load GooglePay SDK
		loadCustomScript( { url: buttonConfig.sdk_url } ).then( () => {
			googlePayLoaded = true;
			tryToBoot();
		} );

		// Load PayPal
		loadPaypalScript( ppcpConfig, () => {
			paypalLoaded = true;
			tryToBoot();
		} );
	} );
} )( {
	buttonConfig: window.wc_ppcp_googlepay,
	ppcpConfig: window.PayPalCommerceGateway,
	jQuery: window.jQuery,
} );
