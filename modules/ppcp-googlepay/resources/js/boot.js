import { loadCustomScript } from '@paypal/paypal-js';
import { loadPaypalScript } from '../../../ppcp-button/resources/js/modules/Helper/ScriptLoading';
import GooglepayManager from './GooglepayManager';
import { setupButtonEvents } from '../../../ppcp-button/resources/js/modules/Helper/ButtonRefreshHelper';
import { CheckoutBootstrap } from './ContextBootstrap/CheckoutBootstrap';
import moduleStorage from './Helper/GooglePayStorage';

( function ( { buttonConfig, ppcpConfig } ) {
	const context = ppcpConfig.context;

	let manager;

	const bootstrap = function () {
		manager = new GooglepayManager( buttonConfig, ppcpConfig );
		manager.init();

		if ( 'continuation' === context || 'checkout' === context ) {
			const checkoutBootstap = new CheckoutBootstrap( moduleStorage );

			checkoutBootstap.init();
		}
	};

	setupButtonEvents( function () {
		if ( manager ) {
			manager.reinit();
		}
	} );

	document.addEventListener( 'DOMContentLoaded', () => {
		if ( ! buttonConfig || ! ppcpConfig ) {
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
} );
